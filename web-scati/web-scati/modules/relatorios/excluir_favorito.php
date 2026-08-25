<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT nome FROM relatorios_personalizados WHERE id = :id');
$stmt->execute(['id' => $id]);
$favorito = $stmt->fetch();

if (!$favorito) {
    flash('danger', 'Relatório salvo não encontrado.');
    redirect('/modules/relatorios/index.php');
}

$pdo->prepare('DELETE FROM relatorios_personalizados WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Relatório "' . $favorito['nome'] . '" excluído com sucesso.');
redirect('/modules/relatorios/index.php');
