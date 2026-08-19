<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirAdmin();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;
$usuarioAtualId = usuarioLogado()['id'];

$registroUsuario = ['usuario' => '', 'perfil' => 'Padrão'];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Usuário não encontrado.');
        redirect('/modules/usuarios/index.php');
    }
    $registroUsuario = array_merge($registroUsuario, $registro);
}

$totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'Administrador'")->fetchColumn();

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registroUsuario['usuario'] = trim($_POST['usuario'] ?? '');
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');
    $perfilSubmetido = trim($_POST['perfil'] ?? 'Padrão');

    if ($registroUsuario['usuario'] === '') {
        $erros[] = 'O campo Usuário é obrigatório.';
    }
    if (!in_array($perfilSubmetido, perfisUsuario(), true)) {
        $erros[] = 'Perfil de acesso inválido.';
    }
    if (!$edicao && $senha === '') {
        $erros[] = 'A senha é obrigatória para um novo usuário.';
    }
    if ($senha !== '' && strlen($senha) < 6) {
        $erros[] = 'A senha deve ter pelo menos 6 caracteres.';
    }
    if ($senha !== '' && $senha !== $confirmarSenha) {
        $erros[] = 'A confirmação de senha não confere.';
    }
    // Precisa sobrar sempre pelo menos 1 administrador no sistema.
    if ($edicao && (int) $id === $usuarioAtualId && $registroUsuario['perfil'] === 'Administrador' && $perfilSubmetido !== 'Administrador' && $totalAdmins <= 1) {
        $erros[] = 'Não é possível remover o único administrador do sistema.';
    }

    $registroUsuario['perfil'] = $perfilSubmetido;

    if (empty($erros)) {
        $sqlCheck = 'SELECT id FROM usuarios WHERE usuario = :usuario' . ($edicao ? ' AND id != :id' : '');
        $stmtCheck = $pdo->prepare($sqlCheck);
        $paramsCheck = ['usuario' => $registroUsuario['usuario']];
        if ($edicao) {
            $paramsCheck['id'] = $id;
        }
        $stmtCheck->execute($paramsCheck);
        if ($stmtCheck->fetch()) {
            $erros[] = 'Já existe um usuário com este nome.';
        }
    }

    if (empty($erros)) {
        if ($edicao) {
            if ($senha !== '') {
                $pdo->prepare('UPDATE usuarios SET usuario = :usuario, senha_hash = :senha_hash, perfil = :perfil WHERE id = :id')
                    ->execute([
                        'usuario' => $registroUsuario['usuario'],
                        'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                        'perfil' => $perfilSubmetido,
                        'id' => $id,
                    ]);
            } else {
                $pdo->prepare('UPDATE usuarios SET usuario = :usuario, perfil = :perfil WHERE id = :id')
                    ->execute(['usuario' => $registroUsuario['usuario'], 'perfil' => $perfilSubmetido, 'id' => $id]);
            }
            // Se o próprio usuário logado for editado, mantém o nome exibido em sessão atualizado.
            if ((int) $id === $usuarioAtualId) {
                $_SESSION['usuario_nome'] = $registroUsuario['usuario'];
                $_SESSION['usuario_perfil'] = $perfilSubmetido;
            }
            flash('success', 'Usuário atualizado com sucesso.');
        } else {
            $pdo->prepare('INSERT INTO usuarios (usuario, senha_hash, perfil) VALUES (:usuario, :senha_hash, :perfil)')
                ->execute([
                    'usuario' => $registroUsuario['usuario'],
                    'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                    'perfil' => $perfilSubmetido,
                ]);
            flash('success', 'Usuário cadastrado com sucesso.');
        }
        redirect('/modules/usuarios/index.php');
    }
}

$pageTitle = $edicao ? 'Editar Usuário' : 'Novo Usuário';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-people me-2"></i><?= $edicao ? 'Editar Usuário' : 'Novo Usuário' ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post">
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Usuário *</label>
                <input type="text" name="usuario" class="form-control" required autofocus value="<?= e($registroUsuario['usuario']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Perfil de acesso *</label>
                <select name="perfil" class="form-select">
                    <?php foreach (perfisUsuario() as $perfil): ?>
                        <option value="<?= e($perfil) ?>" <?= $registroUsuario['perfil'] === $perfil ? 'selected' : '' ?>><?= e($perfil) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    <strong>Administrador</strong>: acesso completo, inclusive gerenciar usuários.<br>
                    <strong>Padrão</strong>: acesso completo ao sistema, exceto gerenciar usuários.<br>
                    <strong>Solicitante</strong>: só acessa a aba de Chamados, para registrar e acompanhar os próprios chamados.
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Senha <?= $edicao ? '' : '*' ?></label>
                <input type="password" name="senha" class="form-control" <?= $edicao ? '' : 'required' ?>>
                <?php if ($edicao): ?>
                    <div class="form-text">Deixe em branco para manter a senha atual.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Confirmar Senha <?= $edicao ? '' : '*' ?></label>
                <input type="password" name="confirmar_senha" class="form-control" <?= $edicao ? '' : 'required' ?>>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
