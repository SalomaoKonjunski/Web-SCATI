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

// Apaga do disco os arquivos anexados antes de excluir a nota — a
// exclusão das linhas em anexos_notas acontece sozinha (FOREIGN KEY ...
// ON DELETE CASCADE), mas os arquivos em uploads/anexos_notas/ não, então
// precisam ser removidos manualmente aqui pra não ficarem órfãos.
$stmtAnexos = $pdo->prepare('SELECT nome_arquivo FROM anexos_notas WHERE nota_id = :id');
$stmtAnexos->execute(['id' => $id]);
foreach ($stmtAnexos->fetchAll() as $anexo) {
    $caminho = __DIR__ . '/../../uploads/anexos_notas/' . basename($anexo['nome_arquivo']);
    if (is_file($caminho)) {
        unlink($caminho);
    }
}

$pdo->prepare('DELETE FROM notas WHERE id = :id AND usuario_id = :usuario_id')->execute(['id' => $id, 'usuario_id' => $usuarioId]);

flash('success', 'Nota "' . $nota['titulo'] . '" excluída com sucesso.');
redirect('/modules/notas/index.php');
