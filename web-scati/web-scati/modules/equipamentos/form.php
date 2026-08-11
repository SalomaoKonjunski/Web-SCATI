<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

// Ao abrir o cadastro a partir de uma aba específica (ex.: "Nova Impressora"),
// pré-seleciona o tipo do equipamento.
$tipoPreSelecionado = $_GET['tipo'] ?? '';
$tipoInicial = in_array($tipoPreSelecionado, tiposEquipamento(), true) ? $tipoPreSelecionado : 'Computador';

$equipamento = [
    'patrimonio' => '', 'tipo' => $tipoInicial, 'marca' => '', 'modelo' => '', 'numero_serie' => '',
    'hostname' => '', 'processador' => '', 'memoria_ram' => '', 'armazenamento' => '', 'sistema_operacional' => '',
    'status' => 'Disponível', 'localizacao' => '', 'usuario_responsavel' => '', 'rede_id' => '', 'acesso_usb' => '',
    'ip' => '', 'modelo_toner' => '', 'qtd_toners' => '', 'toner_duracao_dias' => '',
    'ip_fixo' => '', 'placa_mae' => '', 'placa_video' => '',
    'funcao_servidor' => '', 'servidor_status' => 'Ativo', 'servidor_observacoes' => '',
    'valor_aquisicao' => '', 'data_compra' => '', 'fornecedor' => '', 'numero_nota_fiscal' => '',
    'garantia' => '', 'valor_atual' => '', 'observacoes_financeiras' => '',
];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM equipamentos WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Equipamento não encontrado.');
        redirect('/modules/equipamentos/index.php');
    }
    $equipamento = array_merge($equipamento, $registro);
}

// Trata o gerenciamento de Toner direto no cadastro/edição da impressora
// (POST nesta mesma página, só faz sentido quando o equipamento já existe)
if ($edicao && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vincular_toner_form'])) {
    $itemId = (int) ($_POST['item_estoque_id'] ?? 0);
    if ($itemId > 0) {
        $stmtDecr = $pdo->prepare('UPDATE estoque SET quantidade = quantidade - 1 WHERE id = :id AND quantidade > 0');
        $stmtDecr->execute(['id' => $itemId]);

        if ($stmtDecr->rowCount() > 0) {
            $stmtNome = $pdo->prepare('SELECT nome FROM estoque WHERE id = :id');
            $stmtNome->execute(['id' => $itemId]);
            $nomeItem = $stmtNome->fetchColumn();

            $pdo->prepare('INSERT INTO itens_vinculados (estoque_id, equipamento_id) VALUES (:estoque_id, :equipamento_id)')
                ->execute(['estoque_id' => $itemId, 'equipamento_id' => $id]);

            registrarHistorico($id, 'Item', 'Toner "' . $nomeItem . '" vinculado a este equipamento');
            flash('success', 'Toner vinculado com sucesso.');
        } else {
            flash('danger', 'Toner indisponível para vínculo (sem unidades em estoque).');
        }
    }
    redirect('/modules/equipamentos/form.php?id=' . $id . '#toner');
}

