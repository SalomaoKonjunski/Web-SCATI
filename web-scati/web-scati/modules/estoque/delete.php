<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT nome FROM estoque WHERE id = :id');
$stmt->execute(['id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    flash('danger', 'Item de estoque não encontrado.');
    redirect('/modules/estoque/index.php');
}

$pdo->prepare('DELETE FROM estoque WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Item "' . $item['nome'] . '" excluído do estoque.');
redirect('/modules/estoque/index.php');
