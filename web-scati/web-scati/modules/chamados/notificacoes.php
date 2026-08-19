<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

header('Content-Type: application/json');
echo json_encode([
    'nao_lidas' => contarChamadosComRespostaNaoLida(usuarioLogado()['id']),
]);
