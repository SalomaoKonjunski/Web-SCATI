<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$pageTitle = 'Categorias de Equipamentos';

$categorias = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM equipamentos e WHERE e.tipo = c.nome) AS total_equipamentos
     FROM categorias_equipamento c
     ORDER BY c.nome ASC'
)->fetchAll();

$nomesProtegidos = tiposEquipamentoProtegidos();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-pc-display me-2"></i>Categorias de Equipamentos</h1>
        <a href="../configuracoes/index.php" class="small"><i class="bi bi-arrow-left"></i> Voltar para Configurações</a>
    </div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Categoria</a>
</div>

<p class="text-muted small">Estes são os tipos disponíveis no campo "Tipo" do cadastro de Equipamentos.</p>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th class="text-center">Equipamentos</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categorias)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">Nenhuma categoria cadastrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($categorias as $cat): ?>
                    <?php $protegida = in_array($cat['nome'], $nomesProtegidos, true); ?>
                    <tr>
                        <td>
                            <strong><?= e($cat['nome']) ?></strong>
                            <?php if ($protegida): ?>
                                <i class="bi bi-lock-fill text-muted ms-1" title="Usada por funcionalidades do sistema — não pode ser renomeada nem excluída."></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><span class="badge bg-secondary"><?= (int) $cat['total_equipamentos'] ?></span></td>
                        <td class="text-end">
                            <a href="form.php?id=<?= (int) $cat['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <?php if (!$protegida): ?>
                                <a href="delete.php?id=<?= (int) $cat['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                                   data-confirm-msg="Excluir a categoria &quot;<?= e($cat['nome']) ?>&quot;?"><i class="bi bi-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
