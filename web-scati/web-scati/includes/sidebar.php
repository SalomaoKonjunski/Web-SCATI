<?php
// Identifica o módulo atual pela URL para destacar o item ativo no menu.
$currentPath = $_SERVER['SCRIPT_NAME'];
function isActive(string $needle, string $currentPath): string
{
    return str_contains($currentPath, $needle) ? 'active' : '';
}
$totalChamadosAbertos = (int) db()->query("SELECT COUNT(*) FROM chamados WHERE status NOT IN ('Concluído', 'Cancelado')")->fetchColumn();
?>
<aside class="scati-sidebar" id="scatiSidebar">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= $currentPath === BASE_URL . '/index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/chamados/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/chamados/index.php">
                <i class="bi bi-life-preserver"></i> Chamados
                <?php if ($totalChamadosAbertos > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $totalChamadosAbertos ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/equipamentos/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/equipamentos/index.php">
                <i class="bi bi-pc-display"></i> Equipamentos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/impressoras/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/impressoras/index.php">
                <i class="bi bi-printer"></i> Impressoras
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/estoque/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/estoque/index.php">
                <i class="bi bi-box-seam"></i> Estoque
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/redes/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/redes/index.php">
                <i class="bi bi-diagram-3"></i> Redes
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/licencas/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/licencas/index.php">
                <i class="bi bi-key"></i> Licenças
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/relatorios/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/relatorios/index.php">
                <i class="bi bi-bar-chart-line"></i> Relatórios
            </a>
        </li>
        <li class="nav-item mt-3 border-top border-secondary-subtle pt-3">
            <a class="nav-link <?= isActive('/modules/configuracoes/', $currentPath) ?: (isActive('/modules/categorias_estoque/', $currentPath) ?: isActive('/modules/tipos_manutencao/', $currentPath)) ?>" href="<?= BASE_URL ?>/modules/configuracoes/index.php">
                <i class="bi bi-gear"></i> Configurações
            </a>
        </li>
        <?php if ((usuarioLogado()['admin'] ?? false)): ?>
        <li class="nav-item">
            <a class="nav-link <?= isActive('/modules/usuarios/', $currentPath) ?>" href="<?= BASE_URL ?>/modules/usuarios/index.php">
                <i class="bi bi-people"></i> Usuários
            </a>
        </li>
        <?php endif; ?>
    </ul>
</aside>
