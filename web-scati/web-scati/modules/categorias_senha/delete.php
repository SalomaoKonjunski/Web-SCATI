<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirAdmin();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT nome FROM categorias_senha WHERE id = :id');
$stmt->execute(['id' => $id]);
$categoria = $stmt->fetch();

if (!$categoria) {
    flash('danger', 'Categoria não encontrada.');
    redirect('/modules/categorias_senha/index.php');
}

$stmtCount = $pdo->prepare('SELECT COUNT(*) FROM senhas WHERE categoria = :nome');
$stmtCount->execute(['nome' => $categoria['nome']]);
$totalSenhas = (int) $stmtCount->fetchColumn();

if ($totalSenhas > 0) {
    flash('danger', 'Não é possível excluir a categoria "' . $categoria['nome'] . '": existem ' . $totalSenhas . ' senha(s) nela. Mova ou exclua essas senhas primeiro.');
    redirect('/modules/categorias_senha/index.php');
}

$pdo->prepare('DELETE FROM categorias_senha WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Categoria "' . $categoria['nome'] . '" excluída com sucesso.');
redirect('/modules/categorias_senha/index.php');
