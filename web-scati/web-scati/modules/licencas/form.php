<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$licenca = [
    'equipamento_id' => $_GET['equipamento_id'] ?? '', 'software' => '', 'fabricante' => '', 'tipo' => 'OEM',
    'chave' => '', 'versao' => '', 'data_aquisicao' => '', 'data_validade' => '', 'observacoes' => '',
];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM licencas WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Licença não encontrada.');
        redirect('/modules/licencas/index.php');
    }
    $licenca = array_merge($licenca, $registro);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $licenca['equipamento_id'] = $_POST['equipamento_id'] ?? '';
    $licenca['software'] = trim($_POST['software'] ?? '');
    $licenca['fabricante'] = trim($_POST['fabricante'] ?? '');
    $licenca['tipo'] = $_POST['tipo'] ?? 'OEM';
    $licenca['chave'] = trim($_POST['chave'] ?? '');
    $licenca['versao'] = trim($_POST['versao'] ?? '');
    $licenca['data_aquisicao'] = $_POST['data_aquisicao'] ?? '';
    $licenca['data_validade'] = $_POST['data_validade'] ?? '';
    $licenca['observacoes'] = trim($_POST['observacoes'] ?? '');

    if ($licenca['software'] === '') {
        $erros[] = 'O campo Nome do Software é obrigatório.';
    }
    if (!in_array($licenca['tipo'], tiposLicenca(), true)) {
        $erros[] = 'Tipo de licença inválido.';
    }

    if (empty($erros)) {
        $equipamentoId = $licenca['equipamento_id'] !== '' ? (int) $licenca['equipamento_id'] : null;
        $dados = [
            'equipamento_id' => $equipamentoId,
            'software' => $licenca['software'],
            'fabricante' => $licenca['fabricante'] ?: null,
            'tipo' => $licenca['tipo'],
            'chave' => $licenca['chave'] ?: null,
            'versao' => $licenca['versao'] ?: null,
            'data_aquisicao' => $licenca['data_aquisicao'] ?: null,
            'data_validade' => $licenca['data_validade'] ?: null,
            'observacoes' => $licenca['observacoes'] ?: null,
        ];

        if ($edicao) {
            $dados['id'] = $id;
            $pdo->prepare(
                'UPDATE licencas SET equipamento_id=:equipamento_id, software=:software, fabricante=:fabricante,
                 tipo=:tipo, chave=:chave, versao=:versao, data_aquisicao=:data_aquisicao,
                 data_validade=:data_validade, observacoes=:observacoes WHERE id=:id'
            )->execute($dados);

            // Se o vínculo mudou diretamente por este formulário, registra no histórico dos equipamentos envolvidos
            $vinculoAnterior = $registro['equipamento_id'] ? (int) $registro['equipamento_id'] : null;
            if ($vinculoAnterior !== $equipamentoId) {
                if ($vinculoAnterior) {
                    registrarHistorico($vinculoAnterior, 'Licença', 'Licença "' . $licenca['software'] . '" removida deste equipamento');
                }
                if ($equipamentoId) {
                    registrarHistorico($equipamentoId, 'Licença', 'Licença "' . $licenca['software'] . '" vinculada a este equipamento');
                }
            }

            flash('success', 'Licença atualizada com sucesso.');
        } else {
            $pdo->prepare(
                'INSERT INTO licencas (equipamento_id, software, fabricante, tipo, chave, versao, data_aquisicao, data_validade, observacoes)
                 VALUES (:equipamento_id, :software, :fabricante, :tipo, :chave, :versao, :data_aquisicao, :data_validade, :observacoes)'
            )->execute($dados);

            if ($equipamentoId) {
                registrarHistorico($equipamentoId, 'Licença', 'Licença "' . $licenca['software'] . '" vinculada a este equipamento');
            }

            flash('success', 'Licença cadastrada com sucesso.');
        }

        if ($equipamentoId) {
            redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#licenciamento');
        }
        redirect('/modules/licencas/index.php');
    }
}

$equipamentos = $pdo->query('SELECT id, patrimonio, hostname FROM equipamentos ORDER BY patrimonio')->fetchAll();
$pageTitle = $edicao ? 'Editar Licença' : 'Nova Licença';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-key me-2"></i><?= $edicao ? 'Editar Licença' : 'Nova Licença' ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i> Regra de negócio: cada licença pode estar vinculada a, no máximo,
    <strong>um único equipamento</strong>. Um equipamento, por sua vez, pode possuir várias licenças.
</div>

<form method="post">
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Nome do Software *</label>
                <input type="text" name="software" class="form-control" required value="<?= e($licenca['software']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Fabricante</label>
                <input type="text" name="fabricante" class="form-control" value="<?= e($licenca['fabricante']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo da Licença *</label>
                <select name="tipo" class="form-select" required>
                    <?php foreach (tiposLicenca() as $tipo): ?>
                        <option value="<?= e($tipo) ?>" <?= $licenca['tipo'] === $tipo ? 'selected' : '' ?>><?= e($tipo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Versão</label>
                <input type="text" name="versao" class="form-control" value="<?= e($licenca['versao']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Chave de Ativação</label>
                <input type="text" name="chave" class="form-control" value="<?= e($licenca['chave']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data de Aquisição</label>
                <input type="date" name="data_aquisicao" class="form-control" value="<?= e($licenca['data_aquisicao']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data de Validade</label>
                <input type="date" name="data_validade" class="form-control" value="<?= e($licenca['data_validade']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Equipamento Vinculado</label>
                <select name="equipamento_id" class="form-select">
                    <option value="">Nenhum (sem vínculo)</option>
                    <?php foreach ($equipamentos as $eq): ?>
                        <option value="<?= (int) $eq['id'] ?>" <?= (string) $licenca['equipamento_id'] === (string) $eq['id'] ? 'selected' : '' ?>>
                            <?= e($eq['patrimonio']) ?><?= $eq['hostname'] ? ' — ' . e($eq['hostname']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Observações</label>
                <input type="text" name="observacoes" class="form-control" value="<?= e($licenca['observacoes']) ?>">
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
