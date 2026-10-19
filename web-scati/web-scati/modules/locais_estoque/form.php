<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('estoque', 'alterar');

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$local = ['nome' => ''];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM locais_estoque WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Local não encontrado.');
        redirect('/modules/locais_estoque/index.php');
    }
    $local = array_merge($local, $registro);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $local['nome'] = trim($_POST['nome'] ?? '');

    if ($local['nome'] === '') {
        $erros[] = 'O campo Nome é obrigatório.';
    }

    if (empty($erros)) {
        $sqlCheck = 'SELECT id FROM locais_estoque WHERE nome = :nome' . ($edicao ? ' AND id != :id' : '');
        $stmtCheck = $pdo->prepare($sqlCheck);
        $paramsCheck = ['nome' => $local['nome']];
        if ($edicao) {
            $paramsCheck['id'] = $id;
        }
        $stmtCheck->execute($paramsCheck);
        if ($stmtCheck->fetch()) {
            $erros[] = 'Já existe um local com este nome.';
        }
    }

    if (empty($erros)) {
        if ($edicao) {
            $pdo->prepare('UPDATE locais_estoque SET nome = :nome WHERE id = :id')
                ->execute(['nome' => $local['nome'], 'id' => $id]);
            flash('success', 'Local atualizado com sucesso.');
        } else {
            $pdo->prepare('INSERT INTO locais_estoque (nome) VALUES (:nome)')
                ->execute(['nome' => $local['nome']]);
            flash('success', 'Local cadastrado com sucesso.');
        }
        redirect('/modules/locais_estoque/index.php');
    }
}

$pageTitle = $edicao ? 'Editar Local' : 'Novo Local';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-geo-alt me-2"></i><?= $edicao ? 'Editar Local' : 'Novo Local' ?></h1>
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
                <input type="text" name="nome" class="form-control" required value="<?= e($local['nome']) ?>" placeholder="Ex: Depósito TI, Sala do Financeiro...">
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
