<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('tarefas', 'ver');

$pdo = db();
$pageTitle = 'Central de Alertas';
$podeAlterar = temPermissao('tarefas', 'alterar');

$tarefas = $pdo->query(
    'SELECT t.*, u.usuario AS responsavel_nome
     FROM tarefas_periodicas t
     LEFT JOIN usuarios u ON u.id = t.responsavel_id
     ORDER BY t.ativo DESC, t.proxima_execucao ASC'
)->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-bell me-2"></i>Central de Alertas</h1>
        <span class="small text-muted">Avisos recorrentes (ex.: troca de toner, limpeza do cortador de papel) que aparecem no card "Central de Alertas" do Dashboard quando vencem.</span>
    </div>
    <?php if ($podeAlterar): ?>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Alerta</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Alerta</th>
                    <th>Frequência</th>
                    <th>Responsável</th>
                    <th>Próxima Execução</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tarefas)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhum alerta cadastrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($tarefas as $tarefa): ?>
                    <?php
                        $prazo = prazoTarefa($tarefa['proxima_execucao']);
                        $horasParaVencer = (int) floor((strtotime($tarefa['proxima_execucao']) - time()) / 3600);
                        $proximaDoAviso = $horasParaVencer <= (int) $tarefa['horas_aviso_antecedencia'];
                    ?>
                    <tr class="<?= !$tarefa['ativo'] ? 'text-muted' : '' ?>">
                        <td>
                            <strong><?= e($tarefa['titulo']) ?></strong>
                            <?php if ($tarefa['descricao']): ?>
                                <div class="small text-muted text-truncate" style="max-width: 320px;"><?= e($tarefa['descricao']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e(descricaoFrequenciaTarefa($tarefa['frequencia_tipo'], (int) $tarefa['frequencia_valor'])) ?></td>
                        <td><?= $tarefa['responsavel_nome'] ? e($tarefa['responsavel_nome']) : '<span class="text-muted">Qualquer um da equipe</span>' ?></td>
                        <td>
                            <?= formatDateTime($tarefa['proxima_execucao']) ?>
                            <?php if ($tarefa['ativo'] && $prazo['vencida']): ?>
                                <span class="badge bg-danger ms-1">Atrasada há <?= e($prazo['texto']) ?></span>
                            <?php elseif ($tarefa['ativo'] && $proximaDoAviso): ?>
                                <span class="badge bg-warning text-dark ms-1">Vence em <?= e($prazo['texto']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $tarefa['ativo']
                                ? '<span class="badge bg-success">Ativa</span>'
                                : '<span class="badge bg-secondary">Pausada</span>' ?>
                        </td>
                        <td class="text-end">
                            <?php if ($podeAlterar): ?>
                                <form method="post" action="concluir.php" class="d-inline">
                                    <input type="hidden" name="id" value="<?= (int) $tarefa['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Marcar como concluída hoje">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <a href="form.php?id=<?= (int) $tarefa['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                <a href="delete.php?id=<?= (int) $tarefa['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                                   data-confirm-msg="Excluir o alerta &quot;<?= e($tarefa['titulo']) ?>&quot;?"><i class="bi bi-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
