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

// --- Itens de estoque por categoria (gráfico) -----------------------------
$itensPorCategoriaRaw = $pdo->query(
    "SELECT c.nome AS categoria, COALESCE(SUM(es.quantidade), 0) AS total
     FROM categorias_estoque c
     LEFT JOIN estoque es ON es.categoria_id = c.id
     GROUP BY c.id, c.nome
     HAVING total > 0
     ORDER BY total DESC"
)->fetchAll();

$totalGeralEstoque = (int) array_sum(array_column($itensPorCategoriaRaw, 'total'));

// No máximo 5 categorias reais na cor própria; o restante é agrupado em "Outros".
if (count($itensPorCategoriaRaw) > 5) {
    $itensPorCategoriaChart = array_slice($itensPorCategoriaRaw, 0, 5);
    $itensPorCategoriaChart[] = [
        'categoria' => 'Outras categorias',
        'total' => array_sum(array_column(array_slice($itensPorCategoriaRaw, 5), 'total')),
    ];
} else {
    $itensPorCategoriaChart = $itensPorCategoriaRaw;
}

$coresCategoriaChart = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#898781'];
$raioDonut = 80;
$circunferenciaDonut = 2 * M_PI * $raioDonut;
$gapDonutPx = 3;
$anguloAcumuladoDonut = 0;
$segmentosDonut = [];
foreach ($itensPorCategoriaChart as $i => $cat) {
    $fracao = $totalGeralEstoque > 0 ? ((int) $cat['total']) / $totalGeralEstoque : 0;
    $comprimentoTotal = $fracao * $circunferenciaDonut;
    $comprimentoDesenhado = max($comprimentoTotal - $gapDonutPx, 0);
    $segmentosDonut[] = [
        'categoria'    => $cat['categoria'],
        'total'        => (int) $cat['total'],
        'percentual'   => $totalGeralEstoque > 0 ? round($fracao * 100, 1) : 0,
        'dasharray'    => $comprimentoDesenhado . ' ' . ($circunferenciaDonut - $comprimentoDesenhado),
        'dashoffset'   => -$anguloAcumuladoDonut,
        'cor'          => $coresCategoriaChart[$i] ?? '#898781',
    ];
    $anguloAcumuladoDonut += $comprimentoTotal;
}

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

<!-- Itens de estoque por categoria -->
<div class="card mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-pie-chart me-1"></i> Itens de Estoque por Categoria</strong>
        <?php if (!empty($segmentosDonut)): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="estoqueChartTableToggle">
                <i class="bi bi-table"></i> Ver como tabela
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($segmentosDonut)): ?>
            <p class="text-muted mb-0">Nenhum item em estoque cadastrado ainda.</p>
        <?php else: ?>
            <div class="scati-donut-wrap" id="estoqueChartWrap">
                <div class="scati-donut-figure">
                    <svg viewBox="0 0 200 200" width="200" height="200" class="scati-donut-svg" role="img"
                         aria-label="Distribuição de <?= $totalGeralEstoque ?> itens de estoque entre <?= count($segmentosDonut) ?> categorias">
                        <g transform="rotate(-90 100 100)">
                            <?php foreach ($segmentosDonut as $seg): ?>
                                <circle
                                    cx="100" cy="100" r="<?= $raioDonut ?>" fill="none"
                                    stroke="<?= e($seg['cor']) ?>" stroke-width="28"
                                    stroke-dasharray="<?= $seg['dasharray'] ?>"
                                    stroke-dashoffset="<?= $seg['dashoffset'] ?>"
                                    class="scati-donut-seg" tabindex="0"
                                    data-categoria="<?= e($seg['categoria']) ?>"
                                    data-total="<?= $seg['total'] ?>"
                                    data-percentual="<?= $seg['percentual'] ?>"
                                ><title><?= e($seg['categoria']) ?>: <?= $seg['total'] ?> item(ns) (<?= $seg['percentual'] ?>%)</title></circle>
                            <?php endforeach; ?>
                        </g>
                    </svg>
                    <div class="scati-donut-center">
                        <div class="scati-donut-total"><?= $totalGeralEstoque ?></div>
                        <div class="scati-donut-total-label">itens</div>
                    </div>
                </div>
                <div class="scati-donut-legend">
                    <?php foreach ($segmentosDonut as $seg): ?>
                        <div class="scati-legend-item">
                            <span class="scati-legend-swatch" style="background-color: <?= e($seg['cor']) ?>;"></span>
                            <span class="scati-legend-label"><?= e($seg['categoria']) ?></span>
                            <span class="scati-legend-value"><?= $seg['total'] ?> (<?= $seg['percentual'] ?>%)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="table-responsive d-none" id="estoqueChartTable">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Categoria</th>
                            <th>Quantidade</th>
                            <th>Percentual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($segmentosDonut as $seg): ?>
                            <tr>
                                <td><?= e($seg['categoria']) ?></td>
                                <td><?= $seg['total'] ?></td>
                                <td><?= $seg['percentual'] ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="scati-donut-tooltip" id="estoqueDonutTooltip" role="tooltip" hidden></div>
        <?php endif; ?>
    </div>
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