if ($edicao && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_toner_form'])) {
    $novoNome = trim($_POST['novo_item_nome'] ?? '');
    $novaCategoriaId = (int) ($_POST['novo_item_categoria_id'] ?? 0);
    $novaMarca = trim($_POST['novo_item_marca'] ?? '');
    $novoModelo = trim($_POST['novo_item_modelo'] ?? '');
    $novaQuantidade = (int) ($_POST['novo_item_quantidade'] ?? 0);
    $novaQuantidadeMinima = (int) ($_POST['novo_item_quantidade_minima'] ?? 0);
    $novaLocalizacao = trim($_POST['novo_item_localizacao'] ?? '');
    $novasObservacoes = trim($_POST['novo_item_observacoes'] ?? '');

    if ($novoNome === '' || $novaCategoriaId <= 0 || $novaQuantidade < 1) {
        flash('danger', 'Preencha nome, categoria e uma quantidade de pelo menos 1 unidade para cadastrar e vincular o toner.');
        redirect('/modules/equipamentos/form.php?id=' . $id . '#toner');
    }

    $stmtCat = $pdo->prepare('SELECT nome FROM categorias_estoque WHERE id = :id');
    $stmtCat->execute(['id' => $novaCategoriaId]);
    $categoriaNome = $stmtCat->fetchColumn() ?: null;

    $stmtExistente = $pdo->prepare(
        "SELECT id, quantidade FROM estoque
         WHERE LOWER(TRIM(nome)) = LOWER(TRIM(:nome))
           AND LOWER(TRIM(COALESCE(marca, ''))) = LOWER(TRIM(:marca))
           AND LOWER(TRIM(COALESCE(modelo, ''))) = LOWER(TRIM(:modelo))
         LIMIT 1"
    );
    $stmtExistente->execute(['nome' => $novoNome, 'marca' => $novaMarca, 'modelo' => $novoModelo]);
    $itemExistente = $stmtExistente->fetch();

    if ($itemExistente) {
        $itemId = (int) $itemExistente['id'];
        $pdo->prepare('UPDATE estoque SET quantidade = quantidade + :quantidade WHERE id = :id')
            ->execute(['quantidade' => $novaQuantidade, 'id' => $itemId]);

        registrarHistoricoEstoque(
            $itemId,
            $novoNome,
            $categoriaNome,
            'Alteração',
            'Quantidade alterada de ' . (int) $itemExistente['quantidade'] . ' para ' . ((int) $itemExistente['quantidade'] + $novaQuantidade) . ' (+' . $novaQuantidade . ', via cadastro da impressora)'
        );
        $mensagemCadastro = 'Toner já existia no estoque — quantidade somada e ';
    } else {
        $pdo->prepare(
            'INSERT INTO estoque (nome, categoria_id, marca, modelo, quantidade, quantidade_minima, localizacao, observacoes)
             VALUES (:nome, :categoria_id, :marca, :modelo, :quantidade, :quantidade_minima, :localizacao, :observacoes)'
        )->execute([
            'nome' => $novoNome,
            'categoria_id' => $novaCategoriaId,
            'marca' => $novaMarca ?: null,
            'modelo' => $novoModelo ?: null,
            'quantidade' => $novaQuantidade,
            'quantidade_minima' => $novaQuantidadeMinima,
            'localizacao' => $novaLocalizacao ?: null,
            'observacoes' => $novasObservacoes ?: null,
        ]);
        $itemId = (int) $pdo->lastInsertId();

        registrarHistoricoEstoque($itemId, $novoNome, $categoriaNome, 'Cadastro', 'Item cadastrado no estoque');
        $mensagemCadastro = 'Toner cadastrado no estoque e ';
    }

    $stmtDecr = $pdo->prepare('UPDATE estoque SET quantidade = quantidade - 1 WHERE id = :id AND quantidade > 0');
    $stmtDecr->execute(['id' => $itemId]);

    if ($stmtDecr->rowCount() > 0) {
        $pdo->prepare('INSERT INTO itens_vinculados (estoque_id, equipamento_id) VALUES (:estoque_id, :equipamento_id)')
            ->execute(['estoque_id' => $itemId, 'equipamento_id' => $id]);
        registrarHistorico($id, 'Item', 'Toner "' . $novoNome . '" ' . ($itemExistente ? 'reaproveitado do estoque' : 'cadastrado no estoque') . ' e vinculado a este equipamento');
        flash('success', $mensagemCadastro . 'vinculado com sucesso.');
    } else {
        flash('success', $mensagemCadastro . 'atualizado, mas não foi possível vinculá-lo automaticamente.');
    }

    redirect('/modules/equipamentos/form.php?id=' . $id . '#toner');
}

