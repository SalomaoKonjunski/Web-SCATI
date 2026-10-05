<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('equipamentos', 'alterar');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$corpo = json_decode(file_get_contents('php://input') ?: '', true);
$ids = is_array($corpo['ids'] ?? null) ? array_map('intval', $corpo['ids']) : [];

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = db();

// Só reordena ids que realmente existem em categorias_equipamento - nunca
// confia direto na lista de ids que veio do navegador sem checar.
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmtValidos = $pdo->prepare("SELECT id FROM categorias_equipamento WHERE id IN ($placeholders)");
$stmtValidos->execute($ids);
$idsValidos = array_map('intval', array_column($stmtValidos->fetchAll(), 'id'));

$stmtUpdate = $pdo->prepare('UPDATE categorias_equipamento SET ordem = :ordem WHERE id = :id');
$posicao = 0;
foreach ($ids as $id) {
    if (in_array($id, $idsValidos, true)) {
        $stmtUpdate->execute(['ordem' => $posicao, 'id' => $id]);
        $posicao++;
    }
}

echo json_encode(['ok' => true]);
