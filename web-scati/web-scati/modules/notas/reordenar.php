<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$usuarioId = (int) usuarioLogado()['id'];
$corpo = json_decode(file_get_contents('php://input') ?: '', true);
$ids = is_array($corpo['ids'] ?? null) ? array_map('intval', $corpo['ids']) : [];

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = db();

// Só reordena notas que realmente pertencem a este usuário — nunca confia
// direto na lista de ids que veio do navegador sem checar o dono.
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmtDono = $pdo->prepare("SELECT id FROM notas WHERE id IN ($placeholders) AND usuario_id = ?");
$stmtDono->execute([...$ids, $usuarioId]);
$idsDoUsuario = array_map('intval', array_column($stmtDono->fetchAll(), 'id'));

$stmtUpdate = $pdo->prepare('UPDATE notas SET ordem = :ordem WHERE id = :id AND usuario_id = :usuario_id');
$posicao = 0;
foreach ($ids as $idNota) {
    if (in_array($idNota, $idsDoUsuario, true)) {
        $stmtUpdate->execute(['ordem' => $posicao, 'id' => $idNota, 'usuario_id' => $usuarioId]);
        $posicao++;
    }
}

echo json_encode(['ok' => true]);
