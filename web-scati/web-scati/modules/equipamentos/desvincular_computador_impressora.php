<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('equipamentos', 'alterar');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$voltarId = (int) ($_GET['voltar_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT ci.*, ec.nome AS computador_nome, ei.nome AS impressora_nome
     FROM computador_impressoras ci
     JOIN equipamentos ec ON ec.id = ci.computador_id
     JOIN equipamentos ei ON ei.id = ci.impressora_id
     WHERE ci.id = :id'
);
$stmt->execute(['id' => $id]);
$vinculo = $stmt->fetch();

if (!$vinculo) {
    flash('danger', 'Vínculo não encontrado.');
    redirect('/modules/equipamentos/index.php');
}

$computadorId = (int) $vinculo['computador_id'];
$impressoraId = (int) $vinculo['impressora_id'];

// Só aceita voltar pra uma das duas pontas do vínculo que acabou de ser
// desfeito — nunca um id arbitrário vindo da URL.
if (!in_array($voltarId, [$computadorId, $impressoraId], true)) {
    $voltarId = $impressoraId;
}

$pdo->prepare('DELETE FROM computador_impressoras WHERE id = :id')->execute(['id' => $id]);

$outroNome = $voltarId === $computadorId ? $vinculo['impressora_nome'] : $vinculo['computador_nome'];
registrarHistorico($voltarId, 'Vínculo', '"' . $outroNome . '" desvinculado deste equipamento');
flash('success', '"' . $outroNome . '" desvinculado com sucesso.');

redirect('/modules/equipamentos/view.php?id=' . $voltarId . '#vinculoCompImp');
