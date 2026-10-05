<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('equipamentos', 'alterar');

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$tipo = ['nome' => '', 'cor' => corCategoriaPadrao()];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM tipos_manutencao WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Tipo de manutenção não encontrado.');
        redirect('/modules/tipos_manutencao/index.php');
    }
    $tipo = array_merge($tipo, $registro);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo['nome'] = trim($_POST['nome'] ?? '');
    $tipo['cor'] = (string) ($_POST['cor'] ?? '');

    if ($tipo['nome'] === '') {
        $erros[] = 'O campo Nome é obrigatório.';
    }
    if (!corCategoriaValida($tipo['cor'])) {
        $erros[] = 'Selecione uma cor válida.';
    }

    if (empty($erros)) {
        $sqlCheck = 'SELECT id FROM tipos_manutencao WHERE nome = :nome' . ($edicao ? ' AND id != :id' : '');
        $stmtCheck = $pdo->prepare($sqlCheck);
        $paramsCheck = ['nome' => $tipo['nome']];
        if ($edicao) {
            $paramsCheck['id'] = $id;
        }
        $stmtCheck->execute($paramsCheck);
        if ($stmtCheck->fetch()) {
            $erros[] = 'Já existe um tipo de manutenção com este nome.';
        }
    }

    if (empty($erros)) {
        if ($edicao) {
            $pdo->prepare('UPDATE tipos_manutencao SET nome = :nome, cor = :cor WHERE id = :id')
                ->execute(['nome' => $tipo['nome'], 'cor' => $tipo['cor'], 'id' => $id]);
            flash('success', 'Tipo de manutenção atualizado com sucesso.');
        } else {
            $pdo->prepare('INSERT INTO tipos_manutencao (nome, cor) VALUES (:nome, :cor)')
                ->execute(['nome' => $tipo['nome'], 'cor' => $tipo['cor']]);
            flash('success', 'Tipo de manutenção cadastrado com sucesso.');
        }
        redirect('/modules/tipos_manutencao/index.php');
    }
}

$pageTitle = $edicao ? 'Editar Tipo de Manutenção' : 'Novo Tipo de Manutenção';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-tools me-2"></i><?= $edicao ? 'Editar Tipo de Manutenção' : 'Novo Tipo de Manutenção' ?></h1>
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
                <input type="text" name="nome" class="form-control" required value="<?= e($tipo['nome']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label d-block">Cor do Tipo</label>
                <p class="text-muted small mb-2">Usada para destacar este tipo em listas e menus do sistema.</p>
                <?php $corPersonalizada = !array_key_exists($tipo['cor'], paletaCoresCategoria()); ?>
                <div class="d-flex flex-wrap gap-2 js-seletor-cor-categoria">
                    <?php foreach (paletaCoresCategoria() as $corOpcao => $nomeCor): ?>
                        <button type="button" class="scati-cor-bolha js-cor-categoria-opcao <?= $tipo['cor'] === $corOpcao ? 'scati-cor-selecionada' : '' ?>"
                                data-cor="<?= e($corOpcao) ?>" style="background-color: <?= e($corOpcao) ?>;" title="<?= e($nomeCor) ?>">
                            <i class="bi bi-check-lg scati-cor-check"></i>
                        </button>
                    <?php endforeach; ?>
                    <span class="scati-cor-bolha scati-cor-custom js-cor-categoria-custom-swatch <?= $corPersonalizada ? 'scati-cor-selecionada' : '' ?>"
                          style="<?= $corPersonalizada ? 'background-color: ' . e($tipo['cor']) . ';' : '' ?>" title="Outra cor...">
                        <input type="color" class="js-cor-categoria-custom-input" value="<?= e($corPersonalizada ? $tipo['cor'] : '#808080') ?>">
                        <i class="bi bi-eyedropper scati-cor-custom-icon"></i>
                        <i class="bi bi-check-lg scati-cor-check"></i>
                    </span>
                    <input type="hidden" name="cor" value="<?= e($tipo['cor']) ?>" class="js-cor-categoria-valor">
                </div>
            </div>
            <div class="col-12">
                <label class="form-label d-block">Pré-visualização</label>
                <span class="badge js-cor-categoria-preview" id="previewCorCategoria" style="background-color: <?= e($tipo['cor']) ?>;">
                    <?= e($tipo['nome'] !== '' ? $tipo['nome'] : 'Nome do tipo') ?>
                </span>
            </div>
        </div>
    </div>
    <?php if ($edicao): ?>
        <p class="text-muted small">
            Renomear este tipo não altera os registros já existentes no histórico dos equipamentos —
            eles continuam mostrando o nome antigo.
        </p>
    <?php endif; ?>
    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
