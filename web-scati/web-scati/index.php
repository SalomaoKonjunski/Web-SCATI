<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Dashboard';
$pdo = db();

// --- Totais por tipo de equipamento ---------------------------------
$totaisPorTipo = $pdo->query(
    "SELECT tipo, COUNT(*) AS total FROM equipamentos GROUP BY tipo"
)->fetchAll();
$mapaTipos = array_column($totaisPorTipo, 'total', 'tipo');

// --- Totais por status -----------------------------------------------
$totaisPorStatus = $pdo->query(
    "SELECT status, COUNT(*) AS total FROM equipamentos GROUP BY status"
)->fetchAll();
$mapaStatus = array_column($totaisPorStatus, 'total', 'status');

// --- Estoque -----------------------------------------------------------
$totalItensEstoque = (int) $pdo->query("SELECT COALESCE(SUM(quantidade),0) FROM estoque")->fetchColumn();
$estoqueAbaixoMinimo = (int) $pdo->query(
    "SELECT COUNT(*) FROM estoque WHERE quantidade < quantidade_minima"
)->fetchColumn();

// --- Licenças ------------------------------------------------------------
$totalLicencas = (int) $pdo->query("SELECT COUNT(*) FROM licencas")->fetchColumn();

// --- Financeiro (apenas se houver algum valor cadastrado) --------------
$valorTotalPatrimonio = $pdo->query(
    "SELECT SUM(valor_atual) FROM equipamentos WHERE valor_atual IS NOT NULL"
)->fetchColumn();
$temInfoFinanceira = $valorTotalPatrimonio !== null;

// --- Últimos equipamentos cadastrados -----------------------------------
$ultimosEquipamentos = $pdo->query(
    "SELECT id, patrimonio, tipo, marca, modelo, status, criado_em
     FROM equipamentos ORDER BY criado_em DESC LIMIT 5"
)->fetchAll();

// --- Central de Alertas --------------------------------------------------
// Licenças já vencidas ou vencendo nos próximos 30 dias.
$licencasVencendo = $pdo->query(
    "SELECT l.id, l.software, l.data_validade, e.patrimonio
     FROM licencas l LEFT JOIN equipamentos e ON e.id = l.equipamento_id
     WHERE l.data_validade IS NOT NULL AND l.data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY l.data_validade ASC"
)->fetchAll();

// Itens de estoque abaixo da quantidade mínima.
$itensEstoqueBaixo = $pdo->query(
    "SELECT id, nome, quantidade, quantidade_minima
     FROM estoque WHERE quantidade < quantidade_minima ORDER BY nome"
)->fetchAll();

// Impressoras sem nenhum toner vinculado no momento.
$impressorasSemToner = $pdo->query(
    "SELECT e.id, e.patrimonio, e.marca, e.modelo
     FROM equipamentos e
     WHERE e.tipo = 'Impressora'
       AND NOT EXISTS (
           SELECT 1 FROM itens_vinculados iv
           JOIN estoque es ON es.id = iv.estoque_id
           JOIN categorias_estoque c ON c.id = es.categoria_id
           WHERE iv.equipamento_id = e.id AND c.nome = 'Toner'
       )
     ORDER BY e.patrimonio"
)->fetchAll();

$totalAlertas = count($licencasVencendo) + count($itensEstoqueBaixo) + count($impressorasSemToner);

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h1>
</div>

