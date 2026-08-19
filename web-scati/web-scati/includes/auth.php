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
 * Retorna os dados do usuário logado (id, usuario, perfil, admin,
 * solicitante) ou null se ninguém estiver autenticado nesta sessão.
 */
function usuarioLogado(): ?array
{
    iniciarSessao();
    if (!isset($_SESSION['usuario_id'])) {
        return null;
    }
    $perfil = $_SESSION['usuario_perfil'];
    return [
        'id'          => (int) $_SESSION['usuario_id'],
        'usuario'     => $_SESSION['usuario_nome'],
        'perfil'      => $perfil,
        'admin'       => $perfil === 'Administrador',
        'solicitante' => $perfil === 'Solicitante',
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

/**
 * Bloqueia o acesso a páginas fora da aba de Chamados para o perfil
 * Solicitante, que só pode registrar e acompanhar seus próprios chamados.
 */
function exigirNaoSolicitante(): void
{
    exigirLogin();
    if (usuarioLogado()['solicitante']) {
        flash('danger', 'Seu perfil só tem acesso à aba de Chamados.');
        redirect('/modules/chamados/index.php');
    }
}
