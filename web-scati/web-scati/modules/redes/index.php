<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$pageTitle = 'Redes';

$busca = trim($_GET['busca'] ?? '');

$sql = "SELECT r.*,
        (SELECT COUNT(*) FROM equipamentos e WHERE e.rede_id = r.id) AS total_equipamentos
        FROM redes r WHERE 1=1";
$params = [];
if ($busca !== '') {
    $sql .= " AND (r.nome LIKE :busca_nome OR r.faixa_ip LIKE :busca_faixa)";
    $params['busca_nome'] = '%' . $busca . '%';
    $params['busca_faixa'] = '%' . $busca . '%';
}
$sql .= " ORDER BY r.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$redes = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-diagram-3 me-2"></i>Redes</h1>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Rede</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-8">
                <input type="text" name="busca" class="form-control" placeholder="Pesquisar por nome ou faixa de IP..." value="<?= e($busca) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
            </div>
            <div class="col-md-2">
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
                    <th>Nome</th>
                    <th>Faixa de IP</th>
                    <th>Gateway</th>
                    <th class="text-center">Equipamentos</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($redes)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma rede cadastrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($redes as $rede): ?>
                    <tr>
                        <td><strong><?= e($rede['nome']) ?></strong></td>
                        <td><?= e($rede['faixa_ip']) ?: '-' ?></td>
                        <td><?= e($rede['gateway']) ?: '-' ?></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= (int) $rede['total_equipamentos'] ?></span></td>
                        <td class="text-end">
                            <a href="form.php?id=<?= (int) $rede['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <a href="delete.php?id=<?= (int) $rede['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir a rede &quot;<?= e($rede['nome']) ?>&quot;?"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
