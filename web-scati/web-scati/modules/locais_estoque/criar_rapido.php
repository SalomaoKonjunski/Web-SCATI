<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

header('Content-Type: application/json; charset=utf-8');

// Endpoint compartilhado: o dropdown "+ Novo local..." é usado tanto no
// cadastro de Estoque quanto no de Transferências — basta ter permissão
// de alterar em qualquer um dos dois módulos que o consomem.
if (!temPermissao('estoque', 'alterar') && !temPermissao('transferencias', 'alterar')) {
    http_response_code(403);
    echo json_encode(['erro' => 'Sem permissão para cadastrar locais.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

$pdo = db();
$nome = trim($_POST['nome'] ?? '');

if ($nome === '') {
    http_response_code(422);
    echo json_encode(['erro' => 'Informe um nome para o novo local.']);
    exit;
}

// Se já existir um local com esse nome (sem diferenciar maiúsculas/minúsculas
// ou espaços nas pontas), reaproveita em vez de criar um cadastro duplicado —
// mesma regra usada ao cadastrar um item de estoque com nome repetido.
$stmtExistente = $pdo->prepare('SELECT id, nome FROM locais_estoque WHERE LOWER(TRIM(nome)) = LOWER(TRIM(:nome)) LIMIT 1');
$stmtExistente->execute(['nome' => $nome]);
$existente = $stmtExistente->fetch();

if ($existente) {
    echo json_encode(['id' => (int) $existente['id'], 'nome' => $existente['nome']]);
    exit;
}

$pdo->prepare('INSERT INTO locais_estoque (nome) VALUES (:nome)')->execute(['nome' => $nome]);
$novoId = (int) $pdo->lastInsertId();

echo json_encode(['id' => $novoId, 'nome' => $nome]);
