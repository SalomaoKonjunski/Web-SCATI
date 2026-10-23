<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('transferencias', 'alterar');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

// Apaga do disco os arquivos anexados antes de excluir a transferência — a
// exclusão das linhas em anexos_transferencias acontece sozinha (FOREIGN
// KEY ... ON DELETE CASCADE), mas os arquivos em uploads/anexos_transferencias/
// não, então precisam ser removidos manualmente aqui pra não ficarem órfãos.
$stmtAnexos = $pdo->prepare('SELECT nome_arquivo FROM anexos_transferencias WHERE transferencia_id = :id');
$stmtAnexos->execute(['id' => $id]);
foreach ($stmtAnexos->fetchAll() as $anexo) {
    $caminho = __DIR__ . '/../../uploads/anexos_transferencias/' . basename($anexo['nome_arquivo']);
    if (is_file($caminho)) {
        unlink($caminho);
    }
}

$stmt = $pdo->prepare('DELETE FROM transferencias WHERE id = :id');
$stmt->execute(['id' => $id]);

if ($stmt->rowCount() > 0) {
    flash('success', 'Transferência excluída com sucesso.');
} else {
    flash('danger', 'Transferência não encontrada.');
}

redirect('/modules/transferencias/index.php');
