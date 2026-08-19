<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;
$usuarioAtual = usuarioLogado();

// O perfil Solicitante só pode registrar chamados novos; não pode editar
// nenhum chamado, nem os que ele mesmo abriu.
if ($edicao && $usuarioAtual['solicitante']) {
    flash('danger', 'Seu perfil não pode editar chamados.');
    redirect('/modules/chamados/index.php');
}

$chamado = [
    'titulo' => '', 'descricao' => '', 'solicitante' => '',
    'prioridade' => 'Média', 'status' => 'Aberto', 'responsavel_id' => '',
];

// Só o Administrador pode escolher quem é o solicitante ao abrir um chamado;
// para os demais perfis, o solicitante é sempre o próprio usuário logado.
$podeEscolherSolicitante = $usuarioAtual['admin'];
if (!$edicao && !$podeEscolherSolicitante) {
    $chamado['solicitante'] = $usuarioAtual['usuario'];
}

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM chamados WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Chamado não encontrado.');
        redirect('/modules/chamados/index.php');
    }
    $chamado = array_merge($chamado, $registro);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($chamado as $campo => $valorPadrao) {
        $chamado[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    if (!$edicao && !$podeEscolherSolicitante) {
        $chamado['solicitante'] = $usuarioAtual['usuario'];
    }

    if ($chamado['titulo'] === '') {
        $erros[] = 'O campo Título é obrigatório.';
    }
    if ($chamado['descricao'] === '') {
        $erros[] = 'O campo Descrição (a solicitação) é obrigatório.';
    }
    if (!in_array($chamado['prioridade'], prioridadesChamado(), true)) {
        $erros[] = 'Prioridade inválida.';
    }
    if (!in_array($chamado['status'], statusChamado(), true)) {
        $erros[] = 'Status inválido.';
    }

    if (empty($erros)) {
        // O Solicitante não escolhe andamento/responsável: todo chamado novo
        // criado por esse perfil nasce "Aberto" e sem responsável atribuído.
        $status = $usuarioAtual['solicitante'] ? 'Aberto' : $chamado['status'];
        $responsavelId = $usuarioAtual['solicitante']
            ? null
            : ($chamado['responsavel_id'] !== '' ? (int) $chamado['responsavel_id'] : null);
        $concluidoEm = ($edicao ? $registro['concluido_em'] : null);
        if ($status === 'Concluído' && $concluidoEm === null) {
            $concluidoEm = date('Y-m-d H:i:s');
        } elseif ($status !== 'Concluído') {
            $concluidoEm = null;
        }

        $dados = [
            'titulo' => $chamado['titulo'],
            'descricao' => $chamado['descricao'] ?: null,
            'solicitante' => $chamado['solicitante'] ?: null,
            'prioridade' => $chamado['prioridade'],
            'status' => $status,
            'responsavel_id' => $responsavelId,
            'concluido_em' => $concluidoEm,
        ];

        if ($edicao) {
            $dados['id'] = $id;
            $pdo->prepare(
                'UPDATE chamados SET titulo = :titulo, descricao = :descricao, solicitante = :solicitante,
                        prioridade = :prioridade, status = :status,
                        responsavel_id = :responsavel_id, concluido_em = :concluido_em
                 WHERE id = :id'
            )->execute($dados);
            flash('success', 'Chamado atualizado com sucesso.');
        } else {
            $dados['criado_por_id'] = $usuarioAtual['id'];
            $pdo->prepare(
                'INSERT INTO chamados (titulo, descricao, solicitante, criado_por_id, prioridade, status, responsavel_id, concluido_em)
                 VALUES (:titulo, :descricao, :solicitante, :criado_por_id, :prioridade, :status, :responsavel_id, :concluido_em)'
            )->execute($dados);
            flash('success', 'Chamado registrado com sucesso.');
        }
        redirect('/modules/chamados/index.php');
    }
}

$usuarios = $pdo->query('SELECT id, usuario FROM usuarios ORDER BY usuario')->fetchAll();

$pageTitle = $edicao ? 'Editar Chamado' : 'Novo Chamado';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-life-preserver me-2"></i><?= $edicao ? 'Editar Chamado' : 'Novo Chamado' ?></h1>
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
            <div class="col-md-8">
                <label class="form-label">Título *</label>
                <input type="text" name="titulo" class="form-control" required autofocus placeholder="Ex: Impressora da recepção não imprime" value="<?= e($chamado['titulo']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Usuário</label>
                <?php if (!$edicao && !$podeEscolherSolicitante): ?>
                    <input type="text" name="solicitante" class="form-control" value="<?= e($chamado['solicitante']) ?>" readonly>
                    <div class="form-text">Definido automaticamente como o seu usuário.</div>
                <?php else: ?>
                    <input type="text" name="solicitante" class="form-control" placeholder="Quem pediu / nome, setor..." value="<?= e($chamado['solicitante']) ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-12">
                <label class="form-label">Descrição (a solicitação) *</label>
                <textarea name="descricao" class="form-control" rows="3" required placeholder="Detalhes do problema ou solicitação"><?= e($chamado['descricao']) ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Prioridade *</label>
                <select name="prioridade" class="form-select" required>
                    <?php foreach (prioridadesChamado() as $prioridade): ?>
                        <option value="<?= e($prioridade) ?>" <?= $chamado['prioridade'] === $prioridade ? 'selected' : '' ?>><?= e($prioridade) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!$usuarioAtual['solicitante']): ?>
            <div class="col-md-4">
                <label class="form-label">Andamento</label>
                <select name="status" class="form-select">
                    <?php foreach (statusChamado() as $status): ?>
                        <option value="<?= e($status) ?>" <?= $chamado['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Responsável</label>
                <div class="d-flex gap-2">
                    <select name="responsavel_id" id="responsavel_id" class="form-select">
                        <option value="">Não atribuído</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (string) $chamado['responsavel_id'] === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['usuario']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($usuarioAtual['admin']): ?>
                    <button type="button" class="btn btn-outline-secondary text-nowrap" onclick="document.getElementById('responsavel_id').value = '<?= (int) $usuarioAtual['id'] ?>';">
                        <i class="bi bi-person-plus"></i> Atribuir para mim
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
