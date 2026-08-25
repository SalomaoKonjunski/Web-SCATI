<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$pageTitle = 'Construtor de Relatório';

$origens = origensRelatorioPersonalizado();
$operadoresPorTipo = operadoresRelatorioPersonalizado();

$erros = [];

// Salva a configuração atual (origem + colunas + filtros) como um
// relatório favorito reutilizável.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_favorito'])) {
    $nomeFavorito = trim($_POST['nome_favorito'] ?? '');
    $origemPost = (string) ($_POST['origem'] ?? '');
    $colunasPost = is_array($_POST['colunas'] ?? null) ? $_POST['colunas'] : [];

    $filtrosPost = [];
    $camposPost = is_array($_POST['filtro_campo'] ?? null) ? $_POST['filtro_campo'] : [];
    $operadoresPost = is_array($_POST['filtro_operador'] ?? null) ? $_POST['filtro_operador'] : [];
    $valoresPost = is_array($_POST['filtro_valor'] ?? null) ? $_POST['filtro_valor'] : [];
    foreach ($camposPost as $i => $campo) {
        if ($campo === '') {
            continue;
        }
        $filtrosPost[] = ['campo' => $campo, 'operador' => $operadoresPost[$i] ?? '', 'valor' => $valoresPost[$i] ?? ''];
    }

    $consultaValida = montarRelatorioPersonalizado($origemPost, $colunasPost, $filtrosPost);

    if ($nomeFavorito === '') {
        $erros[] = 'Informe um nome para salvar o relatório.';
    } elseif ($consultaValida === null) {
        $erros[] = 'Selecione a origem e ao menos uma coluna antes de salvar.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO relatorios_personalizados (nome, origem, colunas, filtros, criado_por)
             VALUES (:nome, :origem, :colunas, :filtros, :criado_por)'
        );
        $stmt->execute([
            'nome' => $nomeFavorito,
            'origem' => $origemPost,
            'colunas' => json_encode(array_values($colunasPost)),
            'filtros' => json_encode($filtrosPost),
            'criado_por' => usuarioLogado()['usuario'] ?? null,
        ]);
        flash('success', 'Relatório "' . $nomeFavorito . '" salvo com sucesso.');
        redirect('/modules/relatorios/construtor.php?favorito_id=' . $pdo->lastInsertId());
    }
}

// O estado atual (origem, colunas, filtros) vem de um favorito salvo
// (?favorito_id=) ou dos parâmetros da própria URL (?origem=...).
$favoritoId = (int) ($_GET['favorito_id'] ?? 0);
$favoritoAtual = null;
if ($favoritoId > 0) {
    $stmtFav = $pdo->prepare('SELECT * FROM relatorios_personalizados WHERE id = :id');
    $stmtFav->execute(['id' => $favoritoId]);
    $favoritoAtual = $stmtFav->fetch();
    if (!$favoritoAtual) {
        flash('danger', 'Relatório salvo não encontrado.');
        redirect('/modules/relatorios/construtor.php');
    }
}

if ($favoritoAtual) {
    $origemAtual = $favoritoAtual['origem'];
    $colunasSelecionadas = json_decode($favoritoAtual['colunas'], true) ?: [];
    $filtrosAtuais = json_decode($favoritoAtual['filtros'], true) ?: [];
} else {
    $origemAtual = (string) ($_GET['origem'] ?? 'equipamentos');
    $colunasSelecionadas = is_array($_GET['colunas'] ?? null) ? $_GET['colunas'] : [];

    $filtrosAtuais = [];
    $camposGet = is_array($_GET['filtro_campo'] ?? null) ? $_GET['filtro_campo'] : [];
    $operadoresGet = is_array($_GET['filtro_operador'] ?? null) ? $_GET['filtro_operador'] : [];
    $valoresGet = is_array($_GET['filtro_valor'] ?? null) ? $_GET['filtro_valor'] : [];
    foreach ($camposGet as $i => $campo) {
        if ($campo === '') {
            continue;
        }
        $filtrosAtuais[] = ['campo' => $campo, 'operador' => $operadoresGet[$i] ?? '', 'valor' => $valoresGet[$i] ?? ''];
    }
}

if (!isset($origens[$origemAtual])) {
    $origemAtual = 'equipamentos';
}
if (!is_array($colunasSelecionadas)) {
    $colunasSelecionadas = [];
}
$origemDefAtual = $origens[$origemAtual];

// Gera o relatório assim que houver origem + ao menos 1 coluna válidas.
$gerado = false;
$linhas = [];
$colunasResultado = [];
if (!empty($colunasSelecionadas)) {
    $consulta = montarRelatorioPersonalizado($origemAtual, $colunasSelecionadas, $filtrosAtuais);
    if ($consulta !== null) {
        $stmt = $pdo->prepare($consulta['sql']);
        $stmt->execute($consulta['params']);
        $linhas = $stmt->fetchAll();
        $colunasResultado = $consulta['colunas'];
        $gerado = true;
    }
}

