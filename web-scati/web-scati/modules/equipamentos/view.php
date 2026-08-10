<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT e.*, r.nome AS rede_nome, r.faixa_ip AS rede_faixa_ip
     FROM equipamentos e LEFT JOIN redes r ON r.id = e.rede_id
     WHERE e.id = :id'
);
$stmt->execute(['id' => $id]);
$eq = $stmt->fetch();

if (!$eq) {
    flash('danger', 'Equipamento não encontrado.');
    redirect('/modules/equipamentos/index.php');
}

// Trata o envio do formulário de nova observação (POST nesta mesma página)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_observacao'])) {
    $texto = trim($_POST['nova_observacao']);
    if ($texto !== '') {
        $stmtObs = $pdo->prepare(
            'INSERT INTO observacoes_equipamentos (equipamento_id, texto) VALUES (:id, :texto)'
        );
        $stmtObs->execute(['id' => $id, 'texto' => $texto]);
        flash('success', 'Observação registrada.');
    }
    redirect('/modules/equipamentos/view.php?id=' . $id . '#observacoes');
}

// Trata o registro de uma manutenção no histórico (POST nesta mesma página)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_manutencao'])) {
    $tipoManutencao = $_POST['tipo_manutencao'] ?? '';
    $dataManutencao = $_POST['data_manutencao'] ?? '';
    $descricaoManutencao = trim($_POST['descricao_manutencao'] ?? '');

    if (in_array($tipoManutencao, tiposManutencao(), true) && $dataManutencao !== '') {
        $descricaoFinal = $descricaoManutencao !== '' ? $descricaoManutencao : $tipoManutencao;
        $stmtManut = $pdo->prepare(
            'INSERT INTO historico_equipamentos (equipamento_id, data_hora, evento, descricao)
             VALUES (:id, :data_hora, :evento, :descricao)'
        );
        $stmtManut->execute([
            'id' => $id,
            'data_hora' => $dataManutencao . ' ' . date('H:i:s'),
            'evento' => $tipoManutencao,
            'descricao' => $descricaoFinal,
        ]);
        flash('success', 'Manutenção registrada no histórico.');
    } else {
        flash('danger', 'Selecione o tipo de manutenção e a data.');
    }
    redirect('/modules/equipamentos/view.php?id=' . $id . '#historico');
}

// Trata a vinculação de uma unidade de um item de estoque a este equipamento (POST nesta mesma página)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vincular_item'])) {
    $itemId = (int) ($_POST['item_estoque_id'] ?? 0);
    if ($itemId > 0) {
        // Reserva 1 unidade de forma atômica: só decrementa se ainda houver quantidade disponível
        $stmtDecr = $pdo->prepare('UPDATE estoque SET quantidade = quantidade - 1 WHERE id = :id AND quantidade > 0');
        $stmtDecr->execute(['id' => $itemId]);

        if ($stmtDecr->rowCount() > 0) {
            $stmtNome = $pdo->prepare('SELECT nome FROM estoque WHERE id = :id');
            $stmtNome->execute(['id' => $itemId]);
            $nomeItem = $stmtNome->fetchColumn();

            $pdo->prepare('INSERT INTO itens_vinculados (estoque_id, equipamento_id) VALUES (:estoque_id, :equipamento_id)')
                ->execute(['estoque_id' => $itemId, 'equipamento_id' => $id]);

            registrarHistorico($id, 'Item', 'Item "' . $nomeItem . '" vinculado a este equipamento');
            flash('success', 'Item vinculado com sucesso.');
        } else {
            flash('danger', 'Item indisponível para vínculo (sem unidades em estoque).');
        }
    }
    redirect('/modules/equipamentos/view.php?id=' . $id . '#itens');
}

