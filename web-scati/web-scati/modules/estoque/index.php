<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$pageTitle = 'Estoque';

$busca = trim($_GET['busca'] ?? '');
$filtroCategoria = $_GET['categoria_id'] ?? '';
$somenteAbaixoMinimo = isset($_GET['abaixo_minimo']);

$sql = "SELECT es.*, c.nome AS categoria_nome,
               COUNT(iv.id) AS qtd_vinculada,
               GROUP_CONCAT(DISTINCT COALESCE(eq.patrimonio, 'Indefinido') ORDER BY eq.patrimonio SEPARATOR ', ') AS equipamentos_vinculados
        FROM estoque es
        JOIN categorias_estoque c ON c.id = es.categoria_id
        LEFT JOIN itens_vinculados iv ON iv.estoque_id = es.id
        LEFT JOIN equipamentos eq ON eq.id = iv.equipamento_id
        WHERE 1=1";
$params = [];

if ($busca !== '') {
    $sql .= " AND (es.nome LIKE :busca_nome OR es.marca LIKE :busca_marca OR es.modelo LIKE :busca_modelo)";
    $params['busca_nome'] = '%' . $busca . '%';
    $params['busca_marca'] = '%' . $busca . '%';
    $params['busca_modelo'] = '%' . $busca . '%';
}
if ($filtroCategoria !== '') {
    $sql .= " AND es.categoria_id = :categoria_id";
    $params['categoria_id'] = $filtroCategoria;
}
if ($somenteAbaixoMinimo) {
    $sql .= " AND es.quantidade < es.quantidade_minima";
}

$sql .= " GROUP BY es.id ORDER BY es.nome ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$itens = $stmt->fetchAll();

// Separa em duas abas conforme o item tem (ou não) alguma unidade
// vinculada a um equipamento — mesma consulta de cima, só dividida em
// PHP para não duplicar os filtros de busca/categoria/mínimo em duas SQLs.
$itensNaoVinculados = array_values(array_filter($itens, fn($item) => (int) $item['qtd_vinculada'] === 0));
$itensVinculados = array_values(array_filter($itens, fn($item) => (int) $item['qtd_vinculada'] > 0));

$categorias = $pdo->query('SELECT id, nome FROM categorias_estoque ORDER BY nome')->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-box-seam me-2"></i>Estoque</h1>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Item</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Pesquisar</label>
                <input type="text" name="busca" class="form-control" placeholder="Nome, marca ou modelo..." value="<?= e($busca) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Categoria</label>
                <select name="categoria_id" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= (string) $filtroCategoria === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-center">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="abaixo_minimo" id="abaixoMinimo" value="1" <?= $somenteAbaixoMinimo ? 'checked' : '' ?>>
                    <label class="form-check-label" for="abaixoMinimo">Somente abaixo do mínimo</label>
                </div>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
                <a href="index.php" class="btn btn-outline-secondary" title="Limpar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<ul class="nav nav-tabs" id="estoqueTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#naoVinculados" type="button">
            Não Vinculados <span class="badge bg-secondary"><?= count($itensNaoVinculados) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#vinculados" type="button">
            Vinculados <span class="badge bg-secondary"><?= count($itensVinculados) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content">

    <!-- Não Vinculados -->
    <div class="tab-pane fade show active" id="naoVinculados">
        <div class="card border-top-0 rounded-top-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Marca / Modelo</th>
                            <th class="text-center">Quantidade</th>
                            <th class="text-center">Mínimo</th>
                            <th>Localização</th>
                            <th>Observações</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($itensNaoVinculados)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Nenhum item sem vínculo encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($itensNaoVinculados as $item): ?>
                            <?php $abaixoMinimo = $item['quantidade'] < $item['quantidade_minima']; ?>
                            <tr class="<?= $abaixoMinimo ? 'estoque-baixo' : '' ?>" data-href="form.php?id=<?= (int) $item['id'] ?>" title="Abrir cadastro do item">
                                <td><?= e($item['nome']) ?></td>
                                <td><?= e($item['categoria_nome']) ?></td>
                                <td><?= e(trim(($item['marca'] ?? '') . ' ' . ($item['modelo'] ?? ''))) ?: '-' ?></td>
                                <td class="text-center">
                                    <form method="post" action="ajustar_quantidade.php" class="d-inline-flex align-items-center gap-1">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <button type="submit" name="acao" value="diminuir" class="btn btn-sm btn-outline-secondary px-2" title="Diminuir quantidade" <?= (int) $item['quantidade'] <= 0 ? 'disabled' : '' ?>>
                                            <i class="bi bi-dash-lg"></i>
                                        </button>
                                        <span class="fw-semibold mx-1" style="min-width: 1.6em; display: inline-block;"><?= (int) $item['quantidade'] ?></span>
                                        <input type="number" name="valor" value="1" min="1" class="form-control form-control-sm text-center" style="width: 4.2em;" title="Quantidade a ajustar">
                                        <button type="submit" name="acao" value="aumentar" class="btn btn-sm btn-outline-secondary px-2" title="Aumentar quantidade">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-center text-muted"><?= (int) $item['quantidade_minima'] ?></td>
                                <td><?= e($item['localizacao']) ?: '-' ?></td>
                                <td>
                                    <?php if (!empty($item['observacoes'])): ?>
                                        <div class="small text-muted text-truncate" style="max-width: 220px;" title="<?= e($item['observacoes']) ?>">
                                            <i class="bi bi-sticky-fill text-warning"></i> <?= e($item['observacoes']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($abaixoMinimo): ?>
                                        <span class="badge bg-danger me-2"><i class="bi bi-exclamation-triangle"></i> Baixo</span>
                                    <?php endif; ?>
                                    <a href="form.php?id=<?= (int) $item['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="delete.php?id=<?= (int) $item['id'] ?>" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted small mt-2">
            <i class="bi bi-info-circle"></i> Itens sem nenhuma unidade vinculada a um equipamento — disponíveis para uso.
        </p>
    </div>

    <!-- Vinculados -->
    <div class="tab-pane fade" id="vinculados">
        <div class="card border-top-0 rounded-top-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Marca / Modelo</th>
                            <th class="text-center">Qtd. Vinculada</th>
                            <th>Localização</th>
                            <th>Vinculado a</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($itensVinculados)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Nenhum item vinculado encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($itensVinculados as $item): ?>
                            <tr data-href="form.php?id=<?= (int) $item['id'] ?>" title="Abrir cadastro do item">
                                <td><?= e($item['nome']) ?></td>
                                <td><?= e($item['categoria_nome']) ?></td>
                                <td><?= e(trim(($item['marca'] ?? '') . ' ' . ($item['modelo'] ?? ''))) ?: '-' ?></td>
                                <td class="text-center fw-semibold"><?= (int) $item['qtd_vinculada'] ?></td>
                                <td><?= e($item['localizacao']) ?: '-' ?></td>
                                <td>
                                    <span class="badge bg-primary"><?= (int) $item['qtd_vinculada'] ?> unidade(s)</span>
                                    <div class="small text-muted mt-1"><?= e($item['equipamentos_vinculados']) ?></div>
                                </td>
                                <td class="text-end">
                                    <a href="form.php?id=<?= (int) $item['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="delete.php?id=<?= (int) $item['id'] ?>" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted small mt-2">
            <i class="bi bi-info-circle"></i> Itens com pelo menos 1 unidade vinculada a algum equipamento — a quantidade aqui é só a parte já em uso.
        </p>
    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
