<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT nome FROM senhas WHERE id = :id');
$stmt->execute(['id' => $id]);
$senha = $stmt->fetch();

if (!$senha) {
    flash('danger', 'Senha não encontrada.');
    redirect('/modules/senhas/index.php');
}

$pdo->prepare('DELETE FROM senhas WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Senha "' . $senha['nome'] . '" excluída com sucesso.');
redirect('/modules/senhas/index.php');