<!-- Central de Alertas -->
<div class="card mb-4 <?= $totalAlertas > 0 ? 'border-warning' : '' ?>">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-bell me-1"></i> Central de Alertas</strong>
        <?php if ($totalAlertas > 0): ?>
            <span class="badge bg-danger"><?= $totalAlertas ?> alerta(s)</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($totalAlertas === 0): ?>
            <p class="text-success mb-0"><i class="bi bi-check-circle me-1"></i> Tudo em dia! Nenhum alerta no momento.</p>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($licencasVencendo as $lic): ?>
                    <?php
                        $diasParaVencer = (int) floor((strtotime($lic['data_validade']) - strtotime('today')) / 86400);
                        $vencida = $diasParaVencer < 0;
                    ?>
                    <a href="<?= BASE_URL ?>/modules/licencas/index.php" class="list-group-item list-group-item-action">
                        <span class="badge <?= $vencida ? 'bg-danger' : 'bg-warning text-dark' ?> me-2">Licença</span>
                        <?= e($lic['software']) ?><?= $lic['patrimonio'] ? ' — ' . e($lic['patrimonio']) : '' ?>
                        <?= $vencida
                            ? 'vencida em ' . formatDate($lic['data_validade'])
                            : 'vence em ' . formatDate($lic['data_validade']) . ' (' . $diasParaVencer . ' dia(s))' ?>
                    </a>
                <?php endforeach; ?>

                <?php foreach ($itensEstoqueBaixo as $item): ?>
                    <a href="<?= BASE_URL ?>/modules/estoque/index.php" class="list-group-item list-group-item-action">
                        <span class="badge bg-danger me-2">Estoque</span>
                        <?= e($item['nome']) ?> — <?= (int) $item['quantidade'] ?> em estoque (mínimo <?= (int) $item['quantidade_minima'] ?>)
                    </a>
                <?php endforeach; ?>

                <?php foreach ($impressorasSemToner as $imp): ?>
                    <?php $marcaModeloImp = trim(($imp['marca'] ?? '') . ' ' . ($imp['modelo'] ?? '')); ?>
                    <a href="<?= BASE_URL ?>/modules/equipamentos/view.php?id=<?= (int) $imp['id'] ?>#toner" class="list-group-item list-group-item-action">
                        <span class="badge bg-warning text-dark me-2">Impressora</span>
                        <?= e($imp['patrimonio']) ?><?= $marcaModeloImp ? ' — ' . e($marcaModeloImp) : '' ?> sem toner vinculado
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- KPIs de equipamentos por tipo -->
<div class="row g-3 mb-2">
    <div class="col-6 col-md-3">
        <div class="card scati-kpi-card border-start border-4 border-primary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Computadores</div>
                    <div class="kpi-value"><?= (int) ($mapaTipos['Computador'] ?? 0) ?></div>
                </div>
                <i class="bi bi-pc-display kpi-icon text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card scati-kpi-card border-start border-4 border-primary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Notebooks</div>
                    <div class="kpi-value"><?= (int) ($mapaTipos['Notebook'] ?? 0) ?></div>
                </div>
                <i class="bi bi-laptop kpi-icon text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card scati-kpi-card border-start border-4 border-primary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Impressoras</div>
                    <div class="kpi-value"><?= (int) ($mapaTipos['Impressora'] ?? 0) ?></div>
                </div>
                <i class="bi bi-printer kpi-icon text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card scati-kpi-card border-start border-4 border-primary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Monitores</div>
                    <div class="kpi-value"><?= (int) ($mapaTipos['Monitor'] ?? 0) ?></div>
                </div>
                <i class="bi bi-display kpi-icon text-primary"></i>
            </div>
        </div>
    </div>
</div>

<!-- KPIs de status -->
<div class="row g-3 mt-1">
    <div class="col-6 col-md-3">
        <div class="card scati-kpi-card border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small">Em uso</div>
                <div class="kpi-value"><?= (int) ($mapaStatus['Em uso'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card scati-kpi-card border-start border-4 border-info">
            <div class="card-body">
                <div class="text-muted small">Disponíveis</div>
                <div class="kpi-value"><?= (int) ($mapaStatus['Disponível'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card scati-kpi-card border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-muted small">Com defeito</div>
                <div class="kpi-value"><?= (int) ($mapaStatus['Com defeito'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card scati-kpi-card border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-muted small">Em manutenção</div>
                <div class="kpi-value"><?= (int) ($mapaStatus['Em manutenção'] ?? 0) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Estoque, Licenças e Financeiro -->
<div class="row g-3 mt-1">
    <div class="col-md-4">
        <div class="card scati-kpi-card">
            <div class="card-body">
                <div class="text-muted small">Itens em estoque</div>
                <div class="kpi-value"><?= $totalItensEstoque ?></div>
                <?php if ($estoqueAbaixoMinimo > 0): ?>
                    <span class="badge bg-danger mt-2">
                        <i class="bi bi-exclamation-triangle"></i> <?= $estoqueAbaixoMinimo ?> item(ns) abaixo do mínimo
                    </span>
                <?php else: ?>
                    <span class="badge bg-success mt-2">Estoque normalizado</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card scati-kpi-card">
            <div class="card-body">
                <div class="text-muted small">Licenças cadastradas</div>
                <div class="kpi-value"><?= $totalLicencas ?></div>
            </div>
        </div>
    </div>
    <?php if ($temInfoFinanceira): ?>
    <div class="col-md-4">
        <div class="card scati-kpi-card">
            <div class="card-body">
                <div class="text-muted small">Valor total aproximado do patrimônio</div>
                <div class="kpi-value"><?= formatMoney($valorTotalPatrimonio) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Últimos equipamentos cadastrados -->
<div class="card mt-4">
    <div class="card-header bg-white">
        <strong><i class="bi bi-clock-history me-1"></i> Últimos equipamentos cadastrados</strong>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Patrimônio</th>
                    <th>Tipo</th>
                    <th>Marca / Modelo</th>
                    <th>Status</th>
                    <th>Cadastrado em</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ultimosEquipamentos)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Nenhum equipamento cadastrado ainda.</td></tr>
                <?php endif; ?>
                <?php foreach ($ultimosEquipamentos as $eq): ?>
                    <tr data-href="<?= BASE_URL ?>/modules/equipamentos/view.php?id=<?= (int) $eq['id'] ?>">
                        <td><?= e($eq['patrimonio']) ?></td>
                        <td><?= e($eq['tipo']) ?></td>
                        <td><?= e(trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? ''))) ?: '-' ?></td>
                        <td><span class="badge <?= statusBadgeClass($eq['status']) ?>"><?= e($eq['status']) ?></span></td>
                        <td><?= formatDateTime($eq['criado_em']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
