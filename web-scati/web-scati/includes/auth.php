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
 *
 * "admin" significa literalmente o perfil protegido Administrador (ver
 * perfis_acesso.protegido) — usado só nos poucos lugares que precisam
 * continuar travados nele mesmo que o administrador mude as permissões
 * de outros perfis (gerenciar os próprios Perfis de Acesso, e a conta
 * de "garantir que sobra sempre 1 administrador" no cadastro de
 * Usuários). O resto do sistema usa temPermissao()/exigirPermissao(),
 * que consultam a tabela perfil_permissoes.
 *
 * "solicitante" é mantido por compatibilidade com o restante do código
 * de Chamados: continua significando "só pode ver/responder os próprios
 * chamados, sem gerenciar os demais" — hoje equivale a não ter
 * Chamados:Alterar.
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
        'solicitante' => !temPermissao('chamados', 'alterar', $perfil),
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
 * literalmente o perfil protegido Administrador. Reservado pras poucas
 * páginas que não podem virar configuráveis via Perfis de Acesso —
 * Gerenciar Perfis de Acesso em si, acima de tudo, já que qualquer
 * perfil que pudesse editar permissões poderia se dar acesso total.
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
 * Bloqueia o acesso à página atual caso o perfil do usuário logado não
 * tenha a permissão pedida (ver temPermissao()) naquele módulo. Tenta
 * mandar quem for barrado pra algum lugar que ele realmente possa ver
 * (Chamados, senão o Dashboard) — e, no caso raro de um perfil sem
 * acesso a nada, pra uma página de aviso em vez de ficar preso num
 * loop de redirecionamentos.
 */
function exigirPermissao(string $modulo, string $acao = 'ver'): void
{
    exigirLogin();
    if (temPermissao($modulo, $acao)) {
        return;
    }
    flash('danger', 'Seu perfil não tem acesso a esta página.');
    // Sem a exceção pro próprio módulo pedido: o guard de chamados/index.php
    // e do dashboard só pedem "ver" naquele módulo, então mandar pra lá é
    // sempre seguro quando temPermissao() devolve true ali — mesmo que a
    // checagem que acabou de falhar tenha sido também por "chamados" ou
    // "dashboard" (ex.: tinha "ver" mas não o "alterar" que a ação pedia).
    if (temPermissao('chamados', 'ver')) {
        redirect('/modules/chamados/index.php');
    }
    if (temPermissao('dashboard', 'ver')) {
        redirect('/index.php');
    }
    redirect('/modules/auth/sem_acesso.php');
}
