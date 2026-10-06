<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$pageTitle = 'Senhas';
$usuarioAtual = usuarioLogado();
$usuarioId = (int) $usuarioAtual['id'];

// Acesso total ao cofre: quem tem "Senhas: Visualizar/Alterar" em Perfis
// de Acesso. Sem isso, só entra aqui quem tiver pelo menos uma senha
// compartilhada individualmente com ele (ver compartilhar.php) — nesse
// caso só vê as que foram compartilhadas, nunca o cofre inteiro.
$temAcessoTotal = temPermissao('senhas', 'ver');
$podeAlterar = temPermissao('senhas', 'alterar');

if (!$temAcessoTotal && !temAlgumaSenhaCompartilhada($usuarioId)) {
    exigirPermissao('senhas', 'ver'); // sem acesso nenhum: cai no mesmo bloqueio/fallback de sempre
}

if ($temAcessoTotal) {
    $busca = trim($_GET['busca'] ?? '');
    $filtroCategoria = $_GET['categoria'] ?? '';

    $sql = 'SELECT * FROM senhas WHERE 1=1';
    $params = [];

    if ($busca !== '') {
        $sql .= ' AND (nome LIKE :busca_nome OR usuario LIKE :busca_usuario OR observacoes LIKE :busca_obs)';
        $params['busca_nome'] = '%' . $busca . '%';
        $params['busca_usuario'] = '%' . $busca . '%';
        $params['busca_obs'] = '%' . $busca . '%';
    }
    if ($filtroCategoria !== '') {
        $sql .= ' AND categoria = :categoria';
        $params['categoria'] = $filtroCategoria;
    }

    $sql .= ' ORDER BY nome ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $senhas = $stmt->fetchAll();
} else {
    // Visão restrita: só as senhas compartilhadas com este usuário, sem
    // pesquisa/filtro (lista tende a ser pequena) e sem botão "Nova Senha".
    $stmt = $pdo->prepare(
        'SELECT s.*, sc.pode_editar
         FROM senhas s
         JOIN senha_compartilhamentos sc ON sc.senha_id = s.id AND sc.usuario_id = :usuario_id
         ORDER BY s.nome ASC'
    );
    $stmt->execute(['usuario_id' => $usuarioId]);
    $senhas = $stmt->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-shield-lock me-2"></i>Senhas</h1>
    <?php if ($podeAlterar): ?>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Senha</a>
    <?php endif; ?>
</div>

<?php if (!$temAcessoTotal): ?>
    <p class="text-muted small"><i class="bi bi-share me-1"></i> Você está vendo só as senhas que foram compartilhadas com você.</p>
<?php endif; ?>

<?php if ($temAcessoTotal): ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Pesquisar</label>
                <input type="text" name="busca" class="form-control" placeholder="Nome, usuário, observações..." value="<?= e($busca) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Categoria</label>
                <select name="categoria" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach (categoriasSenha() as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $filtroCategoria === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
                <a href="index.php" class="btn btn-outline-secondary" title="Limpar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome / Serviço</th>
                    <th>Categoria</th>
                    <th>Usuário</th>
                    <th>Senha</th>
                    <th>Observações</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($senhas)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma senha encontrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($senhas as $senha): ?>
                    <?php
                        $senhaTexto = descriptografar($senha['senha_cifrada']) ?? '';
                        $podeEditarEsta = $temAcessoTotal ? $podeAlterar : (bool) $senha['pode_editar'];
                    ?>
                    <tr <?= $podeEditarEsta ? 'data-href="form.php?id=' . (int) $senha['id'] . '" title="Abrir cadastro da senha"' : '' ?>>
                        <td><strong><?= e($senha['nome']) ?></strong></td>
                        <td><?= badgeCor($senha['categoria'], corCategoriaSenha($senha['categoria'])) ?></td>
                        <td><?= e($senha['usuario']) ?: '-' ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2 senha-campo">
                                <span class="senha-mono senha-valor" data-senha="<?= e($senhaTexto) ?>" data-revelado="0">••••••••••</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1 js-toggle-senha" title="Mostrar"><i class="bi bi-eye"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1 js-copiar-senha" title="Copiar"><i class="bi bi-clipboard"></i></button>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($senha['observacoes'])): ?>
                                <div class="small text-muted text-truncate" style="max-width: 260px;" title="<?= e($senha['observacoes']) ?>"><?= e($senha['observacoes']) ?></div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($podeEditarEsta): ?>
                                <a href="form.php?id=<?= (int) $senha['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                            <?php if ($temAcessoTotal && $podeAlterar): ?>
                                <a href="compartilhar.php?id=<?= (int) $senha['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Compartilhar"><i class="bi bi-share"></i></a>
                                <a href="delete.php?id=<?= (int) $senha['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                                   data-confirm-msg="Excluir a senha &quot;<?= e($senha['nome']) ?>&quot;? Esta ação não pode ser desfeita." title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($temAcessoTotal): ?>
    <p class="text-muted small mt-2"><i class="bi bi-info-circle"></i> Além de quem tem a permissão "Senhas" em Perfis de Acesso, uma senha específica pode ser compartilhada individualmente com outra pessoa pelo botão <i class="bi bi-share"></i>.</p>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
