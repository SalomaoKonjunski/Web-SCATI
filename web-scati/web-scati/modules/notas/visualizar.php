<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('notas', 'ver');

$pdo = db();
$usuarioId = usuarioLogado()['id'];
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT n.*, u.usuario AS dono_nome, nc.pode_editar
     FROM notas n
     JOIN usuarios u ON u.id = n.usuario_id
     JOIN nota_compartilhamentos nc ON nc.nota_id = n.id
     WHERE n.id = :id AND nc.usuario_id = :usuario_id'
);
$stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
$nota = $stmt->fetch();

if (!$nota) {
    flash('danger', 'Nota não encontrada.');
    redirect('/modules/notas/index.php');
}

// Quem pode editar é levado direto pro formulário de edição, não faz
// sentido ficar nesta tela só de leitura.
if ((int) $nota['pode_editar'] === 1) {
    redirect('/modules/notas/form.php?id=' . $id);
}

$anexos = $pdo->prepare('SELECT * FROM anexos_notas WHERE nota_id = :id ORDER BY criado_em DESC');
$anexos->execute(['id' => $id]);
$anexos = $anexos->fetchAll();

$pageTitle = $nota['titulo'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-journal-text me-2"></i><?= e($nota['titulo']) ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<p class="text-muted small">
    <i class="bi bi-share me-1"></i> Compartilhada por <strong><?= e($nota['dono_nome']) ?></strong> — somente visualização.
    Atualizada em <?= formatDateTime($nota['atualizado_em']) ?>.
</p>

<div class="card mb-3">
    <div class="card-body">
        <p class="mb-0" style="white-space: pre-wrap;"><?= e($nota['conteudo'] ?? '') ?></p>
    </div>
</div>

<div class="card mb-5">
    <div class="card-header bg-white"><strong><i class="bi bi-paperclip me-1"></i> Anexos</strong></div>
    <div class="card-body">
        <?php if (empty($anexos)): ?>
            <p class="text-muted mb-0">Nenhum anexo vinculado a esta nota.</p>
        <?php else: ?>
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Arquivo</th><th>Tamanho</th><th>Enviado em</th><th class="text-end">Ações</th></tr>
                </thead>
                <tbody>
                <?php foreach ($anexos as $anexo): ?>
                    <?php $extensaoAnexo = pathinfo($anexo['nome_original'], PATHINFO_EXTENSION); ?>
                    <tr>
                        <td><i class="bi <?= iconeAnexo($extensaoAnexo) ?> me-1 text-muted"></i><?= e($anexo['nome_original']) ?></td>
                        <td><?= formatBytes((int) $anexo['tamanho']) ?></td>
                        <td><?= formatDateTime($anexo['criado_em']) ?></td>
                        <td class="text-end">
                            <a href="anexo_download.php?id=<?= (int) $anexo['id'] ?>" class="btn btn-sm btn-outline-primary" title="Baixar">
                                <i class="bi bi-download"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
