<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirAdmin();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;
$usuarioAtual = usuarioLogado();

$item = [
    'nome' => '', 'categoria' => 'Rede', 'usuario' => '', 'senha' => '', 'observacoes' => '',
];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM senhas WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Senha não encontrada.');
        redirect('/modules/senhas/index.php');
    }
    $item = array_merge($item, $registro);
    $item['senha'] = descriptografar($registro['senha_cifrada']) ?? '';
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($item as $campo => $valorPadrao) {
        $item[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    if ($item['nome'] === '') {
        $erros[] = 'O campo Nome / Serviço é obrigatório.';
    }
    if (!in_array($item['categoria'], categoriasSenha(), true)) {
        $erros[] = 'Categoria inválida.';
    }
    if ($item['senha'] === '') {
        $erros[] = 'O campo Senha é obrigatório.';
    }

    if (empty($erros)) {
        $dados = [
            'nome' => $item['nome'],
            'categoria' => $item['categoria'],
            'usuario' => $item['usuario'] ?: null,
            'senha_cifrada' => criptografar($item['senha']),
            'observacoes' => $item['observacoes'] ?: null,
        ];

        if ($edicao) {
            $dados['atualizado_por'] = $usuarioAtual['usuario'] ?? null;
            $dados['id'] = $id;
            $pdo->prepare(
                'UPDATE senhas SET nome = :nome, categoria = :categoria, usuario = :usuario,
                        senha_cifrada = :senha_cifrada, observacoes = :observacoes, atualizado_por = :atualizado_por
                 WHERE id = :id'
            )->execute($dados);
            flash('success', 'Senha atualizada com sucesso.');
        } else {
            $dados['criado_por'] = $usuarioAtual['usuario'] ?? null;
            $pdo->prepare(
                'INSERT INTO senhas (nome, categoria, usuario, senha_cifrada, observacoes, criado_por)
                 VALUES (:nome, :categoria, :usuario, :senha_cifrada, :observacoes, :criado_por)'
            )->execute($dados);
            flash('success', 'Senha cadastrada com sucesso.');
        }
        redirect('/modules/senhas/index.php');
    }
}

$pageTitle = $edicao ? 'Editar Senha' : 'Nova Senha';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-shield-lock me-2"></i><?= $edicao ? 'Editar Senha' : 'Nova Senha' ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($edicao): ?>
    <p class="text-muted small mb-3">
        Cadastrada por <?= e($item['criado_por'] ?? '-') ?> em <?= formatDateTime($item['criado_em']) ?><?php if (!empty($item['atualizado_por'])): ?>
        · última alteração por <?= e($item['atualizado_por']) ?> em <?= formatDateTime($item['atualizado_em']) ?><?php endif; ?>
    </p>
<?php endif; ?>

<form method="post">
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-8">
                <label class="form-label">Nome / Serviço *</label>
                <input type="text" name="nome" class="form-control" required autofocus placeholder="Ex: Roteador Sala de Servidores" value="<?= e($item['nome']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Categoria *</label>
                <select name="categoria" class="form-select" required>
                    <?php foreach (categoriasSenha() as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $item['categoria'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Usuário / Login</label>
                <input type="text" name="usuario" class="form-control" placeholder="Ex: admin" value="<?= e($item['usuario']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Senha *</label>
                <div class="input-group senha-campo-form">
                    <input type="password" name="senha" class="form-control senha-mono js-senha-input" required value="<?= e($item['senha']) ?>">
                    <button class="btn btn-outline-secondary js-toggle-senha-form" type="button" title="Mostrar/ocultar"><i class="bi bi-eye"></i></button>
                    <button class="btn btn-outline-secondary js-copiar-senha-form" type="button" title="Copiar"><i class="bi bi-clipboard"></i></button>
                </div>
            </div>
            <div class="col-md-12">
                <label class="form-label">Observações</label>
                <textarea name="observacoes" class="form-control" rows="2" placeholder="Endereço/URL, anotações extras..."><?= e($item['observacoes']) ?></textarea>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
