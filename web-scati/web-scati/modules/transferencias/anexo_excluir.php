<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('transferencias', 'alterar');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM anexos_transferencias WHERE id = :id');
$stmt->execute(['id' => $id]);
$anexo = $stmt->fetch();

if (!$anexo) {
    flash('danger', 'Anexo não encontrado.');
    redirect('/modules/transferencias/index.php');
}

$transferenciaId = (int) $anexo['transferencia_id'];
$caminho = __DIR__ . '/../../uploads/anexos_transferencias/' . basename($anexo['nome_arquivo']);

$pdo->prepare('DELETE FROM anexos_transferencias WHERE id = :id')->execute(['id' => $id]);

if (is_file($caminho)) {
    unlink($caminho);
}

flash('success', 'Anexo "' . $anexo['nome_original'] . '" excluído com sucesso.');
redirect('/modules/transferencias/view.php?id=' . $transferenciaId . '#anexos');
