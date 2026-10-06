<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('chamados', 'alterar');

$usuarioAtual = usuarioLogado();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, titulo, responsavel_id, status FROM chamados WHERE id = :id');
$stmt->execute(['id' => $id]);
$chamado = $stmt->fetch();

if (!$chamado) {
    flash('danger', 'Chamado não encontrado.');
    redirect('/modules/chamados/index.php');
}

// Autoatribuição (qualquer um da equipe pode assumir um chamado ainda sem
// responsável) só vale enquanto ninguém mais assumiu — para atribuir a
// OUTRA pessoa, ou reatribuir um chamado que já tem responsável, só o
// Administrador pode (ver atribuir_outro.php).
if ($chamado['responsavel_id'] !== null) {
    flash('danger', 'Este chamado já tem um responsável.');
    redirect('/modules/chamados/index.php');
}

// Assumir um chamado novo já o coloca em andamento — "Aberto" passa a
// significar exclusivamente "ainda sem responsável".
$novoStatus = $chamado['status'] === 'Aberto' ? 'Em andamento' : $chamado['status'];
$pdo->prepare('UPDATE chamados SET responsavel_id = :responsavel_id, status = :status WHERE id = :id')
    ->execute(['responsavel_id' => $usuarioAtual['id'], 'status' => $novoStatus, 'id' => $id]);

registrarHistoricoChamado($id, 'Responsável', 'Atribuído a "' . $usuarioAtual['usuario'] . '"');

flash('success', 'Chamado "' . $chamado['titulo'] . '" atribuído a você.');
redirect('/modules/chamados/index.php');
