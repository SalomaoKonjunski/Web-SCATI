<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('estoque', 'ver');

$pdo = db();
$pageTitle = 'Locais de Estoque';

$locais = $pdo->query(
    'SELECT lo.*, COUNT(es.id) AS total_itens
     FROM locais_estoque lo
     LEFT JOIN estoque es ON es.local_id = lo.id
     GROUP BY lo.id, lo.nome
     ORDER BY lo.nome ASC'
)->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-geo-alt me-2"></i>Locais de Estoque</h1>
        <a href="../configuracoes/index.php" class="small"><i class="bi bi-arrow-left"></i> Voltar para Configurações</a>
    </div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Local</a>
</div>

<p class="small text-muted">Usados no campo "Localização" do cadastro de Estoque (dropdown) e como filtro na listagem.</p>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th class="text-center">Itens de Estoque</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($locais)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">Nenhum local cadastrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($locais as $local): ?>
                    <tr>
                        <td><?= e($local['nome']) ?></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= (int) $local['total_itens'] ?></span></td>
                        <td class="text-end">
                            <a href="form.php?id=<?= (int) $local['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <a href="delete.php?id=<?= (int) $local['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir o local &quot;<?= e($local['nome']) ?>&quot;?<?= (int) $local['total_itens'] > 0 ? ' ' . (int) $local['total_itens'] . ' item(ns) de estoque com esse local ficarão sem localização definida.' : '' ?>">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
