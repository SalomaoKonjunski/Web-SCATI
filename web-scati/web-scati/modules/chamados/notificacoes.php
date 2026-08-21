<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$usuarioAtual = usuarioLogado();
// Administrador e Padrão enxergam a listagem de chamados inteira, então
// são notificados sobre qualquer chamado; o perfil Usuário só é
// notificado sobre os próprios (solicitante ou responsável).
$verTodos = !$usuarioAtual['solicitante'];

$itens = listarChamadosNaoLidos($usuarioAtual['id'], $verTodos);

header('Content-Type: application/json');
echo json_encode([
    'nao_lidas' => contarChamadosNaoLidos($usuarioAtual['id'], $verTodos),
    'itens' => array_map(function (array $item): array {
        $ehSolicitacao = $item['tipo'] === 'solicitacao';
        $resumo = trim((string) ($ehSolicitacao ? $item['descricao'] : $item['ultima_mensagem']));
        if (mb_strlen($resumo) > 80) {
            $resumo = mb_substr($resumo, 0, 80) . '…';
        }
        return [
            'chamado_id'          => (int) $item['chamado_id'],
            'titulo'              => $item['titulo'],
            'tipo'                => $item['tipo'],
            'mensagem'            => $resumo,
            'usuario_nome'        => $ehSolicitacao ? $item['solicitante'] : $item['ultima_usuario_nome'],
            'qtd_mensagens_novas' => (int) $item['qtd_mensagens_novas'],
        ];
    }, $itens),
]);
