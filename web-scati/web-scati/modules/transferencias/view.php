<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('transferencias', 'ver');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM transferencias WHERE id = :id');
$stmt->execute(['id' => $id]);
$transferencia = $stmt->fetch();

if (!$transferencia) {
    flash('danger', 'Transferência não encontrada.');
    redirect('/modules/transferencias/index.php');
}

$stmtItens = $pdo->prepare(
    'SELECT ti.*, e.tipo AS equipamento_tipo, e.patrimonio AS equipamento_patrimonio
     FROM transferencia_itens ti LEFT JOIN equipamentos e ON e.id = ti.equipamento_id
     WHERE ti.transferencia_id = :id ORDER BY ti.id ASC'
);
$stmtItens->execute(['id' => $id]);
$itens = $stmtItens->fetchAll();

$pageTitle = 'Transferência #' . $id;
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-truck me-2"></i>Transferência #<?= (int) $transferencia['id'] ?></h1>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        <a href="delete.php?id=<?= (int) $transferencia['id'] ?>" class="btn btn-outline-danger js-confirm-delete"
           data-confirm-msg="Excluir esta transferência? Os equipamentos não voltam ao local de origem automaticamente.">
            <i class="bi bi-trash"></i> Excluir
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">Origem</div>
                <div class="fw-semibold"><?= e($transferencia['origem']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Destino</div>
                <div class="fw-semibold"><?= e($transferencia['destino']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Responsável</div>
                <div class="fw-semibold"><?= e($transferencia['responsavel_nome']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Data</div>
                <div class="fw-semibold"><?= formatDate($transferencia['data_transferencia']) ?></div>
            </div>
            <?php if (!empty($transferencia['observacoes'])): ?>
            <div class="col-12">
                <div class="text-muted small">Observações</div>
                <div><?= nl2br(e($transferencia['observacoes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light"><strong>Itens (<?= count($itens) ?>)</strong></div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Equipamento</th><th>Tipo</th><th>Patrimônio</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                    <tr>
                        <td>
                            <?= e($item['equipamento_nome']) ?>
                            <?php if ((int) $item['equipamento_novo'] === 1): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1">Novo</span>
                            <?php endif; ?>
                            <?php if (!$item['equipamento_id']): ?>
                                <span class="badge bg-secondary ms-1">Excluído</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($item['equipamento_tipo'] ?? '-') ?></td>
                        <td><?= $item['equipamento_patrimonio'] ? e($item['equipamento_patrimonio']) : '-' ?></td>
                        <td class="text-end">
                            <?php if ($item['equipamento_id']): ?>
                                <a href="../equipamentos/view.php?id=<?= (int) $item['equipamento_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-box-arrow-up-right"></i> Ver ficha
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
