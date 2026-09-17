<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$usuarioId = usuarioLogado()['id'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$nota = ['titulo' => '', 'conteudo' => ''];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM notas WHERE id = :id AND usuario_id = :usuario_id');
    $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Nota não encontrada.');
        redirect('/modules/notas/index.php');
    }
    $nota = array_merge($nota, $registro);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nota['titulo'] = trim($_POST['titulo'] ?? '');
    $nota['conteudo'] = trim($_POST['conteudo'] ?? '');

    if ($nota['titulo'] === '') {
        $erros[] = 'O campo Título é obrigatório.';
    }

    if (empty($erros)) {
        if ($edicao) {
            $pdo->prepare('UPDATE notas SET titulo = :titulo, conteudo = :conteudo WHERE id = :id AND usuario_id = :usuario_id')
                ->execute([
                    'titulo' => $nota['titulo'],
                    'conteudo' => $nota['conteudo'] ?: null,
                    'id' => $id,
                    'usuario_id' => $usuarioId,
                ]);
            flash('success', 'Nota atualizada com sucesso.');
        } else {
            $pdo->prepare('INSERT INTO notas (usuario_id, titulo, conteudo) VALUES (:usuario_id, :titulo, :conteudo)')
                ->execute([
                    'usuario_id' => $usuarioId,
                    'titulo' => $nota['titulo'],
                    'conteudo' => $nota['conteudo'] ?: null,
                ]);
            flash('success', 'Nota criada com sucesso.');
        }
        redirect('/modules/notas/index.php');
    }
}

$pageTitle = $edicao ? 'Editar Nota' : 'Nova Nota';

// Anexos (só existem para notas já salvas)
$anexos = [];
if ($edicao) {
    $stmtAnexos = $pdo->prepare('SELECT * FROM anexos_notas WHERE nota_id = :id ORDER BY criado_em DESC');
    $stmtAnexos->execute(['id' => $id]);
    $anexos = $stmtAnexos->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-journal-text me-2"></i><?= $edicao ? 'Editar Nota' : 'Nova Nota' ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post">
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-12">
                <label class="form-label">Título *</label>
                <input type="text" name="titulo" class="form-control" required autofocus value="<?= e($nota['titulo']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Conteúdo</label>
                <textarea name="conteudo" class="form-control" rows="14"><?= e($nota['conteudo'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php if (!$edicao): ?>
    <div class="card mb-5">
        <div class="card-body">
            <p class="text-muted mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Salve a nota primeiro — depois disso, uma seção "Anexos" aparece aqui para
                vincular arquivos (PDF, Word, Excel, etc.) a ela.
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="card mb-5" id="anexos">
        <div class="card-header bg-white"><strong><i class="bi bi-paperclip me-1"></i> Anexos</strong></div>
        <div class="card-body">
            <form method="post" action="anexo_upload.php" enctype="multipart/form-data" class="mb-4">
                <label class="form-label fw-semibold">+ Novo Anexo</label>
                <input type="hidden" name="nota_id" value="<?= (int) $id ?>">
                <div class="row g-2">
                    <div class="col-md-9">
                        <input type="file" name="arquivo" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload"></i> Enviar</button>
                    </div>
                </div>
                <div class="form-text">
                    Tamanho máximo 10 MB. Formatos aceitos: <?= e(implode(', ', extensoesAnexoPermitidas())) ?>.
                </div>
            </form>

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
                                <a href="anexo_excluir.php?id=<?= (int) $anexo['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                                   data-confirm-msg="Excluir o anexo &quot;<?= e($anexo['nome_original']) ?>&quot;? Esta ação não pode ser desfeita." title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
