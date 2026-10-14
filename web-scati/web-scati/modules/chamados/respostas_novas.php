<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('chamados', 'ver');

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$usuarioAtual = usuarioLogado();
$chamadoId = (int) ($_GET['chamado_id'] ?? 0);
$aposId = (int) ($_GET['apos_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, criado_por_id FROM chamados WHERE id = :id');
$stmt->execute(['id' => $chamadoId]);
$chamado = $stmt->fetch();

// Mesma regra de visibilidade da ficha (modules/chamados/form.php): o
// perfil Usuário só acompanha os próprios chamados; os demais perfis
// enxergam a conversa de qualquer chamado que tenham permissão de ver,
// mesmo sem estar diretamente envolvidos (só não podem responder).
if (!$chamado || ($usuarioAtual['solicitante'] && (int) $chamado['criado_por_id'] !== (int) $usuarioAtual['id'])) {
    http_response_code(404);
    echo json_encode(['erro' => 'Chamado não encontrado.']);
    exit;
}

$stmtRespostas = $pdo->prepare(
    'SELECT * FROM chamado_respostas WHERE chamado_id = :chamado_id AND id > :apos_id ORDER BY criado_em ASC, id ASC'
);
$stmtRespostas->execute(['chamado_id' => $chamadoId, 'apos_id' => $aposId]);
$respostasNovas = $stmtRespostas->fetchAll();

$anexosPorResposta = [];
if (!empty($respostasNovas)) {
    $idsRespostas = array_column($respostasNovas, 'id');
    $marcadores = implode(',', array_fill(0, count($idsRespostas), '?'));
    $stmtAnexos = $pdo->prepare("SELECT * FROM chamado_resposta_anexos WHERE resposta_id IN ($marcadores) ORDER BY id ASC");
    $stmtAnexos->execute($idsRespostas);
    foreach ($stmtAnexos->fetchAll() as $anexo) {
        $anexosPorResposta[(int) $anexo['resposta_id']][] = $anexo;
    }

    // Quem está com o chat aberto e recebendo as mensagens ao vivo já as
    // viu — mantém a contagem de não lidos (sininho, menu lateral) em dia.
    marcarChamadoVisto($chamadoId, (int) $usuarioAtual['id']);
}

$itens = [];
$ultimoId = $aposId;
foreach ($respostasNovas as $resposta) {
    $itens[] = [
        'id' => (int) $resposta['id'],
        'html' => renderRespostaChamado($resposta, $anexosPorResposta[(int) $resposta['id']] ?? [], (int) $usuarioAtual['id']),
    ];
    $ultimoId = (int) $resposta['id'];
}

echo json_encode(['respostas' => $itens, 'ultimo_id' => $ultimoId]);
