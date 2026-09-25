<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirAdmin();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$categoria = ['nome' => ''];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM categorias_senha WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Categoria não encontrada.');
        redirect('/modules/categorias_senha/index.php');
    }
    $categoria = array_merge($categoria, $registro);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoria['nome'] = trim($_POST['nome'] ?? '');

    if ($categoria['nome'] === '') {
        $erros[] = 'O campo Nome é obrigatório.';
    }

    if (empty($erros)) {
        $sqlCheck = 'SELECT id FROM categorias_senha WHERE nome = :nome' . ($edicao ? ' AND id != :id' : '');
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
            $pdo->prepare('UPDATE categorias_senha SET nome = :nome WHERE id = :id')
                ->execute(['nome' => $categoria['nome'], 'id' => $id]);

            // categoria em "senhas" guarda o nome como texto "ao vivo" (sem
            // FK), igual equipamentos.tipo — renomear aqui precisa atualizar
            // junto as senhas que já usavam o nome antigo.
            if ($categoria['nome'] !== $registro['nome']) {
                $pdo->prepare('UPDATE senhas SET categoria = :novo WHERE categoria = :antigo')
                    ->execute(['novo' => $categoria['nome'], 'antigo' => $registro['nome']]);
            }

            flash('success', 'Categoria atualizada com sucesso.');
        } else {
            $pdo->prepare('INSERT INTO categorias_senha (nome) VALUES (:nome)')
                ->execute(['nome' => $categoria['nome']]);
            flash('success', 'Categoria cadastrada com sucesso.');
        }
        redirect('/modules/categorias_senha/index.php');
    }
}

$pageTitle = $edicao ? 'Editar Categoria' : 'Nova Categoria';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-shield-lock me-2"></i><?= $edicao ? 'Editar Categoria' : 'Nova Categoria' ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($edicao): ?>
    <p class="text-muted small mb-3">
        Renomear esta categoria atualiza automaticamente todas as senhas que já
        usavam o nome antigo.
    </p>
<?php endif; ?>

<form method="post">
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Nome *</label>
                <input type="text" name="nome" class="form-control" required autofocus value="<?= e($categoria['nome']) ?>">
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
