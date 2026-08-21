<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$usuarioAtual = usuarioLogado();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/chamados/index.php');
}

$chamadoId = (int) ($_POST['chamado_id'] ?? 0);
$texto = trim($_POST['texto'] ?? '');

// Observações são uma anotação interna, visível só para administradores.
if (!$usuarioAtual['admin']) {
    flash('danger', 'Só o Administrador pode adicionar observações.');
    redirect('/modules/chamados/form.php?id=' . $chamadoId);
}

$stmt = $pdo->prepare('SELECT id FROM chamados WHERE id = :id');
$stmt->execute(['id' => $chamadoId]);
if (!$stmt->fetch()) {
    flash('danger', 'Chamado não encontrado.');
    redirect('/modules/chamados/index.php');
}

if ($texto === '') {
    flash('danger', 'Digite uma observação antes de salvar.');
    redirect('/modules/chamados/form.php?id=' . $chamadoId);
}

$pdo->prepare(
    'INSERT INTO chamado_observacoes (chamado_id, usuario_id, texto) VALUES (:chamado_id, :usuario_id, :texto)'
)->execute([
    'chamado_id' => $chamadoId,
    'usuario_id' => $usuarioAtual['id'],
    'texto' => $texto,
]);

flash('success', 'Observação adicionada.');
redirect('/modules/chamados/form.php?id=' . $chamadoId);
