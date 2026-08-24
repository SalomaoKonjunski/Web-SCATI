<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT nome FROM categorias_equipamento WHERE id = :id');
$stmt->execute(['id' => $id]);
$categoria = $stmt->fetch();

if (!$categoria) {
    flash('danger', 'Categoria não encontrada.');
    redirect('/modules/categorias_equipamento/index.php');
}

if (in_array($categoria['nome'], tiposEquipamentoProtegidos(), true)) {
    flash('danger', 'A categoria "' . $categoria['nome'] . '" não pode ser excluída, pois é usada por funcionalidades do sistema.');
    redirect('/modules/categorias_equipamento/index.php');
}

$stmtCount = $pdo->prepare('SELECT COUNT(*) FROM equipamentos WHERE tipo = :nome');
$stmtCount->execute(['nome' => $categoria['nome']]);
$totalEquipamentos = (int) $stmtCount->fetchColumn();

if ($totalEquipamentos > 0) {
    flash('danger', 'Não é possível excluir a categoria "' . $categoria['nome'] . '": existem ' . $totalEquipamentos . ' equipamento(s) nela. Altere o tipo desses equipamentos primeiro.');
    redirect('/modules/categorias_equipamento/index.php');
}

$pdo->prepare('DELETE FROM categorias_equipamento WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Categoria "' . $categoria['nome'] . '" excluída com sucesso.');
redirect('/modules/categorias_equipamento/index.php');
