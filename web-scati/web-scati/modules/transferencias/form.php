<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('transferencias', 'alterar');

$pdo = db();

$dados = [
    'origem' => '', 'destino' => '', 'responsavel_id' => '',
    'data_transferencia' => date('Y-m-d'), 'observacoes' => '',
];
$itensPostados = [];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($dados as $campo => $valorPadrao) {
        $dados[$campo] = trim($_POST[$campo] ?? '');
    }
    $itensPost = is_array($_POST['itens'] ?? null) ? $_POST['itens'] : [];
    $itensPostados = $itensPost; // reaproveitado para reexibir o formulário em caso de erro

    if ($dados['origem'] === '') {
        $erros[] = 'O campo Origem é obrigatório.';
    }
    if ($dados['destino'] === '') {
        $erros[] = 'O campo Destino é obrigatório.';
    }
    $responsavelId = $dados['responsavel_id'] !== '' ? (int) $dados['responsavel_id'] : null;
    $responsavelNome = null;
    if ($responsavelId === null) {
        $erros[] = 'Selecione o Responsável pela transferência.';
    } else {
        $stmtU = $pdo->prepare('SELECT usuario FROM usuarios WHERE id = :id');
        $stmtU->execute(['id' => $responsavelId]);
        $responsavelNome = $stmtU->fetchColumn();
        if ($responsavelNome === false) {
            $erros[] = 'Responsável inválido.';
            $responsavelId = null;
        }
    }
    if ($dados['data_transferencia'] === '' || !DateTime::createFromFormat('Y-m-d', $dados['data_transferencia'])) {
        $erros[] = 'Informe uma Data de transferência válida.';
    }

    // Monta e valida a lista de itens. Linhas em branco (nenhum equipamento
    // selecionado e nenhum nome digitado) são ignoradas silenciosamente —
    // é normal sobrar uma linha vazia ao remover/adicionar itens no JS.
    $itensValidados = [];
    foreach ($itensPost as $item) {
        $modo = $item['modo'] ?? 'existente';
        if ($modo === 'novo') {
            $tipo = trim($item['novo_tipo'] ?? '');
            $nome = trim($item['novo_nome'] ?? '');
            $patrimonio = trim($item['patrimonio'] ?? '');
            if ($nome === '' && $patrimonio === '') {
                continue;
            }
            if ($nome === '') {
                $erros[] = 'Informe o nome do novo equipamento (patrimônio "' . $patrimonio . '").';
                continue;
            }
            if (!in_array($tipo, tiposEquipamento(), true)) {
                $erros[] = 'Tipo inválido para o novo equipamento "' . $nome . '".';
                continue;
            }
            if ($patrimonio !== '') {
                $stmtCheck = $pdo->prepare('SELECT id FROM equipamentos WHERE patrimonio = :p');
                $stmtCheck->execute(['p' => $patrimonio]);
                if ($stmtCheck->fetch()) {
                    $erros[] = 'Já existe um equipamento cadastrado com o patrimônio "' . $patrimonio . '".';
                    continue;
                }
            }
            $itensValidados[] = ['modo' => 'novo', 'tipo' => $tipo, 'nome' => $nome, 'patrimonio' => $patrimonio ?: null];
        } else {
            $equipamentoId = (int) ($item['equipamento_id'] ?? 0);
            if ($equipamentoId <= 0) {
                continue;
            }
            $itensValidados[] = ['modo' => 'existente', 'equipamento_id' => $equipamentoId];
        }
    }
    if (empty($itensValidados)) {
        $erros[] = 'Adicione ao menos um item à transferência.';
    }

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            $stmtT = $pdo->prepare(
                'INSERT INTO transferencias (origem, destino, responsavel_id, responsavel_nome, data_transferencia, observacoes)
                 VALUES (:origem, :destino, :responsavel_id, :responsavel_nome, :data, :observacoes)'
            );
            $stmtT->execute([
                'origem' => $dados['origem'],
                'destino' => $dados['destino'],
                'responsavel_id' => $responsavelId,
                'responsavel_nome' => $responsavelNome,
                'data' => $dados['data_transferencia'],
                'observacoes' => $dados['observacoes'] ?: null,
            ]);
            $transferenciaId = (int) $pdo->lastInsertId();

            foreach ($itensValidados as $it) {
                if ($it['modo'] === 'novo') {
                    $pdo->prepare(
                        'INSERT INTO equipamentos (nome, patrimonio, tipo, status, localizacao)
                         VALUES (:nome, :patrimonio, :tipo, :status, :localizacao)'
                    )->execute([
                        'nome' => $it['nome'],
                        'patrimonio' => $it['patrimonio'],
                        'tipo' => $it['tipo'],
                        'status' => 'Em uso',
                        'localizacao' => $dados['destino'],
                    ]);
                    $equipamentoId = (int) $pdo->lastInsertId();
                    $equipamentoNome = $it['nome'];
                    $ehNovo = 1;
                    registrarHistorico($equipamentoId, 'Transferência',
                        'Recebido por transferência de "' . $dados['origem'] . '" para "' . $dados['destino'] . '". Responsável: ' . $responsavelNome . '.');
                } else {
                    $equipamentoId = $it['equipamento_id'];
                    $stmtEq = $pdo->prepare('SELECT nome, patrimonio FROM equipamentos WHERE id = :id');
                    $stmtEq->execute(['id' => $equipamentoId]);
                    $eq = $stmtEq->fetch();
                    if (!$eq) {
                        continue;
                    }
                    $equipamentoNome = nomeEquipamento($eq['nome'], $eq['patrimonio']);
                    $ehNovo = 0;
                    $pdo->prepare('UPDATE equipamentos SET localizacao = :destino WHERE id = :id')
                        ->execute(['destino' => $dados['destino'], 'id' => $equipamentoId]);
                    registrarHistorico($equipamentoId, 'Transferência',
                        'Transferido de "' . $dados['origem'] . '" para "' . $dados['destino'] . '". Responsável: ' . $responsavelNome . '.');
                }

                $pdo->prepare(
                    'INSERT INTO transferencia_itens (transferencia_id, equipamento_id, equipamento_nome, equipamento_novo)
                     VALUES (:tid, :eid, :nome, :novo)'
                )->execute([
                    'tid' => $transferenciaId,
                    'eid' => $equipamentoId,
                    'nome' => $equipamentoNome,
                    'novo' => $ehNovo,
                ]);
            }

            $pdo->commit();
            flash('success', 'Transferência registrada com sucesso.');
            redirect('/modules/transferencias/view.php?id=' . $transferenciaId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $erros[] = 'Ocorreu um erro ao registrar a transferência. Tente novamente.';
        }
    }
}

