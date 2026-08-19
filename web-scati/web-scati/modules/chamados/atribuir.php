<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$usuarioAtual = usuarioLogado();

$stmt = $pdo->prepare('SELECT id, titulo FROM chamados WHERE id = :id');
$stmt->execute(['id' => $id]);
$chamado = $stmt->fetch();

if (!$chamado) {
    flash('danger', 'Chamado não encontrado.');
    redirect('/modules/chamados/index.php');
}

$pdo->prepare('UPDATE chamados SET responsavel_id = :responsavel_id WHERE id = :id')
    ->execute(['responsavel_id' => $usuarioAtual['id'], 'id' => $id]);

flash('success', 'Chamado "' . $chamado['titulo'] . '" atribuído a você.');
redirect('/modules/chamados/index.php');
