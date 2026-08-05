<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT software, equipamento_id FROM licencas WHERE id = :id');
$stmt->execute(['id' => $id]);
$licenca = $stmt->fetch();

if (!$licenca) {
    flash('danger', 'Licença não encontrada.');
    redirect('/modules/licencas/index.php');
}

if ($licenca['equipamento_id']) {
    registrarHistorico((int) $licenca['equipamento_id'], 'Licença', 'Licença "' . $licenca['software'] . '" removida (exclusão)');
}

$pdo->prepare('DELETE FROM licencas WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Licença "' . $licenca['software'] . '" excluída com sucesso.');
redirect('/modules/licencas/index.php');
