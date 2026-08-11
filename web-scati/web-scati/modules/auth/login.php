<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$pdo = db();

if (usuarioLogado() !== null) {
    redirect('/index.php');
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = (string) ($_POST['senha'] ?? '');

    if ($usuario === '' || $senha === '') {
        $erro = 'Informe usuário e senha.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE usuario = :usuario');
        $stmt->execute(['usuario' => $usuario]);
        $registro = $stmt->fetch();

        if ($registro && password_verify($senha, $registro['senha_hash'])) {
            iniciarSessao();
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = (int) $registro['id'];
            $_SESSION['usuario_nome'] = $registro['usuario'];
            $_SESSION['usuario_admin'] = (int) $registro['admin'];
            redirect('/index.php');
        } else {
            $erro = 'Usuário ou senha inválidos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · Web SCATI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef1f6;
        }
        .login-card { max-width: 380px; width: 100%; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <i class="bi bi-hdd-network" style="font-size: 2.5rem; color: #0d3b66;"></i>
            <h1 class="h4 mt-2 mb-0 fw-bold">Web SCATI</h1>
            <span class="text-muted small">Sistema de Controle de Ativos de TI</span>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Entrar</h2>
                <?php if ($erro !== ''): ?>
                    <div class="alert alert-danger py-2 small"><?= e($erro) ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Usuário</label>
                        <input type="text" name="usuario" class="form-control" required autofocus value="<?= e($_POST['usuario'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha</label>
                        <input type="password" name="senha" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Entrar
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