// Trata o cadastro (ou reaproveitamento) de um item de estoque direto nesta
// página, já vinculando-o automaticamente a este equipamento (POST nesta mesma página)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_item_estoque'])) {
    $destinoAba = in_array($_POST['destino_aba'] ?? '', ['itens', 'toner'], true) ? $_POST['destino_aba'] : 'itens';

    $novoNome = trim($_POST['novo_item_nome'] ?? '');
    $novaCategoriaId = (int) ($_POST['novo_item_categoria_id'] ?? 0);
    $novaMarca = trim($_POST['novo_item_marca'] ?? '');
    $novoModelo = trim($_POST['novo_item_modelo'] ?? '');
    $novaQuantidade = (int) ($_POST['novo_item_quantidade'] ?? 0);
    $novaQuantidadeMinima = (int) ($_POST['novo_item_quantidade_minima'] ?? 0);
    $novaLocalizacao = trim($_POST['novo_item_localizacao'] ?? '');
    $novasObservacoes = trim($_POST['novo_item_observacoes'] ?? '');

    if ($novoNome === '' || $novaCategoriaId <= 0 || $novaQuantidade < 1) {
        flash('danger', 'Preencha nome, categoria e uma quantidade de pelo menos 1 unidade para cadastrar e vincular o item.');
        redirect('/modules/equipamentos/view.php?id=' . $id . '#' . $destinoAba);
    }

    $stmtCat = $pdo->prepare('SELECT nome FROM categorias_estoque WHERE id = :id');
    $stmtCat->execute(['id' => $novaCategoriaId]);
    $categoriaNome = $stmtCat->fetchColumn() ?: null;

    // Procura um item já cadastrado com o mesmo nome, marca e modelo (comparação
    // sem diferenciar maiúsculas/minúsculas ou espaços nas pontas) para não
    // duplicar o cadastro — só soma a quantidade ao registro existente. Marca e
    // modelo diferentes (ex.: "Monitor" Asus x "Monitor" Acer) geram registros
    // separados no Estoque — assim cada modelo mantém sua própria quantidade e
    // fica visível a qual equipamento cada um foi vinculado.
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
            'Quantidade alterada de ' . (int) $itemExistente['quantidade'] . ' para ' . ((int) $itemExistente['quantidade'] + $novaQuantidade) . ' (+' . $novaQuantidade . ', via aba de vinculação)'
        );
        $mensagemCadastro = 'Item já existia no estoque — quantidade somada e ';
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
        $mensagemCadastro = 'Item cadastrado no estoque e ';
    }

    // Vincula automaticamente 1 unidade a este equipamento
    $stmtDecr = $pdo->prepare('UPDATE estoque SET quantidade = quantidade - 1 WHERE id = :id AND quantidade > 0');
    $stmtDecr->execute(['id' => $itemId]);

    if ($stmtDecr->rowCount() > 0) {
        $pdo->prepare('INSERT INTO itens_vinculados (estoque_id, equipamento_id) VALUES (:estoque_id, :equipamento_id)')
            ->execute(['estoque_id' => $itemId, 'equipamento_id' => $id]);
        registrarHistorico($id, 'Item', 'Item "' . $novoNome . '" ' . ($itemExistente ? 'reaproveitado do estoque' : 'cadastrado no estoque') . ' e vinculado a este equipamento');
        flash('success', $mensagemCadastro . 'vinculado com sucesso.');
    } else {
        flash('success', $mensagemCadastro . 'atualizado, mas não foi possível vinculá-lo automaticamente.');
    }

    redirect('/modules/equipamentos/view.php?id=' . $id . '#' . $destinoAba);
}

// Licenças vinculadas a este equipamento
$licencas = $pdo->prepare('SELECT * FROM licencas WHERE equipamento_id = :id ORDER BY software');
$licencas->execute(['id' => $id]);
$licencas = $licencas->fetchAll();

// Histórico automático (mais recente primeiro)
$historico = $pdo->prepare('SELECT * FROM historico_equipamentos WHERE equipamento_id = :id ORDER BY data_hora DESC');
$historico->execute(['id' => $id]);
$historico = $historico->fetchAll();

// Observações (cronológicas, mais recente primeiro)
$observacoes = $pdo->prepare('SELECT * FROM observacoes_equipamentos WHERE equipamento_id = :id ORDER BY data_hora DESC');
$observacoes->execute(['id' => $id]);
$observacoes = $observacoes->fetchAll();

// Anexos (mais recente primeiro)
$anexos = $pdo->prepare('SELECT * FROM anexos_equipamentos WHERE equipamento_id = :id ORDER BY criado_em DESC');
$anexos->execute(['id' => $id]);
$anexos = $anexos->fetchAll();

// Itens de estoque vinculados a este equipamento (uma linha por unidade vinculada)
// Primeiro soma por registro exato do Estoque (mesmo nome, marca e modelo);
// depois, em PHP, agrupa por nome do item, somando a quantidade total e
// mantendo a quantidade de cada marca/modelo separada para exibição.
$itensVinculadosPorRegistro = $pdo->prepare(
    'SELECT MIN(iv.id) AS vinculo_id, es.id AS estoque_id, es.nome, es.marca, es.modelo,
            COUNT(iv.id) AS qtd_registro, c.nome AS categoria_nome
     FROM itens_vinculados iv
     JOIN estoque es ON es.id = iv.estoque_id
     JOIN categorias_estoque c ON c.id = es.categoria_id
     WHERE iv.equipamento_id = :id
     GROUP BY es.id, es.nome, es.marca, es.modelo, c.nome
     ORDER BY es.nome, es.marca, es.modelo'
);
$itensVinculadosPorRegistro->execute(['id' => $id]);
$itensVinculadosPorRegistro = $itensVinculadosPorRegistro->fetchAll();

