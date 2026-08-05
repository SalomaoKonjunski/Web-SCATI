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

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h1>
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
