<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT l.*, e.patrimonio AS patrimonio_atual
     FROM licencas l LEFT JOIN equipamentos e ON e.id = l.equipamento_id
     WHERE l.id = :id'
);
$stmt->execute(['id' => $id]);
$licenca = $stmt->fetch();

if (!$licenca) {
    flash('danger', 'Licença não encontrada.');
    redirect('/modules/licencas/index.php');
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novoEquipamentoId = $_POST['novo_equipamento_id'] !== '' ? (int) $_POST['novo_equipamento_id'] : null;
    $equipamentoAnteriorId = $licenca['equipamento_id'] ? (int) $licenca['equipamento_id'] : null;

    if ($novoEquipamentoId === $equipamentoAnteriorId) {
        $erros[] = 'Selecione um equipamento diferente do atual para realizar a transferência.';
    }

    if (empty($erros)) {
        // Remove o vínculo anterior e registra o novo (seção 12: "Transferência de Licença")
        $pdo->prepare('UPDATE licencas SET equipamento_id = :novo WHERE id = :id')
            ->execute(['novo' => $novoEquipamentoId, 'id' => $id]);

        if ($equipamentoAnteriorId) {
            registrarHistorico($equipamentoAnteriorId, 'Licença',
                'Licença "' . $licenca['software'] . '" transferida para outro equipamento');
        }
        if ($novoEquipamentoId) {
            registrarHistorico($novoEquipamentoId, 'Licença',
                'Licença "' . $licenca['software'] . '" recebida por transferência' .
                ($equipamentoAnteriorId ? ' (anteriormente em ' . patrimonioOuIndefinido($licenca['patrimonio_atual']) . ')' : ''));
        }

        flash('success', 'Licença transferida com sucesso.');
        redirect('/modules/licencas/index.php');
    }
}

$equipamentos = $pdo->query('SELECT id, patrimonio, hostname FROM equipamentos ORDER BY patrimonio')->fetchAll();
$pageTitle = 'Transferir Licença';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-arrow-left-right me-2"></i>Transferir Licença</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <table class="table table-sm mb-4">
            <tr><th style="width:30%">Licença</th><td><?= e($licenca['software']) ?> <span class="text-muted small"><?= e($licenca['versao']) ?></span></td></tr>
            <tr><th>Equipamento Atual</th><td><?= $licenca['equipamento_id'] ? e(patrimonioOuIndefinido($licenca['patrimonio_atual'])) : '<span class="text-muted">Sem vínculo</span>' ?></td></tr>
        </table>

        <form method="post">
            <input type="hidden" name="id" value="<?= (int) $licenca['id'] ?>">
            <div class="mb-3">
                <label class="form-label">Novo Equipamento</label>
                <select name="novo_equipamento_id" class="form-select" required>
                    <option value="">Sem vínculo</option>
                    <?php foreach ($equipamentos as $eq): ?>
                        <option value="<?= (int) $eq['id'] ?>" <?= (int) $eq['id'] === (int) $licenca['equipamento_id'] ? 'disabled' : '' ?>>
                            <?= e(patrimonioOuIndefinido($eq['patrimonio'])) ?><?= $eq['hostname'] ? ' — ' . e($eq['hostname']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">O vínculo com o equipamento anterior será removido automaticamente.</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-left-right"></i> Confirmar Transferência</button>
            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
