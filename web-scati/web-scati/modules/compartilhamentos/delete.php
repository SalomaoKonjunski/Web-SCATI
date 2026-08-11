<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM compartilhamentos_servidor WHERE id = :id');
$stmt->execute(['id' => $id]);
$compartilhamento = $stmt->fetch();

if (!$compartilhamento) {
    flash('danger', 'Compartilhamento não encontrado.');
    redirect('/modules/equipamentos/index.php');
}

$equipamentoId = (int) $compartilhamento['equipamento_id'];

// A exclusão remove em cascata os vínculos com os computadores.
$pdo->prepare('DELETE FROM compartilhamentos_servidor WHERE id = :id')->execute(['id' => $id]);

registrarHistorico($equipamentoId, 'Compartilhamento', 'Compartilhamento "' . $compartilhamento['nome'] . '" removido');

flash('success', 'Compartilhamento "' . $compartilhamento['nome'] . '" excluído com sucesso.');
redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#compartilhamentos');