$itensVinculadosAgrupados = [];
foreach ($itensVinculadosPorRegistro as $registroItem) {
    $chaveGrupo = $registroItem['nome'] . '|' . $registroItem['categoria_nome'];
    if (!isset($itensVinculadosAgrupados[$chaveGrupo])) {
        $itensVinculadosAgrupados[$chaveGrupo] = [
            'vinculo_id' => $registroItem['vinculo_id'],
            'estoque_id' => $registroItem['estoque_id'],
            'nome' => $registroItem['nome'],
            'categoria_nome' => $registroItem['categoria_nome'],
            'qtd_vinculada' => 0,
            'marcas' => [],
        ];
    }
    $itensVinculadosAgrupados[$chaveGrupo]['qtd_vinculada'] += (int) $registroItem['qtd_registro'];
    $itensVinculadosAgrupados[$chaveGrupo]['vinculo_id'] = min($itensVinculadosAgrupados[$chaveGrupo]['vinculo_id'], $registroItem['vinculo_id']);
    $itensVinculadosAgrupados[$chaveGrupo]['marcas'][] = [
        'texto' => trim(($registroItem['marca'] ?? '') . ' ' . ($registroItem['modelo'] ?? '')) ?: '-',
        'qtd' => (int) $registroItem['qtd_registro'],
    ];
}
$itensVinculados = array_values($itensVinculadosAgrupados);

// Itens de estoque com unidades disponíveis para vincular a este equipamento
$itensDisponiveis = $pdo->query(
    'SELECT es.*, c.nome AS categoria_nome
     FROM estoque es JOIN categorias_estoque c ON c.id = es.categoria_id
     WHERE es.quantidade > 0 ORDER BY c.nome, es.nome'
)->fetchAll();

// Toners vinculados/disponíveis (subconjunto dos itens acima, restrito à categoria "Toner")
$tonersVinculados = array_values(array_filter($itensVinculados, fn($iv) => $iv['categoria_nome'] === 'Toner'));
$tonersDisponiveis = array_values(array_filter($itensDisponiveis, fn($disp) => $disp['categoria_nome'] === 'Toner'));

// Categorias de estoque, usadas no formulário de cadastro rápido de item (abaixo)
$categoriasEstoque = $pdo->query('SELECT id, nome FROM categorias_estoque ORDER BY nome')->fetchAll();
$categoriaTonerId = 0;
foreach ($categoriasEstoque as $catEst) {
    if ($catEst['nome'] === 'Toner') {
        $categoriaTonerId = (int) $catEst['id'];
        break;
    }
}

// Compartilhamentos de rede (apenas para equipamentos do tipo Servidor)
$compartilhamentos = $pdo->prepare('SELECT * FROM compartilhamentos_servidor WHERE equipamento_id = :id ORDER BY nome');
$compartilhamentos->execute(['id' => $id]);
$compartilhamentos = $compartilhamentos->fetchAll();

$computadoresVinculados = [];
if (!empty($compartilhamentos)) {
    $placeholders = implode(',', array_fill(0, count($compartilhamentos), '?'));
    $stmtVinc = $pdo->prepare(
        "SELECT cc.compartilhamento_id, e.id, e.patrimonio, e.hostname
         FROM compartilhamento_computadores cc
         JOIN equipamentos e ON e.id = cc.equipamento_id
         WHERE cc.compartilhamento_id IN ($placeholders)
         ORDER BY e.patrimonio"
    );
    $stmtVinc->execute(array_column($compartilhamentos, 'id'));
    foreach ($stmtVinc->fetchAll() as $v) {
        $computadoresVinculados[$v['compartilhamento_id']][] = $v;
    }
}

