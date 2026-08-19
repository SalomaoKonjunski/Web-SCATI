<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$pageTitle = 'Licenças';

$busca = trim($_GET['busca'] ?? '');
$filtroVinculo = $_GET['vinculo'] ?? ''; // '', 'vinculada', 'nao_vinculada'

$sql = "SELECT l.*, e.patrimonio, e.hostname
        FROM licencas l LEFT JOIN equipamentos e ON e.id = l.equipamento_id
        WHERE 1=1";
$params = [];

if ($busca !== '') {
    $sql .= " AND (l.software LIKE :busca_software OR l.fabricante LIKE :busca_fabricante OR e.patrimonio LIKE :busca_patrimonio)";
    $params['busca_software'] = '%' . $busca . '%';
    $params['busca_fabricante'] = '%' . $busca . '%';
    $params['busca_patrimonio'] = '%' . $busca . '%';
}
if ($filtroVinculo === 'vinculada') {
    $sql .= " AND l.equipamento_id IS NOT NULL";
} elseif ($filtroVinculo === 'nao_vinculada') {
    $sql .= " AND l.equipamento_id IS NULL";
}

$sql .= " ORDER BY l.software ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$licencas = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-key me-2"></i>Licenças</h1>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Licença</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Pesquisar</label>
                <input type="text" name="busca" class="form-control" placeholder="Software, fabricante ou patrimônio..." value="<?= e($busca) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Vínculo</label>
                <select name="vinculo" class="form-select">
                    <option value="">Todas</option>
                    <option value="vinculada" <?= $filtroVinculo === 'vinculada' ? 'selected' : '' ?>>Vinculadas a um equipamento</option>
                    <option value="nao_vinculada" <?= $filtroVinculo === 'nao_vinculada' ? 'selected' : '' ?>>Sem vínculo</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
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
                    <th>Software</th>
                    <th>Fabricante</th>
                    <th>Tipo</th>
                    <th>Equipamento Vinculado</th>
                    <th>Validade</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($licencas)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma licença encontrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($licencas as $lic): ?>
                    <tr>
                        <td><strong><?= e($lic['software']) ?></strong> <?= $lic['versao'] ? '<span class="text-muted small">v' . e($lic['versao']) . '</span>' : '' ?></td>
                        <td><?= e($lic['fabricante']) ?: '-' ?></td>
                        <td><?= e($lic['tipo']) ?></td>
                        <td>
                            <?php if ($lic['equipamento_id']): ?>
                                <a href="../equipamentos/view.php?id=<?= (int) $lic['equipamento_id'] ?>"><?= e(patrimonioOuIndefinido($lic['patrimonio'])) ?></a>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border">Sem vínculo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatDate($lic['data_validade']) ?></td>
                        <td class="text-end">
                            <a href="form.php?id=<?= (int) $lic['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <a href="transferir.php?id=<?= (int) $lic['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Transferir para outro equipamento"><i class="bi bi-arrow-left-right"></i></a>
                            <a href="delete.php?id=<?= (int) $lic['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir a licença &quot;<?= e($lic['software']) ?>&quot;?"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
