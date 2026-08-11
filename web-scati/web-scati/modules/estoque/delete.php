<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
// Presente quando a exclusão é acionada a partir da ficha de um equipamento (ex.: tela de Toner da impressora).
$equipamentoOrigemId = isset($_GET['equipamento_id']) ? (int) $_GET['equipamento_id'] : (isset($_POST['equipamento_id']) ? (int) $_POST['equipamento_id'] : null);

$stmt = $pdo->prepare(
    'SELECT es.*, c.nome AS categoria_nome FROM estoque es JOIN categorias_estoque c ON c.id = es.categoria_id WHERE es.id = :id'
);
$stmt->execute(['id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    flash('danger', 'Item de estoque não encontrado.');
    redirect($equipamentoOrigemId ? '/modules/equipamentos/view.php?id=' . $equipamentoOrigemId . '#toner' : '/modules/estoque/index.php');
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motivo = trim($_POST['motivo'] ?? '');

    if ($motivo === '') {
        $erros[] = 'Informe o motivo da exclusão para continuar.';
    }

    if (empty($erros)) {
        // Registra a exclusão no histórico de cada equipamento que tinha uma unidade deste item vinculada.
        $stmtVinc = $pdo->prepare(
            'SELECT iv.equipamento_id, e.patrimonio
             FROM itens_vinculados iv JOIN equipamentos e ON e.id = iv.equipamento_id
             WHERE iv.estoque_id = :id'
        );
        $stmtVinc->execute(['id' => $id]);
        foreach ($stmtVinc->fetchAll() as $vinc) {
            registrarHistorico(
                (int) $vinc['equipamento_id'],
                'Item',
                'Item "' . $item['nome'] . '" excluído do estoque enquanto vinculado a este equipamento. Motivo: ' . $motivo
            );
        }

        registrarHistoricoEstoque((int) $item['id'], $item['nome'], $item['categoria_nome'], 'Exclusão', $motivo);

        // A exclusão do item remove em cascata os vínculos que ele tinha com equipamentos.
        $pdo->prepare('DELETE FROM estoque WHERE id = :id')->execute(['id' => $id]);

        flash('success', 'Item "' . $item['nome'] . '" excluído do estoque.');
        redirect($equipamentoOrigemId ? '/modules/equipamentos/view.php?id=' . $equipamentoOrigemId . '#toner' : '/modules/estoque/index.php');
    }
}

$pageTitle = 'Excluir Item de Estoque';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-trash me-2"></i>Excluir Item de Estoque</h1>
    <a href="<?= $equipamentoOrigemId ? '../equipamentos/view.php?id=' . $equipamentoOrigemId . '#toner' : 'index.php' ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Você está prestes a excluir permanentemente o item <strong><?= e($item['nome']) ?></strong>
            (<?= e($item['categoria_nome']) ?><?= trim(($item['marca'] ?? '') . ' ' . ($item['modelo'] ?? '')) ? ' — ' . e(trim(($item['marca'] ?? '') . ' ' . ($item['modelo'] ?? ''))) : '' ?>).
            Esta ação não pode ser desfeita.
        </div>

        <form method="post">
            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
            <?php if ($equipamentoOrigemId): ?>
                <input type="hidden" name="equipamento_id" value="<?= $equipamentoOrigemId ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label">Motivo da Exclusão *</label>
                <textarea name="motivo" class="form-control" rows="3" required placeholder="Ex: Toner esgotado"><?= e($_POST['motivo'] ?? '') ?></textarea>
                <div class="form-text">Obrigatório. Fica registrado no histórico junto com a exclusão.</div>
            </div>
            <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Confirmar Exclusão</button>
            <a href="<?= $equipamentoOrigemId ? '../equipamentos/view.php?id=' . $equipamentoOrigemId . '#toner' : 'index.php' ?>" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
