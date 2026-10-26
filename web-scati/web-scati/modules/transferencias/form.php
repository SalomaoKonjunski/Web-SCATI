<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('transferencias', 'alterar');

$pdo = db();

// Unifica os locais já usados em Equipamentos com o cadastro de Locais de
// Estoque (único registro de "locais" do sistema) — garante que nenhum
// local já em uso em algum equipamento fique de fora do dropdown de
// Origem/Destino. Idempotente: INSERT IGNORE não duplica nada nas vezes
// seguintes.
$pdo->exec(
    "INSERT IGNORE INTO locais_estoque (nome)
     SELECT DISTINCT TRIM(localizacao) FROM equipamentos
     WHERE localizacao IS NOT NULL AND TRIM(localizacao) <> ''"
);
$locaisCadastrados = locaisEstoqueNomes();

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
    } elseif (!in_array($dados['origem'], $locaisCadastrados, true)) {
        $erros[] = 'Origem inválida — escolha um local cadastrado.';
    }
    if ($dados['destino'] === '') {
        $erros[] = 'O campo Destino é obrigatório.';
    } elseif (!in_array($dados['destino'], $locaisCadastrados, true)) {
        $erros[] = 'Destino inválido — escolha um local cadastrado.';
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
    $quantidadeSolicitadaPorEstoque = []; // soma por estoque_id, pra pegar o caso de 2 linhas do mesmo item
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
            $quantidade = (int) ($item['quantidade'] ?? 1);
            if ($quantidade < 1) {
                $quantidade = 1;
            }
            if ($quantidade > 999) {
                $erros[] = 'A quantidade do item "' . $nome . '" não pode passar de 999 de uma vez.';
                continue;
            }
            if ($quantidade > 1 && $patrimonio !== '') {
                $erros[] = 'Informe o Patrimônio apenas quando a quantidade for 1 (item "' . $nome . '") — cada equipamento tem um patrimônio único.';
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
            $itensValidados[] = ['modo' => 'novo', 'tipo' => $tipo, 'nome' => $nome, 'patrimonio' => $patrimonio ?: null, 'quantidade' => $quantidade];
        } elseif ($modo === 'estoque') {
            $estoqueId = (int) ($item['estoque_id'] ?? 0);
            $quantidade = (int) ($item['quantidade'] ?? 0);
            if ($estoqueId <= 0) {
                continue;
            }
            if ($quantidade < 1) {
                $erros[] = 'Informe uma quantidade válida para o item de estoque selecionado.';
                continue;
            }
            $quantidadeSolicitadaPorEstoque[$estoqueId] = ($quantidadeSolicitadaPorEstoque[$estoqueId] ?? 0) + $quantidade;
            $itensValidados[] = ['modo' => 'estoque', 'estoque_id' => $estoqueId, 'quantidade' => $quantidade];
        } else {
            $equipamentoId = (int) ($item['equipamento_id'] ?? 0);
            if ($equipamentoId <= 0) {
                continue;
            }
            $itensValidados[] = ['modo' => 'existente', 'equipamento_id' => $equipamentoId];
        }
    }
    // Confere o total pedido de cada item de estoque (somando todas as
    // linhas que apontam pro mesmo item) contra a quantidade disponível —
    // uma linha isolada pode parecer válida, mas a soma de duas linhas do
    // mesmo item pode passar do que existe no local de origem.
    foreach ($quantidadeSolicitadaPorEstoque as $estoqueId => $quantidadeTotal) {
        $stmtEstoqueCheck = $pdo->prepare('SELECT nome, quantidade FROM estoque WHERE id = :id');
        $stmtEstoqueCheck->execute(['id' => $estoqueId]);
        $estoqueCheck = $stmtEstoqueCheck->fetch();
        if (!$estoqueCheck) {
            $erros[] = 'Um dos itens de estoque selecionados não foi encontrado.';
            continue;
        }
        if ($quantidadeTotal > (int) $estoqueCheck['quantidade']) {
            $erros[] = 'Quantidade solicitada de "' . $estoqueCheck['nome'] . '" (' . $quantidadeTotal . ') é maior que a disponível (' . (int) $estoqueCheck['quantidade'] . ').';
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
                    // Quantidade > 1 cria várias unidades do mesmo item de uma vez
                    // (ex.: 5 "Monitor LG 24"), cada uma com seu próprio cadastro
                    // em equipamentos — o nome ganha "(unidade N/total)" só quando
                    // há mais de uma, pra não ficarem indistinguíveis na listagem.
                    for ($unidade = 1; $unidade <= $it['quantidade']; $unidade++) {
                        $nomeUnidade = $it['quantidade'] > 1
                            ? $it['nome'] . ' (unidade ' . $unidade . '/' . $it['quantidade'] . ')'
                            : $it['nome'];

                        $pdo->prepare(
                            'INSERT INTO equipamentos (nome, patrimonio, tipo, status, localizacao)
                             VALUES (:nome, :patrimonio, :tipo, :status, :localizacao)'
                        )->execute([
                            'nome' => $nomeUnidade,
                            'patrimonio' => $it['patrimonio'],
                            'tipo' => $it['tipo'],
                            'status' => 'Em uso',
                            'localizacao' => $dados['destino'],
                        ]);
                        $equipamentoId = (int) $pdo->lastInsertId();
                        registrarHistorico($equipamentoId, 'Transferência',
                            'Recebido por transferência de "' . $dados['origem'] . '" para "' . $dados['destino'] . '". Responsável: ' . $responsavelNome . '.');

                        $pdo->prepare(
                            'INSERT INTO transferencia_itens (transferencia_id, equipamento_id, item_nome, equipamento_novo)
                             VALUES (:tid, :eid, :nome, 1)'
                        )->execute([
                            'tid' => $transferenciaId,
                            'eid' => $equipamentoId,
                            'nome' => $nomeUnidade,
                        ]);
                    }
                    continue;
                }

                if ($it['modo'] === 'estoque') {
                    $stmtEs = $pdo->prepare('SELECT nome, categoria_id, marca, modelo, quantidade FROM estoque WHERE id = :id');
                    $stmtEs->execute(['id' => $it['estoque_id']]);
                    $es = $stmtEs->fetch();
                    if (!$es) {
                        continue;
                    }
                    $quantidadeTransferida = $it['quantidade'];
                    $quantidadeRestante = (int) $es['quantidade'] - $quantidadeTransferida;

                    $stmtLocalDestino = $pdo->prepare('SELECT id FROM locais_estoque WHERE nome = :nome');
                    $stmtLocalDestino->execute(['nome' => $dados['destino']]);
                    $destinoLocalId = (int) $stmtLocalDestino->fetchColumn();

                    if ($quantidadeRestante <= 0) {
                        // Transferiu a quantidade inteira — só muda o local do próprio item.
                        $pdo->prepare('UPDATE estoque SET local_id = :local_id WHERE id = :id')
                            ->execute(['local_id' => $destinoLocalId, 'id' => $it['estoque_id']]);
                        $estoqueIdFinal = $it['estoque_id'];
                    } else {
                        // Transferiu só parte — reduz a origem e soma num item igual
                        // já existente no destino (ou cria um novo lá), mesma regra
                        // de "não duplicar item igual" usada no cadastro de Estoque.
                        $pdo->prepare('UPDATE estoque SET quantidade = :q WHERE id = :id')
                            ->execute(['q' => $quantidadeRestante, 'id' => $it['estoque_id']]);

                        $stmtIgual = $pdo->prepare(
                            "SELECT id FROM estoque
                             WHERE LOWER(TRIM(nome)) = LOWER(TRIM(:nome))
                               AND LOWER(TRIM(COALESCE(marca, ''))) = LOWER(TRIM(COALESCE(:marca, '')))
                               AND LOWER(TRIM(COALESCE(modelo, ''))) = LOWER(TRIM(COALESCE(:modelo, '')))
                               AND local_id = :local_id
                             LIMIT 1"
                        );
                        $stmtIgual->execute([
                            'nome' => $es['nome'], 'marca' => $es['marca'], 'modelo' => $es['modelo'], 'local_id' => $destinoLocalId,
                        ]);
                        $igualId = $stmtIgual->fetchColumn();

                        if ($igualId) {
                            $pdo->prepare('UPDATE estoque SET quantidade = quantidade + :q WHERE id = :id')
                                ->execute(['q' => $quantidadeTransferida, 'id' => $igualId]);
                            $estoqueIdFinal = (int) $igualId;
                        } else {
                            $pdo->prepare(
                                'INSERT INTO estoque (nome, categoria_id, marca, modelo, quantidade, local_id)
                                 VALUES (:nome, :categoria_id, :marca, :modelo, :quantidade, :local_id)'
                            )->execute([
                                'nome' => $es['nome'], 'categoria_id' => $es['categoria_id'], 'marca' => $es['marca'],
                                'modelo' => $es['modelo'], 'quantidade' => $quantidadeTransferida, 'local_id' => $destinoLocalId,
                            ]);
                            $estoqueIdFinal = (int) $pdo->lastInsertId();
                        }
                    }

                    $stmtCat = $pdo->prepare('SELECT nome FROM categorias_estoque WHERE id = :id');
                    $stmtCat->execute(['id' => $es['categoria_id']]);
                    $categoriaNome = $stmtCat->fetchColumn() ?: null;
                    $itemNomeEstoque = trim($es['nome'] . ' ' . ($es['marca'] ?? '') . ' ' . ($es['modelo'] ?? ''));

                    registrarHistoricoEstoque($estoqueIdFinal, $es['nome'], $categoriaNome, 'Transferência',
                        'Transferido(s) ' . $quantidadeTransferida . ' unidade(s) de "' . $dados['origem'] . '" para "' . $dados['destino'] . '". Responsável: ' . $responsavelNome . '.');

                    $pdo->prepare(
                        "INSERT INTO transferencia_itens (transferencia_id, tipo_item, estoque_id, item_nome, quantidade)
                         VALUES (:tid, 'estoque', :eid, :nome, :qtd)"
                    )->execute([
                        'tid' => $transferenciaId,
                        'eid' => $estoqueIdFinal,
                        'nome' => $itemNomeEstoque,
                        'qtd' => $quantidadeTransferida,
                    ]);
                    continue;
                }

                $equipamentoId = $it['equipamento_id'];
                $stmtEq = $pdo->prepare('SELECT nome, patrimonio FROM equipamentos WHERE id = :id');
                $stmtEq->execute(['id' => $equipamentoId]);
                $eq = $stmtEq->fetch();
                if (!$eq) {
                    continue;
                }
                $equipamentoNome = nomeEquipamento($eq['nome'], $eq['patrimonio']);
                $pdo->prepare('UPDATE equipamentos SET localizacao = :destino WHERE id = :id')
                    ->execute(['destino' => $dados['destino'], 'id' => $equipamentoId]);
                registrarHistorico($equipamentoId, 'Transferência',
                    'Transferido de "' . $dados['origem'] . '" para "' . $dados['destino'] . '". Responsável: ' . $responsavelNome . '.');

                $pdo->prepare(
                    'INSERT INTO transferencia_itens (transferencia_id, equipamento_id, item_nome, equipamento_novo)
                     VALUES (:tid, :eid, :nome, 0)'
                )->execute([
                    'tid' => $transferenciaId,
                    'eid' => $equipamentoId,
                    'nome' => $equipamentoNome,
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
$estoquesDisponiveis = $pdo->query(
    "SELECT es.id, es.nome, es.marca, es.modelo, es.quantidade, lo.nome AS local_nome
     FROM estoque es LEFT JOIN locais_estoque lo ON lo.id = es.local_id
     WHERE es.quantidade > 0
     ORDER BY es.nome ASC"
)->fetchAll();
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
                    <select id="selectOrigem" name="origem" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($locaisCadastrados as $local): ?>
                            <option value="<?= e($local) ?>" <?= $dados['origem'] === $local ? 'selected' : '' ?>><?= e($local) ?></option>
                        <?php endforeach; ?>
                        <option value="novo">+ Novo local...</option>
                    </select>
                    <div class="input-group input-group-sm mt-2 d-none" id="novoOrigemWrap">
                        <input type="text" class="form-control" id="novoOrigemNome" placeholder="Nome do novo local">
                        <button type="button" class="btn btn-primary" id="novoOrigemSalvar"><i class="bi bi-check-lg"></i> Salvar</button>
                        <button type="button" class="btn btn-outline-secondary" id="novoOrigemCancelar"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="form-text text-danger d-none" id="novoOrigemErro"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Destino <span class="text-danger">*</span></label>
                    <select id="selectDestino" name="destino" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($locaisCadastrados as $local): ?>
                            <option value="<?= e($local) ?>" <?= $dados['destino'] === $local ? 'selected' : '' ?>><?= e($local) ?></option>
                        <?php endforeach; ?>
                        <option value="novo">+ Novo local...</option>
                    </select>
                    <div class="input-group input-group-sm mt-2 d-none" id="novoDestinoWrap">
                        <input type="text" class="form-control" id="novoDestinoNome" placeholder="Nome do novo local">
                        <button type="button" class="btn btn-primary" id="novoDestinoSalvar"><i class="bi bi-check-lg"></i> Salvar</button>
                        <button type="button" class="btn btn-outline-secondary" id="novoDestinoCancelar"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="form-text text-danger d-none" id="novoDestinoErro"></div>
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

    <div id="painelItensLocal" class="d-none"></div>

    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <strong>Itens da Transferência</strong>
            <button type="button" id="btnAdicionarItemTransferencia" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Adicionar item</button>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:16%">Tipo de item</th>
                        <th>Item</th>
                        <th style="width:10%">Quantidade</th>
                        <th style="width:14%">Patrimônio</th>
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
                <option value="estoque">Item de Estoque</option>
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
            <select class="form-select form-select-sm item-estoque-select d-none" name="">
                <option value="">Selecione...</option>
                <?php foreach ($estoquesDisponiveis as $es): ?>
                    <?php $labelEs = trim($es['nome'] . ' ' . ($es['marca'] ?? '') . ' ' . ($es['modelo'] ?? '')); ?>
                    <option value="<?= (int) $es['id'] ?>" data-quantidade="<?= (int) $es['quantidade'] ?>">
                        <?= e($labelEs) ?> — disponível: <?= (int) $es['quantidade'] ?><?= $es['local_nome'] ? ' (' . e($es['local_nome']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="number" min="1" max="999" value="1" class="form-control form-control-sm item-quantidade-input" name="" disabled>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm item-patrimonio-input" name="" placeholder="Opcional">
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
