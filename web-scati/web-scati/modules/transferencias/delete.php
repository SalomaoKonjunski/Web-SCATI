<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('transferencias', 'alterar');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('DELETE FROM transferencias WHERE id = :id');
$stmt->execute(['id' => $id]);

if ($stmt->rowCount() > 0) {
    flash('success', 'Transferência excluída com sucesso.');
} else {
    flash('danger', 'Transferência não encontrada.');
}

redirect('/modules/transferencias/index.php');
