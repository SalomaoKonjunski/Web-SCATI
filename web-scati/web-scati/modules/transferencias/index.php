<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('transferencias', 'ver');

$pdo = db();
$pageTitle = 'Transferências';

$busca = trim($_GET['busca'] ?? '');
$responsavelId = ($_GET['responsavel_id'] ?? '') !== '' ? (int) $_GET['responsavel_id'] : null;

$sql = "SELECT t.*, (SELECT COUNT(*) FROM transferencia_itens ti WHERE ti.transferencia_id = t.id) AS total_itens
        FROM transferencias t WHERE 1=1";
$params = [];
if ($busca !== '') {
    $sql .= " AND (t.origem LIKE :busca_origem OR t.destino LIKE :busca_destino)";
    $params['busca_origem'] = '%' . $busca . '%';
    $params['busca_destino'] = '%' . $busca . '%';
}
if ($responsavelId !== null) {
    $sql .= " AND t.responsavel_id = :responsavel_id";
    $params['responsavel_id'] = $responsavelId;
}
$sql .= " ORDER BY t.data_transferencia DESC, t.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transferencias = $stmt->fetchAll();

$usuarios = $pdo->query('SELECT id, usuario FROM usuarios ORDER BY usuario')->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-truck me-2"></i>Transferências</h1>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Transferência</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-6">
                <input type="text" name="busca" class="form-control" placeholder="Pesquisar por origem ou destino..." value="<?= e($busca) ?>">
            </div>
            <div class="col-md-3">
                <select name="responsavel_id" class="form-select">
                    <option value="">Todos os responsáveis</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= $responsavelId === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['usuario']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
            </div>
            <div class="col-md-1">
                <a href="index.php" class="btn btn-outline-secondary w-100">Limpar</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Data</th>
                    <th>Origem</th>
                    <th>Destino</th>
                    <th>Responsável</th>
                    <th class="text-center">Itens</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transferencias)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma transferência registrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($transferencias as $t): ?>
                    <tr>
                        <td><?= formatDate($t['data_transferencia']) ?></td>
                        <td><?= e($t['origem']) ?></td>
                        <td><?= e($t['destino']) ?></td>
                        <td><?= e($t['responsavel_nome']) ?></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= (int) $t['total_itens'] ?></span></td>
                        <td class="text-end">
                            <a href="view.php?id=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> Ver</a>
                            <a href="delete.php?id=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir esta transferência (<?= e($t['origem']) ?> &rarr; <?= e($t['destino']) ?>)? Os equipamentos não voltam ao local de origem automaticamente.">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
