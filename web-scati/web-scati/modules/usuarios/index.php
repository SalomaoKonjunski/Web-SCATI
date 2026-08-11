<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirAdmin();

$pdo = db();
$pageTitle = 'Usuários';

$usuarios = $pdo->query('SELECT * FROM usuarios ORDER BY usuario ASC')->fetchAll();
$totalAdmins = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE admin = 1')->fetchColumn();
$usuarioAtualId = usuarioLogado()['id'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-people me-2"></i>Usuários</h1>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Usuário</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Usuário</th>
                    <th>Perfil</th>
                    <th>Criado em</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Nenhum usuário cadastrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td>
                            <strong><?= e($u['usuario']) ?></strong>
                            <?php if ((int) $u['id'] === $usuarioAtualId): ?>
                                <span class="badge bg-light text-dark border ms-1">Você</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['admin']): ?>
                                <span class="badge bg-primary">Administrador</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Usuário</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatDateTime($u['criado_em']) ?></td>
                        <td class="text-end">
                            <a href="form.php?id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <?php if ((int) $u['id'] === $usuarioAtualId): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Não é possível excluir o próprio usuário logado">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php elseif ($u['admin'] && $totalAdmins <= 1): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Precisa existir ao menos um administrador">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php else: ?>
                                <a href="delete.php?id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                                   data-confirm-msg="Excluir o usuário &quot;<?= e($u['usuario']) ?>&quot;? Esta ação não pode ser desfeita.">
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
