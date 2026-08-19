<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT nome FROM tipos_manutencao WHERE id = :id');
$stmt->execute(['id' => $id]);
$tipo = $stmt->fetch();

if (!$tipo) {
    flash('danger', 'Tipo de manutenção não encontrado.');
    redirect('/modules/tipos_manutencao/index.php');
}

// Registros já existentes no histórico guardam o nome como texto (sem
// referência ao id), então excluir o tipo não afeta o que já foi registrado.
$pdo->prepare('DELETE FROM tipos_manutencao WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Tipo de manutenção "' . $tipo['nome'] . '" excluído com sucesso.');
redirect('/modules/tipos_manutencao/index.php');
