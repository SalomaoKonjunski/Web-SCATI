<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('notas', 'alterar');

$pdo = db();
$usuarioId = usuarioLogado()['id'];
$notaId = (int) ($_GET['id'] ?? $_POST['nota_id'] ?? 0);

// Compartilhamento é configuração do dono da nota — só ele pode decidir
// quem mais tem acesso a ela, mesmo que outra pessoa tenha permissão
// "Alterar" em Notas de forma geral (isso só vale pra notas da própria).
$stmt = $pdo->prepare('SELECT * FROM notas WHERE id = :id AND usuario_id = :usuario_id');
$stmt->execute(['id' => $notaId, 'usuario_id' => $usuarioId]);
$nota = $stmt->fetch();

if (!$nota) {
    flash('danger', 'Nota não encontrada.');
    redirect('/modules/notas/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idsSelecionados = array_map('intval', $_POST['usuarios'] ?? []);
    $podeEditarPorId = $_POST['pode_editar'] ?? [];

    $pdo->prepare('DELETE FROM nota_compartilhamentos WHERE nota_id = :id')->execute(['id' => $notaId]);

    if (!empty($idsSelecionados)) {
        $stmtInsert = $pdo->prepare(
            'INSERT INTO nota_compartilhamentos (nota_id, usuario_id, pode_editar) VALUES (:nota_id, :usuario_id, :pode_editar)'
        );
        foreach ($idsSelecionados as $uid) {
            if ($uid === (int) $usuarioId) {
                continue; // não dá pra compartilhar consigo mesmo (já é o dono)
            }
            $stmtInsert->execute([
                'nota_id' => $notaId,
                'usuario_id' => $uid,
                'pode_editar' => isset($podeEditarPorId[$uid]) ? 1 : 0,
            ]);
        }
    }

    flash('success', 'Compartilhamento da nota "' . $nota['titulo'] . '" atualizado com sucesso.');
    redirect('/modules/notas/index.php');
}

$stmtAtuais = $pdo->prepare('SELECT usuario_id, pode_editar FROM nota_compartilhamentos WHERE nota_id = :id');
$stmtAtuais->execute(['id' => $notaId]);
$compartilhamentosAtuais = array_column($stmtAtuais->fetchAll(), 'pode_editar', 'usuario_id');

$stmtUsuarios = $pdo->prepare('SELECT id, usuario FROM usuarios WHERE id != :id ORDER BY usuario ASC');
$stmtUsuarios->execute(['id' => $usuarioId]);
$usuarios = $stmtUsuarios->fetchAll();

$pageTitle = 'Compartilhar Nota';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-share me-2"></i>Compartilhar Nota</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<p class="text-muted">Nota: <strong><?= e($nota['titulo']) ?></strong></p>

<form method="post">
    <input type="hidden" name="nota_id" value="<?= (int) $notaId ?>">
    <div class="card mb-3">
        <div class="card-header bg-white"><strong>Quem pode ver esta nota</strong></div>
        <div class="card-body">
            <?php if (empty($usuarios)): ?>
                <p class="text-muted mb-0">Não há outras pessoas cadastradas no sistema.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pessoa</th>
                                <th class="text-center">Compartilhar</th>
                                <th class="text-center">Pode editar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usr): ?>
                                <?php $compartilhado = array_key_exists((int) $usr['id'], $compartilhamentosAtuais); ?>
                                <tr>
                                    <td><?= e($usr['usuario']) ?></td>
                                    <td class="text-center">
                                        <input class="form-check-input" type="checkbox" name="usuarios[]"
                                               value="<?= (int) $usr['id'] ?>" <?= $compartilhado ? 'checked' : '' ?>>
                                    </td>
                                    <td class="text-center">
                                        <input class="form-check-input" type="checkbox" name="pode_editar[<?= (int) $usr['id'] ?>]" value="1"
                                               <?= ($compartilhado && (int) $compartilhamentosAtuais[(int) $usr['id']] === 1) ? 'checked' : '' ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Quem só visualiza vê o conteúdo e os anexos, mas não pode alterar nada. Quem pode editar também
                    pode mudar o conteúdo e gerenciar anexos — só você, o dono, pode excluir a nota ou reconfigurar quem tem acesso a ela.
                </p>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
