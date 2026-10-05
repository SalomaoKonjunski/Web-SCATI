<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pageTitle = 'Sem acesso';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · Web SCATI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #eef1f6; }
        .sem-acesso-card { max-width: 420px; width: 100%; }
    </style>
</head>
<body>
    <div class="sem-acesso-card text-center">
        <i class="bi bi-slash-circle" style="font-size: 2.5rem; color: #6c757d;"></i>
        <h1 class="h4 mt-3 mb-2 fw-bold">Seu perfil não tem acesso a nenhuma área do sistema</h1>
        <p class="text-muted">
            Fale com um administrador para revisar as permissões do seu perfil
            de acesso (Configurações &gt; Perfis de Acesso).
        </p>
        <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="btn btn-outline-secondary">
            <i class="bi bi-box-arrow-right"></i> Sair
        </a>
    </div>
</body>
</html>
