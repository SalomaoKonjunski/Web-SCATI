<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('transferencias', 'alterar');

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$local = trim($_GET['local'] ?? '');

if ($local === '') {
    echo json_encode(['estoque' => [], 'equipamentos' => []]);
    exit;
}

// Só itens com quantidade > 0 — sem sentido oferecer pra transferir algo
// que não tem unidade disponível naquele local.
$stmtEstoque = $pdo->prepare(
    "SELECT es.id, es.nome, es.marca, es.modelo, es.quantidade
     FROM estoque es
     JOIN locais_estoque lo ON lo.id = es.local_id
     WHERE lo.nome = :local AND es.quantidade > 0
     ORDER BY es.nome ASC"
);
$stmtEstoque->execute(['local' => $local]);
$estoque = $stmtEstoque->fetchAll();

$stmtEquipamentos = $pdo->prepare(
    "SELECT id, nome, patrimonio, tipo FROM equipamentos WHERE localizacao = :local ORDER BY nome ASC"
);
$stmtEquipamentos->execute(['local' => $local]);
$equipamentos = $stmtEquipamentos->fetchAll();

echo json_encode(['estoque' => $estoque, 'equipamentos' => $equipamentos], JSON_UNESCAPED_UNICODE);
