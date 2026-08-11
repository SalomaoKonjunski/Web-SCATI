<?php
/**
 * Web SCATI - Autenticação e controle de acesso.
 */

declare(strict_types=1);

function iniciarSessao(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Retorna os dados do usuário logado (id, usuario, admin) ou null se
 * ninguém estiver autenticado nesta sessão.
 */
function usuarioLogado(): ?array
{
    iniciarSessao();
    if (!isset($_SESSION['usuario_id'])) {
        return null;
    }
    return [
        'id'      => (int) $_SESSION['usuario_id'],
        'usuario' => $_SESSION['usuario_nome'],
        'admin'   => (bool) $_SESSION['usuario_admin'],
    ];
}

/**
 * Bloqueia o acesso à página atual caso ninguém esteja logado, redirecionando
 * para a tela de login.
 */
function exigirLogin(): void
{
    if (usuarioLogado() === null) {
        redirect('/modules/auth/login.php');
    }
}

/**
 * Bloqueia o acesso à página atual caso o usuário logado não seja
 * administrador (usada nas telas de gerenciamento de usuários).
 */
function exigirAdmin(): void
{
    exigirLogin();
    if (!usuarioLogado()['admin']) {
        flash('danger', 'Apenas o usuário administrador pode acessar esta página.');
        redirect('/index.php');
    }
}
