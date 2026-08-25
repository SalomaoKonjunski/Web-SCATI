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

$registroUsuario = ['usuario' => '', 'perfil' => 'Padrão', 'ramal' => '', 'telefone' => '', 'email_corporativo' => ''];
$senhaEmailAtual = '';

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Usuário não encontrado.');
        redirect('/modules/usuarios/index.php');
    }
    $registroUsuario = array_merge($registroUsuario, $registro);
    $senhaEmailAtual = descriptografar($registro['senha_email_cifrada'] ?? null) ?? '';
}

$totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'Administrador'")->fetchColumn();

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registroUsuario['usuario'] = trim($_POST['usuario'] ?? '');
    $registroUsuario['ramal'] = trim($_POST['ramal'] ?? '');
    $registroUsuario['telefone'] = trim($_POST['telefone'] ?? '');
    $registroUsuario['email_corporativo'] = trim($_POST['email_corporativo'] ?? '');
    $senhaEmailAtual = (string) ($_POST['senha_email_corporativo'] ?? '');
    $senha = (string) ($_POST['senha'] ?? '');
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
    if ($registroUsuario['email_corporativo'] !== '' && !filter_var($registroUsuario['email_corporativo'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um Email Corporativo válido.';
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
        $ramal = $registroUsuario['ramal'] ?: null;
        $telefone = $registroUsuario['telefone'] ?: null;
        $emailCorporativo = $registroUsuario['email_corporativo'] ?: null;
        $senhaEmailCifrada = $senhaEmailAtual !== '' ? criptografar($senhaEmailAtual) : null;

        if ($edicao) {
            if ($senha !== '') {
                $pdo->prepare(
                    'UPDATE usuarios SET usuario = :usuario, senha_hash = :senha_hash, perfil = :perfil, ramal = :ramal,
                        telefone = :telefone, email_corporativo = :email_corporativo, senha_email_cifrada = :senha_email_cifrada
                     WHERE id = :id'
                )->execute([
                    'usuario' => $registroUsuario['usuario'],
                    'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                    'perfil' => $perfilSubmetido,
                    'ramal' => $ramal,
                    'telefone' => $telefone,
                    'email_corporativo' => $emailCorporativo,
                    'senha_email_cifrada' => $senhaEmailCifrada,
                    'id' => $id,
                ]);
            } else {
                $pdo->prepare(
                    'UPDATE usuarios SET usuario = :usuario, perfil = :perfil, ramal = :ramal,
                        telefone = :telefone, email_corporativo = :email_corporativo, senha_email_cifrada = :senha_email_cifrada
                     WHERE id = :id'
                )->execute([
                    'usuario' => $registroUsuario['usuario'],
                    'perfil' => $perfilSubmetido,
                    'ramal' => $ramal,
                    'telefone' => $telefone,
                    'email_corporativo' => $emailCorporativo,
                    'senha_email_cifrada' => $senhaEmailCifrada,
                    'id' => $id,
                ]);
            }
            // Se o próprio usuário logado for editado, mantém o nome exibido em sessão atualizado.
            if ((int) $id === $usuarioAtualId) {
                $_SESSION['usuario_nome'] = $registroUsuario['usuario'];
                $_SESSION['usuario_perfil'] = $perfilSubmetido;
            }
            flash('success', 'Usuário atualizado com sucesso.');
        } else {
            $pdo->prepare(
                'INSERT INTO usuarios (usuario, senha_hash, perfil, ramal, telefone, email_corporativo, senha_email_cifrada)
                 VALUES (:usuario, :senha_hash, :perfil, :ramal, :telefone, :email_corporativo, :senha_email_cifrada)'
            )->execute([
                'usuario' => $registroUsuario['usuario'],
                'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                'perfil' => $perfilSubmetido,
                'ramal' => $ramal,
                'telefone' => $telefone,
                'email_corporativo' => $emailCorporativo,
                'senha_email_cifrada' => $senhaEmailCifrada,
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
                    <strong>Usuário</strong>: só acessa a aba de Chamados, para registrar e acompanhar os próprios chamados.
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Senha <?= $edicao ? '' : '*' ?></label>
                <div class="input-group">
                    <input type="password" name="senha" class="form-control senha-toggle-input" <?= $edicao ? '' : 'required' ?>>
                    <button type="button" class="btn btn-outline-secondary senha-toggle-btn" tabindex="-1" title="Mostrar/ocultar senha"><i class="bi bi-eye"></i></button>
                </div>
                <?php if ($edicao): ?>
                    <div class="form-text">Deixe em branco para manter a senha atual.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label">Ramal</label>
                <input type="text" name="ramal" class="form-control" placeholder="Ex: 1234" value="<?= e($registroUsuario['ramal']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control" placeholder="Ex: (11) 98888-7777" value="<?= e($registroUsuario['telefone']) ?>">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white">
            <i class="bi bi-envelope-at me-1"></i> Email Corporativo
        </div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Email Corporativo</label>
                <input type="email" name="email_corporativo" class="form-control" value="<?= e($registroUsuario['email_corporativo']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Senha do Email Corporativo</label>
                <div class="input-group">
                    <input type="password" name="senha_email_corporativo" class="form-control senha-toggle-input" value="<?= e($senhaEmailAtual) ?>">
                    <button type="button" class="btn btn-outline-secondary senha-toggle-btn" tabindex="-1" title="Mostrar/ocultar senha"><i class="bi bi-eye"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
