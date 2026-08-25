<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$pageTitle = 'Bloco de Notas';
$usuarioId = usuarioLogado()['id'];

$stmt = $pdo->prepare('SELECT * FROM notas WHERE usuario_id = :usuario_id ORDER BY atualizado_em DESC');
$stmt->execute(['usuario_id' => $usuarioId]);
$notas = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-journal-text me-2"></i>Bloco de Notas</h1>
        <span class="small text-muted">Visível só para você.</span>
    </div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Nota</a>
</div>

<?php if (empty($notas)): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-journal fs-3"></i>
            <p class="mt-2 mb-0">Nenhuma nota ainda. Clique em "Nova Nota" para começar.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($notas as $nota): ?>
            <div class="col-md-4 col-sm-6">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title text-truncate" title="<?= e($nota['titulo']) ?>"><?= e($nota['titulo']) ?></h5>
                        <p class="card-text text-muted small flex-grow-1" style="white-space: pre-wrap; max-height: 130px; overflow: hidden;"><?= e($nota['conteudo'] ?? '') ?></p>
                        <div class="text-muted small mb-2">Atualizado em <?= formatDateTime($nota['atualizado_em']) ?></div>
                        <div class="d-flex gap-2">
                            <a href="form.php?id=<?= (int) $nota['id'] ?>" class="btn btn-sm btn-outline-primary flex-grow-1"><i class="bi bi-pencil"></i> Editar</a>
                            <a href="delete.php?id=<?= (int) $nota['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir a nota &quot;<?= e($nota['titulo']) ?>&quot;?"><i class="bi bi-trash"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
