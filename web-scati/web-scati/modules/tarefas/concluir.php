<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('tarefas', 'alterar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/tarefas/index.php');
}

$pdo = db();
$id = (int) ($_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM tarefas_periodicas WHERE id = :id');
$stmt->execute(['id' => $id]);
$tarefa = $stmt->fetch();

if (!$tarefa) {
    flash('danger', 'Tarefa não encontrada.');
    redirect('/modules/tarefas/index.php');
}

$hoje = date('Y-m-d');
$proximaExecucao = proximaExecucaoTarefa($tarefa['frequencia_tipo'], (int) $tarefa['frequencia_valor'], $hoje);

$pdo->prepare(
    'UPDATE tarefas_periodicas SET ultima_execucao = :hoje, proxima_execucao = :proxima WHERE id = :id'
)->execute(['hoje' => $hoje, 'proxima' => $proximaExecucao, 'id' => $id]);

registrarHistoricoTarefa($id, 'Concluída', 'Tarefa "' . $tarefa['titulo'] . '" marcada como concluída. Próxima execução: ' . formatDate($proximaExecucao) . '.');

flash('success', 'Tarefa "' . $tarefa['titulo'] . '" marcada como concluída. Próxima execução: ' . formatDate($proximaExecucao) . '.');
redirect('/modules/tarefas/index.php');
