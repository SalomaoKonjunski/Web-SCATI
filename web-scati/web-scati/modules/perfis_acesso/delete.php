<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirAdmin();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM perfis_acesso WHERE id = :id');
$stmt->execute(['id' => $id]);
$perfil = $stmt->fetch();

if (!$perfil) {
    flash('danger', 'Perfil não encontrado.');
    redirect('/modules/perfis_acesso/index.php');
}

if ($perfil['protegido']) {
    flash('danger', 'O perfil "' . $perfil['nome'] . '" é protegido e não pode ser excluído.');
    redirect('/modules/perfis_acesso/index.php');
}

$stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE perfil = :nome');
$stmtTotal->execute(['nome' => $perfil['nome']]);
$total = (int) $stmtTotal->fetchColumn();

if ($total > 0) {
    flash('danger', 'Não é possível excluir o perfil "' . $perfil['nome'] . '": existem ' . $total . ' usuário(s) com ele. Mude o perfil desses usuários primeiro.');
    redirect('/modules/perfis_acesso/index.php');
}

$pdo->prepare('DELETE FROM perfis_acesso WHERE id = :id')->execute(['id' => $id]);
flash('success', 'Perfil "' . $perfil['nome'] . '" excluído com sucesso.');
redirect('/modules/perfis_acesso/index.php');