$pageTitle = 'Ficha do Equipamento';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-pc-display me-2"></i><?= e($eq['patrimonio']) ?></h1>
        <span class="badge <?= statusBadgeClass($eq['status']) ?> mt-1"><?= e($eq['status']) ?></span>
        <span class="text-muted ms-2"><?= e($eq['tipo']) ?> · <?= e(trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? ''))) ?: '' ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        <a href="ficha_impressao.php?id=<?= (int) $eq['id'] ?>" class="btn btn-outline-secondary" target="_blank">
            <i class="bi bi-printer"></i> Ficha para Impressão
        </a>
        <a href="form.php?id=<?= (int) $eq['id'] ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
        <a href="delete.php?id=<?= (int) $eq['id'] ?>" class="btn btn-outline-danger js-confirm-delete"
           data-confirm-msg="Excluir o equipamento <?= e($eq['patrimonio']) ?>? Esta ação não pode ser desfeita.">
            <i class="bi bi-trash"></i> Excluir
        </a>
    </div>
</div>

<ul class="nav nav-tabs" id="fichaTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#dados" type="button">Dados Gerais</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#licenciamento" type="button">
            Licenciamento <span class="badge bg-secondary"><?= count($licencas) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#itens" type="button">
            Itens Vinculados <span class="badge bg-secondary"><?= count($itensVinculados) ?></span>
        </button>
    </li>
    <?php if (ehImpressora($eq['tipo'])): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#toner" type="button">
            Toner <span class="badge bg-secondary"><?= count($tonersVinculados) ?></span>
        </button>
    </li>
    <?php endif; ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#financeiro" type="button">Financeiro</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#historico" type="button">
            Histórico <span class="badge bg-secondary"><?= count($historico) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#observacoes" type="button">
            Observações <span class="badge bg-secondary"><?= count($observacoes) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#anexos" type="button">
            Anexos <span class="badge bg-secondary"><?= count($anexos) ?></span>
        </button>
    </li>
    <?php if (ehServidor($eq['tipo'])): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#compartilhamentos" type="button">
            Compartilhamentos <span class="badge bg-secondary"><?= count($compartilhamentos) ?></span>
        </button>
    </li>
    <?php endif; ?>
</ul>

