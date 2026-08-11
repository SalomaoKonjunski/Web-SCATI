<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$rede = ['nome' => '', 'faixa_ip' => '', 'gateway' => '', 'observacoes' => ''];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM redes WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Rede não encontrada.');
        redirect('/modules/redes/index.php');
    }
    $rede = array_merge($rede, $registro);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rede['nome'] = trim($_POST['nome'] ?? '');
    $rede['faixa_ip'] = trim($_POST['faixa_ip'] ?? '');
    $rede['gateway'] = trim($_POST['gateway'] ?? '');
    $rede['observacoes'] = trim($_POST['observacoes'] ?? '');

    if ($rede['nome'] === '') {
        $erros[] = 'O campo Nome da Rede é obrigatório.';
    }

    if (empty($erros)) {
        $dados = [
            'nome' => $rede['nome'],
            'faixa_ip' => $rede['faixa_ip'] ?: null,
            'gateway' => $rede['gateway'] ?: null,
            'observacoes' => $rede['observacoes'] ?: null,
        ];

        if ($edicao) {
            $dados['id'] = $id;
            $pdo->prepare('UPDATE redes SET nome=:nome, faixa_ip=:faixa_ip, gateway=:gateway, observacoes=:observacoes WHERE id=:id')->execute($dados);
            flash('success', 'Rede atualizada com sucesso.');
        } else {
            $pdo->prepare('INSERT INTO redes (nome, faixa_ip, gateway, observacoes) VALUES (:nome, :faixa_ip, :gateway, :observacoes)')->execute($dados);
            flash('success', 'Rede cadastrada com sucesso.');
        }
        redirect('/modules/redes/index.php');
    }
}

$pageTitle = $edicao ? 'Editar Rede' : 'Nova Rede';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-diagram-3 me-2"></i><?= $edicao ? 'Editar Rede' : 'Nova Rede' ?></h1>
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
                <label class="form-label">Nome da Rede *</label>
                <input type="text" name="nome" class="form-control" required value="<?= e($rede['nome']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Faixa de IP</label>
                <input type="text" name="faixa_ip" class="form-control" placeholder="Ex: 192.168.1.0/24" value="<?= e($rede['faixa_ip']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Gateway</label>
                <input type="text" name="gateway" class="form-control" placeholder="Ex: 192.168.1.1" value="<?= e($rede['gateway']) ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label">Observações</label>
                <input type="text" name="observacoes" class="form-control" value="<?= e($rede['observacoes']) ?>">
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
