<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirAdmin();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$categoria = ['nome' => '', 'cor' => corCategoriaPadrao()];

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
    $categoria['cor'] = (string) ($_POST['cor'] ?? '');

    if ($categoria['nome'] === '') {
        $erros[] = 'O campo Nome é obrigatório.';
    }
    if (!corCategoriaValida($categoria['cor'])) {
        $erros[] = 'Selecione uma cor válida.';
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
            $pdo->prepare('UPDATE categorias_senha SET nome = :nome, cor = :cor WHERE id = :id')
                ->execute(['nome' => $categoria['nome'], 'cor' => $categoria['cor'], 'id' => $id]);

            // categoria em "senhas" guarda o nome como texto "ao vivo" (sem
            // FK), igual equipamentos.tipo — renomear aqui precisa atualizar
            // junto as senhas que já usavam o nome antigo.
            if ($categoria['nome'] !== $registro['nome']) {
                $pdo->prepare('UPDATE senhas SET categoria = :novo WHERE categoria = :antigo')
                    ->execute(['novo' => $categoria['nome'], 'antigo' => $registro['nome']]);
            }

            flash('success', 'Categoria atualizada com sucesso.');
        } else {
            $pdo->prepare('INSERT INTO categorias_senha (nome, cor) VALUES (:nome, :cor)')
                ->execute(['nome' => $categoria['nome'], 'cor' => $categoria['cor']]);
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
            <div class="col-12">
                <label class="form-label d-block">Cor da Categoria</label>
                <p class="text-muted small mb-2">Usada para destacar esta categoria em listas e menus do sistema.</p>
                <?php $corPersonalizada = !array_key_exists($categoria['cor'], paletaCoresCategoria()); ?>
                <div class="d-flex flex-wrap gap-2 js-seletor-cor-categoria">
                    <?php foreach (paletaCoresCategoria() as $corOpcao => $nomeCor): ?>
                        <button type="button" class="scati-cor-bolha js-cor-categoria-opcao <?= $categoria['cor'] === $corOpcao ? 'scati-cor-selecionada' : '' ?>"
                                data-cor="<?= e($corOpcao) ?>" style="background-color: <?= e($corOpcao) ?>;" title="<?= e($nomeCor) ?>">
                            <i class="bi bi-check-lg scati-cor-check"></i>
                        </button>
                    <?php endforeach; ?>
                    <span class="scati-cor-bolha scati-cor-custom js-cor-categoria-custom-swatch <?= $corPersonalizada ? 'scati-cor-selecionada' : '' ?>"
                          style="<?= $corPersonalizada ? 'background-color: ' . e($categoria['cor']) . ';' : '' ?>" title="Outra cor...">
                        <input type="color" class="js-cor-categoria-custom-input" value="<?= e($corPersonalizada ? $categoria['cor'] : '#808080') ?>">
                        <i class="bi bi-eyedropper scati-cor-custom-icon"></i>
                        <i class="bi bi-check-lg scati-cor-check"></i>
                    </span>
                    <input type="hidden" name="cor" value="<?= e($categoria['cor']) ?>" class="js-cor-categoria-valor">
                </div>
            </div>
            <div class="col-12">
                <label class="form-label d-block">Pré-visualização</label>
                <span class="badge js-cor-categoria-preview" id="previewCorCategoria" style="background-color: <?= e($categoria['cor']) ?>;">
                    <?= e($categoria['nome'] !== '' ? $categoria['nome'] : 'Nome da categoria') ?>
                </span>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
