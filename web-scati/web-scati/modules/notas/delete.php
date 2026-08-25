<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$usuarioId = usuarioLogado()['id'];
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT titulo FROM notas WHERE id = :id AND usuario_id = :usuario_id');
$stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
$nota = $stmt->fetch();

if (!$nota) {
    flash('danger', 'Nota não encontrada.');
    redirect('/modules/notas/index.php');
}

$pdo->prepare('DELETE FROM notas WHERE id = :id AND usuario_id = :usuario_id')->execute(['id' => $id, 'usuario_id' => $usuarioId]);

flash('success', 'Nota "' . $nota['titulo'] . '" excluída com sucesso.');
redirect('/modules/notas/index.php');
