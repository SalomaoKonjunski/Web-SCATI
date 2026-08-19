<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$pageTitle = 'Tipos de Manutenção';

$tipos = $pdo->query(
    'SELECT t.*,
        (SELECT COUNT(*) FROM historico_equipamentos h WHERE h.evento = t.nome) AS total_usos
     FROM tipos_manutencao t
     ORDER BY t.nome ASC'
)->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-tools me-2"></i>Tipos de Manutenção</h1>
        <a href="../configuracoes/index.php" class="small"><i class="bi bi-arrow-left"></i> Voltar para Configurações</a>
    </div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Tipo</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th class="text-center">Usado no Histórico</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tipos)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">Nenhum tipo de manutenção cadastrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($tipos as $tipo): ?>
                    <tr>
                        <td><strong><?= e($tipo['nome']) ?></strong></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= (int) $tipo['total_usos'] ?></span></td>
                        <td class="text-end">
                            <a href="form.php?id=<?= (int) $tipo['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <a href="delete.php?id=<?= (int) $tipo['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir o tipo de manutenção &quot;<?= e($tipo['nome']) ?>&quot;? Registros já existentes no histórico não são afetados.">
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
