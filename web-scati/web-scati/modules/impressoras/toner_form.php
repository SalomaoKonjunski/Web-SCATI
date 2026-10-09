<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('impressoras', 'alterar');

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$toner = [
    'nome' => '', 'tipo' => 'Toner', 'marca' => '', 'modelo' => '',
    'quantidade' => '0', 'quantidade_minima' => '0', 'localizacao' => '', 'observacoes' => '',
];
$impressorasVinculadasIds = [];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM toners WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Toner/Tinta não encontrado.');
        redirect('/modules/impressoras/toners.php');
    }
    $toner = array_merge($toner, $registro);

    $stmtVinc = $pdo->prepare('SELECT equipamento_id FROM toner_impressoras WHERE toner_id = :id');
    $stmtVinc->execute(['id' => $id]);
    $impressorasVinculadasIds = array_map('intval', $stmtVinc->fetchAll(PDO::FETCH_COLUMN));
}

$impressoras = $pdo->query(
    "SELECT id, nome, patrimonio, localizacao FROM equipamentos WHERE tipo = 'Impressora' ORDER BY nome ASC"
)->fetchAll();

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toner['nome'] = trim($_POST['nome'] ?? '');
    $toner['tipo'] = ($_POST['tipo'] ?? '') === 'Tinta' ? 'Tinta' : 'Toner';
    $toner['marca'] = trim($_POST['marca'] ?? '');
    $toner['modelo'] = trim($_POST['modelo'] ?? '');
    $toner['quantidade_minima'] = $_POST['quantidade_minima'] ?? '0';
    $toner['localizacao'] = trim($_POST['localizacao'] ?? '');
    $toner['observacoes'] = trim($_POST['observacoes'] ?? '');
    $impressorasSelecionadas = array_map('intval', $_POST['impressoras'] ?? []);

    if ($toner['nome'] === '') {
        $erros[] = 'O campo Nome é obrigatório.';
    }
    if (!is_numeric($toner['quantidade_minima']) || (int) $toner['quantidade_minima'] < 0) {
        $erros[] = 'Quantidade mínima inválida.';
    }
    if (!$edicao) {
        $toner['quantidade'] = $_POST['quantidade'] ?? '0';
        if (!is_numeric($toner['quantidade']) || (int) $toner['quantidade'] < 0) {
            $erros[] = 'Quantidade em estoque inválida.';
        }
    }

    if (empty($erros)) {
        if ($edicao) {
            $pdo->prepare(
                'UPDATE toners SET nome = :nome, tipo = :tipo, marca = :marca, modelo = :modelo,
                 quantidade_minima = :quantidade_minima, localizacao = :localizacao, observacoes = :observacoes
                 WHERE id = :id'
            )->execute([
                'nome' => $toner['nome'],
                'tipo' => $toner['tipo'],
                'marca' => $toner['marca'] ?: null,
                'modelo' => $toner['modelo'] ?: null,
                'quantidade_minima' => (int) $toner['quantidade_minima'],
                'localizacao' => $toner['localizacao'] ?: null,
                'observacoes' => $toner['observacoes'] ?: null,
                'id' => $id,
            ]);
            $tonerId = $id;
        } else {
            $pdo->prepare(
                'INSERT INTO toners (nome, tipo, marca, modelo, quantidade, quantidade_minima, localizacao, observacoes)
                 VALUES (:nome, :tipo, :marca, :modelo, :quantidade, :quantidade_minima, :localizacao, :observacoes)'
            )->execute([
                'nome' => $toner['nome'],
                'tipo' => $toner['tipo'],
                'marca' => $toner['marca'] ?: null,
                'modelo' => $toner['modelo'] ?: null,
                'quantidade' => (int) $toner['quantidade'],
                'quantidade_minima' => (int) $toner['quantidade_minima'],
                'localizacao' => $toner['localizacao'] ?: null,
                'observacoes' => $toner['observacoes'] ?: null,
            ]);
            $tonerId = (int) $pdo->lastInsertId();
            registrarMovimentacaoToner($tonerId, 'Cadastro', (int) $toner['quantidade']);
        }

        // Reconstrói os vínculos do zero a cada salvamento — mais simples e
        // seguro que sincronizar diferença por diferença (mesmo padrão já
        // usado em Perfis de Acesso).
        $pdo->prepare('DELETE FROM toner_impressoras WHERE toner_id = :id')->execute(['id' => $tonerId]);
        if (!empty($impressorasSelecionadas)) {
            $stmtVincInsert = $pdo->prepare('INSERT INTO toner_impressoras (toner_id, equipamento_id) VALUES (:toner_id, :equipamento_id)');
            foreach ($impressorasSelecionadas as $equipamentoId) {
                $stmtVincInsert->execute(['toner_id' => $tonerId, 'equipamento_id' => $equipamentoId]);
            }
        }

        flash('success', $edicao ? 'Toner/Tinta atualizado com sucesso.' : 'Toner/Tinta cadastrado com sucesso.');
        redirect('/modules/impressoras/toners.php');
    }
    $impressorasVinculadasIds = $impressorasSelecionadas;
}

