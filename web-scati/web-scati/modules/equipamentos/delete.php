<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT patrimonio FROM equipamentos WHERE id = :id');
$stmt->execute(['id' => $id]);
$equipamento = $stmt->fetch();

if (!$equipamento) {
    flash('danger', 'Equipamento não encontrado.');
    redirect('/modules/equipamentos/index.php');
}

// Itens de estoque vinculados devolvem suas unidades ao estoque em vez de ficarem presos a um equipamento inexistente.
$stmtItensVinc = $pdo->prepare('SELECT estoque_id FROM itens_vinculados WHERE equipamento_id = :id');
$stmtItensVinc->execute(['id' => $id]);
$stmtIncrementa = $pdo->prepare('UPDATE estoque SET quantidade = quantidade + 1 WHERE id = :id');
foreach ($stmtItensVinc->fetchAll() as $vinc) {
    $stmtIncrementa->execute(['id' => $vinc['estoque_id']]);
}

// A exclusão do equipamento remove em cascata seu histórico, observações e vínculos de itens.
// Licenças vinculadas ficam sem equipamento (equipamento_id = NULL) em vez de serem apagadas.
$pdo->prepare('DELETE FROM equipamentos WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Equipamento "' . $equipamento['patrimonio'] . '" excluído com sucesso.');
redirect('/modules/equipamentos/index.php');
