<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('tarefas', 'alterar');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT titulo FROM tarefas_periodicas WHERE id = :id');
$stmt->execute(['id' => $id]);
$tarefa = $stmt->fetch();

if (!$tarefa) {
    flash('danger', 'Tarefa não encontrada.');
    redirect('/modules/tarefas/index.php');
}

$pdo->prepare('DELETE FROM tarefas_periodicas WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Tarefa "' . $tarefa['titulo'] . '" excluída com sucesso.');
redirect('/modules/tarefas/index.php');
