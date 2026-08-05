<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$pageTitle = 'Relatórios';

$relatorio = $_GET['relatorio'] ?? '';

$titulosRelatorios = [
    'todos_equipamentos'      => 'Lista completa de equipamentos',
    'equipamentos_em_uso'     => 'Equipamentos em uso',
    'equipamentos_disponiveis'=> 'Equipamentos disponíveis',
    'equipamentos_defeito'    => 'Equipamentos com defeito',
    'equipamentos_descartados'=> 'Equipamentos descartados',
    'equipamentos_por_responsavel' => 'Equipamentos por responsável',
    'equipamentos_por_rede'   => 'Equipamentos por rede',
    'estoque_lista'           => 'Lista de itens em estoque',
    'estoque_baixo'           => 'Itens com estoque baixo',
    'licencas_lista'          => 'Lista de licenças',
    'licencas_vinculadas'     => 'Licenças vinculadas',
    'financeiro_valor_total'  => 'Valor total dos equipamentos',
    'financeiro_sem_valor'    => 'Equipamentos sem valor cadastrado',
    'financeiro_garantia'     => 'Equipamentos em garantia',
];

$linhas = [];
$colunas = [];

if ($relatorio !== '' && isset($titulosRelatorios[$relatorio])) {
    switch ($relatorio) {
        case 'todos_equipamentos':
            $colunas = ['Patrimônio', 'Tipo', 'Marca/Modelo', 'Status', 'Responsável'];
            $rows = $pdo->query("SELECT patrimonio, tipo, CONCAT_WS(' ', marca, modelo) AS marca_modelo, status, usuario_responsavel FROM equipamentos ORDER BY patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['patrimonio'], $r['tipo'], $r['marca_modelo'] ?: '-', $r['status'], $r['usuario_responsavel'] ?: '-']; }
            break;

        case 'equipamentos_em_uso':
        case 'equipamentos_disponiveis':
        case 'equipamentos_defeito':
        case 'equipamentos_descartados':
            $mapaStatus = [
                'equipamentos_em_uso' => 'Em uso',
                'equipamentos_disponiveis' => 'Disponível',
                'equipamentos_defeito' => 'Com defeito',
                'equipamentos_descartados' => 'Descartado',
            ];
            $colunas = ['Patrimônio', 'Tipo', 'Marca/Modelo', 'Localização', 'Responsável'];
            $stmt = $pdo->prepare("SELECT patrimonio, tipo, CONCAT_WS(' ', marca, modelo) AS marca_modelo, localizacao, usuario_responsavel FROM equipamentos WHERE status = :status ORDER BY patrimonio");
            $stmt->execute(['status' => $mapaStatus[$relatorio]]);
            foreach ($stmt->fetchAll() as $r) { $linhas[] = [$r['patrimonio'], $r['tipo'], $r['marca_modelo'] ?: '-', $r['localizacao'] ?: '-', $r['usuario_responsavel'] ?: '-']; }
            break;

        case 'equipamentos_por_responsavel':
            $colunas = ['Responsável', 'Patrimônio', 'Tipo', 'Status'];
            $rows = $pdo->query("SELECT usuario_responsavel, patrimonio, tipo, status FROM equipamentos WHERE usuario_responsavel IS NOT NULL AND usuario_responsavel <> '' ORDER BY usuario_responsavel, patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['usuario_responsavel'], $r['patrimonio'], $r['tipo'], $r['status']]; }
            break;

        case 'equipamentos_por_rede':
            $colunas = ['Rede', 'Patrimônio', 'Tipo', 'Status'];
            $rows = $pdo->query("SELECT r.nome AS rede_nome, e.patrimonio, e.tipo, e.status FROM equipamentos e JOIN redes r ON r.id = e.rede_id ORDER BY r.nome, e.patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['rede_nome'], $r['patrimonio'], $r['tipo'], $r['status']]; }
            break;

        case 'estoque_lista':
            $colunas = ['Nome', 'Categoria', 'Quantidade', 'Mínimo', 'Localização'];
            $rows = $pdo->query("SELECT es.nome, c.nome AS categoria, es.quantidade, es.quantidade_minima, es.localizacao FROM estoque es JOIN categorias_estoque c ON c.id = es.categoria_id ORDER BY es.nome")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['nome'], $r['categoria'], (string) $r['quantidade'], (string) $r['quantidade_minima'], $r['localizacao'] ?: '-']; }
            break;

        case 'estoque_baixo':
            $colunas = ['Nome', 'Categoria', 'Quantidade', 'Mínimo'];
            $rows = $pdo->query("SELECT es.nome, c.nome AS categoria, es.quantidade, es.quantidade_minima FROM estoque es JOIN categorias_estoque c ON c.id = es.categoria_id WHERE es.quantidade < es.quantidade_minima ORDER BY es.nome")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['nome'], $r['categoria'], (string) $r['quantidade'], (string) $r['quantidade_minima']]; }
            break;

        case 'licencas_lista':
            $colunas = ['Software', 'Fabricante', 'Tipo', 'Equipamento'];
            $rows = $pdo->query("SELECT l.software, l.fabricante, l.tipo, e.patrimonio FROM licencas l LEFT JOIN equipamentos e ON e.id = l.equipamento_id ORDER BY l.software")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['software'], $r['fabricante'] ?: '-', $r['tipo'], $r['patrimonio'] ?: 'Sem vínculo']; }
            break;

        case 'licencas_vinculadas':
            $colunas = ['Software', 'Equipamento', 'Responsável'];
            $rows = $pdo->query("SELECT l.software, e.patrimonio, e.usuario_responsavel FROM licencas l JOIN equipamentos e ON e.id = l.equipamento_id ORDER BY e.patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['software'], $r['patrimonio'], $r['usuario_responsavel'] ?: '-']; }
            break;

        case 'financeiro_valor_total':
            $colunas = ['Patrimônio', 'Tipo', 'Valor de Aquisição', 'Valor Atual'];
            $rows = $pdo->query("SELECT patrimonio, tipo, valor_aquisicao, valor_atual FROM equipamentos WHERE valor_atual IS NOT NULL ORDER BY patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['patrimonio'], $r['tipo'], formatMoney($r['valor_aquisicao']), formatMoney($r['valor_atual'])]; }
            break;

        case 'financeiro_sem_valor':
            $colunas = ['Patrimônio', 'Tipo', 'Status'];
            $rows = $pdo->query("SELECT patrimonio, tipo, status FROM equipamentos WHERE valor_atual IS NULL ORDER BY patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['patrimonio'], $r['tipo'], $r['status']]; }
            break;

        case 'financeiro_garantia':
            $colunas = ['Patrimônio', 'Tipo', 'Garantia', 'Data da Compra'];
            $rows = $pdo->query("SELECT patrimonio, tipo, garantia, data_compra FROM equipamentos WHERE garantia IS NOT NULL AND garantia <> '' ORDER BY patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['patrimonio'], $r['tipo'], $r['garantia'], formatDate($r['data_compra'])]; }
            break;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 class="h3 mb-4"><i class="bi bi-bar-chart-line me-2"></i>Relatórios</h1>

<div class="row g-4">
    <div class="col-md-3">
        <div class="list-group">
            <div class="list-group-item list-group-item-secondary fw-semibold">Equipamentos</div>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'todos_equipamentos' ? 'active' : '' ?>" href="?relatorio=todos_equipamentos">Lista completa</a>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'equipamentos_em_uso' ? 'active' : '' ?>" href="?relatorio=equipamentos_em_uso">Em uso</a>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'equipamentos_disponiveis' ? 'active' : '' ?>" href="?relatorio=equipamentos_disponiveis">Disponíveis</a>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'equipamentos_defeito' ? 'active' : '' ?>" href="?relatorio=equipamentos_defeito">Com defeito</a>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'equipamentos_descartados' ? 'active' : '' ?>" href="?relatorio=equipamentos_descartados">Descartados</a>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'equipamentos_por_responsavel' ? 'active' : '' ?>" href="?relatorio=equipamentos_por_responsavel">Por responsável</a>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'equipamentos_por_rede' ? 'active' : '' ?>" href="?relatorio=equipamentos_por_rede">Por rede</a>

            <div class="list-group-item list-group-item-secondary fw-semibold">Estoque</div>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'estoque_lista' ? 'active' : '' ?>" href="?relatorio=estoque_lista">Lista de itens</a>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'estoque_baixo' ? 'active' : '' ?>" href="?relatorio=estoque_baixo">Estoque baixo</a>

            <div class="list-group-item list-group-item-secondary fw-semibold">Licenças</div>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'licencas_lista' ? 'active' : '' ?>" href="?relatorio=licencas_lista">Lista de licenças</a>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'licencas_vinculadas' ? 'active' : '' ?>" href="?relatorio=licencas_vinculadas">Licenças vinculadas</a>

            <div class="list-group-item list-group-item-secondary fw-semibold">Financeiro</div>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'financeiro_valor_total' ? 'active' : '' ?>" href="?relatorio=financeiro_valor_total">Valor dos equipamentos</a>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'financeiro_sem_valor' ? 'active' : '' ?>" href="?relatorio=financeiro_sem_valor">Sem valor cadastrado</a>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'financeiro_garantia' ? 'active' : '' ?>" href="?relatorio=financeiro_garantia">Em garantia</a>
        </div>
    </div>

    <div class="col-md-9">
        <?php if ($relatorio === ''): ?>
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-arrow-left fs-3"></i>
                    <p class="mt-2 mb-0">Selecione um relatório na lista ao lado.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong><?= e($titulosRelatorios[$relatorio]) ?></strong>
                    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><?php foreach ($colunas as $col): ?><th><?= e($col) ?></th><?php endforeach; ?></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($linhas)): ?>
                                <tr><td colspan="<?= count($colunas) ?>" class="text-center text-muted py-4">Nenhum registro encontrado para este relatório.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($linhas as $linha): ?>
                                <tr><?php foreach ($linha as $valor): ?><td><?= e((string) $valor) ?></td><?php endforeach; ?></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="text-muted small mt-2"><?= count($linhas) ?> registro(s) encontrado(s).</p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
