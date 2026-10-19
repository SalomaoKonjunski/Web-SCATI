<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('estoque', 'alterar');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT nome FROM locais_estoque WHERE id = :id');
$stmt->execute(['id' => $id]);
$local = $stmt->fetch();

if (!$local) {
    flash('danger', 'Local não encontrado.');
    redirect('/modules/locais_estoque/index.php');
}

// Itens de estoque com este local ficam com local_id = NULL (ON DELETE SET NULL).
$pdo->prepare('DELETE FROM locais_estoque WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Local "' . $local['nome'] . '" excluído com sucesso.');
redirect('/modules/locais_estoque/index.php');
