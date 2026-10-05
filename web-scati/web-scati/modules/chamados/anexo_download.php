<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$usuarioAtual = usuarioLogado();
$id = (int) ($_GET['id'] ?? 0);

// O anexo só é liberado se o chamado da resposta dona dele puder ser visto
// por este usuário (join, nunca confia só no id do anexo): o perfil
// Usuário só vê os próprios chamados, os demais perfis veem todos.
$stmt = $pdo->prepare(
    'SELECT a.*, c.criado_por_id FROM chamado_resposta_anexos a
     JOIN chamado_respostas r ON r.id = a.resposta_id
     JOIN chamados c ON c.id = r.chamado_id
     WHERE a.id = :id'
);
$stmt->execute(['id' => $id]);
$anexo = $stmt->fetch();

if ($anexo && $usuarioAtual['solicitante'] && (int) $anexo['criado_por_id'] !== (int) $usuarioAtual['id']) {
    $anexo = false;
}

if (!$anexo) {
    http_response_code(404);
    exit('Anexo não encontrado.');
}

// nome_arquivo é sempre um nome gerado internamente (hex + extensão validada),
// nunca o nome original enviado pelo usuário — basename() aqui é só uma
// camada extra de proteção contra path traversal.
$caminho = __DIR__ . '/../../uploads/anexos_chamados/' . basename($anexo['nome_arquivo']);

if (!is_file($caminho)) {
    http_response_code(404);
    exit('Arquivo não encontrado no servidor.');
}

// Fotos são exibidas direto na conversa (inline); os demais tipos de
// arquivo são sempre baixados.
$tipoMime = $anexo['tipo_mime'] ?: 'application/octet-stream';
$disposicao = str_starts_with($tipoMime, 'image/') ? 'inline' : 'attachment';

header('Content-Type: ' . $tipoMime);
header('Content-Disposition: ' . $disposicao . '; filename="' . basename($anexo['nome_original']) . '"');
header('Content-Length: ' . (string) filesize($caminho));
header('X-Content-Type-Options: nosniff');
readfile($caminho);
exit;
