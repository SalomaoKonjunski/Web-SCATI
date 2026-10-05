<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('tarefas', 'alterar');

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$tarefa = [
    'titulo' => '', 'descricao' => '', 'frequencia_tipo' => 'dias', 'frequencia_valor' => 1,
    'dias_aviso_antecedencia' => 3, 'proxima_execucao' => date('Y-m-d'), 'responsavel_id' => '', 'ativo' => 1,
];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM tarefas_periodicas WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Tarefa não encontrada.');
        redirect('/modules/tarefas/index.php');
    }
    $tarefa = array_merge($tarefa, $registro);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tarefa['titulo'] = trim($_POST['titulo'] ?? '');
    $tarefa['descricao'] = trim($_POST['descricao'] ?? '');
    $tarefa['frequencia_tipo'] = $_POST['frequencia_tipo'] ?? 'dias';
    $tarefa['frequencia_valor'] = (int) ($_POST['frequencia_valor'] ?? 1);
    $tarefa['dias_aviso_antecedencia'] = (int) ($_POST['dias_aviso_antecedencia'] ?? 0);
    $tarefa['proxima_execucao'] = $_POST['proxima_execucao'] ?? '';
    $tarefa['responsavel_id'] = $_POST['responsavel_id'] ?? '';
    $tarefa['ativo'] = isset($_POST['ativo']) ? 1 : 0;

    if ($tarefa['titulo'] === '') {
        $erros[] = 'O campo Título é obrigatório.';
    }
    if (!in_array($tarefa['frequencia_tipo'], ['dias', 'semanas', 'meses'], true)) {
        $erros[] = 'Frequência inválida.';
    }
    if ($tarefa['frequencia_valor'] < 1) {
        $erros[] = 'O campo "A cada" deve ser 1 ou maior.';
    }
    if ($tarefa['dias_aviso_antecedencia'] < 0) {
        $erros[] = 'O campo "Avisar com antecedência" não pode ser negativo.';
    }
    if (!DateTime::createFromFormat('Y-m-d', $tarefa['proxima_execucao'])) {
        $erros[] = 'O campo Próxima Execução é obrigatório.';
    }

    if (empty($erros)) {
        $dados = [
            'titulo' => $tarefa['titulo'],
            'descricao' => $tarefa['descricao'] ?: null,
            'frequencia_tipo' => $tarefa['frequencia_tipo'],
            'frequencia_valor' => $tarefa['frequencia_valor'],
            'dias_aviso_antecedencia' => $tarefa['dias_aviso_antecedencia'],
            'proxima_execucao' => $tarefa['proxima_execucao'],
            'responsavel_id' => $tarefa['responsavel_id'] !== '' ? (int) $tarefa['responsavel_id'] : null,
            'ativo' => $tarefa['ativo'],
        ];

        if ($edicao) {
            $dados['id'] = $id;
            $pdo->prepare(
                'UPDATE tarefas_periodicas SET titulo = :titulo, descricao = :descricao,
                 frequencia_tipo = :frequencia_tipo, frequencia_valor = :frequencia_valor,
                 dias_aviso_antecedencia = :dias_aviso_antecedencia, proxima_execucao = :proxima_execucao,
                 responsavel_id = :responsavel_id, ativo = :ativo WHERE id = :id'
            )->execute($dados);
            flash('success', 'Tarefa atualizada com sucesso.');
        } else {
            $pdo->prepare(
                'INSERT INTO tarefas_periodicas (titulo, descricao, frequencia_tipo, frequencia_valor,
                 dias_aviso_antecedencia, proxima_execucao, responsavel_id, ativo)
                 VALUES (:titulo, :descricao, :frequencia_tipo, :frequencia_valor,
                 :dias_aviso_antecedencia, :proxima_execucao, :responsavel_id, :ativo)'
            )->execute($dados);
            flash('success', 'Tarefa criada com sucesso.');
        }
        redirect('/modules/tarefas/index.php');
    }
}

$usuarios = $pdo->query('SELECT id, usuario FROM usuarios ORDER BY usuario ASC')->fetchAll();

$pageTitle = $edicao ? 'Editar Tarefa' : 'Nova Tarefa';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-arrow-repeat me-2"></i><?= $edicao ? 'Editar Tarefa' : 'Nova Tarefa' ?></h1>
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
                <input type="text" name="titulo" class="form-control" required autofocus
                       placeholder="Ex: Troca de toner, Limpeza do cortador de papel..." value="<?= e($tarefa['titulo']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Responsável</label>
                <select name="responsavel_id" class="form-select">
                    <option value="">Qualquer um da equipe</option>
                    <?php foreach ($usuarios as $usr): ?>
                        <option value="<?= (int) $usr['id'] ?>" <?= (string) $tarefa['responsavel_id'] === (string) $usr['id'] ? 'selected' : '' ?>>
                            <?= e($usr['usuario']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Descrição</label>
                <textarea name="descricao" class="form-control" rows="3"><?= e($tarefa['descricao'] ?? '') ?></textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label">A cada *</label>
                <input type="number" min="1" name="frequencia_valor" class="form-control" required value="<?= (int) $tarefa['frequencia_valor'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Período *</label>
                <select name="frequencia_tipo" class="form-select">
                    <option value="dias" <?= $tarefa['frequencia_tipo'] === 'dias' ? 'selected' : '' ?>>Dia(s)</option>
                    <option value="semanas" <?= $tarefa['frequencia_tipo'] === 'semanas' ? 'selected' : '' ?>>Semana(s)</option>
                    <option value="meses" <?= $tarefa['frequencia_tipo'] === 'meses' ? 'selected' : '' ?>>Mês(es)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Próxima Execução *</label>
                <input type="date" name="proxima_execucao" class="form-control" required value="<?= e($tarefa['proxima_execucao']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Avisar com antecedência (dias) *</label>
                <input type="number" min="0" name="dias_aviso_antecedencia" class="form-control" required value="<?= (int) $tarefa['dias_aviso_antecedencia'] ?>">
                <div class="form-text">Com quantos dias de antecedência ela aparece na Central de Alertas.</div>
            </div>
            <?php if ($edicao && $tarefa['ultima_execucao']): ?>
                <div class="col-12">
                    <p class="text-muted small mb-0">Última execução registrada: <?= formatDate($tarefa['ultima_execucao']) ?>.</p>
                </div>
            <?php endif; ?>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="ativo" id="ativo" <?= $tarefa['ativo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ativo">Tarefa ativa (pausada não aparece na Central de Alertas)</label>
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
