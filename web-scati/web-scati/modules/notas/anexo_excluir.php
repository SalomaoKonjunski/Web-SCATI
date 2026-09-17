<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$usuarioId = usuarioLogado()['id'];
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT a.* FROM anexos_notas a
     JOIN notas n ON n.id = a.nota_id
     WHERE a.id = :id AND n.usuario_id = :usuario_id'
);
$stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
$anexo = $stmt->fetch();

if (!$anexo) {
    flash('danger', 'Anexo não encontrado.');
    redirect('/modules/notas/index.php');
}

$notaId = (int) $anexo['nota_id'];
$caminho = __DIR__ . '/../../uploads/anexos_notas/' . basename($anexo['nome_arquivo']);

$pdo->prepare('DELETE FROM anexos_notas WHERE id = :id')->execute(['id' => $id]);

if (is_file($caminho)) {
    unlink($caminho);
}

flash('success', 'Anexo "' . $anexo['nome_original'] . '" excluído com sucesso.');
redirect('/modules/notas/form.php?id=' . $notaId . '#anexos');
