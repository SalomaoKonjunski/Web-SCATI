<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$pageTitle = 'Categorias de Estoque';

$categorias = $pdo->query(
    'SELECT c.*, COUNT(es.id) AS total_itens
     FROM categorias_estoque c
     LEFT JOIN estoque es ON es.categoria_id = c.id
     GROUP BY c.id, c.nome
     ORDER BY c.nome ASC'
)->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-tags me-2"></i>Categorias de Estoque</h1>
        <a href="../configuracoes/index.php" class="small"><i class="bi bi-arrow-left"></i> Voltar para Configurações</a>
    </div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Categoria</a>
</div>

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
                <?php if (empty($categorias)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">Nenhuma categoria cadastrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($categorias as $cat): ?>
                    <tr>
                        <td><strong><?= e($cat['nome']) ?></strong></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= (int) $cat['total_itens'] ?></span></td>
                        <td class="text-end">
                            <a href="form.php?id=<?= (int) $cat['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <a href="delete.php?id=<?= (int) $cat['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir a categoria &quot;<?= e($cat['nome']) ?>&quot;?"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