$pageTitle = $edicao ? 'Editar Toner/Tinta' : 'Novo Toner/Tinta';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-droplet-half me-2"></i><?= e($pageTitle) ?></h1>
    <a href="toners.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
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
                <label class="form-label">Nome *</label>
                <input type="text" name="nome" class="form-control" required value="<?= e($toner['nome']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo *</label>
                <select name="tipo" class="form-select" required>
                    <option value="Toner" <?= $toner['tipo'] === 'Toner' ? 'selected' : '' ?>>Toner</option>
                    <option value="Tinta" <?= $toner['tipo'] === 'Tinta' ? 'selected' : '' ?>>Tinta</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantidade mínima</label>
                <input type="number" min="0" name="quantidade_minima" class="form-control" value="<?= e((string) $toner['quantidade_minima']) ?>">
                <div class="form-text">Pra destacar na listagem quando estiver baixo.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Marca</label>
                <input type="text" name="marca" class="form-control" value="<?= e($toner['marca']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Modelo</label>
                <input type="text" name="modelo" class="form-control" value="<?= e($toner['modelo']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Quantidade em estoque <?= $edicao ? '' : '*' ?></label>
                <?php if ($edicao): ?>
                    <input type="text" class="form-control" value="<?= (int) $toner['quantidade'] ?>" disabled>
                    <div class="form-text">
                        <a href="toner.php?id=<?= (int) $id ?>">Ajustar pela tela de quantidade <i class="bi bi-box-arrow-up-right"></i></a>
                    </div>
                <?php else: ?>
                    <input type="number" min="0" name="quantidade" class="form-control" required value="<?= e((string) $toner['quantidade']) ?>">
                    <div class="form-text">Só aqui na criação — depois, ajuste pela tela de quantidade.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-8">
                <label class="form-label">Localização</label>
                <input type="text" name="localizacao" class="form-control" placeholder="Ex: Armário de suprimentos, prateleira 2" value="<?= e($toner['localizacao']) ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label">Observações</label>
                <textarea name="observacoes" class="form-control" rows="2"><?= e($toner['observacoes']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white">
            <i class="bi bi-printer me-1"></i> Impressoras que usam este toner/tinta
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Marque todas as impressoras compatíveis com este modelo — pode marcar mais de uma. Isso é o que
                permite, por exemplo, dar baixa num toner e escolher pra qual delas ele foi instalado.
            </p>
            <?php if (empty($impressoras)): ?>
                <p class="text-muted small mb-0">Nenhuma impressora cadastrada ainda.</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($impressoras as $imp): ?>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="impressoras[]" value="<?= (int) $imp['id'] ?>"
                                       id="imp<?= (int) $imp['id'] ?>" <?= in_array((int) $imp['id'], $impressorasVinculadasIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="imp<?= (int) $imp['id'] ?>">
                                    <?= e(nomeEquipamento($imp['nome'], $imp['patrimonio'])) ?><?= $imp['localizacao'] ? ' — ' . e($imp['localizacao']) : '' ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="toners.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
