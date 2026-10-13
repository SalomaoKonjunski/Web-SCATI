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
// Presente só no botão "Marcar como Concluída" do cadastro (form.php) —
// é o que libera concluir antes da data prevista. O botão rápido da
// listagem nunca envia isso, e só aparece (sem estar desabilitado)
// quando o alerta já venceu — ver prazoTarefa().
$viaCadastro = isset($_POST['via_cadastro']);

$stmt = $pdo->prepare('SELECT * FROM tarefas_periodicas WHERE id = :id');
$stmt->execute(['id' => $id]);
$tarefa = $stmt->fetch();

if (!$tarefa) {
    flash('danger', 'Alerta não encontrado.');
    redirect('/modules/tarefas/index.php');
}

$aindaNaoChegou = !prazoTarefa($tarefa['proxima_execucao'])['vencida'];
if (!$viaCadastro && $aindaNaoChegou) {
    flash('danger', 'O alerta "' . $tarefa['titulo'] . '" ainda não chegou na data prevista (' . formatDateTime($tarefa['proxima_execucao']) . '). Para concluir antes do prazo, abra o cadastro deste alerta.');
    redirect('/modules/tarefas/index.php');
}

$hoje = date('Y-m-d');
// Preserva o horário já configurado no alerta (ex.: "a cada 7 dias às
// 14:00" continua vencendo às 14:00, não no horário em que alguém clicou
// em "Concluir").
$horaAlvo = (new DateTime($tarefa['proxima_execucao']))->format('H:i');
$proximaExecucao = proximaExecucaoTarefa($tarefa['frequencia_tipo'], (int) $tarefa['frequencia_valor'], $hoje, $horaAlvo);

$pdo->prepare(
    'UPDATE tarefas_periodicas SET ultima_execucao = :hoje, proxima_execucao = :proxima WHERE id = :id'
)->execute(['hoje' => $hoje, 'proxima' => $proximaExecucao, 'id' => $id]);

registrarHistoricoTarefa($id, 'Concluída', 'Alerta "' . $tarefa['titulo'] . '" marcado como concluído' . ($aindaNaoChegou ? ' (antes da data prevista, pelo cadastro)' : '') . '. Próxima execução: ' . formatDateTime($proximaExecucao) . '.');

flash('success', 'Alerta "' . $tarefa['titulo'] . '" marcado como concluído. Próxima execução: ' . formatDateTime($proximaExecucao) . '.');
redirect($viaCadastro ? '/modules/tarefas/form.php?id=' . $id : '/modules/tarefas/index.php');