// Registra a troca física do toner: reinicia a contagem do prazo de alerta
// a partir de hoje, independente da vinculação de itens do Estoque.
if ($edicao && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_troca_toner'])) {
    $pdo->prepare('UPDATE equipamentos SET toner_ultima_troca = CURDATE() WHERE id = :id')->execute(['id' => $id]);
    registrarHistorico($id, 'Manutenção', 'Troca de toner registrada — prazo de alerta reiniciado');
    flash('success', 'Troca de toner registrada. O prazo de alerta foi reiniciado a partir de hoje.');
    redirect('/modules/equipamentos/form.php?id=' . $id . '#toner');
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e sanitiza os dados enviados
    foreach ($equipamento as $campo => $valorPadrao) {
        $equipamento[$campo] = trim($_POST[$campo] ?? '');
    }

    // Validações essenciais
    if (!in_array($equipamento['tipo'], tiposEquipamento(), true)) {
        $erros[] = 'Tipo de equipamento inválido.';
    }
    if (!in_array($equipamento['status'], statusEquipamento(), true)) {
        $erros[] = 'Status inválido.';
    }

    // Verifica patrimônio único
    if ($equipamento['patrimonio'] !== '') {
        $sqlCheck = 'SELECT id FROM equipamentos WHERE patrimonio = :patrimonio' . ($edicao ? ' AND id != :id' : '');
        $stmtCheck = $pdo->prepare($sqlCheck);
        $paramsCheck = ['patrimonio' => $equipamento['patrimonio']];
        if ($edicao) {
            $paramsCheck['id'] = $id;
        }
        $stmtCheck->execute($paramsCheck);
        if ($stmtCheck->fetch()) {
            $erros[] = 'Já existe um equipamento cadastrado com este patrimônio.';
        }
    }

    if (empty($erros)) {
        // Normaliza campos numéricos/nulos
        $redeId = $equipamento['rede_id'] !== '' ? (int) $equipamento['rede_id'] : null;
        $qtdToners = $equipamento['qtd_toners'] !== '' ? (int) $equipamento['qtd_toners'] : null;
        $tonerDuracaoDias = $equipamento['toner_duracao_dias'] !== '' ? (int) $equipamento['toner_duracao_dias'] : null;
        $valorAquisicao = $equipamento['valor_aquisicao'] !== '' ? (float) str_replace(',', '.', $equipamento['valor_aquisicao']) : null;
        $valorAtual = $equipamento['valor_atual'] !== '' ? (float) str_replace(',', '.', $equipamento['valor_atual']) : null;
        $dataCompra = $equipamento['data_compra'] !== '' ? $equipamento['data_compra'] : null;

        $dadosParaSalvar = [
            'patrimonio' => $equipamento['patrimonio'] ?: null,
            'tipo' => $equipamento['tipo'],
            'marca' => $equipamento['marca'] ?: null,
            'modelo' => $equipamento['modelo'] ?: null,
            'numero_serie' => $equipamento['numero_serie'] ?: null,
            'hostname' => $equipamento['hostname'] ?: null,
            'processador' => $equipamento['processador'] ?: null,
            'memoria_ram' => $equipamento['memoria_ram'] ?: null,
            'armazenamento' => $equipamento['armazenamento'] ?: null,
            'sistema_operacional' => $equipamento['sistema_operacional'] ?: null,
            'status' => $equipamento['status'],
            'localizacao' => $equipamento['localizacao'] ?: null,
            'usuario_responsavel' => $equipamento['usuario_responsavel'] ?: null,
            'rede_id' => $redeId,
            'acesso_usb' => $equipamento['acesso_usb'] !== '' ? 1 : 0,
            'ip' => $equipamento['ip'] ?: null,
            'modelo_toner' => $equipamento['modelo_toner'] ?: null,
            'qtd_toners' => $qtdToners,
            'toner_duracao_dias' => $tonerDuracaoDias,
            'ip_fixo' => $equipamento['ip_fixo'] ?: null,
            'placa_mae' => $equipamento['placa_mae'] ?: null,
            'placa_video' => $equipamento['placa_video'] ?: null,
            'funcao_servidor' => $equipamento['funcao_servidor'] ?: null,
            'servidor_status' => $equipamento['servidor_status'] ?: null,
            'servidor_observacoes' => $equipamento['servidor_observacoes'] ?: null,
            'valor_aquisicao' => $valorAquisicao,
            'data_compra' => $dataCompra,
            'fornecedor' => $equipamento['fornecedor'] ?: null,
            'numero_nota_fiscal' => $equipamento['numero_nota_fiscal'] ?: null,
            'garantia' => $equipamento['garantia'] ?: null,
            'valor_atual' => $valorAtual,
            'observacoes_financeiras' => $equipamento['observacoes_financeiras'] ?: null,
        ];

        try {
            if ($edicao) {
                // Monta o histórico comparando valores antigos x novos antes de salvar
                $mudancas = [];
                if ($registro['status'] !== $dadosParaSalvar['status']) {
                    $mudancas[] = ['Alteração', "Status alterado de \"{$registro['status']}\" para \"{$dadosParaSalvar['status']}\""];
                }
                if (($registro['localizacao'] ?? '') !== ($dadosParaSalvar['localizacao'] ?? '')) {
                    $de = $registro['localizacao'] ?: '(vazio)';
                    $para = $dadosParaSalvar['localizacao'] ?: '(vazio)';
                    $mudancas[] = ['Alteração', "Localização alterada de \"$de\" para \"$para\""];
                }
                if (($registro['usuario_responsavel'] ?? '') !== ($dadosParaSalvar['usuario_responsavel'] ?? '')) {
                    $de = $registro['usuario_responsavel'] ?: '(vazio)';
                    $para = $dadosParaSalvar['usuario_responsavel'] ?: '(vazio)';
                    $mudancas[] = ['Alteração', "Responsável alterado de \"$de\" para \"$para\""];
                }
                if ((int) ($registro['rede_id'] ?? 0) !== (int) ($dadosParaSalvar['rede_id'] ?? 0)) {
                    $mudancas[] = ['Alteração', 'Rede do equipamento foi alterada'];
                }
                if ((int) ($registro['acesso_usb'] ?? 0) !== (int) $dadosParaSalvar['acesso_usb']) {
                    $mudancas[] = ['Alteração', 'Acesso a dispositivos USB ' . ($dadosParaSalvar['acesso_usb'] ? 'permitido' : 'bloqueado')];
                }
                if (ehServidor($dadosParaSalvar['tipo']) && ($registro['servidor_status'] ?? null) !== $dadosParaSalvar['servidor_status']) {
                    $de = $registro['servidor_status'] ?: '(vazio)';
                    $para = $dadosParaSalvar['servidor_status'] ?: '(vazio)';
                    $mudancas[] = ['Alteração', "Status do servidor alterado de \"$de\" para \"$para\""];
                }
                $camposFinanceiros = ['valor_aquisicao', 'data_compra', 'fornecedor', 'numero_nota_fiscal', 'garantia', 'valor_atual', 'observacoes_financeiras'];
                foreach ($camposFinanceiros as $campoFin) {
                    if ((string) ($registro[$campoFin] ?? '') !== (string) ($dadosParaSalvar[$campoFin] ?? '')) {
                        $mudancas[] = ['Alteração', 'Informações financeiras atualizadas'];
                        break;
                    }
                }

                $sql = 'UPDATE equipamentos SET
                        patrimonio = :patrimonio, tipo = :tipo, marca = :marca, modelo = :modelo,
                        numero_serie = :numero_serie, hostname = :hostname, processador = :processador,
                        memoria_ram = :memoria_ram, armazenamento = :armazenamento, sistema_operacional = :sistema_operacional,
                        status = :status, localizacao = :localizacao, usuario_responsavel = :usuario_responsavel, rede_id = :rede_id,
                        acesso_usb = :acesso_usb,
                        ip = :ip, modelo_toner = :modelo_toner, qtd_toners = :qtd_toners, toner_duracao_dias = :toner_duracao_dias,
                        ip_fixo = :ip_fixo, placa_mae = :placa_mae,
                        placa_video = :placa_video,
                        funcao_servidor = :funcao_servidor, servidor_status = :servidor_status, servidor_observacoes = :servidor_observacoes,
                        valor_aquisicao = :valor_aquisicao, data_compra = :data_compra, fornecedor = :fornecedor,
                        numero_nota_fiscal = :numero_nota_fiscal, garantia = :garantia, valor_atual = :valor_atual,
                        observacoes_financeiras = :observacoes_financeiras
                        WHERE id = :id';
                $dadosParaSalvar['id'] = $id;
                $pdo->prepare($sql)->execute($dadosParaSalvar);

                foreach ($mudancas as [$evento, $descricao]) {
                    registrarHistorico($id, $evento, $descricao);
                }

                flash('success', 'Equipamento atualizado com sucesso.');
                redirect('/modules/equipamentos/view.php?id=' . $id);
            } else {
                $sql = 'INSERT INTO equipamentos
                        (patrimonio, tipo, marca, modelo, numero_serie, hostname, processador, memoria_ram,
                         armazenamento, sistema_operacional, status, localizacao, usuario_responsavel, rede_id, acesso_usb,
                         ip, modelo_toner, qtd_toners, toner_duracao_dias, ip_fixo, placa_mae, placa_video, funcao_servidor, servidor_status, servidor_observacoes,
                         valor_aquisicao, data_compra, fornecedor, numero_nota_fiscal,
                         garantia, valor_atual, observacoes_financeiras)
                        VALUES
                        (:patrimonio, :tipo, :marca, :modelo, :numero_serie, :hostname, :processador, :memoria_ram,
                         :armazenamento, :sistema_operacional, :status, :localizacao, :usuario_responsavel, :rede_id, :acesso_usb,
                         :ip, :modelo_toner, :qtd_toners, :toner_duracao_dias, :ip_fixo, :placa_mae, :placa_video, :funcao_servidor, :servidor_status, :servidor_observacoes,
                         :valor_aquisicao, :data_compra, :fornecedor, :numero_nota_fiscal,
                         :garantia, :valor_atual, :observacoes_financeiras)';
                $pdo->prepare($sql)->execute($dadosParaSalvar);
                $novoId = (int) $pdo->lastInsertId();

                registrarHistorico($novoId, 'Cadastro', 'Equipamento cadastrado');

                flash('success', 'Equipamento cadastrado com sucesso.');
                redirect('/modules/equipamentos/view.php?id=' . $novoId);
            }
        } catch (PDOException $e) {
            $erros[] = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

$redes = $pdo->query('SELECT id, nome FROM redes ORDER BY nome')->fetchAll();
$pageTitle = $edicao ? 'Editar Equipamento' : 'Novo Equipamento';

// Status do alerta de troca de toner por tempo de uso (independente da
// vinculação de itens do Estoque — baseado só na data da última troca
// registrada e na duração estimada configurada para esta impressora).
$tonerStatus = null;
if ($edicao && !empty($registro['toner_duracao_dias']) && !empty($registro['toner_ultima_troca'])) {
    $diasAlertaToner = (int) configGet('dias_alerta_toner', '7');
    $proximaTroca = new DateTime($registro['toner_ultima_troca']);
    $proximaTroca->modify('+' . (int) $registro['toner_duracao_dias'] . ' days');
    $hoje = new DateTime('today');
    $diasRestantes = (int) floor(($proximaTroca->getTimestamp() - $hoje->getTimestamp()) / 86400);
    if ($diasRestantes < 0) {
        $nivel = 'vencido';
    } elseif ($diasRestantes <= $diasAlertaToner) {
        $nivel = 'alerta';
    } else {
        $nivel = 'ok';
    }
    $tonerStatus = ['proxima_troca' => $proximaTroca, 'dias_restantes' => $diasRestantes, 'nivel' => $nivel];
}

// Dados de Toner, usados apenas ao editar um equipamento do tipo Impressora
$tonersVinculados = [];
$tonersDisponiveis = [];
$categoriaTonerId = 0;
if ($edicao && ehImpressora($equipamento['tipo'])) {
    $categoriasEstoque = $pdo->query('SELECT id, nome FROM categorias_estoque ORDER BY nome')->fetchAll();
    foreach ($categoriasEstoque as $catEst) {
        if ($catEst['nome'] === 'Toner') {
            $categoriaTonerId = (int) $catEst['id'];
            break;
        }
    }

    $tonersPorRegistro = $pdo->prepare(
        "SELECT MIN(iv.id) AS vinculo_id, es.id AS estoque_id, es.nome, es.marca, es.modelo,
                COUNT(iv.id) AS qtd_registro
         FROM itens_vinculados iv
         JOIN estoque es ON es.id = iv.estoque_id
         JOIN categorias_estoque c ON c.id = es.categoria_id
         WHERE iv.equipamento_id = :id AND c.nome = 'Toner'
         GROUP BY es.id, es.nome, es.marca, es.modelo
         ORDER BY es.nome, es.marca, es.modelo"
    );
    $tonersPorRegistro->execute(['id' => $id]);
    $tonersPorRegistro = $tonersPorRegistro->fetchAll();

    $tonersAgrupados = [];
    foreach ($tonersPorRegistro as $registroToner) {
        $chaveGrupo = $registroToner['nome'];
        if (!isset($tonersAgrupados[$chaveGrupo])) {
            $tonersAgrupados[$chaveGrupo] = [
                'vinculo_id' => $registroToner['vinculo_id'],
                'estoque_id' => $registroToner['estoque_id'],
                'nome' => $registroToner['nome'],
                'qtd_vinculada' => 0,
                'marcas' => [],
            ];
        }
        $tonersAgrupados[$chaveGrupo]['qtd_vinculada'] += (int) $registroToner['qtd_registro'];
        $tonersAgrupados[$chaveGrupo]['vinculo_id'] = min($tonersAgrupados[$chaveGrupo]['vinculo_id'], $registroToner['vinculo_id']);
        $tonersAgrupados[$chaveGrupo]['marcas'][] = [
            'texto' => trim(($registroToner['marca'] ?? '') . ' ' . ($registroToner['modelo'] ?? '')) ?: '-',
            'qtd' => (int) $registroToner['qtd_registro'],
        ];
    }
    $tonersVinculados = array_values($tonersAgrupados);

    $tonersDisponiveis = $pdo->prepare(
        "SELECT es.* FROM estoque es
         JOIN categorias_estoque c ON c.id = es.categoria_id
         WHERE es.quantidade > 0 AND c.nome = 'Toner'
         ORDER BY es.nome"
    );
    $tonersDisponiveis->execute();
    $tonersDisponiveis = $tonersDisponiveis->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-pc-display me-2"></i><?= $edicao ? 'Editar Equipamento' : 'Novo Equipamento' ?>
    </h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" novalidate>
    <!-- Identificação -->
    <div class="card mb-3">
        <div class="card-header bg-white"><strong><i class="bi bi-tag me-1"></i> Identificação</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Patrimônio</label>
                <input type="text" name="patrimonio" class="form-control" placeholder="Indefinido" value="<?= e($equipamento['patrimonio']) ?>">
                <div class="form-text">Deixe em branco se o patrimônio ainda não foi definido.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo *</label>
                <select name="tipo" id="tipo" class="form-select" required>
                    <?php foreach (tiposEquipamento() as $tipo): ?>
                        <option value="<?= e($tipo) ?>" <?= $equipamento['tipo'] === $tipo ? 'selected' : '' ?>><?= e($tipo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Marca</label>
                <input type="text" name="marca" class="form-control" value="<?= e($equipamento['marca']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Modelo</label>
                <input type="text" name="modelo" class="form-control" value="<?= e($equipamento['modelo']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Número de Série</label>
                <input type="text" name="numero_serie" class="form-control" value="<?= e($equipamento['numero_serie']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Hostname</label>
                <input type="text" name="hostname" class="form-control" value="<?= e($equipamento['hostname']) ?>">
            </div>
        </div>
    </div>

    <!-- Hardware -->
    <div class="card mb-3" id="hardwareFields">
        <div class="card-header bg-white"><strong><i class="bi bi-cpu me-1"></i> Hardware</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Processador</label>
                <input type="text" name="processador" class="form-control" value="<?= e($equipamento['processador']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Memória RAM</label>
                <input type="text" name="memoria_ram" class="form-control" placeholder="Ex: 16GB" value="<?= e($equipamento['memoria_ram']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Armazenamento</label>
                <input type="text" name="armazenamento" class="form-control" placeholder="Ex: SSD 512GB" value="<?= e($equipamento['armazenamento']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Sistema Operacional</label>
                <input type="text" name="sistema_operacional" class="form-control" value="<?= e($equipamento['sistema_operacional']) ?>">
            </div>
            <div class="col-md-3" id="placaMaeField">
                <label class="form-label">Placa Mãe</label>
                <input type="text" name="placa_mae" class="form-control" placeholder="Ex: ASUS PRIME B460M-A" value="<?= e($equipamento['placa_mae']) ?>">
            </div>
            <div class="col-md-3" id="placaVideoField">
                <label class="form-label">Placa de Vídeo</label>
                <input type="text" name="placa_video" class="form-control" placeholder="Ex: NVIDIA GeForce GTX 1650" value="<?= e($equipamento['placa_video']) ?>">
            </div>
        </div>
    </div>

    <!-- Campos específicos de impressora -->
    <div class="card mb-3" id="printerFields">
        <div class="card-header bg-white"><strong><i class="bi bi-printer me-1"></i> Dados da Impressora</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Endereço IP</label>
                <input type="text" name="ip" class="form-control" value="<?= e($equipamento['ip']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Modelo do Toner</label>
                <input type="text" name="modelo_toner" class="form-control" value="<?= e($equipamento['modelo_toner']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Qtd. de Toners Disponíveis</label>
                <input type="number" min="0" name="qtd_toners" class="form-control" value="<?= e((string) $equipamento['qtd_toners']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Duração estimada do toner (dias)</label>
                <input type="number" min="1" name="toner_duracao_dias" class="form-control" placeholder="Ex: 90 (≈ 3 meses)" value="<?= e((string) $equipamento['toner_duracao_dias']) ?>">
                <div class="form-text">Usado para calcular quando avisar sobre a próxima troca. Deixe em branco para não gerar alerta por tempo.</div>
            </div>
        </div>
    </div>

    <!-- Campos específicos de computador -->
    <div class="card mb-3" id="computerFields">
        <div class="card-header bg-white"><strong><i class="bi bi-ethernet me-1"></i> Rede do Computador</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">IP Fixo</label>
                <input type="text" name="ip_fixo" class="form-control" placeholder="Ex: 192.168.1.10" value="<?= e($equipamento['ip_fixo']) ?>">
            </div>
        </div>
    </div>

    <!-- Campos específicos de servidor -->
    <div class="card mb-3" id="serverFields">
        <div class="card-header bg-white"><strong><i class="bi bi-hdd-rack me-1"></i> Informações do Servidor</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Função do Servidor</label>
                <input type="text" name="funcao_servidor" class="form-control" list="funcoesServidorList" value="<?= e($equipamento['funcao_servidor']) ?>">
                <datalist id="funcoesServidorList">
                    <?php foreach (funcoesServidor() as $funcao): ?>
                        <option value="<?= e($funcao) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-4">
                <label class="form-label">Status do Servidor</label>
                <select name="servidor_status" class="form-select">
                    <?php foreach (statusServidor() as $st): ?>
                        <option value="<?= e($st) ?>" <?= $equipamento['servidor_status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12">
                <label class="form-label">Observações</label>
                <textarea name="servidor_observacoes" class="form-control" rows="3"><?= e($equipamento['servidor_observacoes']) ?></textarea>
            </div>
        </div>
    </div>

    <!-- Localização e uso -->
    <div class="card mb-3">
        <div class="card-header bg-white"><strong><i class="bi bi-geo-alt me-1"></i> Localização e Uso</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Status *</label>
                <select name="status" class="form-select" required>
                    <?php foreach (statusEquipamento() as $status): ?>
                        <option value="<?= e($status) ?>" <?= $equipamento['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Localização</label>
                <input type="text" name="localizacao" class="form-control" placeholder="Ex: Sala Financeiro" value="<?= e($equipamento['localizacao']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Usuário Responsável</label>
                <input type="text" name="usuario_responsavel" class="form-control" value="<?= e($equipamento['usuario_responsavel']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Rede</label>
                <select name="rede_id" class="form-select">
                    <option value="">Nenhuma</option>
                    <?php foreach ($redes as $rede): ?>
                        <option value="<?= (int) $rede['id'] ?>" <?= (string) $equipamento['rede_id'] === (string) $rede['id'] ? 'selected' : '' ?>><?= e($rede['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input type="checkbox" name="acesso_usb" id="acessoUsb" class="form-check-input" value="1" <?= !empty($equipamento['acesso_usb']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="acessoUsb">Permitir acesso a dispositivos USB</label>
                </div>
            </div>
        </div>
    </div>

    <!-- Financeiro (opcional) -->
    <div class="card mb-3">
        <div class="card-header bg-white">
            <strong><i class="bi bi-cash-coin me-1"></i> Financeiro (opcional)</strong>
        </div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Valor de Aquisição</label>
                <input type="text" name="valor_aquisicao" class="form-control" placeholder="0,00" value="<?= e((string) $equipamento['valor_aquisicao']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data da Compra</label>
                <input type="date" name="data_compra" class="form-control" value="<?= e($equipamento['data_compra']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Fornecedor</label>
                <input type="text" name="fornecedor" class="form-control" value="<?= e($equipamento['fornecedor']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Número da Nota Fiscal</label>
                <input type="text" name="numero_nota_fiscal" class="form-control" value="<?= e($equipamento['numero_nota_fiscal']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Garantia</label>
                <input type="text" name="garantia" class="form-control" placeholder="Ex: até 03/2027" value="<?= e($equipamento['garantia']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Valor Atual</label>
                <input type="text" name="valor_atual" class="form-control" placeholder="0,00" value="<?= e((string) $equipamento['valor_atual']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Observações Financeiras</label>
                <input type="text" name="observacoes_financeiras" class="form-control" value="<?= e($equipamento['observacoes_financeiras']) ?>">
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php if (!$edicao): ?>
    <div class="card mb-5" id="tonerNotaNovoField" style="display: none;">
        <div class="card-body">
            <p class="text-muted mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Salve o cadastro da impressora primeiro — depois disso, uma seção "Toner" aparece aqui
                para vincular ou cadastrar o toner sem precisar sair desta tela.
            </p>
        </div>
    </div>
<?php elseif (ehImpressora($equipamento['tipo'])): ?>
    <div class="card mb-5" id="toner">
        <div class="card-header bg-white"><strong><i class="bi bi-inkbottle me-1"></i> Toner</strong></div>
        <div class="card-body">
            <h6 class="text-muted text-uppercase small mb-3">Alerta de troca por tempo de uso</h6>
            <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                <div>
                    <?php if ($tonerStatus === null): ?>
                        <?php if (empty($registro['toner_duracao_dias'])): ?>
                            <span class="badge bg-secondary">Não configurado</span>
                            <span class="text-muted small ms-1">Defina a "Duração estimada do toner" acima para ativar este alerta.</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Aguardando 1ª troca</span>
                            <span class="text-muted small ms-1">Duração configurada: <?= (int) $registro['toner_duracao_dias'] ?> dia(s). Registre a troca para o prazo começar a contar.</span>
                        <?php endif; ?>
                    <?php elseif ($tonerStatus['nivel'] === 'vencido'): ?>
                        <span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> Troca atrasada</span>
                        <span class="text-muted small ms-1">Prevista para <?= $tonerStatus['proxima_troca']->format('d/m/Y') ?> (há <?= abs($tonerStatus['dias_restantes']) ?> dia(s)).</span>
                    <?php elseif ($tonerStatus['nivel'] === 'alerta'): ?>
                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Troca se aproximando</span>
                        <span class="text-muted small ms-1">Prevista para <?= $tonerStatus['proxima_troca']->format('d/m/Y') ?> (em <?= $tonerStatus['dias_restantes'] ?> dia(s)).</span>
                    <?php else: ?>
                        <span class="badge bg-success">Em dia</span>
                        <span class="text-muted small ms-1">Próxima troca prevista para <?= $tonerStatus['proxima_troca']->format('d/m/Y') ?> (em <?= $tonerStatus['dias_restantes'] ?> dia(s)).</span>
                    <?php endif; ?>
                    <div class="text-muted small mt-1">
                        Última troca registrada: <?= !empty($registro['toner_ultima_troca']) ? (new DateTime($registro['toner_ultima_troca']))->format('d/m/Y') : 'nunca' ?>
                    </div>
                </div>
                <form method="post" class="ms-auto">
                    <button type="submit" name="registrar_troca_toner" value="1" class="btn btn-outline-primary btn-sm js-confirm-delete"
                            data-confirm-msg="Registrar a troca do toner desta impressora hoje? O prazo de alerta será reiniciado a partir de hoje.">
                        <i class="bi bi-arrow-repeat"></i> Registrar Troca de Toner
                    </button>
                </form>
            </div>

            <h6 class="text-muted text-uppercase small mb-3">Toner instalado nesta impressora</h6>
            <?php if (empty($tonersVinculados)): ?>
                <p class="text-muted">Nenhum toner vinculado a esta impressora.</p>
            <?php else: ?>
                <table class="table table-sm table-hover mb-4">
                    <thead class="table-light">
                        <tr><th>Nome</th><th>Marca/Modelo</th><th class="text-center">Qtd. Itens</th><th class="text-end">Ações</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tonersVinculados as $tv): ?>
                        <tr>
                            <td><?= e($tv['nome']) ?></td>
                            <td>
                                <?php foreach ($tv['marcas'] as $marcaItem): ?>
                                    <div class="d-flex justify-content-between gap-3" style="max-width: 260px;">
                                        <span><?= e($marcaItem['texto']) ?></span>
                                        <span class="text-muted"><?= $marcaItem['qtd'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-center" title="Quantidade deste item vinculada a esta impressora"><?= (int) $tv['qtd_vinculada'] ?></td>
                            <td class="text-end">
                                <a href="../estoque/desvincular.php?id=<?= (int) $tv['vinculo_id'] ?>" class="btn btn-sm btn-outline-secondary js-confirm-delete"
                                   data-confirm-msg="Desvincular 1 unidade de &quot;<?= e($tv['nome']) ?>&quot; desta impressora?<?= (int) $tv['qtd_vinculada'] > 1 ? ' Ainda restarão ' . ((int) $tv['qtd_vinculada'] - 1) . ' unidade(s) vinculada(s).' : '' ?> Ela voltará a ficar disponível no estoque.">
                                    <i class="bi bi-x-lg"></i> Desvincular
                                </a>
                                <a href="../estoque/delete.php?id=<?= (int) $tv['estoque_id'] ?>&equipamento_id=<?= (int) $id ?>" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i> Excluir Toner
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <form method="post" class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">+ Vincular Toner do Estoque</label>
                    <select name="item_estoque_id" class="form-select" <?= empty($tonersDisponiveis) ? 'disabled' : '' ?> required>
                        <?php if (empty($tonersDisponiveis)): ?>
                            <option value="">Nenhum toner disponível no estoque</option>
                        <?php else: ?>
                            <option value="">Selecione um toner...</option>
                            <?php foreach ($tonersDisponiveis as $disp): ?>
                                <?php $marcaModelo = trim(($disp['marca'] ?? '') . ' ' . ($disp['modelo'] ?? '')); ?>
                                <option value="<?= (int) $disp['id'] ?>">
                                    <?= e($disp['nome']) ?><?= $marcaModelo ? ' (' . e($marcaModelo) . ')' : '' ?> · <?= (int) $disp['quantidade'] ?> disponível(is)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div class="form-text">Somente itens da categoria "Toner" aparecem aqui.</div>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="vincular_toner_form" value="1" class="btn btn-primary w-100" <?= empty($tonersDisponiveis) ? 'disabled' : '' ?>>
                        <i class="bi bi-plus-lg"></i> Vincular
                    </button>
                </div>
            </form>

            <?php if ($categoriaTonerId > 0): ?>
            <div class="mt-3">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#novoTonerFormCollapse">
                    <i class="bi bi-plus-circle"></i> Cadastrar e Vincular
                </button>
                <div class="collapse mt-3" id="novoTonerFormCollapse">
                    <div class="card card-body bg-light">
                        <p class="text-muted small mb-3">
                            Cadastra um toner no Estoque (categoria "Toner") e já vincula a esta impressora, sem
                            sair desta página. Se já existir um item com o mesmo nome, marca e modelo, a
                            quantidade informada é somada a ele em vez de criar um cadastro duplicado.
                        </p>
                        <form method="post" class="row g-2">
                            <input type="hidden" name="novo_item_categoria_id" value="<?= (int) $categoriaTonerId ?>">
                            <div class="col-md-4">
                                <label class="form-label small">Nome *</label>
                                <input type="text" name="novo_item_nome" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Marca</label>
                                <input type="text" name="novo_item_marca" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Modelo</label>
                                <input type="text" name="novo_item_modelo" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Quantidade cadastrada *</label>
                                <input type="number" min="1" name="novo_item_quantidade" class="form-control form-control-sm" value="1" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Quantidade mínima</label>
                                <input type="number" min="0" name="novo_item_quantidade_minima" class="form-control form-control-sm" value="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Localização</label>
                                <input type="text" name="novo_item_localizacao" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small">Observações</label>
                                <input type="text" name="novo_item_observacoes" class="form-control form-control-sm">
                            </div>
                            <div class="col-12 mt-2">
                                <button type="submit" name="cadastrar_toner_form" value="1" class="btn btn-sm btn-primary">
                                    <i class="bi bi-check-lg"></i> Cadastrar e vincular a esta impressora
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
