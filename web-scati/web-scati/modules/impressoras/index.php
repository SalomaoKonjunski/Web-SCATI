<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$pageTitle = 'Impressoras';

$busca = trim($_GET['busca'] ?? '');
$filtroStatus = $_GET['status'] ?? '';

$sql = "SELECT e.*,
               GROUP_CONCAT(DISTINCT CASE WHEN c.nome = 'Toner' THEN es.nome END ORDER BY es.nome SEPARATOR ', ') AS toners_nomes,
               COUNT(DISTINCT CASE WHEN c.nome = 'Toner' THEN iv.id END) AS qtd_toners_vinculados
        FROM equipamentos e
        LEFT JOIN itens_vinculados iv ON iv.equipamento_id = e.id
        LEFT JOIN estoque es ON es.id = iv.estoque_id
        LEFT JOIN categorias_estoque c ON c.id = es.categoria_id
        WHERE e.tipo = 'Impressora'";
$params = [];

if ($busca !== '') {
    $sql .= " AND (e.nome LIKE :busca1 OR e.patrimonio LIKE :busca2 OR e.marca LIKE :busca3 OR e.modelo LIKE :busca4 OR e.ip LIKE :busca5)";
    $curingaBusca = '%' . $busca . '%';
    $params['busca1'] = $curingaBusca;
    $params['busca2'] = $curingaBusca;
    $params['busca3'] = $curingaBusca;
    $params['busca4'] = $curingaBusca;
    $params['busca5'] = $curingaBusca;
}
if ($filtroStatus !== '') {
    $sql .= " AND e.status = :status";
    $params['status'] = $filtroStatus;
}

$sql .= " GROUP BY e.id ORDER BY e.nome ASC, e.patrimonio ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$impressoras = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-printer me-2"></i>Impressoras</h1>
    <a href="../equipamentos/form.php?tipo=Impressora" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Impressora</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Pesquisar</label>
                <input type="text" name="busca" class="form-control" placeholder="Nome, patrimônio, marca, modelo ou IP..." value="<?= e($busca) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (statusEquipamento() as $status): ?>
                        <option value="<?= e($status) ?>" <?= $filtroStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
                <a href="index.php" class="btn btn-outline-secondary" title="Limpar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th>Patrimônio</th>
                    <th>Marca / Modelo</th>
                    <th>IP</th>
                    <th>Status</th>
                    <th>Localização</th>
                    <th>Toner</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($impressoras)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma impressora cadastrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($impressoras as $imp): ?>
                    <tr data-href="../equipamentos/view.php?id=<?= (int) $imp['id'] ?>">
                        <td>
                            <?php if ($imp['nome']): ?>
                                <strong><?= e($imp['nome']) ?></strong>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Sem nome</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($imp['patrimonio']): ?>
                                <?= e($imp['patrimonio']) ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Indefinido</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(trim(($imp['marca'] ?? '') . ' ' . ($imp['modelo'] ?? ''))) ?: '-' ?></td>
                        <td><?= e($imp['ip']) ?: '-' ?></td>
                        <td><span class="badge <?= statusBadgeClass($imp['status']) ?>"><?= e($imp['status']) ?></span></td>
                        <td><?= e($imp['localizacao']) ?: '-' ?></td>
                        <td>
                            <?php if ((int) $imp['qtd_toners_vinculados'] > 0): ?>
                                <?= e($imp['toners_nomes']) ?>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> Sem toner</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="../equipamentos/view.php?id=<?= (int) $imp['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            <a href="../equipamentos/form.php?id=<?= (int) $imp['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <a href="../equipamentos/delete.php?id=<?= (int) $imp['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir a impressora &quot;<?= e(nomeEquipamento($imp['nome'], $imp['patrimonio'])) ?>&quot;?"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
