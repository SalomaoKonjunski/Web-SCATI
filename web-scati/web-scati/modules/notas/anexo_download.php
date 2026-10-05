<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('notas', 'ver');

$pdo = db();
$usuarioId = usuarioLogado()['id'];
$id = (int) ($_GET['id'] ?? 0);

// O download só é liberado se a nota dona do anexo pertencer ao usuário
// logado, ou se a nota foi compartilhada com ele (visualizar ou editar
// — quem só visualiza também pode baixar os anexos).
$stmt = $pdo->prepare(
    'SELECT a.* FROM anexos_notas a
     JOIN notas n ON n.id = a.nota_id
     LEFT JOIN nota_compartilhamentos nc ON nc.nota_id = n.id AND nc.usuario_id = :usuario_id2
     WHERE a.id = :id AND (n.usuario_id = :usuario_id OR nc.usuario_id IS NOT NULL)'
);
$stmt->execute(['id' => $id, 'usuario_id' => $usuarioId, 'usuario_id2' => $usuarioId]);
$anexo = $stmt->fetch();

if (!$anexo) {
    http_response_code(404);
    exit('Anexo não encontrado.');
}

// nome_arquivo é sempre um nome gerado internamente (hex + extensão validada),
// nunca o nome original enviado pelo usuário — basename() aqui é só uma
// camada extra de proteção contra path traversal.
$caminho = __DIR__ . '/../../uploads/anexos_notas/' . basename($anexo['nome_arquivo']);

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