/**
 * Renderiza uma linha do bloco de filtros (passo 3). Usada tanto para os
 * filtros já configurados (na primeira carga da página) quanto para a
 * <template> clonada via JS ao clicar em "Adicionar filtro".
 */
function renderFiltroLinha(array $origemDef, array $operadoresPorTipo, string $campo, string $operador, string $valor): void
{
    $def = $origemDef['colunas'][$campo] ?? null;
    $tipoHtml = 'text';
    if ($def) {
        $tipoHtml = match ($def['tipo']) {
            'numero', 'dinheiro' => 'number',
            'data', 'datahora' => 'date',
            default => 'text',
        };
    }
    $semValor = in_array($operador, ['vazio', 'nao_vazio'], true);
    static $contador = 0;
    $contador++;
    $datalistId = 'filtroDatalist_' . $contador;
    ?>
    <div class="filtro-linha d-flex gap-2 align-items-center mb-2">
        <select class="form-select form-select-sm filtro-campo-select" name="filtro_campo[]" style="max-width: 200px;">
            <option value="">Selecione...</option>
            <?php foreach ($origemDef['colunas'] as $chaveCol => $defCol): ?>
                <option value="<?= e($chaveCol) ?>" <?= $campo === $chaveCol ? 'selected' : '' ?>><?= e($defCol['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select form-select-sm filtro-operador-select" name="filtro_operador[]" style="max-width: 170px;">
            <?php if ($def): ?>
                <?php foreach ($operadoresPorTipo[$def['tipo']] as $chaveOp => $labelOp): ?>
                    <option value="<?= e($chaveOp) ?>" <?= $operador === $chaveOp ? 'selected' : '' ?>><?= e($labelOp) ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <input type="<?= e($tipoHtml) ?>" class="form-control form-control-sm filtro-valor-input" name="filtro_valor[]"
               value="<?= e($valor) ?>" list="<?= e($datalistId) ?>"
               style="max-width: 200px; <?= $semValor ? 'display:none;' : '' ?>">
        <datalist id="<?= e($datalistId) ?>">
            <?php if ($def && !empty($def['opcoes'])): ?>
                <?php foreach ($def['opcoes'] as $opcao): ?>
                    <option value="<?= e($opcao) ?>">
                <?php endforeach; ?>
            <?php endif; ?>
        </datalist>
        <button type="button" class="btn btn-sm btn-outline-danger btn-remover-filtro" title="Remover filtro"><i class="bi bi-trash"></i></button>
    </div>
    <?php
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><i class="bi bi-magic me-2"></i>Construtor de Relatório</h1>
        <a href="index.php" class="small"><i class="bi bi-arrow-left"></i> Voltar para Relatórios</a>
    </div>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($gerado): ?>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <strong><?= e($origemDefAtual['label']) ?></strong>
            <?php if ($favoritoAtual): ?>
                <span class="text-muted">— <?= e($favoritoAtual['nome']) ?></span>
            <?php endif; ?>
            <div><a href="#construtorForm" class="small"><i class="bi bi-pencil"></i> Editar origem, colunas ou filtros</a></div>
        </div>
        <div class="d-flex gap-2 d-print-none">
            <a class="btn btn-sm btn-outline-secondary" href="exportar_csv.php?<?= e(http_build_query(['origem' => $origemAtual, 'colunas' => $colunasSelecionadas, 'filtro_campo' => array_column($filtrosAtuais, 'campo'), 'filtro_operador' => array_column($filtrosAtuais, 'operador'), 'filtro_valor' => array_column($filtrosAtuais, 'valor')])) ?>">
                <i class="bi bi-filetype-csv"></i> Exportar CSV
            </a>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
        </div>
    </div>

    <?php if (!empty($filtrosAtuais)): ?>
        <div class="card mb-3">
            <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
                <span class="text-muted small me-1">Filtros aplicados:</span>
                <?php foreach ($filtrosAtuais as $filtro): ?>
                    <?php
                        $defCampo = $origemDefAtual['colunas'][$filtro['campo']] ?? null;
                        if (!$defCampo) {
                            continue;
                        }
                        $labelOperador = $operadoresPorTipo[$defCampo['tipo']][$filtro['operador']] ?? $filtro['operador'];
                        $semValorBadge = in_array($filtro['operador'], ['vazio', 'nao_vazio'], true);
                    ?>
                    <span class="badge bg-light text-dark border">
                        <?= e($defCampo['label']) ?> <?= e($labelOperador) ?><?= $semValorBadge ? '' : ' ' . e((string) $filtro['valor']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Resultado</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><?php foreach ($colunasResultado as $def): ?><th><?= e($def['label']) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php if (empty($linhas)): ?>
                        <tr><td colspan="<?= count($colunasResultado) ?>" class="text-center text-muted py-4">Nenhum registro encontrado para esta configuração.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($linhas as $linha): ?>
                        <tr>
                            <?php foreach ($colunasResultado as $chave => $def): ?>
                                <td><?= e(formatarValorRelatorioPersonalizado($linha[$chave] ?? null, $def['tipo'])) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="text-muted small mt-2"><?= count($linhas) ?> registro(s) encontrado(s).</p>

    <div class="card mb-5 d-print-none">
        <div class="card-body">
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="salvar_favorito" value="1">
                <input type="hidden" name="origem" value="<?= e($origemAtual) ?>">
                <?php foreach ($colunasSelecionadas as $chaveCol): ?>
                    <input type="hidden" name="colunas[]" value="<?= e($chaveCol) ?>">
                <?php endforeach; ?>
                <?php foreach ($filtrosAtuais as $filtro): ?>
                    <input type="hidden" name="filtro_campo[]" value="<?= e($filtro['campo']) ?>">
                    <input type="hidden" name="filtro_operador[]" value="<?= e($filtro['operador']) ?>">
                    <input type="hidden" name="filtro_valor[]" value="<?= e((string) $filtro['valor']) ?>">
                <?php endforeach; ?>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Salvar este relatório como favorito</label>
                    <input type="text" name="nome_favorito" class="form-control form-control-sm" placeholder="Nome do relatório" value="<?= $favoritoAtual ? e($favoritoAtual['nome']) : '' ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-bookmark"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<form method="get" id="construtorForm" class="d-print-none">

    <!-- Passo 1: Origem dos dados -->
    <div class="card mb-3">
        <div class="card-header bg-white">
            <strong><span class="badge bg-primary rounded-circle me-2">1</span>Origem dos dados</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($origens as $chaveOrigem => $def): ?>
                    <div class="col-md-3">
                        <label class="card h-100 p-3 <?= $origemAtual === $chaveOrigem ? 'border-primary border-2 shadow-sm' : '' ?>" style="cursor:pointer;">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="origem" value="<?= e($chaveOrigem) ?>"
                                       <?= $origemAtual === $chaveOrigem ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span class="fw-semibold"><i class="bi <?= e($def['icone']) ?> me-1"></i> <?= e($def['label']) ?></span>
                            </div>
                            <div class="text-muted small mt-1"><?= e($def['descricao']) ?></div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Passo 2: Colunas -->
    <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong><span class="badge bg-primary rounded-circle me-2">2</span>Colunas</strong>
            <span class="text-muted small"><?= count($colunasSelecionadas) ?> de <?= count($origemDefAtual['colunas']) ?> selecionadas</span>
        </div>
        <div class="card-body">
            <p class="text-muted small">Marque as colunas que devem aparecer no relatório.</p>
            <div class="row g-2">
                <?php foreach ($origemDefAtual['colunas'] as $chaveCol => $defCol): ?>
                    <div class="col-md-3 col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="colunas[]" value="<?= e($chaveCol) ?>" id="col_<?= e($chaveCol) ?>"
                                   <?= in_array($chaveCol, $colunasSelecionadas, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="col_<?= e($chaveCol) ?>"><?= e($defCol['label']) ?></label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Passo 3: Filtros -->
    <div class="card mb-3">
        <div class="card-header bg-white">
            <strong><span class="badge bg-primary rounded-circle me-2">3</span>Filtros</strong>
        </div>
        <div class="card-body">
            <p class="text-muted small">Opcional. Combine quantos filtros quiser — todos precisam ser verdadeiros ao mesmo tempo (E).</p>

            <div id="filtrosContainer">
                <?php foreach ($filtrosAtuais as $filtro): ?>
                    <?php renderFiltroLinha($origemDefAtual, $operadoresPorTipo, (string) $filtro['campo'], (string) $filtro['operador'], (string) $filtro['valor']); ?>
                <?php endforeach; ?>
            </div>

            <button class="btn btn-sm btn-outline-primary" type="button" id="btnAdicionarFiltro"><i class="bi bi-plus-lg"></i> Adicionar filtro</button>

            <template id="filtroLinhaTemplate">
                <?php renderFiltroLinha($origemDefAtual, $operadoresPorTipo, '', '', ''); ?>
            </template>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-bar-chart-line"></i> Gerar Relatório</button>
    </div>
</form>

<script>
    window.SCATI_COLUNAS_ORIGEM = <?= json_encode(array_map(
        fn (array $def): array => ['label' => $def['label'], 'tipo' => $def['tipo'], 'opcoes' => $def['opcoes'] ?? null],
        $origemDefAtual['colunas']
    ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.SCATI_OPERADORES_POR_TIPO = <?= json_encode($operadoresPorTipo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
