<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('equipamentos', 'alterar');

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

// Ao abrir o cadastro a partir de uma aba específica (ex.: "Nova Impressora"),
// pré-seleciona o tipo do equipamento.
$tipoPreSelecionado = $_GET['tipo'] ?? '';
$tipoInicial = in_array($tipoPreSelecionado, tiposEquipamento(), true) ? $tipoPreSelecionado : 'Computador';

$equipamento = [
    'nome' => '', 'patrimonio' => '', 'tipo' => $tipoInicial, 'marca' => '', 'modelo' => '', 'numero_serie' => '',
    'hostname' => '', 'processador' => '', 'memoria_ram' => '', 'armazenamento' => '', 'sistema_operacional' => '',
    'status' => 'Disponível', 'localizacao' => '', 'usuario_responsavel' => '', 'rede_id' => '', 'acesso_usb' => '',
    'ip' => '', 'toner_duracao_dias' => '',
    'ip_fixo' => '', 'placa_mae' => '', 'placa_video' => '',
    'funcao_servidor' => '', 'servidor_status' => 'Ativo', 'servidor_observacoes' => '',
    'qtd_portas_switch' => '',
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

// Cadastro, vínculo com outras impressoras e ajuste de quantidade de
// Toner/Tinta ficam em modules/impressoras/toners.php — aqui só resta o
// botão manual de "Registrar Troca", como fallback independente de
// baixa de estoque.
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

    // Só grava os campos dos grupos habilitados na categoria escolhida —
    // valida contra o banco, não confia em campos escondidos via inspecionar
    // elemento para um tipo sem aquele grupo habilitado.
    $gruposHabilitados = gruposHabilitadosParaTipo($equipamento['tipo']);
    foreach (gruposCamposEquipamento() as $chave => $grupo) {
        if (in_array($chave, $gruposHabilitados, true)) {
            continue;
        }
        foreach ($grupo['campos'] as $nomeCampo => $config) {
            $equipamento[$nomeCampo] = '';
        }
    }

    // Validações essenciais
    if ($equipamento['nome'] === '') {
        $erros[] = 'O campo Nome do Equipamento é obrigatório.';
    }
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
        $tonerDuracaoDias = $equipamento['toner_duracao_dias'] !== '' ? (int) $equipamento['toner_duracao_dias'] : null;
        $qtdPortasSwitch = $equipamento['qtd_portas_switch'] !== '' ? (int) $equipamento['qtd_portas_switch'] : null;
        $valorAquisicao = $equipamento['valor_aquisicao'] !== '' ? (float) str_replace(',', '.', $equipamento['valor_aquisicao']) : null;
        $valorAtual = $equipamento['valor_atual'] !== '' ? (float) str_replace(',', '.', $equipamento['valor_atual']) : null;
        $dataCompra = $equipamento['data_compra'] !== '' ? $equipamento['data_compra'] : null;

        $dadosParaSalvar = [
            'nome' => $equipamento['nome'],
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
            'toner_duracao_dias' => $tonerDuracaoDias,
            'ip_fixo' => $equipamento['ip_fixo'] ?: null,
            'placa_mae' => $equipamento['placa_mae'] ?: null,
            'placa_video' => $equipamento['placa_video'] ?: null,
            'funcao_servidor' => $equipamento['funcao_servidor'] ?: null,
            'servidor_status' => $equipamento['servidor_status'] ?: null,
            'servidor_observacoes' => $equipamento['servidor_observacoes'] ?: null,
            'qtd_portas_switch' => $qtdPortasSwitch,
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
                if (($registro['nome'] ?? '') !== $dadosParaSalvar['nome']) {
                    $de = $registro['nome'] ?: '(vazio)';
                    $para = $dadosParaSalvar['nome'] ?: '(vazio)';
                    $mudancas[] = ['Alteração', "Nome alterado de \"$de\" para \"$para\""];
                }
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
                if (temMapeamentoPortas($dadosParaSalvar['tipo']) && (int) ($registro['qtd_portas_switch'] ?? 0) !== (int) ($dadosParaSalvar['qtd_portas_switch'] ?? 0)) {
                    $mudancas[] = ['Alteração', 'Quantidade de portas do switch alterada para ' . ((int) $dadosParaSalvar['qtd_portas_switch'] ?: '0')];
                }
                $camposFinanceiros = ['valor_aquisicao', 'data_compra', 'fornecedor', 'numero_nota_fiscal', 'garantia', 'valor_atual', 'observacoes_financeiras'];
                foreach ($camposFinanceiros as $campoFin) {
                    if ((string) ($registro[$campoFin] ?? '') !== (string) ($dadosParaSalvar[$campoFin] ?? '')) {
                        $mudancas[] = ['Alteração', 'Informações financeiras atualizadas'];
                        break;
                    }
                }

                $sql = 'UPDATE equipamentos SET
                        nome = :nome, patrimonio = :patrimonio, tipo = :tipo, marca = :marca, modelo = :modelo,
                        numero_serie = :numero_serie, hostname = :hostname, processador = :processador,
                        memoria_ram = :memoria_ram, armazenamento = :armazenamento, sistema_operacional = :sistema_operacional,
                        status = :status, localizacao = :localizacao, usuario_responsavel = :usuario_responsavel, rede_id = :rede_id,
                        acesso_usb = :acesso_usb,
                        ip = :ip, toner_duracao_dias = :toner_duracao_dias,
                        ip_fixo = :ip_fixo, placa_mae = :placa_mae,
                        placa_video = :placa_video,
                        funcao_servidor = :funcao_servidor, servidor_status = :servidor_status, servidor_observacoes = :servidor_observacoes,
                        qtd_portas_switch = :qtd_portas_switch,
                        valor_aquisicao = :valor_aquisicao, data_compra = :data_compra, fornecedor = :fornecedor,
                        numero_nota_fiscal = :numero_nota_fiscal, garantia = :garantia, valor_atual = :valor_atual,
                        observacoes_financeiras = :observacoes_financeiras
                        WHERE id = :id';
                $dadosParaSalvar['id'] = $id;
                $pdo->prepare($sql)->execute($dadosParaSalvar);

                foreach ($mudancas as [$evento, $descricao]) {
                    registrarHistorico($id, $evento, $descricao);
                }

                if (temMapeamentoPortas($dadosParaSalvar['tipo'])) {
                    sincronizarPortasSwitch($id, $qtdPortasSwitch);
                }

                flash('success', 'Equipamento atualizado com sucesso.');
                redirect('/modules/equipamentos/view.php?id=' . $id);
            } else {
                $sql = 'INSERT INTO equipamentos
                        (nome, patrimonio, tipo, marca, modelo, numero_serie, hostname, processador, memoria_ram,
                         armazenamento, sistema_operacional, status, localizacao, usuario_responsavel, rede_id, acesso_usb,
                         ip, toner_duracao_dias, ip_fixo, placa_mae, placa_video, funcao_servidor, servidor_status, servidor_observacoes,
                         qtd_portas_switch,
                         valor_aquisicao, data_compra, fornecedor, numero_nota_fiscal,
                         garantia, valor_atual, observacoes_financeiras)
                        VALUES
                        (:nome, :patrimonio, :tipo, :marca, :modelo, :numero_serie, :hostname, :processador, :memoria_ram,
                         :armazenamento, :sistema_operacional, :status, :localizacao, :usuario_responsavel, :rede_id, :acesso_usb,
                         :ip, :toner_duracao_dias, :ip_fixo, :placa_mae, :placa_video, :funcao_servidor, :servidor_status, :servidor_observacoes,
                         :qtd_portas_switch,
                         :valor_aquisicao, :data_compra, :fornecedor, :numero_nota_fiscal,
                         :garantia, :valor_atual, :observacoes_financeiras)';
                $pdo->prepare($sql)->execute($dadosParaSalvar);
                $novoId = (int) $pdo->lastInsertId();

                registrarHistorico($novoId, 'Cadastro', 'Equipamento cadastrado');

                if (temMapeamentoPortas($dadosParaSalvar['tipo'])) {
                    sincronizarPortasSwitch($novoId, $qtdPortasSwitch);
                }

                flash('success', 'Equipamento cadastrado com sucesso.');
                redirect('/modules/equipamentos/view.php?id=' . $novoId);
            }
        } catch (PDOException $e) {
            $erros[] = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

$redes = $pdo->query('SELECT id, nome FROM redes ORDER BY nome')->fetchAll();
$categoriasEquip = $pdo->query('SELECT * FROM categorias_equipamento ORDER BY ordem ASC, nome ASC')->fetchAll();
$gruposEquipamento = gruposCamposEquipamento();
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

// Toners/Tintas vinculados a esta impressora (catálogo próprio — ver
// modules/impressoras/toners.php), usados apenas ao editar um
// equipamento do tipo Impressora.
$tonersVinculados = [];
if ($edicao && ehImpressora($equipamento['tipo'])) {
    $stmtToners = $pdo->prepare(
        "SELECT t.id, t.nome, t.tipo, t.marca, t.modelo, t.quantidade
         FROM toner_impressoras ti JOIN toners t ON t.id = ti.toner_id
         WHERE ti.equipamento_id = :id ORDER BY t.nome"
    );
    $stmtToners->execute(['id' => $id]);
    $tonersVinculados = $stmtToners->fetchAll();
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
            <div class="col-md-6">
                <label class="form-label">Nome do Equipamento *</label>
                <input type="text" name="nome" class="form-control" required placeholder="Ex: Notebook do Financeiro" value="<?= e($equipamento['nome']) ?>">
                <div class="form-text">Nome usado para identificar o equipamento nas listagens (além do patrimônio).</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Patrimônio</label>
                <input type="text" name="patrimonio" class="form-control" placeholder="Indefinido" value="<?= e($equipamento['patrimonio']) ?>">
                <div class="form-text">Deixe em branco se o patrimônio ainda não foi definido.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo *</label>
                <select name="tipo" id="tipo" class="form-select" required>
                    <?php foreach ($categoriasEquip as $catEquip): ?>
                        <?php
                            $gruposDaCategoria = [];
                            foreach ($gruposEquipamento as $chave => $grupo) {
                                if (!empty($catEquip[$grupo['coluna']])) {
                                    $gruposDaCategoria[] = $chave;
                                }
                            }
                        ?>
                        <option value="<?= e($catEquip['nome']) ?>" data-grupos="<?= e(implode(',', $gruposDaCategoria)) ?>" <?= $equipamento['tipo'] === $catEquip['nome'] ? 'selected' : '' ?>><?= e($catEquip['nome']) ?></option>
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
            <div class="col-md-3">
                <label class="form-label">Número de Série</label>
                <input type="text" name="numero_serie" class="form-control" value="<?= e($equipamento['numero_serie']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Hostname</label>
                <input type="text" name="hostname" class="form-control" value="<?= e($equipamento['hostname']) ?>">
            </div>
        </div>
    </div>

    <!-- Hardware -->
    <div class="card mb-3" id="grupoCampos_hardware">
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
            <div class="col-md-3">
                <label class="form-label">Placa Mãe</label>
                <input type="text" name="placa_mae" class="form-control" placeholder="Ex: ASUS PRIME B460M-A" value="<?= e($equipamento['placa_mae']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Placa de Vídeo</label>
                <input type="text" name="placa_video" class="form-control" placeholder="Ex: NVIDIA GeForce GTX 1650" value="<?= e($equipamento['placa_video']) ?>">
            </div>
        </div>
    </div>

    <!-- Campos específicos de impressora -->
    <div class="card mb-3" id="grupoCampos_impressora">
        <div class="card-header bg-white"><strong><i class="bi bi-printer me-1"></i> Dados da Impressora</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Endereço IP</label>
                <input type="text" name="ip" class="form-control" value="<?= e($equipamento['ip']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Duração estimada do toner (dias)</label>
                <input type="number" min="1" name="toner_duracao_dias" class="form-control" placeholder="Ex: 90 (≈ 3 meses)" value="<?= e((string) $equipamento['toner_duracao_dias']) ?>">
                <div class="form-text">Usado para calcular quando avisar sobre a próxima troca. Deixe em branco para não gerar alerta por tempo.</div>
            </div>
            <?php if ($edicao && ehImpressora($equipamento['tipo'])): ?>
            <div class="col-md-4 d-flex align-items-end">
                <a href="../impressoras/toners.php" class="btn btn-outline-secondary w-100"><i class="bi bi-droplet-half"></i> Cadastro de Toner/Tinta</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Campos específicos de computador -->
    <div class="card mb-3" id="grupoCampos_rede_computador">
        <div class="card-header bg-white"><strong><i class="bi bi-ethernet me-1"></i> Rede do Computador</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">IP Fixo</label>
                <input type="text" name="ip_fixo" class="form-control" placeholder="Ex: 192.168.1.10" value="<?= e($equipamento['ip_fixo']) ?>">
            </div>
        </div>
    </div>

    <!-- Campos específicos de servidor -->
    <div class="card mb-3" id="grupoCampos_servidor">
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

    <!-- Campos específicos de switch -->
    <div class="card mb-3" id="grupoCampos_switch">
        <div class="card-header bg-white"><strong><i class="bi bi-diagram-3 me-1"></i> Portas do Switch</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Quantidade de Portas</label>
                <input type="number" min="1" max="96" name="qtd_portas_switch" class="form-control" placeholder="Ex: 24" value="<?= e((string) $equipamento['qtd_portas_switch']) ?>">
                <div class="form-text">
                    Gera automaticamente o mapeamento de portas na aba "Mapeamento de Portas" da ficha deste
                    equipamento. Reduzir a quantidade não apaga vínculos já feitos nas portas além do novo
                    limite — eles só ficam ocultos até a quantidade ser aumentada de novo.
                </div>
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
                mostrando o toner vinculado a ela.
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

            <h6 class="text-muted text-uppercase small mb-3">Toner desta impressora</h6>
            <?php if (empty($tonersVinculados)): ?>
                <p class="text-muted">Nenhum toner vinculado a esta impressora ainda.</p>
            <?php else: ?>
                <table class="table table-sm table-hover mb-2">
                    <thead class="table-light">
                        <tr><th>Toner</th><th class="text-center">Em estoque</th><th class="text-end">Ações</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tonersVinculados as $tv): ?>
                        <tr>
                            <td>
                                <strong><?= e($tv['nome']) ?></strong>
                                <span class="text-muted small"><?= e(trim(($tv['marca'] ?? '') . ' · ' . ($tv['modelo'] ?? ''), ' ·')) ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= (int) $tv['quantidade'] <= 0 ? 'bg-danger' : 'bg-success' ?>"><?= (int) $tv['quantidade'] ?></span>
                            </td>
                            <td class="text-end">
                                <a href="../impressoras/toner.php?id=<?= (int) $tv['id'] ?>&de_impressora=<?= (int) $id ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-droplet-half"></i> Gerenciar neste Toner
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p class="text-muted small mb-0">
                <i class="bi bi-info-circle"></i> Cadastro, vínculo com outras impressoras e ajuste de quantidade
                agora ficam todos em <a href="../impressoras/toners.php">Impressoras &gt; Toners e Tintas</a>.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
