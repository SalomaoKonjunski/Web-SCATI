<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirAdmin();

$pdo = db();
$pageTitle = 'Perfis de Acesso';

$perfis = $pdo->query(
    'SELECT pa.*, COUNT(u.id) AS total_usuarios
     FROM perfis_acesso pa
     LEFT JOIN usuarios u ON u.perfil = pa.nome
     GROUP BY pa.id, pa.nome
     ORDER BY pa.protegido DESC, pa.nome ASC'
)->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-person-badge me-2"></i>Perfis de Acesso</h1>
        <a href="../configuracoes/index.php" class="small"><i class="bi bi-arrow-left"></i> Voltar para Configurações</a>
    </div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Perfil</a>
</div>

<p class="text-muted small">
    Cada perfil define quais abas aparecem no menu lateral e se o usuário pode só <strong>visualizar</strong>
    ou também <strong>alterar</strong> (criar, editar, excluir) os dados de cada uma. O perfil
    <strong>Administrador</strong> é fixo, com acesso completo sempre — garante que o sistema nunca
    fique sem ninguém capaz de gerenciar os outros perfis e usuários.
</p>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th class="text-center">Usuários</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($perfis as $perfil): ?>
                    <tr>
                        <td>
                            <strong><?= e($perfil['nome']) ?></strong>
                            <?php if ($perfil['protegido']): ?>
                                <i class="bi bi-lock-fill text-muted ms-1" title="Perfil fixo do sistema — sempre com acesso completo, não pode ser renomeado, alterado nem excluído."></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><span class="badge bg-secondary"><?= (int) $perfil['total_usuarios'] ?></span></td>
                        <td class="text-end">
                            <?php if ($perfil['protegido']): ?>
                                <a href="form.php?id=<?= (int) $perfil['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> Ver permissões</a>
                            <?php else: ?>
                                <a href="form.php?id=<?= (int) $perfil['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <a href="delete.php?id=<?= (int) $perfil['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                                   data-confirm-msg="Excluir o perfil &quot;<?= e($perfil['nome']) ?>&quot;?"><i class="bi bi-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
