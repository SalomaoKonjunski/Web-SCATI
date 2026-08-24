<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$grupos = gruposCamposEstoque();

$item = ['nome' => '', 'categoria_id' => '', 'marca' => '', 'modelo' => '', 'quantidade' => 0, 'quantidade_minima' => 0, 'localizacao' => '', 'observacoes' => ''];
foreach ($grupos as $grupo) {
    foreach ($grupo['campos'] as $nomeCampo => $config) {
        $item[$nomeCampo] = $config['tipo'] === 'checkbox' ? 0 : '';
    }
}

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

    // Só grava os campos extras dos grupos habilitados na categoria
    // selecionada — valida contra o banco, não confia em campos escondidos
    // via inspecionar elemento para uma categoria sem aquele grupo.
    $flagsCategoria = [];
    $categoriaSelecionadaId = (int) ($item['categoria_id'] ?: 0);
    if ($categoriaSelecionadaId > 0) {
        $stmtCatFlags = $pdo->prepare('SELECT * FROM categorias_estoque WHERE id = :id');
        $stmtCatFlags->execute(['id' => $categoriaSelecionadaId]);
        $catFlagsRow = $stmtCatFlags->fetch();
        if ($catFlagsRow) {
            foreach ($grupos as $grupo) {
                $flagsCategoria[$grupo['coluna']] = (bool) $catFlagsRow[$grupo['coluna']];
            }
        }
    }

    $camposExtras = [];
    foreach ($grupos as $grupo) {
        $habilitado = $flagsCategoria[$grupo['coluna']] ?? false;
        foreach ($grupo['campos'] as $nomeCampo => $config) {
            if (!$habilitado) {
                $item[$nomeCampo] = $config['tipo'] === 'checkbox' ? 0 : '';
                $camposExtras[$nomeCampo] = $config['tipo'] === 'checkbox' ? 0 : null;
                continue;
            }
            if ($config['tipo'] === 'checkbox') {
                $item[$nomeCampo] = isset($_POST[$nomeCampo]) ? 1 : 0;
                $camposExtras[$nomeCampo] = $item[$nomeCampo];
                continue;
            }
            $valorBruto = trim((string) ($_POST[$nomeCampo] ?? ''));
            $item[$nomeCampo] = $valorBruto;
            $camposExtras[$nomeCampo] = match (true) {
                $valorBruto === ''            => null,
                $config['tipo'] === 'numero'   => (int) $valorBruto,
                $config['tipo'] === 'dinheiro' => (float) str_replace(',', '.', $valorBruto),
                $config['tipo'] === 'rede'     => (int) $valorBruto,
                default                        => $valorBruto,
            };
        }
    }

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
        $dados = array_merge([
            'nome' => $item['nome'],
            'categoria_id' => (int) $item['categoria_id'],
            'marca' => $item['marca'] ?: null,
            'modelo' => $item['modelo'] ?: null,
            'quantidade' => (int) $item['quantidade'],
            'quantidade_minima' => (int) $item['quantidade_minima'],
            'localizacao' => $item['localizacao'] ?: null,
            'observacoes' => $item['observacoes'] ?: null,
        ], $camposExtras);

        // Nomes das colunas extras vêm de gruposCamposEstoque() (fixo no código,
        // não de entrada do usuário), então montar a lista de colunas/placeholders
        // dinamicamente aqui é seguro e evita repetir os mesmos 22 campos à mão.
        $colunasExtras = [];
        foreach ($grupos as $grupo) {
            $colunasExtras = array_merge($colunasExtras, array_keys($grupo['campos']));
        }

        if ($edicao) {
            $dados['id'] = $id;
            $setExtras = implode(', ', array_map(fn (string $c): string => "$c = :$c", $colunasExtras));
            $pdo->prepare(
                "UPDATE estoque SET nome=:nome, categoria_id=:categoria_id, marca=:marca, modelo=:modelo,
                 quantidade=:quantidade, quantidade_minima=:quantidade_minima, localizacao=:localizacao,
                 observacoes=:observacoes, $setExtras WHERE id=:id"
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
                $colunasExtrasLista = implode(', ', $colunasExtras);
                $placeholdersExtras = implode(', ', array_map(fn (string $c): string => ":$c", $colunasExtras));
                $pdo->prepare(
                    "INSERT INTO estoque (nome, categoria_id, marca, modelo, quantidade, quantidade_minima, localizacao, observacoes, $colunasExtrasLista)
                     VALUES (:nome, :categoria_id, :marca, :modelo, :quantidade, :quantidade_minima, :localizacao, :observacoes, $placeholdersExtras)"
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
$redes = $pdo->query('SELECT id, nome FROM redes ORDER BY nome')->fetchAll();
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
                <select name="categoria_id" id="categoriaEstoqueSelect" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($categorias as $cat): ?>
                        <?php
                            $gruposDaCategoria = [];
                            foreach ($grupos as $chave => $grupo) {
                                if (!empty($cat[$grupo['coluna']])) {
                                    $gruposDaCategoria[] = $chave;
                                }
                            }
                        ?>
                        <option value="<?= (int) $cat['id'] ?>" data-grupos="<?= e(implode(',', $gruposDaCategoria)) ?>" <?= (string) $item['categoria_id'] === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['nome']) ?></option>
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

    <?php foreach ($grupos as $chave => $grupo): ?>
        <div class="card mb-3" id="grupoCampos_<?= e($chave) ?>" style="display:none;">
            <div class="card-header bg-white">
                <i class="bi <?= e($grupo['icone']) ?> me-1"></i> <?= e($grupo['label']) ?>
            </div>
            <div class="card-body row g-3">
                <?php foreach ($grupo['campos'] as $nomeCampo => $config): ?>
                    <div class="col-md-<?= $config['tipo'] === 'textarea' ? '12' : '4' ?>">
                        <?php if ($config['tipo'] === 'checkbox'): ?>
                            <div class="form-check mt-4">
                                <input type="checkbox" name="<?= e($nomeCampo) ?>" id="campo_<?= e($nomeCampo) ?>" class="form-check-input" value="1" <?= !empty($item[$nomeCampo]) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="campo_<?= e($nomeCampo) ?>"><?= e($config['label']) ?></label>
                            </div>
                        <?php else: ?>
                            <label class="form-label"><?= e($config['label']) ?></label>
                            <?php if ($config['tipo'] === 'textarea'): ?>
                                <textarea name="<?= e($nomeCampo) ?>" class="form-control" rows="2"><?= e((string) $item[$nomeCampo]) ?></textarea>
                            <?php elseif ($config['tipo'] === 'select'): ?>
                                <select name="<?= e($nomeCampo) ?>" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($config['opcoes'] as $opcao): ?>
                                        <option value="<?= e($opcao) ?>" <?= (string) $item[$nomeCampo] === $opcao ? 'selected' : '' ?>><?= e($opcao) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($config['tipo'] === 'rede'): ?>
                                <select name="<?= e($nomeCampo) ?>" class="form-select">
                                    <option value="">Nenhuma</option>
                                    <?php foreach ($redes as $rede): ?>
                                        <option value="<?= (int) $rede['id'] ?>" <?= (string) $item[$nomeCampo] === (string) $rede['id'] ? 'selected' : '' ?>><?= e($rede['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($config['tipo'] === 'data'): ?>
                                <input type="date" name="<?= e($nomeCampo) ?>" class="form-control" value="<?= e((string) $item[$nomeCampo]) ?>">
                            <?php elseif ($config['tipo'] === 'numero'): ?>
                                <input type="number" name="<?= e($nomeCampo) ?>" class="form-control" value="<?= e((string) $item[$nomeCampo]) ?>">
                            <?php elseif ($config['tipo'] === 'dinheiro'): ?>
                                <input type="text" name="<?= e($nomeCampo) ?>" class="form-control" placeholder="0,00" value="<?= e((string) $item[$nomeCampo]) ?>">
                            <?php else: ?>
                                <input type="text" name="<?= e($nomeCampo) ?>" class="form-control" <?= isset($config['placeholder']) ? 'placeholder="' . e($config['placeholder']) . '"' : '' ?> value="<?= e((string) $item[$nomeCampo]) ?>">
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
