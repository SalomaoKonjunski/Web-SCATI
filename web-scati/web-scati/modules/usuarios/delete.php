<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirAdmin();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$usuarioAtualId = usuarioLogado()['id'];

$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id');
$stmt->execute(['id' => $id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    flash('danger', 'Usuário não encontrado.');
    redirect('/modules/usuarios/index.php');
}

if ($id === $usuarioAtualId) {
    flash('danger', 'Não é possível excluir o próprio usuário logado.');
    redirect('/modules/usuarios/index.php');
}

if ($usuario['perfil'] === 'Administrador') {
    $totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'Administrador'")->fetchColumn();
    if ($totalAdmins <= 1) {
        flash('danger', 'Não é possível excluir o único administrador do sistema.');
        redirect('/modules/usuarios/index.php');
    }
}

$pdo->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $id]);

flash('success', 'Usuário "' . $usuario['usuario'] . '" excluído com sucesso.');
redirect('/modules/usuarios/index.php');
