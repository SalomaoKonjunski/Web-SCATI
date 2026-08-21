<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$usuarioAtual = usuarioLogado();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/chamados/index.php');
}

$chamadoId = (int) ($_POST['chamado_id'] ?? 0);
$texto = trim($_POST['texto'] ?? '');

$stmt = $pdo->prepare('SELECT id, criado_por_id FROM chamados WHERE id = :id');
$stmt->execute(['id' => $chamadoId]);
$chamado = $stmt->fetch();

if (!$chamado) {
    flash('danger', 'Chamado não encontrado.');
    redirect('/modules/chamados/index.php');
}

// O perfil Usuário só pode anotar nos próprios chamados.
if ($usuarioAtual['solicitante'] && (int) $chamado['criado_por_id'] !== (int) $usuarioAtual['id']) {
    flash('danger', 'Você só pode acompanhar os próprios chamados.');
    redirect('/modules/chamados/index.php');
}

if ($texto === '') {
    flash('danger', 'Digite uma observação antes de salvar.');
    redirect('/modules/chamados/form.php?id=' . $chamadoId);
}

$pdo->prepare(
    'INSERT INTO chamado_observacoes (chamado_id, usuario_id, texto) VALUES (:chamado_id, :usuario_id, :texto)'
)->execute([
    'chamado_id' => $chamadoId,
    'usuario_id' => $usuarioAtual['id'],
    'texto' => $texto,
]);
$novaObservacaoId = (int) $pdo->lastInsertId();

// Além de quem escreveu, o autor pode marcar outros cadastros para também
// verem esta observação — valida contra usuários existentes para não
// aceitar ids inventados vindos do POST.
$idsExistentes = array_map('intval', $pdo->query('SELECT id FROM usuarios')->fetchAll(PDO::FETCH_COLUMN));
$idsCompartilhar = array_unique(array_filter(
    array_map('intval', (array) ($_POST['visualizadores'] ?? [])),
    fn (int $uid): bool => $uid !== (int) $usuarioAtual['id'] && in_array($uid, $idsExistentes, true)
));

if (!empty($idsCompartilhar)) {
    $stmtVis = $pdo->prepare(
        'INSERT INTO chamado_observacao_visualizadores (observacao_id, usuario_id) VALUES (:oid, :uid)'
    );
    foreach ($idsCompartilhar as $uid) {
        $stmtVis->execute(['oid' => $novaObservacaoId, 'uid' => $uid]);
    }
}

flash('success', 'Observação adicionada.');
redirect('/modules/chamados/form.php?id=' . $chamadoId);
