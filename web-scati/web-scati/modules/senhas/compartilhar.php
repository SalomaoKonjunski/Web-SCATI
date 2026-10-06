<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('senhas', 'alterar');

$pdo = db();
$usuarioAtual = usuarioLogado();
$senhaId = (int) ($_GET['id'] ?? $_POST['senha_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM senhas WHERE id = :id');
$stmt->execute(['id' => $senhaId]);
$senha = $stmt->fetch();

if (!$senha) {
    flash('danger', 'Senha não encontrada.');
    redirect('/modules/senhas/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idsSelecionados = array_map('intval', $_POST['usuarios'] ?? []);
    $podeEditarPorId = $_POST['pode_editar'] ?? [];

    $pdo->prepare('DELETE FROM senha_compartilhamentos WHERE senha_id = :id')->execute(['id' => $senhaId]);

    if (!empty($idsSelecionados)) {
        $stmtInsert = $pdo->prepare(
            'INSERT INTO senha_compartilhamentos (senha_id, usuario_id, pode_editar) VALUES (:senha_id, :usuario_id, :pode_editar)'
        );
        foreach ($idsSelecionados as $uid) {
            $stmtInsert->execute([
                'senha_id' => $senhaId,
                'usuario_id' => $uid,
                'pode_editar' => isset($podeEditarPorId[$uid]) ? 1 : 0,
            ]);
        }
    }

    flash('success', 'Compartilhamento da senha "' . $senha['nome'] . '" atualizado com sucesso.');
    redirect('/modules/senhas/index.php');
}

$stmtAtuais = $pdo->prepare('SELECT usuario_id, pode_editar FROM senha_compartilhamentos WHERE senha_id = :id');
$stmtAtuais->execute(['id' => $senhaId]);
$compartilhamentosAtuais = array_column($stmtAtuais->fetchAll(), 'pode_editar', 'usuario_id');

$stmtUsuarios = $pdo->prepare('SELECT id, usuario FROM usuarios WHERE id != :id ORDER BY usuario ASC');
$stmtUsuarios->execute(['id' => $usuarioAtual['id']]);
$usuarios = $stmtUsuarios->fetchAll();

$pageTitle = 'Compartilhar Senha';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-share me-2"></i>Compartilhar Senha</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<p class="text-muted">Senha: <strong><?= e($senha['nome']) ?></strong></p>

<form method="post">
    <input type="hidden" name="senha_id" value="<?= (int) $senhaId ?>">
    <div class="card mb-3">
        <div class="card-header bg-white"><strong>Quem pode acessar esta senha</strong></div>
        <div class="card-body">
            <p class="text-muted small">
                Além de quem já tem a permissão "Senhas" em Perfis de Acesso, as pessoas marcadas aqui também
                enxergam esta senha específica, mesmo sem essa permissão.
            </p>
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
                    Quem só visualiza vê o nome, usuário, senha e observações, mas não pode alterar nada.
                    Excluir a senha continua exclusivo de quem tem "Senhas: Alterar" de forma geral.
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
