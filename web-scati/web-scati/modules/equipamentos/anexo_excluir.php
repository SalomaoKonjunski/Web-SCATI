<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM anexos_equipamentos WHERE id = :id');
$stmt->execute(['id' => $id]);
$anexo = $stmt->fetch();

if (!$anexo) {
    flash('danger', 'Anexo não encontrado.');
    redirect('/modules/equipamentos/index.php');
}

$equipamentoId = (int) $anexo['equipamento_id'];
$caminho = __DIR__ . '/../../uploads/anexos/' . basename($anexo['nome_arquivo']);

$pdo->prepare('DELETE FROM anexos_equipamentos WHERE id = :id')->execute(['id' => $id]);

if (is_file($caminho)) {
    unlink($caminho);
}

registrarHistorico($equipamentoId, 'Anexo', 'Arquivo "' . $anexo['nome_original'] . '" removido deste equipamento');

flash('success', 'Anexo "' . $anexo['nome_original'] . '" excluído com sucesso.');
redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#anexos');
