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
    'historico_alteracoes'    => 'Histórico de alterações',
];

$linhas = [];
$colunas = [];

// Filtros específicos do relatório "Histórico de alterações"
$filtroHistEvento = $_GET['h_evento'] ?? '';
$filtroHistCategoria = $_GET['h_categoria'] ?? '';
$filtroHistDataIni = $_GET['h_data_ini'] ?? '';
$filtroHistDataFim = $_GET['h_data_fim'] ?? '';
$filtroHistBusca = trim($_GET['h_busca'] ?? '');
$eventosHistorico = $pdo->query(
    "SELECT DISTINCT evento FROM (
        SELECT evento FROM historico_equipamentos
        UNION
        SELECT evento FROM historico_estoque
    ) t ORDER BY evento"
)->fetchAll(PDO::FETCH_COLUMN);
$categoriasHistorico = $pdo->query('SELECT nome FROM categorias_estoque ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);

if ($relatorio !== '' && isset($titulosRelatorios[$relatorio])) {
    switch ($relatorio) {
        case 'todos_equipamentos':
            $colunas = ['Patrimônio', 'Tipo', 'Marca/Modelo', 'Status', 'Responsável'];
            $rows = $pdo->query("SELECT patrimonio, tipo, CONCAT_WS(' ', marca, modelo) AS marca_modelo, status, usuario_responsavel FROM equipamentos ORDER BY patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [patrimonioOuIndefinido($r['patrimonio']), $r['tipo'], $r['marca_modelo'] ?: '-', $r['status'], $r['usuario_responsavel'] ?: '-']; }
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
            foreach ($stmt->fetchAll() as $r) { $linhas[] = [patrimonioOuIndefinido($r['patrimonio']), $r['tipo'], $r['marca_modelo'] ?: '-', $r['localizacao'] ?: '-', $r['usuario_responsavel'] ?: '-']; }
            break;

        case 'equipamentos_por_responsavel':
            $colunas = ['Responsável', 'Patrimônio', 'Tipo', 'Status'];
            $rows = $pdo->query("SELECT usuario_responsavel, patrimonio, tipo, status FROM equipamentos WHERE usuario_responsavel IS NOT NULL AND usuario_responsavel <> '' ORDER BY usuario_responsavel, patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['usuario_responsavel'], patrimonioOuIndefinido($r['patrimonio']), $r['tipo'], $r['status']]; }
            break;

        case 'equipamentos_por_rede':
            $colunas = ['Rede', 'Patrimônio', 'Tipo', 'Status'];
            $rows = $pdo->query("SELECT r.nome AS rede_nome, e.patrimonio, e.tipo, e.status FROM equipamentos e JOIN redes r ON r.id = e.rede_id ORDER BY r.nome, e.patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['rede_nome'], patrimonioOuIndefinido($r['patrimonio']), $r['tipo'], $r['status']]; }
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
            $rows = $pdo->query("SELECT l.software, l.fabricante, l.tipo, l.equipamento_id, e.patrimonio FROM licencas l LEFT JOIN equipamentos e ON e.id = l.equipamento_id ORDER BY l.software")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['software'], $r['fabricante'] ?: '-', $r['tipo'], $r['equipamento_id'] ? patrimonioOuIndefinido($r['patrimonio']) : 'Sem vínculo']; }
            break;

        case 'licencas_vinculadas':
            $colunas = ['Software', 'Equipamento', 'Responsável'];
            $rows = $pdo->query("SELECT l.software, e.patrimonio, e.usuario_responsavel FROM licencas l JOIN equipamentos e ON e.id = l.equipamento_id ORDER BY e.patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [$r['software'], patrimonioOuIndefinido($r['patrimonio']), $r['usuario_responsavel'] ?: '-']; }
            break;

        case 'financeiro_valor_total':
            $colunas = ['Patrimônio', 'Tipo', 'Valor de Aquisição', 'Valor Atual'];
            $rows = $pdo->query("SELECT patrimonio, tipo, valor_aquisicao, valor_atual FROM equipamentos WHERE valor_atual IS NOT NULL ORDER BY patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [patrimonioOuIndefinido($r['patrimonio']), $r['tipo'], formatMoney($r['valor_aquisicao']), formatMoney($r['valor_atual'])]; }
            break;

        case 'financeiro_sem_valor':
            $colunas = ['Patrimônio', 'Tipo', 'Status'];
            $rows = $pdo->query("SELECT patrimonio, tipo, status FROM equipamentos WHERE valor_atual IS NULL ORDER BY patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [patrimonioOuIndefinido($r['patrimonio']), $r['tipo'], $r['status']]; }
            break;

        case 'financeiro_garantia':
            $colunas = ['Patrimônio', 'Tipo', 'Garantia', 'Data da Compra'];
            $rows = $pdo->query("SELECT patrimonio, tipo, garantia, data_compra FROM equipamentos WHERE garantia IS NOT NULL AND garantia <> '' ORDER BY patrimonio")->fetchAll();
            foreach ($rows as $r) { $linhas[] = [patrimonioOuIndefinido($r['patrimonio']), $r['tipo'], $r['garantia'], formatDate($r['data_compra'])]; }
            break;

        case 'historico_alteracoes':
            $colunas = ['Data/Hora', 'Origem', 'Patrimônio/Item', 'Categoria', 'Evento', 'Descrição'];

            $sql = "SELECT * FROM (
                        SELECT h.data_hora, 'Equipamento' AS origem, COALESCE(e.patrimonio, 'Indefinido') AS identificacao, NULL AS categoria, h.evento, h.descricao
                        FROM historico_equipamentos h JOIN equipamentos e ON e.id = h.equipamento_id
                        UNION ALL
                        SELECT he.data_hora, 'Item de Estoque' AS origem, he.item_nome AS identificacao, he.categoria_nome AS categoria, he.evento, he.descricao
                        FROM historico_estoque he
                    ) AS combinado WHERE 1=1";
            $params = [];

            if ($filtroHistEvento !== '') {
                $sql .= ' AND evento = :evento';
                $params['evento'] = $filtroHistEvento;
            }
            if ($filtroHistCategoria !== '') {
                $sql .= ' AND categoria = :categoria';
                $params['categoria'] = $filtroHistCategoria;
            }
            if ($filtroHistDataIni !== '') {
                $sql .= ' AND DATE(data_hora) >= :data_ini';
                $params['data_ini'] = $filtroHistDataIni;
            }
            if ($filtroHistDataFim !== '') {
                $sql .= ' AND DATE(data_hora) <= :data_fim';
                $params['data_fim'] = $filtroHistDataFim;
            }
            if ($filtroHistBusca !== '') {
                $sql .= ' AND identificacao LIKE :busca';
                $params['busca'] = '%' . $filtroHistBusca . '%';
            }

            $sql .= ' ORDER BY data_hora DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $r) {
                $linhas[] = [formatDateTime($r['data_hora']), $r['origem'], $r['identificacao'], $r['categoria'] ?: '-', $r['evento'], $r['descricao']];
            }
            break;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 class="h3 mb-4 d-print-none"><i class="bi bi-bar-chart-line me-2"></i>Relatórios</h1>

<div class="row g-4">
    <div class="col-md-3 d-print-none">
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

            <div class="list-group-item list-group-item-secondary fw-semibold">Histórico</div>
            <a class="list-group-item list-group-item-action <?= $relatorio === 'historico_alteracoes' ? 'active' : '' ?>" href="?relatorio=historico_alteracoes">Histórico de alterações</a>
        </div>
    </div>

    <div class="col-md-9" id="relatorioPrintArea">
        <?php if ($relatorio === ''): ?>
            <div class="card d-print-none">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-arrow-left fs-3"></i>
                    <p class="mt-2 mb-0">Selecione um relatório na lista ao lado.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong><?= e($titulosRelatorios[$relatorio]) ?></strong>
                    <button class="btn btn-sm btn-outline-secondary d-print-none" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
                </div>
                <?php if ($relatorio === 'historico_alteracoes'): ?>
                <div class="card-body border-bottom d-print-none">
                    <form method="get" class="row g-2 align-items-end">
                        <input type="hidden" name="relatorio" value="historico_alteracoes">
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Tipo de Ação</label>
                            <select name="h_evento" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <?php foreach ($eventosHistorico as $ev): ?>
                                    <option value="<?= e($ev) ?>" <?= $filtroHistEvento === $ev ? 'selected' : '' ?>><?= e($ev) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Categoria</label>
                            <select name="h_categoria" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                <?php foreach ($categoriasHistorico as $cat): ?>
                                    <option value="<?= e($cat) ?>" <?= $filtroHistCategoria === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">De</label>
                            <input type="date" name="h_data_ini" class="form-control form-control-sm" value="<?= e($filtroHistDataIni) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Até</label>
                            <input type="date" name="h_data_fim" class="form-control form-control-sm" value="<?= e($filtroHistDataFim) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Patrimônio / Item</label>
                            <input type="text" name="h_busca" class="form-control form-control-sm" value="<?= e($filtroHistBusca) ?>">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
                            <a href="?relatorio=historico_alteracoes" class="btn btn-sm btn-outline-secondary" title="Limpar"><i class="bi bi-x-lg"></i></a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
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
