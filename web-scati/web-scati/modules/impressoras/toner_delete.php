<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('impressoras', 'alterar');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT nome FROM toners WHERE id = :id');
$stmt->execute(['id' => $id]);
$toner = $stmt->fetch();

if (!$toner) {
    flash('danger', 'Toner/Tinta não encontrado.');
    redirect('/modules/impressoras/toners.php');
}

// Vínculos com impressoras e movimentações saem junto (ON DELETE CASCADE).
$pdo->prepare('DELETE FROM toners WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Toner/Tinta "' . $toner['nome'] . '" excluído com sucesso.');
redirect('/modules/impressoras/toners.php');
