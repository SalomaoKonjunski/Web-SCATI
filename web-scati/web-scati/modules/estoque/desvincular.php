<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT iv.*, es.nome AS item_nome
     FROM itens_vinculados iv JOIN estoque es ON es.id = iv.estoque_id
     WHERE iv.id = :id'
);
$stmt->execute(['id' => $id]);
$vinculo = $stmt->fetch();

if (!$vinculo) {
    flash('danger', 'Vínculo não encontrado.');
    redirect('/modules/estoque/index.php');
}

$equipamentoId = (int) $vinculo['equipamento_id'];

$pdo->prepare('DELETE FROM itens_vinculados WHERE id = :id')->execute(['id' => $id]);

// A unidade volta a ficar disponível no estoque.
$pdo->prepare('UPDATE estoque SET quantidade = quantidade + 1 WHERE id = :id')
    ->execute(['id' => $vinculo['estoque_id']]);

registrarHistorico($equipamentoId, 'Item', 'Item "' . $vinculo['item_nome'] . '" desvinculado deste equipamento');

flash('success', 'Item "' . $vinculo['item_nome'] . '" desvinculado e devolvido ao estoque.');
redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#itens');
