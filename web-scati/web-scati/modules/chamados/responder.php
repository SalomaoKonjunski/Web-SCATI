<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push.php';
exigirLogin();

$pdo = db();
$usuarioAtual = usuarioLogado();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/chamados/index.php');
}

$chamadoId = (int) ($_POST['chamado_id'] ?? 0);
$mensagem = trim($_POST['mensagem'] ?? '');

$stmt = $pdo->prepare('SELECT id, titulo, criado_por_id, responsavel_id FROM chamados WHERE id = :id');
$stmt->execute(['id' => $chamadoId]);
$chamado = $stmt->fetch();

if (!$chamado) {
    flash('danger', 'Chamado não encontrado.');
    redirect('/modules/chamados/index.php');
}

// O perfil Usuário só pode responder aos próprios chamados.
if ($usuarioAtual['solicitante'] && (int) $chamado['criado_por_id'] !== (int) $usuarioAtual['id']) {
    flash('danger', 'Você só pode responder aos próprios chamados.');
    redirect('/modules/chamados/index.php');
}

if ($mensagem === '') {
    flash('danger', 'Digite uma mensagem antes de enviar.');
    redirect('/modules/chamados/form.php?id=' . $chamadoId);
}

$pdo->prepare(
    'INSERT INTO chamado_respostas (chamado_id, usuario_id, usuario_nome, mensagem)
     VALUES (:chamado_id, :usuario_id, :usuario_nome, :mensagem)'
)->execute([
    'chamado_id' => $chamadoId,
    'usuario_id' => $usuarioAtual['id'],
    'usuario_nome' => $usuarioAtual['usuario'],
    'mensagem' => $mensagem,
]);

// Quem acabou de responder já viu o chamado até este momento.
marcarChamadoVisto($chamadoId, $usuarioAtual['id']);

// Notifica quem está diretamente envolvido no chamado (solicitante e
// responsável), exceto quem acabou de mandar a mensagem.
$destinatarios = array_unique(array_filter([
    $chamado['criado_por_id'] !== null ? (int) $chamado['criado_por_id'] : null,
    $chamado['responsavel_id'] !== null ? (int) $chamado['responsavel_id'] : null,
]));
$destinatarios = array_diff($destinatarios, [(int) $usuarioAtual['id']]);
foreach ($destinatarios as $destinatarioId) {
    enviarPushParaUsuario(
        $destinatarioId,
        'Nova mensagem no chamado',
        $chamado['titulo'] . ' — ' . $usuarioAtual['usuario'] . ': ' . $mensagem,
        BASE_URL . '/modules/chamados/form.php?id=' . $chamadoId
    );
}

flash('success', 'Resposta enviada.');
redirect('/modules/chamados/form.php?id=' . $chamadoId);
