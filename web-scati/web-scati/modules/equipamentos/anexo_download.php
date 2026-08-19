<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM anexos_equipamentos WHERE id = :id');
$stmt->execute(['id' => $id]);
$anexo = $stmt->fetch();

if (!$anexo) {
    http_response_code(404);
    exit('Anexo não encontrado.');
}

// nome_arquivo é sempre um nome gerado internamente (hex + extensão validada),
// nunca o nome original enviado pelo usuário — basename() aqui é só uma
// camada extra de proteção contra path traversal.
$caminho = __DIR__ . '/../../uploads/anexos/' . basename($anexo['nome_arquivo']);

if (!is_file($caminho)) {
    http_response_code(404);
    exit('Arquivo não encontrado no servidor.');
}

header('Content-Type: ' . ($anexo['tipo_mime'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($anexo['nome_original']) . '"');
header('Content-Length: ' . (string) filesize($caminho));
header('X-Content-Type-Options: nosniff');
readfile($caminho);
exit;
