<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$categoria = ['nome' => ''];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM categorias_estoque WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Categoria não encontrada.');
        redirect('/modules/categorias_estoque/index.php');
    }
    $categoria = array_merge($categoria, $registro);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoria['nome'] = trim($_POST['nome'] ?? '');

    if ($categoria['nome'] === '') {
        $erros[] = 'O campo Nome é obrigatório.';
    }

    // A categoria "Toner" é usada por nome em várias regras do sistema (aba
    // Toner das impressoras, alerta de impressora sem toner, etc.) — renomeá-la
    // quebraria essas telas silenciosamente, então o nome fica protegido.
    if ($edicao && $registro['nome'] === 'Toner' && $categoria['nome'] !== 'Toner') {
        $erros[] = 'A categoria "Toner" não pode ser renomeada, pois é usada pela funcionalidade de Toner de impressoras.';
    }

    if (empty($erros)) {
        $sqlCheck = 'SELECT id FROM categorias_estoque WHERE nome = :nome' . ($edicao ? ' AND id != :id' : '');
        $stmtCheck = $pdo->prepare($sqlCheck);
        $paramsCheck = ['nome' => $categoria['nome']];
        if ($edicao) {
            $paramsCheck['id'] = $id;
        }
        $stmtCheck->execute($paramsCheck);
        if ($stmtCheck->fetch()) {
            $erros[] = 'Já existe uma categoria com este nome.';
        }
    }

    if (empty($erros)) {
        if ($edicao) {
            $pdo->prepare('UPDATE categorias_estoque SET nome = :nome WHERE id = :id')
                ->execute(['nome' => $categoria['nome'], 'id' => $id]);
            flash('success', 'Categoria atualizada com sucesso.');
        } else {
            $pdo->prepare('INSERT INTO categorias_estoque (nome) VALUES (:nome)')
                ->execute(['nome' => $categoria['nome']]);
            flash('success', 'Categoria cadastrada com sucesso.');
        }
        redirect('/modules/categorias_estoque/index.php');
    }
}

$pageTitle = $edicao ? 'Editar Categoria' : 'Nova Categoria';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-tags me-2"></i><?= $edicao ? 'Editar Categoria' : 'Nova Categoria' ?></h1>
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
            <div class="col-md-6">
                <label class="form-label">Nome *</label>
                <input type="text" name="nome" class="form-control" required value="<?= e($categoria['nome']) ?>">
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
