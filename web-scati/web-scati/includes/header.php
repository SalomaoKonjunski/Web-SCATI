<?php
/**
 * Web SCATI - Cabeçalho comum a todas as páginas.
 * Espera que a página que o incluiu já tenha definido $pageTitle.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$pageTitle = $pageTitle ?? 'Web SCATI';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · Web SCATI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark scati-navbar px-3">
    <button class="btn btn-link text-white d-md-none" type="button" id="sidebarToggle">
        <i class="bi bi-list fs-3"></i>
    </button>
    <?php $usuarioAtual = usuarioLogado(); ?>
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/<?= ($usuarioAtual['solicitante'] ?? false) ? 'modules/chamados/index.php' : 'index.php' ?>">
        <i class="bi bi-hdd-network me-2"></i>Web SCATI
    </a>
    <span class="text-white-50 small d-none d-md-inline">Sistema de Controle de Ativos de TI</span>
    <?php if ($usuarioAtual): ?>
        <span class="ms-auto d-flex align-items-center gap-2">
            <span class="text-white-50 small d-none d-sm-inline">
                <i class="bi bi-person-circle"></i> <?= e($usuarioAtual['usuario']) ?>
            </span>
            <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="btn btn-sm btn-outline-light">
                <i class="bi bi-box-arrow-right"></i> Sair
            </a>
        </span>
    <?php endif; ?>
</nav>

<div class="scati-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="scati-content">
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['tipo']) ?> alert-dismissible fade show d-print-none" role="alert">
                <?= e($flash['mensagem']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
