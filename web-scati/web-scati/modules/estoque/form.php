<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$item = ['nome' => '', 'categoria_id' => '', 'marca' => '', 'modelo' => '', 'quantidade' => 0, 'quantidade_minima' => 0, 'localizacao' => '', 'observacoes' => ''];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM estoque WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Item de estoque não encontrado.');
        redirect('/modules/estoque/index.php');
    }
    $item = array_merge($item, $registro);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item['nome'] = trim($_POST['nome'] ?? '');
    $item['categoria_id'] = $_POST['categoria_id'] ?? '';
    $item['marca'] = trim($_POST['marca'] ?? '');
    $item['modelo'] = trim($_POST['modelo'] ?? '');
    $item['quantidade'] = $_POST['quantidade'] ?? '0';
    $item['quantidade_minima'] = $_POST['quantidade_minima'] ?? '0';
    $item['localizacao'] = trim($_POST['localizacao'] ?? '');
    $item['observacoes'] = trim($_POST['observacoes'] ?? '');

    if ($item['nome'] === '') {
        $erros[] = 'O campo Nome é obrigatório.';
    }
    if ($item['categoria_id'] === '') {
        $erros[] = 'Selecione uma categoria.';
    }
    if (!is_numeric($item['quantidade']) || (int) $item['quantidade'] < 0) {
        $erros[] = 'A quantidade não pode ser negativa.';
    }
    if (!is_numeric($item['quantidade_minima']) || (int) $item['quantidade_minima'] < 0) {
        $erros[] = 'A quantidade mínima não pode ser negativa.';
    }

    if (empty($erros)) {
        $dados = [
            'nome' => $item['nome'],
            'categoria_id' => (int) $item['categoria_id'],
            'marca' => $item['marca'] ?: null,
            'modelo' => $item['modelo'] ?: null,
            'quantidade' => (int) $item['quantidade'],
            'quantidade_minima' => (int) $item['quantidade_minima'],
            'localizacao' => $item['localizacao'] ?: null,
            'observacoes' => $item['observacoes'] ?: null,
        ];

        if ($edicao) {
            $dados['id'] = $id;
            $pdo->prepare(
                'UPDATE estoque SET nome=:nome, categoria_id=:categoria_id, marca=:marca, modelo=:modelo,
                 quantidade=:quantidade, quantidade_minima=:quantidade_minima, localizacao=:localizacao,
                 observacoes=:observacoes WHERE id=:id'
            )->execute($dados);

            // Se a quantidade mudou (ex.: reposição de estoque), registra no histórico do item.
            if ((int) $registro['quantidade'] !== $dados['quantidade']) {
                $stmtCat = $pdo->prepare('SELECT nome FROM categorias_estoque WHERE id = :id');
                $stmtCat->execute(['id' => $dados['categoria_id']]);
                $categoriaNome = $stmtCat->fetchColumn() ?: null;

                $diferenca = $dados['quantidade'] - (int) $registro['quantidade'];
                $sinal = $diferenca > 0 ? '+' . $diferenca : (string) $diferenca;
                registrarHistoricoEstoque(
                    $id,
                    $dados['nome'],
                    $categoriaNome,
                    'Alteração',
                    'Quantidade alterada de ' . (int) $registro['quantidade'] . ' para ' . $dados['quantidade'] . ' (' . $sinal . ')'
                );
            }

            flash('success', 'Item de estoque atualizado com sucesso.');
        } else {
            // Verifica se já existe um item com o mesmo nome, marca e modelo (ignorando
            // maiúsculas/minúsculas e espaços nas pontas) — nesse caso, soma a quantidade
            // ao item existente em vez de criar um cadastro duplicado. Marca/modelo
            // diferentes geram registros separados, para manter cada modelo rastreável
            // individualmente (ex.: qual computador está com qual monitor).
            $stmtExistente = $pdo->prepare(
                "SELECT id, nome, categoria_id, quantidade FROM estoque
                 WHERE LOWER(TRIM(nome)) = LOWER(TRIM(:nome))
                   AND LOWER(TRIM(COALESCE(marca, ''))) = LOWER(TRIM(:marca))
                   AND LOWER(TRIM(COALESCE(modelo, ''))) = LOWER(TRIM(:modelo))
                 LIMIT 1"
            );
            $stmtExistente->execute(['nome' => $dados['nome'], 'marca' => $dados['marca'] ?? '', 'modelo' => $dados['modelo'] ?? '']);
            $itemExistente = $stmtExistente->fetch();

            if ($itemExistente) {
                $pdo->prepare('UPDATE estoque SET quantidade = quantidade + :quantidade WHERE id = :id')
                    ->execute(['quantidade' => $dados['quantidade'], 'id' => $itemExistente['id']]);

                $stmtCat = $pdo->prepare('SELECT nome FROM categorias_estoque WHERE id = :id');
                $stmtCat->execute(['id' => $itemExistente['categoria_id']]);
                $categoriaNome = $stmtCat->fetchColumn() ?: null;

                registrarHistoricoEstoque(
                    (int) $itemExistente['id'],
                    $itemExistente['nome'],
                    $categoriaNome,
                    'Alteração',
                    'Quantidade alterada de ' . (int) $itemExistente['quantidade'] . ' para '
                        . ((int) $itemExistente['quantidade'] + $dados['quantidade']) . ' (+' . $dados['quantidade'] . ', item já existente)'
                );

                flash('success', 'Já existia um item de estoque chamado "' . $itemExistente['nome'] . '" — a quantidade informada foi somada a ele em vez de criar um cadastro duplicado.');
            } else {
                $pdo->prepare(
                    'INSERT INTO estoque (nome, categoria_id, marca, modelo, quantidade, quantidade_minima, localizacao, observacoes)
                     VALUES (:nome, :categoria_id, :marca, :modelo, :quantidade, :quantidade_minima, :localizacao, :observacoes)'
                )->execute($dados);
                $novoId = (int) $pdo->lastInsertId();

                $stmtCat = $pdo->prepare('SELECT nome FROM categorias_estoque WHERE id = :id');
                $stmtCat->execute(['id' => $dados['categoria_id']]);
                $categoriaNome = $stmtCat->fetchColumn() ?: null;

                registrarHistoricoEstoque($novoId, $dados['nome'], $categoriaNome, 'Cadastro', 'Item cadastrado no estoque');

                flash('success', 'Item de estoque cadastrado com sucesso.');
            }
        }
        redirect('/modules/estoque/index.php');
    }
}

$categorias = $pdo->query('SELECT * FROM categorias_estoque ORDER BY nome')->fetchAll();
$pageTitle = $edicao ? 'Editar Item de Estoque' : 'Novo Item de Estoque';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-box-seam me-2"></i><?= $edicao ? 'Editar Item' : 'Novo Item' ?></h1>
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
                <label class="form-label">Nome *</label>
                <input type="text" name="nome" class="form-control" required value="<?= e($item['nome']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Categoria *</label>
                <select name="categoria_id" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= (string) $item['categoria_id'] === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Marca</label>
                <input type="text" name="marca" class="form-control" value="<?= e($item['marca']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Modelo</label>
                <input type="text" name="modelo" class="form-control" value="<?= e($item['modelo']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantidade *</label>
                <input type="number" min="0" name="quantidade" class="form-control" required value="<?= e((string) $item['quantidade']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantidade Mínima *</label>
                <input type="number" min="0" name="quantidade_minima" class="form-control" required value="<?= e((string) $item['quantidade_minima']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Localização</label>
                <input type="text" name="localizacao" class="form-control" value="<?= e($item['localizacao']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Observações</label>
                <input type="text" name="observacoes" class="form-control" value="<?= e($item['observacoes']) ?>">
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
