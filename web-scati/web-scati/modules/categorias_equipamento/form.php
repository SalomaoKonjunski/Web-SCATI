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
    $stmt = $pdo->prepare('SELECT * FROM categorias_equipamento WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Categoria não encontrada.');
        redirect('/modules/categorias_equipamento/index.php');
    }
    $categoria = array_merge($categoria, $registro);
}

$protegida = $edicao && in_array($registro['nome'], tiposEquipamentoProtegidos(), true);

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoria['nome'] = trim($_POST['nome'] ?? '');

    if ($categoria['nome'] === '') {
        $erros[] = 'O campo Nome é obrigatório.';
    }

    // Nomes usados por literal em várias partes do sistema (campos
    // específicos do formulário de Equipamentos, filtro de impressoras,
    // compartilhamentos de rede) não podem ser renomeados.
    if ($protegida && $categoria['nome'] !== $registro['nome']) {
        $erros[] = 'A categoria "' . $registro['nome'] . '" não pode ser renomeada, pois é usada por funcionalidades do sistema.';
    }

    if (empty($erros)) {
        $sqlCheck = 'SELECT id FROM categorias_equipamento WHERE nome = :nome' . ($edicao ? ' AND id != :id' : '');
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
            $pdo->prepare('UPDATE categorias_equipamento SET nome = :nome WHERE id = :id')
                ->execute(['nome' => $categoria['nome'], 'id' => $id]);

            // equipamentos.tipo guarda o nome como texto (sem FK) — como é
            // um campo "ao vivo" (não histórico), renomear a categoria
            // atualiza junto os equipamentos que já usavam o nome antigo.
            if ($categoria['nome'] !== $registro['nome']) {
                $pdo->prepare('UPDATE equipamentos SET tipo = :novo WHERE tipo = :antigo')
                    ->execute(['novo' => $categoria['nome'], 'antigo' => $registro['nome']]);
            }

            flash('success', 'Categoria atualizada com sucesso.');
        } else {
            $pdo->prepare('INSERT INTO categorias_equipamento (nome) VALUES (:nome)')
                ->execute(['nome' => $categoria['nome']]);
            flash('success', 'Categoria cadastrada com sucesso.');
        }
        redirect('/modules/categorias_equipamento/index.php');
    }
}

$pageTitle = $edicao ? 'Editar Categoria' : 'Nova Categoria';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-pc-display me-2"></i><?= $edicao ? 'Editar Categoria' : 'Nova Categoria' ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($protegida): ?>
    <div class="alert alert-warning">
        <i class="bi bi-lock-fill me-1"></i> Esta categoria é usada por funcionalidades do sistema (campos específicos do formulário de Equipamentos, filtro de impressoras, compartilhamentos de rede) e não pode ser renomeada.
    </div>
<?php endif; ?>

<form method="post">
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Nome *</label>
                <input type="text" name="nome" class="form-control" required <?= $protegida ? 'readonly' : '' ?> value="<?= e($categoria['nome']) ?>">
            </div>
        </div>
    </div>
    <?php if ($edicao && !$protegida): ?>
        <p class="text-muted small">
            Renomear esta categoria atualiza automaticamente o tipo de todos os equipamentos que já
            usavam o nome antigo.
        </p>
    <?php endif; ?>
    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
