<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

header('Content-Type: application/json');

$pdo = db();
$usuarioId = usuarioLogado()['id'];

$dados = json_decode((string) file_get_contents('php://input'), true);
$endpoint = is_array($dados) ? (string) ($dados['endpoint'] ?? '') : '';

if ($endpoint !== '') {
    $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = :endpoint AND usuario_id = :usuario_id')
        ->execute(['endpoint' => $endpoint, 'usuario_id' => $usuarioId]);
}

echo json_encode(['ok' => true]);
