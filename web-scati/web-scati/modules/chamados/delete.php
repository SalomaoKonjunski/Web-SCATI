<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('chamados', 'alterar');

$pdo = db();
$usuarioAtual = usuarioLogado();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT titulo, status, responsavel_id FROM chamados WHERE id = :id');
$stmt->execute(['id' => $id]);
$chamado = $stmt->fetch();

if (!$chamado) {
    flash('danger', 'Chamado não encontrado.');
    redirect('/modules/chamados/index.php');
}

if (!in_array($chamado['status'], ['Concluído', 'Cancelado'], true)) {
    flash('danger', 'Não é possível excluir um chamado em aberto. Marque-o como Concluído ou Cancelado primeiro.');
    redirect('/modules/chamados/index.php');
}

if (!podeGerenciarChamado($chamado, $usuarioAtual)) {
    flash('danger', 'Só o responsável por este chamado (ou um Administrador) pode excluí-lo.');
    redirect('/modules/chamados/index.php');
}

$pdo->prepare('DELETE FROM chamados WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Chamado "' . $chamado['titulo'] . '" excluído com sucesso.');
redirect('/modules/chamados/index.php');