$usuarios = $pdo->query('SELECT id, usuario FROM usuarios ORDER BY usuario')->fetchAll();
$equipamentosDisponiveis = $pdo->query(
    "SELECT id, nome, patrimonio, tipo FROM equipamentos ORDER BY nome ASC"
)->fetchAll();
$destinosExistentes = $pdo->query(
    "SELECT DISTINCT localizacao FROM equipamentos WHERE localizacao IS NOT NULL AND TRIM(localizacao) <> '' ORDER BY localizacao ASC"
)->fetchAll(PDO::FETCH_COLUMN);
$tipos = tiposEquipamento();

$pageTitle = 'Nova Transferência';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-truck me-2"></i>Nova Transferência</h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" id="formTransferencia">
    <div class="card mb-3">
        <div class="card-header bg-light"><strong>Dados da Transferência</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Origem <span class="text-danger">*</span></label>
                    <input type="text" name="origem" class="form-control" value="<?= e($dados['origem']) ?>" placeholder="Ex.: Obra Jardim das Flores" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Destino <span class="text-danger">*</span></label>
                    <select id="selectDestino" name="destino" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($destinosExistentes as $local): ?>
                            <option value="<?= e($local) ?>" <?= $dados['destino'] === $local ? 'selected' : '' ?>><?= e($local) ?></option>
                        <?php endforeach; ?>
                        <?php if ($dados['destino'] !== '' && !in_array($dados['destino'], $destinosExistentes, true)): ?>
                            <option value="<?= e($dados['destino']) ?>" selected><?= e($dados['destino']) ?></option>
                        <?php endif; ?>
                        <option value="novo">+ Novo local...</option>
                    </select>
                    <div id="novoDestinoWrap" class="d-none mt-2 d-flex gap-2">
                        <input type="text" id="novoDestinoNome" class="form-control form-control-sm" placeholder="Nome do novo local">
                        <button type="button" id="novoDestinoAdicionar" class="btn btn-sm btn-primary text-nowrap">Adicionar</button>
                        <button type="button" id="novoDestinoCancelar" class="btn btn-sm btn-outline-secondary text-nowrap">Cancelar</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data da Transferência <span class="text-danger">*</span></label>
                    <input type="date" name="data_transferencia" class="form-control" value="<?= e($dados['data_transferencia']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Responsável <span class="text-danger">*</span></label>
                    <select name="responsavel_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (string) $u['id'] === $dados['responsavel_id'] ? 'selected' : '' ?>><?= e($u['usuario']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Observações</label>
                    <input type="text" name="observacoes" class="form-control" value="<?= e($dados['observacoes']) ?>" placeholder="Opcional">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <strong>Itens da Transferência</strong>
            <button type="button" id="btnAdicionarItemTransferencia" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Adicionar item</button>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:18%">Tipo de item</th>
                        <th>Equipamento</th>
                        <th style="width:16%">Patrimônio</th>
                        <th style="width:5%"></th>
                    </tr>
                </thead>
                <tbody id="corpoItensTransferencia"></tbody>
            </table>
        </div>
    </div>

    <div class="mt-3 text-end">
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Confirmar Transferência</button>
    </div>
</form>

<template id="templateItemTransferencia">
    <tr class="transferencia-item-linha">
        <td>
            <select class="form-select form-select-sm item-modo-select">
                <option value="existente">Já cadastrado</option>
                <option value="novo">+ Novo equipamento</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm item-equipamento-select" name="">
                <option value="">Selecione...</option>
                <?php foreach ($equipamentosDisponiveis as $eq): ?>
                    <option value="<?= (int) $eq['id'] ?>"><?= e(nomeEquipamento($eq['nome'], $eq['patrimonio'])) ?> (<?= e($eq['tipo']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <div class="item-novo-wrap d-none row g-1">
                <div class="col-4">
                    <select class="form-select form-select-sm" name="">
                        <?php foreach ($tipos as $tipo): ?>
                            <option value="<?= e($tipo) ?>"><?= e($tipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-8">
                    <input type="text" class="form-control form-control-sm" name="" placeholder="Nome / modelo">
                </div>
            </div>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm" name="" placeholder="Opcional">
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger item-remover-linha"><i class="bi bi-trash"></i></button>
        </td>
    </tr>
</template>

<script>
window.SCATI_ITENS_TRANSFERENCIA_POSTADOS = <?= json_encode(array_values($itensPostados), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
