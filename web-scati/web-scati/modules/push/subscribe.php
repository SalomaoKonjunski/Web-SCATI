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
$p256dh = is_array($dados) ? (string) ($dados['keys']['p256dh'] ?? '') : '';
$auth = is_array($dados) ? (string) ($dados['keys']['auth'] ?? '') : '';

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    http_response_code(400);
    echo json_encode(['erro' => 'Dados de inscrição inválidos.']);
    exit;
}

$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;

// Upsert por endpoint: se o mesmo navegador/dispositivo já estava
// inscrito (ex.: outra pessoa usou este computador antes), a inscrição
// passa a apontar para o usuário logado agora.
$pdo->prepare(
    'INSERT INTO push_subscriptions (usuario_id, endpoint, p256dh, auth, user_agent)
     VALUES (:usuario_id, :endpoint, :p256dh, :auth, :user_agent)
     ON DUPLICATE KEY UPDATE usuario_id = :usuario_id2, p256dh = :p256dh2, auth = :auth2, user_agent = :user_agent2'
)->execute([
    'usuario_id' => $usuarioId,
    'endpoint' => $endpoint,
    'p256dh' => $p256dh,
    'auth' => $auth,
    'user_agent' => $userAgent,
    'usuario_id2' => $usuarioId,
    'p256dh2' => $p256dh,
    'auth2' => $auth,
    'user_agent2' => $userAgent,
]);

echo json_encode(['ok' => true]);
