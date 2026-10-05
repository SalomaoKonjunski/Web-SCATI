<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('notas', 'ver');

$pdo = db();
$pageTitle = 'Bloco de Notas';
$usuarioId = usuarioLogado()['id'];

$stmt = $pdo->prepare(
    'SELECT n.*, COUNT(DISTINCT a.id) AS qtd_anexos
     FROM notas n
     LEFT JOIN anexos_notas a ON a.nota_id = n.id
     WHERE n.usuario_id = :usuario_id
     GROUP BY n.id
     ORDER BY n.ordem ASC, n.atualizado_em DESC'
);
$stmt->execute(['usuario_id' => $usuarioId]);
$minhasNotas = $stmt->fetchAll();

$stmtCompartilhadas = $pdo->prepare(
    'SELECT n.*, COUNT(DISTINCT a.id) AS qtd_anexos, nc.pode_editar, u.usuario AS dono_nome
     FROM notas n
     JOIN nota_compartilhamentos nc ON nc.nota_id = n.id AND nc.usuario_id = :usuario_id
     JOIN usuarios u ON u.id = n.usuario_id
     LEFT JOIN anexos_notas a ON a.nota_id = n.id
     GROUP BY n.id
     ORDER BY n.atualizado_em DESC'
);
$stmtCompartilhadas->execute(['usuario_id' => $usuarioId]);
$notasCompartilhadas = $stmtCompartilhadas->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-journal-text me-2"></i>Bloco de Notas</h1>
        <span class="small text-muted">Suas notas e as que outras pessoas compartilharam com você. Arraste um cartão pelo <i class="bi bi-grip-vertical"></i> para reordenar.</span>
    </div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Nota</a>
</div>

<h2 class="h6 text-muted text-uppercase mb-3">Minhas Notas</h2>
<?php if (empty($minhasNotas)): ?>
    <div class="card mb-4">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-journal fs-3"></i>
            <p class="mt-2 mb-0">Nenhuma nota ainda. Clique em "Nova Nota" para começar.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4" id="notasGrid">
        <?php foreach ($minhasNotas as $nota): ?>
            <div class="col-md-4 col-sm-6 js-nota-card" draggable="true" data-id="<?= (int) $nota['id'] ?>">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <h5 class="card-title text-truncate mb-0" title="<?= e($nota['titulo']) ?>"><?= e($nota['titulo']) ?></h5>
                            <i class="bi bi-grip-vertical text-muted flex-shrink-0" title="Arraste para reordenar"></i>
                        </div>
                        <p class="card-text text-muted small flex-grow-1 mt-2" style="white-space: pre-wrap; max-height: 130px; overflow: hidden;"><?= e($nota['conteudo'] ?? '') ?></p>
                        <div class="text-muted small mb-2">
                            Atualizado em <?= formatDateTime($nota['atualizado_em']) ?>
                            <?php if ((int) $nota['qtd_anexos'] > 0): ?>
                                <span class="badge bg-light text-dark border ms-1" title="Anexo(s) vinculado(s)">
                                    <i class="bi bi-paperclip"></i> <?= (int) $nota['qtd_anexos'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="form.php?id=<?= (int) $nota['id'] ?>" class="btn btn-sm btn-outline-primary flex-grow-1"><i class="bi bi-pencil"></i> Editar</a>
                            <a href="compartilhar.php?id=<?= (int) $nota['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Compartilhar"><i class="bi bi-share"></i></a>
                            <a href="delete.php?id=<?= (int) $nota['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir a nota &quot;<?= e($nota['titulo']) ?>&quot;?"><i class="bi bi-trash"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($notasCompartilhadas)): ?>
    <h2 class="h6 text-muted text-uppercase mb-3">Compartilhadas comigo</h2>
    <div class="row g-3 mb-4">
        <?php foreach ($notasCompartilhadas as $nota): ?>
            <div class="col-md-4 col-sm-6">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title text-truncate mb-0" title="<?= e($nota['titulo']) ?>"><?= e($nota['titulo']) ?></h5>
                        <span class="badge bg-light text-dark border mt-2 align-self-start">
                            <i class="bi bi-share"></i> Compartilhada por <?= e($nota['dono_nome']) ?>
                        </span>
                        <p class="card-text text-muted small flex-grow-1 mt-2" style="white-space: pre-wrap; max-height: 130px; overflow: hidden;"><?= e($nota['conteudo'] ?? '') ?></p>
                        <div class="text-muted small mb-2">
                            Atualizado em <?= formatDateTime($nota['atualizado_em']) ?>
                            <?php if ((int) $nota['qtd_anexos'] > 0): ?>
                                <span class="badge bg-light text-dark border ms-1" title="Anexo(s) vinculado(s)">
                                    <i class="bi bi-paperclip"></i> <?= (int) $nota['qtd_anexos'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ((int) $nota['pode_editar'] === 1): ?>
                                <a href="form.php?id=<?= (int) $nota['id'] ?>" class="btn btn-sm btn-outline-primary flex-grow-1"><i class="bi bi-pencil"></i> Editar</a>
                            <?php else: ?>
                                <a href="visualizar.php?id=<?= (int) $nota['id'] ?>" class="btn btn-sm btn-outline-secondary flex-grow-1"><i class="bi bi-eye"></i> Ver</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
