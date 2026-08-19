<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$compartilhamento = [
    'equipamento_id' => $_GET['equipamento_id'] ?? '',
    'nome' => '', 'caminho_pasta' => '', 'descricao' => '', 'permissoes' => '',
];
$computadoresSelecionados = [];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM compartilhamentos_servidor WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Compartilhamento não encontrado.');
        redirect('/modules/equipamentos/index.php');
    }
    $compartilhamento = array_merge($compartilhamento, $registro);

    $stmtVinc = $pdo->prepare('SELECT equipamento_id FROM compartilhamento_computadores WHERE compartilhamento_id = :id');
    $stmtVinc->execute(['id' => $id]);
    $computadoresSelecionados = array_map('intval', array_column($stmtVinc->fetchAll(), 'equipamento_id'));
}

$servidorId = (int) $compartilhamento['equipamento_id'];
$stmtServ = $pdo->prepare('SELECT id, patrimonio, tipo FROM equipamentos WHERE id = :id');
$stmtServ->execute(['id' => $servidorId]);
$servidor = $stmtServ->fetch();

if (!$servidor || !ehServidor($servidor['tipo'])) {
    flash('danger', 'Servidor inválido para cadastro de compartilhamento.');
    redirect('/modules/equipamentos/index.php');
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $compartilhamento['nome'] = trim($_POST['nome'] ?? '');
    $compartilhamento['caminho_pasta'] = trim($_POST['caminho_pasta'] ?? '');
    $compartilhamento['descricao'] = trim($_POST['descricao'] ?? '');
    $compartilhamento['permissoes'] = trim($_POST['permissoes'] ?? '');
    $computadoresSelecionados = array_map('intval', $_POST['computadores'] ?? []);

    if ($compartilhamento['nome'] === '') {
        $erros[] = 'O campo Nome do Compartilhamento é obrigatório.';
    }
    if ($compartilhamento['caminho_pasta'] === '') {
        $erros[] = 'O campo Caminho da Pasta é obrigatório.';
    }

    if (empty($erros)) {
        $dados = [
            'equipamento_id' => $servidorId,
            'nome' => $compartilhamento['nome'],
            'caminho_pasta' => $compartilhamento['caminho_pasta'],
            'descricao' => $compartilhamento['descricao'] ?: null,
            'permissoes' => $compartilhamento['permissoes'] ?: null,
        ];

        if ($edicao) {
            $dados['id'] = $id;
            $pdo->prepare(
                'UPDATE compartilhamentos_servidor SET equipamento_id = :equipamento_id, nome = :nome,
                 caminho_pasta = :caminho_pasta, descricao = :descricao, permissoes = :permissoes WHERE id = :id'
            )->execute($dados);
            $compartilhamentoId = $id;
            flash('success', 'Compartilhamento atualizado com sucesso.');
        } else {
            $pdo->prepare(
                'INSERT INTO compartilhamentos_servidor (equipamento_id, nome, caminho_pasta, descricao, permissoes)
                 VALUES (:equipamento_id, :nome, :caminho_pasta, :descricao, :permissoes)'
            )->execute($dados);
            $compartilhamentoId = (int) $pdo->lastInsertId();
            flash('success', 'Compartilhamento cadastrado com sucesso.');
        }

        // Substitui a lista de computadores vinculados pela seleção atual do formulário.
        $pdo->prepare('DELETE FROM compartilhamento_computadores WHERE compartilhamento_id = :id')
            ->execute(['id' => $compartilhamentoId]);
        if (!empty($computadoresSelecionados)) {
            $stmtLink = $pdo->prepare(
                'INSERT INTO compartilhamento_computadores (compartilhamento_id, equipamento_id) VALUES (:cid, :eid)'
            );
            foreach ($computadoresSelecionados as $compId) {
                $stmtLink->execute(['cid' => $compartilhamentoId, 'eid' => $compId]);
            }
        }

        registrarHistorico(
            $servidorId,
            'Compartilhamento',
            'Compartilhamento "' . $compartilhamento['nome'] . '" ' . ($edicao ? 'atualizado' : 'cadastrado')
        );

        redirect('/modules/equipamentos/view.php?id=' . $servidorId . '#compartilhamentos');
    }
}

$computadores = $pdo->query(
    "SELECT id, patrimonio, hostname FROM equipamentos WHERE tipo IN ('Computador','Notebook') ORDER BY patrimonio"
)->fetchAll();

$pageTitle = $edicao ? 'Editar Compartilhamento' : 'Novo Compartilhamento';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-folder2-open me-2"></i><?= $edicao ? 'Editar Compartilhamento' : 'Novo Compartilhamento' ?>
    </h1>
    <a href="../equipamentos/view.php?id=<?= $servidorId ?>#compartilhamentos" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<p class="text-muted">Servidor: <strong><?= e(patrimonioOuIndefinido($servidor['patrimonio'])) ?></strong></p>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post">
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Nome do Compartilhamento *</label>
                <input type="text" name="nome" class="form-control" required value="<?= e($compartilhamento['nome']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Caminho da Pasta *</label>
                <input type="text" name="caminho_pasta" class="form-control" placeholder="Ex: \\servidor\publico" required value="<?= e($compartilhamento['caminho_pasta']) ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label">Descrição</label>
                <textarea name="descricao" class="form-control" rows="2"><?= e($compartilhamento['descricao']) ?></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label">Permissões (opcional)</label>
                <input type="text" name="permissoes" class="form-control" placeholder="Ex: Leitura/Gravação - Grupo TI" value="<?= e($compartilhamento['permissoes']) ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label">Computadores Vinculados</label>
                <?php if (empty($computadores)): ?>
                    <p class="text-muted small">Nenhum computador cadastrado no sistema.</p>
                <?php else: ?>
                    <div class="row g-2 border rounded p-2" style="max-height: 260px; overflow-y: auto;">
                        <?php foreach ($computadores as $comp): ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="computadores[]" value="<?= (int) $comp['id'] ?>"
                                           id="comp<?= (int) $comp['id'] ?>"
                                           <?= in_array((int) $comp['id'], $computadoresSelecionados, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="comp<?= (int) $comp['id'] ?>">
                                        <?= e(patrimonioOuIndefinido($comp['patrimonio'])) ?><?= $comp['hostname'] ? ' — ' . e($comp['hostname']) : '' ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="../equipamentos/view.php?id=<?= $servidorId ?>#compartilhamentos" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
