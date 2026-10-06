<?php
// Identifica o módulo atual pela URL para destacar o item ativo no menu.
$currentPath = $_SERVER['SCRIPT_NAME'];
function isActive(string $needle, string $currentPath): string
{
    return str_contains($currentPath, $needle) ? 'active' : '';
}
$usuarioAtualSidebar = usuarioLogado();
$souSolicitante = $usuarioAtualSidebar['solicitante'] ?? false;
// O número em vermelho ao lado de "Chamados" só aparece quando existe
// solicitação ou mensagem nova ainda não vista por este usuário (mesmo
// critério do sininho de notificações no topo da tela).
$totalChamadosNaoLidos = contarChamadosNaoLidos((int) $usuarioAtualSidebar['id'], !$souSolicitante);
// Cada item do menu só aparece se o perfil logado tiver "Visualizar" no
// módulo correspondente (Configurações > Perfis de Acesso) — Administrador
// sempre vê tudo (temPermissao() dá acesso completo pra perfil protegido).
$divisorConfigAplicado = false;
?>
<aside class="scati-sidebar" id="scatiSidebar">
    <ul class="nav flex-column">
        <?php if (temPermissao('dashboard', 'ver')): ?>
        <li class="nav-item">
            <a class="nav-link <?= $currentPath === BASE_URL . '/index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <?php endif; ?>
        <?php if (temPermissao('chamados', 'ver')): ?>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/chamados/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/chamados/index.php">
                <i class="bi bi-life-preserver"></i> Chamados
                <?php if ($totalChamadosNaoLidos > 0): ?>
                    <span class="badge bg-danger ms-1" title="Solicitação ou mensagem nova"><?= $totalChamadosNaoLidos ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endif; ?>
        <?php if (temPermissao('equipamentos', 'ver')): ?>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/equipamentos/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/equipamentos/index.php">
                <i class="bi bi-pc-display"></i> Equipamentos
            </a>
        </li>
        <?php endif; ?>
        <?php if (temPermissao('impressoras', 'ver')): ?>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/impressoras/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/impressoras/index.php">
                <i class="bi bi-printer"></i> Impressoras
            </a>
        </li>
        <?php endif; ?>
        <?php if (temPermissao('estoque', 'ver')): ?>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/estoque/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/estoque/index.php">
                <i class="bi bi-box-seam"></i> Estoque
            </a>
        </li>
        <?php endif; ?>
        <?php if (temPermissao('redes', 'ver')): ?>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/redes/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/redes/index.php">
                <i class="bi bi-diagram-3"></i> Redes
            </a>
        </li>
        <?php endif; ?>
        <?php if (temPermissao('licencas', 'ver')): ?>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/licencas/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/licencas/index.php">
                <i class="bi bi-key"></i> Licenças
            </a>
        </li>
        <?php endif; ?>
        <?php if (temPermissao('relatorios', 'ver')): ?>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/relatorios/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/relatorios/index.php">
                <i class="bi bi-bar-chart-line"></i> Relatórios
            </a>
        </li>
        <?php endif; ?>
        <?php if (temPermissao('notas', 'ver')): ?>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/notas/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/notas/index.php">
                <i class="bi bi-journal-text"></i> Bloco de Notas
            </a>
        </li>
        <?php endif; ?>
        <?php if (temPermissao('tarefas', 'ver')): ?>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/tarefas/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/tarefas/index.php">
                <i class="bi bi-bell"></i> Central de Alertas
            </a>
        </li>
        <?php endif; ?>
        <?php if (temPermissao('configuracoes', 'ver')): $divisorConfigAplicado = true; ?>
        <li class="nav-item mt-3 border-top border-secondary-subtle pt-3">
            <a class="nav-link <?= isActive('/modules/configuracoes/', $currentPath) ?: (isActive('/modules/categorias_estoque/', $currentPath) ?: (isActive('/modules/categorias_equipamento/', $currentPath) ?: (isActive('/modules/categorias_senha/', $currentPath) ?: (isActive('/modules/tipos_manutencao/', $currentPath) ?: isActive('/modules/perfis_acesso/', $currentPath))))) ?>" href="<?= BASE_URL ?>/modules/configuracoes/index.php">
                <i class="bi bi-gear"></i> Configurações
            </a>
        </li>
        <?php endif; ?>
        <?php if (temPermissao('senhas', 'ver') || temAlgumaSenhaCompartilhada((int) $usuarioAtualSidebar['id'])): ?>
        <li class="nav-item <?= !$divisorConfigAplicado ? 'mt-3 border-top border-secondary-subtle pt-3' : '' ?>">
            <?php $divisorConfigAplicado = true; ?>
            <a class="nav-link <?= isActive('/modules/senhas/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/senhas/index.php">
                <i class="bi bi-shield-lock"></i> Senhas
            </a>
        </li>
        <?php endif; ?>
        <?php if (temPermissao('usuarios', 'ver')): ?>
        <li class="nav-item <?= !$divisorConfigAplicado ? 'mt-3 border-top border-secondary-subtle pt-3' : '' ?>">
            <a class="nav-link <?= isActive('/modules/usuarios/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/usuarios/index.php">
                <i class="bi bi-people"></i> Usuários
            </a>
        </li>
        <?php endif; ?>
    </ul>
</aside>
