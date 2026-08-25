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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
