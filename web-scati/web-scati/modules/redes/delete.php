<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT nome FROM redes WHERE id = :id');
$stmt->execute(['id' => $id]);
$rede = $stmt->fetch();

if (!$rede) {
    flash('danger', 'Rede não encontrada.');
    redirect('/modules/redes/index.php');
}

// Equipamentos vinculados a esta rede ficam com rede_id = NULL (ON DELETE SET NULL)
$pdo->prepare('DELETE FROM redes WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Rede "' . $rede['nome'] . '" excluída com sucesso.');
redirect('/modules/redes/index.php');
