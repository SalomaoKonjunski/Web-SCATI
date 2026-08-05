<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM estoque WHERE id = :id');
$stmt->execute(['id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    flash('danger', 'Item de estoque não encontrado.');
    redirect('/modules/estoque/index.php');
}

if (!$item['equipamento_id']) {
    flash('danger', 'Este item não está vinculado a nenhum equipamento.');
    redirect('/modules/estoque/index.php');
}

$equipamentoId = (int) $item['equipamento_id'];

$pdo->prepare('UPDATE estoque SET status = :status, equipamento_id = NULL WHERE id = :id')
    ->execute(['status' => 'Disponível', 'id' => $id]);

registrarHistorico($equipamentoId, 'Item', 'Item "' . $item['nome'] . '" desvinculado deste equipamento');

flash('success', 'Item "' . $item['nome'] . '" desvinculado e devolvido ao estoque.');
redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#itens');
