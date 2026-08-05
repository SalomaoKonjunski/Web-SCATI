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

// A exclusão do equipamento remove em cascata seu histórico e observações.
// Licenças vinculadas ficam sem equipamento (equipamento_id = NULL) em vez de serem apagadas.
$pdo->prepare('DELETE FROM equipamentos WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Equipamento "' . $equipamento['patrimonio'] . '" excluído com sucesso.');
redirect('/modules/equipamentos/index.php');
