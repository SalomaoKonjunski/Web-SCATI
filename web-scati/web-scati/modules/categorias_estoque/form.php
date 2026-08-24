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

$categoria = ['nome' => '', 'equipamento_tipo' => ''];
foreach ($grupos as $grupo) {
    $categoria[$grupo['coluna']] = 0;
}

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM categorias_estoque WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Categoria não encontrada.');
        redirect('/modules/categorias_estoque/index.php');
    }
    $categoria = array_merge($categoria, $registro);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoria['nome'] = trim($_POST['nome'] ?? '');
    $modo = ($_POST['modo'] ?? 'estoque') === 'equipamento' ? 'equipamento' : 'estoque';

    if ($modo === 'equipamento') {
        $categoria['equipamento_tipo'] = $_POST['equipamento_tipo'] ?? '';
        foreach ($grupos as $grupo) {
            $categoria[$grupo['coluna']] = 0;
        }
    } else {
        $categoria['equipamento_tipo'] = '';
        foreach ($grupos as $chave => $grupo) {
            $categoria[$grupo['coluna']] = isset($_POST['grupo_' . $chave]) ? 1 : 0;
        }
    }

    if ($categoria['nome'] === '') {
        $erros[] = 'O campo Nome é obrigatório.';
    }

    if ($modo === 'equipamento' && !in_array($categoria['equipamento_tipo'], tiposEquipamento(), true)) {
        $erros[] = 'Selecione o tipo de equipamento.';
    }

    // A categoria "Toner" é usada por nome em várias regras do sistema (aba
    // Toner das impressoras, alerta de impressora sem toner, etc.) — precisa
    // continuar sendo um item de estoque com quantidade, então fica protegida
    // contra renomeação e contra virar uma categoria de Equipamento.
    if ($edicao && $registro['nome'] === 'Toner' && $categoria['nome'] !== 'Toner') {
        $erros[] = 'A categoria "Toner" não pode ser renomeada, pois é usada pela funcionalidade de Toner de impressoras.';
    }
    if ($edicao && $registro['nome'] === 'Toner' && $modo === 'equipamento') {
        $erros[] = 'A categoria "Toner" não pode virar Equipamento, pois é usada pela funcionalidade de Toner de impressoras.';
    }

    if (empty($erros)) {
        $sqlCheck = 'SELECT id FROM categorias_estoque WHERE nome = :nome' . ($edicao ? ' AND id != :id' : '');
        $stmtCheck = $pdo->prepare($sqlCheck);
        $paramsCheck = ['nome' => $categoria['nome']];
        if ($edicao) {
            $paramsCheck['id'] = $id;
        }
        $stmtCheck->execute($paramsCheck);
        if ($stmtCheck->fetch()) {
            $erros[] = 'Já existe uma categoria com este nome.';
        }
    }

    if (empty($erros)) {
        $params = ['nome' => $categoria['nome'], 'equipamento_tipo' => $categoria['equipamento_tipo'] ?: null];
        foreach ($grupos as $grupo) {
            $params[$grupo['coluna']] = $categoria[$grupo['coluna']];
        }

        if ($edicao) {
            $params['id'] = $id;
            $pdo->prepare(
                'UPDATE categorias_estoque SET nome = :nome, equipamento_tipo = :equipamento_tipo,
                    campo_hardware = :campo_hardware, campo_impressora = :campo_impressora,
                    campo_rede_computador = :campo_rede_computador, campo_servidor = :campo_servidor,
                    campo_localizacao_uso = :campo_localizacao_uso, campo_financeiro = :campo_financeiro
                 WHERE id = :id'
            )->execute($params);
            flash('success', 'Categoria atualizada com sucesso.');
        } else {
            $pdo->prepare(
                'INSERT INTO categorias_estoque
                    (nome, equipamento_tipo, campo_hardware, campo_impressora, campo_rede_computador, campo_servidor, campo_localizacao_uso, campo_financeiro)
                 VALUES
                    (:nome, :equipamento_tipo, :campo_hardware, :campo_impressora, :campo_rede_computador, :campo_servidor, :campo_localizacao_uso, :campo_financeiro)'
            )->execute($params);
            flash('success', 'Categoria cadastrada com sucesso.');
        }
        redirect('/modules/categorias_estoque/index.php');
    }
}

$pageTitle = $edicao ? 'Editar Categoria' : 'Nova Categoria';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-tags me-2"></i><?= $edicao ? 'Editar Categoria' : 'Nova Categoria' ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php $modoAtual = !empty($categoria['equipamento_tipo']) ? 'equipamento' : 'estoque'; ?>
<form method="post">
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Nome *</label>
                <input type="text" name="nome" class="form-control" required value="<?= e($categoria['nome']) ?>">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white">
            <i class="bi bi-signpost-split me-1"></i> O que esta categoria representa
        </div>
        <div class="card-body">
            <div class="list-group mb-3">
                <label class="list-group-item d-flex gap-3">
                    <input class="form-check-input flex-shrink-0 mt-1" type="radio" name="modo" id="modoEstoque" value="estoque" <?= $modoAtual === 'estoque' ? 'checked' : '' ?>>
                    <span>
                        <span class="fw-semibold"><i class="bi bi-box-seam me-1"></i> Item de estoque comum</span>
                        <div class="text-muted small">Controlado por quantidade (peça avulsa, suprimento, etc.) — continua cadastrado em Estoque.</div>
                    </span>
                </label>
                <label class="list-group-item d-flex gap-3">
                    <input class="form-check-input flex-shrink-0 mt-1" type="radio" name="modo" id="modoEquipamento" value="equipamento" <?= $modoAtual === 'equipamento' ? 'checked' : '' ?>>
                    <span>
                        <span class="fw-semibold"><i class="bi bi-pc-display me-1"></i> Equipamento</span>
                        <div class="text-muted small">Ativo individual, sem controle de quantidade — "Novo Item" nesta categoria leva direto para o cadastro de Equipamentos.</div>
                    </span>
                </label>
            </div>

            <div id="painelEquipamentoTipo" style="display:none;">
                <label class="form-label">Tipo de Equipamento *</label>
                <select name="equipamento_tipo" class="form-select" style="max-width: 320px;">
                    <option value="">Selecione...</option>
                    <?php foreach (tiposEquipamento() as $tipo): ?>
                        <option value="<?= e($tipo) ?>" <?= $categoria['equipamento_tipo'] === $tipo ? 'selected' : '' ?>><?= e($tipo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card mb-3" id="painelCamposExtras">
        <div class="card-header bg-white">
            <i class="bi bi-ui-checks-grid me-1"></i> Campos extras para os itens desta categoria
        </div>
        <div class="card-body">
            <p class="text-muted small">Marque quais grupos de campos do cadastro de Equipamentos também devem aparecer ao criar/editar um item desta categoria. Deixe tudo desmarcado para uma categoria de item comum (peça avulsa, suprimento, etc.) — só os campos padrão do Estoque.</p>

            <div class="list-group">
                <?php foreach ($grupos as $chave => $grupo): ?>
                    <label class="list-group-item d-flex gap-3">
                        <input class="form-check-input flex-shrink-0 mt-1" type="checkbox" name="grupo_<?= e($chave) ?>" value="1" <?= $categoria[$grupo['coluna']] ? 'checked' : '' ?>>
                        <span>
                            <span class="fw-semibold"><i class="bi <?= e($grupo['icone']) ?> me-1"></i> <?= e($grupo['label']) ?></span>
                            <div class="text-muted small"><?= e(implode(', ', array_column($grupo['campos'], 'label'))) ?></div>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
