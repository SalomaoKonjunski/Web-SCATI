<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;

$equipamento = [
    'patrimonio' => '', 'tipo' => 'Computador', 'marca' => '', 'modelo' => '', 'numero_serie' => '',
    'hostname' => '', 'processador' => '', 'memoria_ram' => '', 'armazenamento' => '', 'sistema_operacional' => '',
    'status' => 'Disponível', 'localizacao' => '', 'usuario_responsavel' => '', 'rede_id' => '',
    'ip' => '', 'modelo_toner' => '', 'qtd_toners' => '',
    'valor_aquisicao' => '', 'data_compra' => '', 'fornecedor' => '', 'numero_nota_fiscal' => '',
    'garantia' => '', 'valor_atual' => '', 'observacoes_financeiras' => '',
];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM equipamentos WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Equipamento não encontrado.');
        redirect('/modules/equipamentos/index.php');
    }
    $equipamento = array_merge($equipamento, $registro);
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e sanitiza os dados enviados
    foreach ($equipamento as $campo => $valorPadrao) {
        $equipamento[$campo] = trim($_POST[$campo] ?? '');
    }

    // Validações essenciais
    if ($equipamento['patrimonio'] === '') {
        $erros[] = 'O campo Patrimônio é obrigatório.';
    }
    if (!in_array($equipamento['tipo'], tiposEquipamento(), true)) {
        $erros[] = 'Tipo de equipamento inválido.';
    }
    if (!in_array($equipamento['status'], statusEquipamento(), true)) {
        $erros[] = 'Status inválido.';
    }

    // Verifica patrimônio único
    if ($equipamento['patrimonio'] !== '') {
        $sqlCheck = 'SELECT id FROM equipamentos WHERE patrimonio = :patrimonio' . ($edicao ? ' AND id != :id' : '');
        $stmtCheck = $pdo->prepare($sqlCheck);
        $paramsCheck = ['patrimonio' => $equipamento['patrimonio']];
        if ($edicao) {
            $paramsCheck['id'] = $id;
        }
        $stmtCheck->execute($paramsCheck);
        if ($stmtCheck->fetch()) {
            $erros[] = 'Já existe um equipamento cadastrado com este patrimônio.';
        }
    }

    if (empty($erros)) {
        // Normaliza campos numéricos/nulos
        $redeId = $equipamento['rede_id'] !== '' ? (int) $equipamento['rede_id'] : null;
        $qtdToners = $equipamento['qtd_toners'] !== '' ? (int) $equipamento['qtd_toners'] : null;
        $valorAquisicao = $equipamento['valor_aquisicao'] !== '' ? (float) str_replace(',', '.', $equipamento['valor_aquisicao']) : null;
        $valorAtual = $equipamento['valor_atual'] !== '' ? (float) str_replace(',', '.', $equipamento['valor_atual']) : null;
        $dataCompra = $equipamento['data_compra'] !== '' ? $equipamento['data_compra'] : null;

        $dadosParaSalvar = [
            'patrimonio' => $equipamento['patrimonio'],
            'tipo' => $equipamento['tipo'],
            'marca' => $equipamento['marca'] ?: null,
            'modelo' => $equipamento['modelo'] ?: null,
            'numero_serie' => $equipamento['numero_serie'] ?: null,
            'hostname' => $equipamento['hostname'] ?: null,
            'processador' => $equipamento['processador'] ?: null,
            'memoria_ram' => $equipamento['memoria_ram'] ?: null,
            'armazenamento' => $equipamento['armazenamento'] ?: null,
            'sistema_operacional' => $equipamento['sistema_operacional'] ?: null,
            'status' => $equipamento['status'],
            'localizacao' => $equipamento['localizacao'] ?: null,
            'usuario_responsavel' => $equipamento['usuario_responsavel'] ?: null,
            'rede_id' => $redeId,
            'ip' => $equipamento['ip'] ?: null,
            'modelo_toner' => $equipamento['modelo_toner'] ?: null,
            'qtd_toners' => $qtdToners,
            'valor_aquisicao' => $valorAquisicao,
            'data_compra' => $dataCompra,
            'fornecedor' => $equipamento['fornecedor'] ?: null,
            'numero_nota_fiscal' => $equipamento['numero_nota_fiscal'] ?: null,
            'garantia' => $equipamento['garantia'] ?: null,
            'valor_atual' => $valorAtual,
            'observacoes_financeiras' => $equipamento['observacoes_financeiras'] ?: null,
        ];

        try {
            if ($edicao) {
                // Monta o histórico comparando valores antigos x novos antes de salvar
                $mudancas = [];
                if ($registro['status'] !== $dadosParaSalvar['status']) {
                    $mudancas[] = ['Alteração', "Status alterado de \"{$registro['status']}\" para \"{$dadosParaSalvar['status']}\""];
                }
                if (($registro['localizacao'] ?? '') !== ($dadosParaSalvar['localizacao'] ?? '')) {
                    $de = $registro['localizacao'] ?: '(vazio)';
                    $para = $dadosParaSalvar['localizacao'] ?: '(vazio)';
                    $mudancas[] = ['Alteração', "Localização alterada de \"$de\" para \"$para\""];
                }
                if (($registro['usuario_responsavel'] ?? '') !== ($dadosParaSalvar['usuario_responsavel'] ?? '')) {
                    $de = $registro['usuario_responsavel'] ?: '(vazio)';
                    $para = $dadosParaSalvar['usuario_responsavel'] ?: '(vazio)';
                    $mudancas[] = ['Alteração', "Responsável alterado de \"$de\" para \"$para\""];
                }
                if ((int) ($registro['rede_id'] ?? 0) !== (int) ($dadosParaSalvar['rede_id'] ?? 0)) {
                    $mudancas[] = ['Alteração', 'Rede do equipamento foi alterada'];
                }
                $camposFinanceiros = ['valor_aquisicao', 'data_compra', 'fornecedor', 'numero_nota_fiscal', 'garantia', 'valor_atual', 'observacoes_financeiras'];
                foreach ($camposFinanceiros as $campoFin) {
                    if ((string) ($registro[$campoFin] ?? '') !== (string) ($dadosParaSalvar[$campoFin] ?? '')) {
                        $mudancas[] = ['Alteração', 'Informações financeiras atualizadas'];
                        break;
                    }
                }

                $sql = 'UPDATE equipamentos SET
                        patrimonio = :patrimonio, tipo = :tipo, marca = :marca, modelo = :modelo,
                        numero_serie = :numero_serie, hostname = :hostname, processador = :processador,
                        memoria_ram = :memoria_ram, armazenamento = :armazenamento, sistema_operacional = :sistema_operacional,
                        status = :status, localizacao = :localizacao, usuario_responsavel = :usuario_responsavel, rede_id = :rede_id,
                        ip = :ip, modelo_toner = :modelo_toner, qtd_toners = :qtd_toners,
                        valor_aquisicao = :valor_aquisicao, data_compra = :data_compra, fornecedor = :fornecedor,
                        numero_nota_fiscal = :numero_nota_fiscal, garantia = :garantia, valor_atual = :valor_atual,
                        observacoes_financeiras = :observacoes_financeiras
                        WHERE id = :id';
                $dadosParaSalvar['id'] = $id;
                $pdo->prepare($sql)->execute($dadosParaSalvar);

                foreach ($mudancas as [$evento, $descricao]) {
                    registrarHistorico($id, $evento, $descricao);
                }

                flash('success', 'Equipamento atualizado com sucesso.');
                redirect('/modules/equipamentos/view.php?id=' . $id);
            } else {
                $sql = 'INSERT INTO equipamentos
                        (patrimonio, tipo, marca, modelo, numero_serie, hostname, processador, memoria_ram,
                         armazenamento, sistema_operacional, status, localizacao, usuario_responsavel, rede_id,
                         ip, modelo_toner, qtd_toners, valor_aquisicao, data_compra, fornecedor, numero_nota_fiscal,
                         garantia, valor_atual, observacoes_financeiras)
                        VALUES
                        (:patrimonio, :tipo, :marca, :modelo, :numero_serie, :hostname, :processador, :memoria_ram,
                         :armazenamento, :sistema_operacional, :status, :localizacao, :usuario_responsavel, :rede_id,
                         :ip, :modelo_toner, :qtd_toners, :valor_aquisicao, :data_compra, :fornecedor, :numero_nota_fiscal,
                         :garantia, :valor_atual, :observacoes_financeiras)';
                $pdo->prepare($sql)->execute($dadosParaSalvar);
                $novoId = (int) $pdo->lastInsertId();

                registrarHistorico($novoId, 'Cadastro', 'Equipamento cadastrado');

                flash('success', 'Equipamento cadastrado com sucesso.');
                redirect('/modules/equipamentos/view.php?id=' . $novoId);
            }
        } catch (PDOException $e) {
            $erros[] = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

$redes = $pdo->query('SELECT id, nome FROM redes ORDER BY nome')->fetchAll();
$pageTitle = $edicao ? 'Editar Equipamento' : 'Novo Equipamento';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-pc-display me-2"></i><?= $edicao ? 'Editar Equipamento' : 'Novo Equipamento' ?>
    </h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" novalidate>
    <!-- Identificação -->
    <div class="card mb-3">
        <div class="card-header bg-white"><strong><i class="bi bi-tag me-1"></i> Identificação</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Patrimônio *</label>
                <input type="text" name="patrimonio" class="form-control" required value="<?= e($equipamento['patrimonio']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo *</label>
                <select name="tipo" id="tipo" class="form-select" required>
                    <?php foreach (tiposEquipamento() as $tipo): ?>
                        <option value="<?= e($tipo) ?>" <?= $equipamento['tipo'] === $tipo ? 'selected' : '' ?>><?= e($tipo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Marca</label>
                <input type="text" name="marca" class="form-control" value="<?= e($equipamento['marca']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Modelo</label>
                <input type="text" name="modelo" class="form-control" value="<?= e($equipamento['modelo']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Número de Série</label>
                <input type="text" name="numero_serie" class="form-control" value="<?= e($equipamento['numero_serie']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Hostname</label>
                <input type="text" name="hostname" class="form-control" value="<?= e($equipamento['hostname']) ?>">
            </div>
        </div>
    </div>

    <!-- Hardware -->
    <div class="card mb-3">
        <div class="card-header bg-white"><strong><i class="bi bi-cpu me-1"></i> Hardware</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Processador</label>
                <input type="text" name="processador" class="form-control" value="<?= e($equipamento['processador']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Memória RAM</label>
                <input type="text" name="memoria_ram" class="form-control" placeholder="Ex: 16GB" value="<?= e($equipamento['memoria_ram']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Armazenamento</label>
                <input type="text" name="armazenamento" class="form-control" placeholder="Ex: SSD 512GB" value="<?= e($equipamento['armazenamento']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Sistema Operacional</label>
                <input type="text" name="sistema_operacional" class="form-control" value="<?= e($equipamento['sistema_operacional']) ?>">
            </div>
        </div>
    </div>

    <!-- Campos específicos de impressora -->
    <div class="card mb-3" id="printerFields">
        <div class="card-header bg-white"><strong><i class="bi bi-printer me-1"></i> Dados da Impressora</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Endereço IP</label>
                <input type="text" name="ip" class="form-control" value="<?= e($equipamento['ip']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Modelo do Toner</label>
                <input type="text" name="modelo_toner" class="form-control" value="<?= e($equipamento['modelo_toner']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Qtd. de Toners Disponíveis</label>
                <input type="number" min="0" name="qtd_toners" class="form-control" value="<?= e((string) $equipamento['qtd_toners']) ?>">
            </div>
        </div>
    </div>

    <!-- Localização e uso -->
    <div class="card mb-3">
        <div class="card-header bg-white"><strong><i class="bi bi-geo-alt me-1"></i> Localização e Uso</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Status *</label>
                <select name="status" class="form-select" required>
                    <?php foreach (statusEquipamento() as $status): ?>
                        <option value="<?= e($status) ?>" <?= $equipamento['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Localização</label>
                <input type="text" name="localizacao" class="form-control" placeholder="Ex: Sala Financeiro" value="<?= e($equipamento['localizacao']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Usuário Responsável</label>
                <input type="text" name="usuario_responsavel" class="form-control" value="<?= e($equipamento['usuario_responsavel']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Rede</label>
                <select name="rede_id" class="form-select">
                    <option value="">Nenhuma</option>
                    <?php foreach ($redes as $rede): ?>
                        <option value="<?= (int) $rede['id'] ?>" <?= (string) $equipamento['rede_id'] === (string) $rede['id'] ? 'selected' : '' ?>><?= e($rede['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Financeiro (opcional) -->
    <div class="card mb-3">
        <div class="card-header bg-white">
            <strong><i class="bi bi-cash-coin me-1"></i> Financeiro (opcional)</strong>
        </div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Valor de Aquisição</label>
                <input type="text" name="valor_aquisicao" class="form-control" placeholder="0,00" value="<?= e((string) $equipamento['valor_aquisicao']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data da Compra</label>
                <input type="date" name="data_compra" class="form-control" value="<?= e($equipamento['data_compra']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Fornecedor</label>
                <input type="text" name="fornecedor" class="form-control" value="<?= e($equipamento['fornecedor']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Número da Nota Fiscal</label>
                <input type="text" name="numero_nota_fiscal" class="form-control" value="<?= e($equipamento['numero_nota_fiscal']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Garantia</label>
                <input type="text" name="garantia" class="form-control" placeholder="Ex: até 03/2027" value="<?= e($equipamento['garantia']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Valor Atual</label>
                <input type="text" name="valor_atual" class="form-control" placeholder="0,00" value="<?= e((string) $equipamento['valor_atual']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Observações Financeiras</label>
                <input type="text" name="observacoes_financeiras" class="form-control" value="<?= e($equipamento['observacoes_financeiras']) ?>">
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
