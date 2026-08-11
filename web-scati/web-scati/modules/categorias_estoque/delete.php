<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT nome FROM categorias_estoque WHERE id = :id');
$stmt->execute(['id' => $id]);
$categoria = $stmt->fetch();

if (!$categoria) {
    flash('danger', 'Categoria não encontrada.');
    redirect('/modules/categorias_estoque/index.php');
}

if ($categoria['nome'] === 'Toner') {
    flash('danger', 'A categoria "Toner" não pode ser excluída, pois é usada pela funcionalidade de Toner de impressoras.');
    redirect('/modules/categorias_estoque/index.php');
}

$stmtCount = $pdo->prepare('SELECT COUNT(*) FROM estoque WHERE categoria_id = :id');
$stmtCount->execute(['id' => $id]);
$totalItens = (int) $stmtCount->fetchColumn();

if ($totalItens > 0) {
    flash('danger', 'Não é possível excluir a categoria "' . $categoria['nome'] . '": existem ' . $totalItens . ' item(ns) de estoque nela. Mova ou exclua esses itens primeiro.');
    redirect('/modules/categorias_estoque/index.php');
}

$pdo->prepare('DELETE FROM categorias_estoque WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Categoria "' . $categoria['nome'] . '" excluída com sucesso.');
redirect('/modules/categorias_estoque/index.php');