<div class="tab-content bg-white border border-top-0 p-4 rounded-bottom mb-5">

    <!-- Dados Gerais -->
    <div class="tab-pane fade show active" id="dados">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small mb-3">Identificação</h6>
                <table class="table table-sm">
                    <tr><th style="width:40%">Patrimônio</th><td><?= e($eq['patrimonio']) ?></td></tr>
                    <tr><th>Tipo</th><td><?= e($eq['tipo']) ?></td></tr>
                    <tr><th>Marca</th><td><?= e($eq['marca']) ?: '-' ?></td></tr>
                    <tr><th>Modelo</th><td><?= e($eq['modelo']) ?: '-' ?></td></tr>
                    <tr><th>Número de Série</th><td><?= e($eq['numero_serie']) ?: '-' ?></td></tr>
                    <tr><th>Hostname</th><td><?= e($eq['hostname']) ?: '-' ?></td></tr>
                </table>

                <h6 class="text-muted text-uppercase small mb-3 mt-4">Hardware</h6>
                <table class="table table-sm">
                    <tr><th style="width:40%">Processador</th><td><?= e($eq['processador']) ?: '-' ?></td></tr>
                    <tr><th>Memória RAM</th><td><?= e($eq['memoria_ram']) ?: '-' ?></td></tr>
                    <tr><th>Armazenamento</th><td><?= e($eq['armazenamento']) ?: '-' ?></td></tr>
                    <tr><th>Sistema Operacional</th><td><?= e($eq['sistema_operacional']) ?: '-' ?></td></tr>
                    <?php if (ehComputador($eq['tipo'])): ?>
                    <tr><th>Placa Mãe</th><td><?= e($eq['placa_mae']) ?: '-' ?></td></tr>
                    <tr><th>Placa de Vídeo</th><td><?= e($eq['placa_video']) ?: '-' ?></td></tr>
                    <?php endif; ?>
                </table>

                <?php if (ehImpressora($eq['tipo'])): ?>
                <h6 class="text-muted text-uppercase small mb-3 mt-4">Dados da Impressora</h6>
                <table class="table table-sm">
                    <tr><th style="width:40%">Endereço IP</th><td><?= e($eq['ip']) ?: '-' ?></td></tr>
                    <tr><th>Modelo do Toner</th><td><?= e($eq['modelo_toner']) ?: '-' ?></td></tr>
                    <tr><th>Qtd. de Toners</th><td><?= $eq['qtd_toners'] !== null ? (int) $eq['qtd_toners'] : '-' ?></td></tr>
                </table>
                <?php endif; ?>

                <?php if (ehComputador($eq['tipo'])): ?>
                <h6 class="text-muted text-uppercase small mb-3 mt-4">Rede do Computador</h6>
                <table class="table table-sm">
                    <tr><th style="width:40%">IP Fixo</th><td><?= e($eq['ip_fixo']) ?: '-' ?></td></tr>
                </table>
                <?php endif; ?>

                <?php if (ehServidor($eq['tipo'])): ?>
                <h6 class="text-muted text-uppercase small mb-3 mt-4">Informações do Servidor</h6>
                <table class="table table-sm">
                    <tr><th style="width:40%">Função do Servidor</th><td><?= e($eq['funcao_servidor']) ?: '-' ?></td></tr>
                    <tr>
                        <th>Status do Servidor</th>
                        <td>
                            <?php if ($eq['servidor_status']): ?>
                                <span class="badge <?= statusServidorBadgeClass($eq['servidor_status']) ?>"><?= e($eq['servidor_status']) ?></span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                    <tr><th>Observações</th><td><?= $eq['servidor_observacoes'] ? nl2br(e($eq['servidor_observacoes'])) : '-' ?></td></tr>
                </table>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small mb-3">Localização e Uso</h6>
                <table class="table table-sm">
                    <tr><th style="width:40%">Status</th><td><span class="badge <?= statusBadgeClass($eq['status']) ?>"><?= e($eq['status']) ?></span></td></tr>
                    <tr><th>Localização</th><td><?= e($eq['localizacao']) ?: '-' ?></td></tr>
                    <tr><th>Usuário Responsável</th><td><?= e($eq['usuario_responsavel']) ?: '-' ?></td></tr>
                    <tr>
                        <th>Rede</th>
                        <td>
                            <?php if ($eq['rede_nome']): ?>
                                <?= e($eq['rede_nome']) ?> <span class="text-muted small">(<?= e($eq['rede_faixa_ip']) ?>)</span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Acesso a Dispositivos USB</th>
                        <td>
                            <?php if ($eq['acesso_usb']): ?>
                                <span class="badge bg-success"><i class="bi bi-usb-symbol"></i> Permitido</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="bi bi-usb-symbol"></i> Bloqueado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Licenciamento -->
    <div class="tab-pane fade" id="licenciamento">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="text-muted text-uppercase small mb-0">Licenças vinculadas a este equipamento</h6>
            <a href="../licencas/form.php?equipamento_id=<?= (int) $eq['id'] ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg"></i> Vincular Nova Licença
            </a>
        </div>
        <?php if (empty($licencas)): ?>
            <p class="text-muted">Nenhuma licença vinculada a este equipamento.</p>
        <?php else: ?>
            <table class="table table-sm table-hover">
                <thead class="table-light">
                    <tr><th>Software</th><th>Fabricante</th><th>Tipo</th><th>Versão</th><th>Validade</th><th class="text-end">Ações</th></tr>
                </thead>
                <tbody>
                <?php foreach ($licencas as $lic): ?>
                    <tr>
                        <td><?= e($lic['software']) ?></td>
                        <td><?= e($lic['fabricante']) ?: '-' ?></td>
                        <td><?= e($lic['tipo']) ?></td>
                        <td><?= e($lic['versao']) ?: '-' ?></td>
                        <td><?= formatDate($lic['data_validade']) ?></td>
                        <td class="text-end">
                            <a href="../licencas/form.php?id=<?= (int) $lic['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <a href="../licencas/transferir.php?id=<?= (int) $lic['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left-right"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Itens Vinculados -->
    <div class="tab-pane fade" id="itens">
        <h6 class="text-muted text-uppercase small mb-3">Itens de estoque vinculados a este equipamento</h6>
        <?php if (empty($itensVinculados)): ?>
            <p class="text-muted">Nenhum item vinculado a este equipamento.</p>
        <?php else: ?>
            <table class="table table-sm table-hover mb-4">
                <thead class="table-light">
                    <tr><th>Nome</th><th>Categoria</th><th>Marca/Modelo</th><th class="text-center">Qtd. Itens</th><th class="text-end">Ações</th></tr>
                </thead>
                <tbody>
                <?php foreach ($itensVinculados as $iv): ?>
                    <tr>
                        <td><?= e($iv['nome']) ?></td>
                        <td><?= e($iv['categoria_nome']) ?></td>
                        <td>
                            <?php foreach ($iv['marcas'] as $marcaItem): ?>
                                <div class="d-flex justify-content-between gap-3" style="max-width: 260px;">
                                    <span><?= e($marcaItem['texto']) ?></span>
                                    <span class="text-muted"><?= $marcaItem['qtd'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td class="text-center" title="Quantidade deste item vinculada a este equipamento"><?= (int) $iv['qtd_vinculada'] ?></td>
                        <td class="text-end">
                            <a href="../estoque/desvincular.php?id=<?= (int) $iv['vinculo_id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Desvincular 1 unidade de &quot;<?= e($iv['nome']) ?>&quot; deste equipamento?<?= (int) $iv['qtd_vinculada'] > 1 ? ' Ainda restarão ' . ((int) $iv['qtd_vinculada'] - 1) . ' unidade(s) vinculada(s).' : '' ?> Ela voltará a ficar disponível no estoque.">
                                <i class="bi bi-x-lg"></i> Desvincular
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <form method="post" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label fw-semibold">+ Vincular Item do Estoque</label>
                <select name="item_estoque_id" class="form-select" <?= empty($itensDisponiveis) ? 'disabled' : '' ?> required>
                    <?php if (empty($itensDisponiveis)): ?>
                        <option value="">Nenhum item disponível no estoque</option>
                    <?php else: ?>
                        <option value="">Selecione um item...</option>
                        <?php foreach ($itensDisponiveis as $disp): ?>
                            <?php $marcaModelo = trim(($disp['marca'] ?? '') . ' ' . ($disp['modelo'] ?? '')); ?>
                            <option value="<?= (int) $disp['id'] ?>">
                                <?= e($disp['nome']) ?> — <?= e($disp['categoria_nome']) ?><?= $marcaModelo ? ' (' . e($marcaModelo) . ')' : '' ?> · <?= (int) $disp['quantidade'] ?> disponível(is)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" name="vincular_item" value="1" class="btn btn-primary w-100" <?= empty($itensDisponiveis) ? 'disabled' : '' ?>>
                    <i class="bi bi-plus-lg"></i> Vincular
                </button>
            </div>
        </form>

        <div class="mt-3">
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#novoItemEstoqueCollapse">
                <i class="bi bi-plus-circle"></i> Cadastrar e Vincular
            </button>
            <div class="collapse mt-3" id="novoItemEstoqueCollapse">
                <div class="card card-body bg-light">
                    <p class="text-muted small mb-3">
                        Cadastra um item de qualquer categoria do Estoque e já vincula 1 unidade a este equipamento,
                        sem sair desta página. Se já existir um item com o mesmo nome, marca e modelo, a quantidade
                        informada é somada ao item existente em vez de criar um cadastro duplicado. Marca ou modelo
                        diferentes (ex.: outra marca de monitor) geram um novo registro, mantendo cada modelo
                        rastreável separadamente.
                    </p>
                    <form method="post" class="row g-2">
                        <input type="hidden" name="destino_aba" value="itens">
                        <div class="col-md-4">
                            <label class="form-label small">Nome *</label>
                            <input type="text" name="novo_item_nome" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Categoria *</label>
                            <select name="novo_item_categoria_id" class="form-select form-select-sm" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($categoriasEstoque as $catOpt): ?>
                                    <option value="<?= (int) $catOpt['id'] ?>"><?= e($catOpt['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Quantidade cadastrada *</label>
                            <input type="number" min="1" name="novo_item_quantidade" class="form-control form-control-sm" value="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Marca</label>
                            <input type="text" name="novo_item_marca" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Modelo</label>
                            <input type="text" name="novo_item_modelo" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Quantidade mínima</label>
                            <input type="number" min="0" name="novo_item_quantidade_minima" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Localização</label>
                            <input type="text" name="novo_item_localizacao" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small">Observações</label>
                            <input type="text" name="novo_item_observacoes" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" name="cadastrar_item_estoque" value="1" class="btn btn-sm btn-primary">
                                <i class="bi bi-check-lg"></i> Cadastrar e vincular a este equipamento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (ehImpressora($eq['tipo'])): ?>
    <!-- Toner -->
    <div class="tab-pane fade" id="toner">
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
                            <a href="../estoque/delete.php?id=<?= (int) $tv['estoque_id'] ?>&equipamento_id=<?= (int) $eq['id'] ?>" class="btn btn-sm btn-outline-danger">
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
                <button type="submit" name="vincular_item" value="1" class="btn btn-primary w-100" <?= empty($tonersDisponiveis) ? 'disabled' : '' ?>>
                    <i class="bi bi-plus-lg"></i> Vincular
                </button>
            </div>
        </form>

        <?php if ($categoriaTonerId > 0): ?>
        <div class="mt-3">
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#novoTonerEstoqueCollapse">
                <i class="bi bi-plus-circle"></i> Cadastrar e Vincular
            </button>
            <div class="collapse mt-3" id="novoTonerEstoqueCollapse">
                <div class="card card-body bg-light">
                    <p class="text-muted small mb-3">
                        Cadastra um toner no Estoque (categoria "Toner") e já vincula a esta impressora, sem sair
                        desta página. Se já existir um item com o mesmo nome, marca e modelo, a quantidade informada
                        é somada a ele em vez de criar um cadastro duplicado.
                    </p>
                    <form method="post" class="row g-2">
                        <input type="hidden" name="destino_aba" value="toner">
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
                            <button type="submit" name="cadastrar_item_estoque" value="1" class="btn btn-sm btn-primary">
                                <i class="bi bi-check-lg"></i> Cadastrar e vincular a esta impressora
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Financeiro -->
    <div class="tab-pane fade" id="financeiro">
        <table class="table table-sm">
            <tr><th style="width:40%">Valor de Aquisição</th><td><?= formatMoney($eq['valor_aquisicao']) ?></td></tr>
            <tr><th>Data da Compra</th><td><?= formatDate($eq['data_compra']) ?></td></tr>
            <tr><th>Fornecedor</th><td><?= e($eq['fornecedor']) ?: '-' ?></td></tr>
            <tr><th>Número da Nota Fiscal</th><td><?= e($eq['numero_nota_fiscal']) ?: '-' ?></td></tr>
            <tr><th>Garantia</th><td><?= e($eq['garantia']) ?: '-' ?></td></tr>
            <tr><th>Valor Atual</th><td><?= formatMoney($eq['valor_atual']) ?></td></tr>
            <tr><th>Observações Financeiras</th><td><?= e($eq['observacoes_financeiras']) ?: '-' ?></td></tr>
        </table>
    </div>

    <!-- Histórico -->
    <div class="tab-pane fade" id="historico">
        <form method="post" class="mb-4">
            <label class="form-label fw-semibold">+ Registrar Manutenção</label>
            <div class="row g-2">
                <div class="col-md-3">
                    <select name="tipo_manutencao" class="form-select" required>
                        <option value="">Tipo de manutenção...</option>
                        <?php foreach (tiposManutencao() as $tipo): ?>
                            <option value="<?= e($tipo) ?>"><?= e($tipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="data_manutencao" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-5">
                    <input type="text" name="descricao_manutencao" class="form-control" placeholder="Descrição (opcional)">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="registrar_manutencao" value="1" class="btn btn-primary w-100">
                        <i class="bi bi-tools"></i> Registrar
                    </button>
                </div>
            </div>
        </form>

        <h6 class="text-muted text-uppercase small mb-3">Histórico automático de alterações</h6>
        <?php if (empty($historico)): ?>
            <p class="text-muted">Nenhum evento registrado.</p>
        <?php else: ?>
            <table class="table table-sm table-hover">
                <thead class="table-light"><tr><th style="width:160px">Data / Hora</th><th style="width:160px">Evento</th><th>Descrição</th></tr></thead>
                <tbody>
                <?php foreach ($historico as $h): ?>
                    <tr>
                        <td><?= formatDateTime($h['data_hora']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= e($h['evento']) ?></span></td>
                        <td><?= e($h['descricao']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Observações -->
    <div class="tab-pane fade" id="observacoes">
        <form method="post" class="mb-4">
            <label class="form-label fw-semibold">+ Nova Observação</label>
            <div class="input-group">
                <input type="text" name="nova_observacao" class="form-control" placeholder="Descreva a observação..." required>
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Adicionar</button>
            </div>
        </form>

        <?php if (empty($observacoes)): ?>
            <p class="text-muted">Nenhuma observação registrada.</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($observacoes as $obs): ?>
                    <li class="list-group-item">
                        <div class="small text-muted"><?= formatDateTime($obs['data_hora']) ?></div>
                        <div><?= nl2br(e($obs['texto'])) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Anexos -->
    <div class="tab-pane fade" id="anexos">
        <form method="post" action="anexo_upload.php" enctype="multipart/form-data" class="mb-4">
            <label class="form-label fw-semibold">+ Novo Anexo</label>
            <input type="hidden" name="equipamento_id" value="<?= (int) $eq['id'] ?>">
            <div class="row g-2">
                <div class="col-md-5">
                    <div class="d-flex gap-2">
                        <input type="file" name="arquivo" id="anexoArquivo" class="form-control" required>
                        <label for="anexoCamera" class="btn btn-outline-secondary d-md-none flex-shrink-0 mb-0" title="Tirar foto">
                            <i class="bi bi-camera"></i>
                        </label>
                        <input type="file" id="anexoCamera" accept="image/*" capture="environment" class="d-none">
                    </div>
                </div>
                <div class="col-md-5">
                    <input type="text" name="descricao" class="form-control" placeholder="Descrição (opcional, ex: Nota fiscal)">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload"></i> Enviar</button>
                </div>
            </div>
            <div class="form-text">
                Tamanho máximo 10 MB. Formatos aceitos: <?= e(implode(', ', extensoesAnexoPermitidas())) ?>.
                <span class="d-md-none">No celular, use <i class="bi bi-camera"></i> para tirar uma foto na hora.</span>
            </div>
        </form>

        <?php if (empty($anexos)): ?>
            <p class="text-muted">Nenhum anexo cadastrado para este equipamento.</p>
        <?php else: ?>
            <table class="table table-sm table-hover">
                <thead class="table-light">
                    <tr><th>Arquivo</th><th>Descrição</th><th>Tamanho</th><th>Enviado em</th><th class="text-end">Ações</th></tr>
                </thead>
                <tbody>
                <?php foreach ($anexos as $anexo): ?>
                    <?php $extensaoAnexo = pathinfo($anexo['nome_original'], PATHINFO_EXTENSION); ?>
                    <tr>
                        <td><i class="bi <?= iconeAnexo($extensaoAnexo) ?> me-1 text-muted"></i><?= e($anexo['nome_original']) ?></td>
                        <td><?= e($anexo['descricao']) ?: '-' ?></td>
                        <td><?= formatBytes((int) $anexo['tamanho']) ?></td>
                        <td><?= formatDateTime($anexo['criado_em']) ?></td>
                        <td class="text-end">
                            <a href="anexo_download.php?id=<?= (int) $anexo['id'] ?>" class="btn btn-sm btn-outline-primary" title="Baixar">
                                <i class="bi bi-download"></i>
                            </a>
                            <a href="anexo_excluir.php?id=<?= (int) $anexo['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir o anexo &quot;<?= e($anexo['nome_original']) ?>&quot;? Esta ação não pode ser desfeita." title="Excluir">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if (ehServidor($eq['tipo'])): ?>
    <!-- Compartilhamentos -->
    <div class="tab-pane fade" id="compartilhamentos">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="text-muted text-uppercase small mb-0">Pastas compartilhadas deste servidor</h6>
            <a href="../compartilhamentos/form.php?equipamento_id=<?= (int) $eq['id'] ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg"></i> Novo Compartilhamento
            </a>
        </div>
        <?php if (empty($compartilhamentos)): ?>
            <p class="text-muted">Nenhum compartilhamento cadastrado para este servidor.</p>
        <?php else: ?>
            <?php foreach ($compartilhamentos as $comp): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?= e($comp['nome']) ?></h6>
                                <div class="small text-muted mb-2"><i class="bi bi-folder2 me-1"></i><?= e($comp['caminho_pasta']) ?></div>
                                <?php if ($comp['descricao']): ?><p class="mb-1"><?= nl2br(e($comp['descricao'])) ?></p><?php endif; ?>
                                <?php if ($comp['permissoes']): ?><div class="small"><strong>Permissões:</strong> <?= e($comp['permissoes']) ?></div><?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="../compartilhamentos/form.php?id=<?= (int) $comp['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="../compartilhamentos/delete.php?id=<?= (int) $comp['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                                   data-confirm-msg="Excluir o compartilhamento <?= e($comp['nome']) ?>? Esta ação não pode ser desfeita." title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small text-muted d-block mb-1">Computadores vinculados:</span>
                            <?php $vinc = $computadoresVinculados[$comp['id']] ?? []; ?>
                            <?php if (empty($vinc)): ?>
                                <span class="text-muted small">Nenhum computador vinculado.</span>
                            <?php else: ?>
                                <?php foreach ($vinc as $v): ?>
                                    <a href="view.php?id=<?= (int) $v['id'] ?>" class="badge bg-light text-dark border text-decoration-none me-1">
                                        <?= e($v['patrimonio']) ?><?= $v['hostname'] ? ' — ' . e($v['hostname']) : '' ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
