<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('relatorios', 'alterar');

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

// Opções (e contagem de registros) de cada campo que pode virar checkbox
// em vez de texto/número/data livre: os de lista fixa (Tipo, Status,
// Categoria, Prioridade...) e também os de texto com poucos valores
// distintos realmente cadastrados (Marca, Localização, Responsável...) —
// ver valoresDistintosRelatorioPersonalizado(), em includes/functions.php.
// Um campo de texto tipicamente único por registro (Nome, Observações...)
// não entra aqui e continua como texto livre. Usado tanto no bloco fixo
// do campo principal quanto nas linhas de Filtros (passo 3).
$opcoesPorCampo = [];
foreach ($origemDefAtual['colunas'] as $chaveCol => $defCol) {
    if (in_array($defCol['tipo'], ['select', 'booleano'], true)) {
        $opcoesPorCampo[$chaveCol] = [
            'opcoes' => $defCol['opcoes'],
            'contagens' => contagensPorOpcaoRelatorioPersonalizado($origemAtual, $chaveCol),
        ];
    } elseif ($defCol['tipo'] === 'texto') {
        $distintos = valoresDistintosRelatorioPersonalizado($origemAtual, $chaveCol);
        if ($distintos !== null) {
            $opcoesPorCampo[$chaveCol] = ['opcoes' => array_keys($distintos), 'contagens' => $distintos];
        }
    }
}

// Separa o filtro do campo principal (ver campo_principal em
// origensRelatorioPersonalizado()) dos demais — ele tem seu próprio bloco
// fixo logo abaixo de "Colunas", então não entra na lista do passo 3
// "Filtros" pra não aparecer duplicado. $filtrosAtuais continua com TODOS
// os filtros (incluindo o do campo principal) pra gerar o relatório, o
// CSV e o favorito salvo normalmente.
$campoPrincipalAtual = $origemDefAtual['campo_principal'] ?? null;
$valoresCampoPrincipal = [];
$filtrosSecundarios = [];
foreach ($filtrosAtuais as $filtro) {
    if ($campoPrincipalAtual !== null && $filtro['campo'] === $campoPrincipalAtual) {
        $valoresCampoPrincipal = array_values(array_filter(array_map('trim', explode(',', (string) $filtro['valor'])), fn ($v) => $v !== ''));
        continue;
    }
    $filtrosSecundarios[] = $filtro;
}
$contagensCampoPrincipal = $campoPrincipalAtual !== null ? ($opcoesPorCampo[$campoPrincipalAtual]['contagens'] ?? []) : [];

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
 * <template> clonada via JS ao clicar em "Adicionar filtro". O campo
 * principal da origem (ver campo_principal em origensRelatorioPersonalizado())
 * não aparece como opção aqui — ele já tem seu próprio bloco fixo logo
 * abaixo de "Colunas" (renderBlocoCampoPrincipal()), então oferecê-lo de
 * novo aqui só duplicaria o mesmo filtro.
 *
 * $opcoesPorCampo (ver construtor.php) diz quais campos têm uma lista de
 * opções pra virar checkbox — tanto os de lista fixa (Tipo, Status...)
 * quanto os de texto com poucos valores distintos cadastrados (Marca,
 * Localização...). Um campo fora dessa lista continua com o seletor de
 * operador normal (contém, é igual a, está vazio...) e um valor livre.
 */
function renderFiltroLinha(array $origemDef, array $operadoresPorTipo, array $opcoesPorCampo, string $campo, string $operador, string $valor): void
{
    $campoPrincipal = $origemDef['campo_principal'] ?? null;
    $def = $origemDef['colunas'][$campo] ?? null;
    $opcoesInfo = $opcoesPorCampo[$campo] ?? null;
    $ehSelecao = $def && $opcoesInfo !== null;
    $tipoHtml = 'text';
    if ($def && !$ehSelecao) {
        $tipoHtml = match ($def['tipo']) {
            'numero', 'dinheiro' => 'number',
            'data', 'datahora' => 'date',
            default => 'text',
        };
    }
    $semValor = !$ehSelecao && in_array($operador, ['vazio', 'nao_vazio'], true);
    static $contador = 0;
    $contador++;
    $linhaId = 'filtroLinha_' . $contador;
    $valoresMarcados = $ehSelecao
        ? array_values(array_filter(array_map('trim', explode(',', $valor)), fn ($v) => $v !== ''))
        : [];
    ?>
    <div class="filtro-linha d-flex flex-wrap gap-2 align-items-center mb-2">
        <select class="form-select form-select-sm filtro-campo-select" name="filtro_campo[]" style="max-width: 200px;">
            <option value="">Selecione...</option>
            <?php foreach ($origemDef['colunas'] as $chaveCol => $defCol): ?>
                <?php if ($chaveCol === $campoPrincipal) continue; ?>
                <option value="<?= e($chaveCol) ?>" <?= $campo === $chaveCol ? 'selected' : '' ?>><?= e($defCol['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select form-select-sm filtro-operador-select" name="filtro_operador[]"
                style="max-width: 170px; <?= $ehSelecao ? 'display:none;' : '' ?>" <?= $ehSelecao ? 'disabled' : '' ?>>
            <?php if ($def && !$ehSelecao): ?>
                <?php foreach ($operadoresPorTipo[$def['tipo']] as $chaveOp => $labelOp): ?>
                    <option value="<?= e($chaveOp) ?>" <?= $operador === $chaveOp ? 'selected' : '' ?>><?= e($labelOp) ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <?php
            // O operador some de vista quando ehSelecao, mas precisa
            // continuar mandando ALGUM valor pro "filtro_operador[]" dessa
            // linha (senão esse array fica mais curto que filtro_campo[] e
            // filtro_valor[], desalinhando o índice das linhas seguintes no
            // parsing — por isso o hidden abaixo, espelhando o mesmo
            // esquema de habilitar/desabilitar já usado no valor).
        ?>
        <input type="hidden" class="filtro-operador-oculto" name="filtro_operador[]" value="igual" <?= $ehSelecao ? '' : 'disabled' ?>>
        <?php
            // Só um dos dois (input de texto ou grupo de checkboxes) fica
            // habilitado por vez — o desabilitado não é enviado no envio do
            // formulário (GET/POST), então nunca sobra um "filtro_valor[]"
            // duplicado pra esta linha.
            $estiloInput = ($ehSelecao || $semValor) ? 'display:none;' : '';
        ?>
        <input type="<?= e($tipoHtml) ?>" class="form-control form-control-sm filtro-valor-input" name="filtro_valor[]"
               value="<?= e($valor) ?>" list="<?= e($linhaId) ?>_datalist"
               style="max-width: 200px; <?= $estiloInput ?>" <?= $ehSelecao ? 'disabled' : '' ?>>
        <datalist id="<?= e($linhaId) ?>_datalist">
            <?php if ($def && !$ehSelecao && !empty($def['opcoes'])): ?>
                <?php foreach ($def['opcoes'] as $opcao): ?>
                    <option value="<?= e($opcao) ?>">
                <?php endforeach; ?>
            <?php endif; ?>
        </datalist>
        <div class="filtro-valor-opcoes d-flex flex-wrap gap-2" style="<?= $ehSelecao ? '' : 'display:none;' ?>">
            <?php if ($ehSelecao): ?>
                <?php foreach ($opcoesInfo['opcoes'] as $indiceOpcao => $opcao): ?>
                    <?php $qtd = $opcoesInfo['contagens'][$opcao] ?? 0; ?>
                    <div class="form-check form-check-inline m-0">
                        <input class="form-check-input filtro-valor-opcao" type="checkbox" value="<?= e($opcao) ?>"
                               <?= in_array($opcao, $valoresMarcados, true) ? 'checked' : '' ?>
                               id="<?= e($linhaId) ?>_opt<?= (int) $indiceOpcao ?>">
                        <label class="form-check-label small" for="<?= e($linhaId) ?>_opt<?= (int) $indiceOpcao ?>">
                            <?= e($opcao) ?> <span class="text-muted">(<?= $qtd > 0 ? $qtd : 'nenhum cadastrado' ?>)</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <input type="hidden" class="filtro-valor-oculto" name="filtro_valor[]"
                   value="<?= e(implode(',', $valoresMarcados)) ?>" <?= $ehSelecao ? '' : 'disabled' ?>>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger btn-remover-filtro" title="Remover filtro"><i class="bi bi-trash"></i></button>
    </div>
    <?php
}

/**
 * Renderiza o bloco fixo do campo principal da origem (ex.: "Tipo" em
 * Equipamentos e Licenças, "Categoria" em Estoque, "Status" em Chamados —
 * ver campo_principal em origensRelatorioPersonalizado()), logo abaixo de
 * "Colunas". Mostra sempre todas as opções possíveis como checkbox, com a
 * quantidade de registros já cadastrados com cada uma — já funciona como
 * um filtro por conta própria (mesmo mecanismo de "está em qualquer uma
 * das marcadas" do passo 3), sem precisar passar por "Adicionar filtro".
 */
function renderBlocoCampoPrincipal(array $origemDef, array $valoresMarcados, array $contagens): void
{
    $campoPrincipal = $origemDef['campo_principal'] ?? null;
    $def = $campoPrincipal ? ($origemDef['colunas'][$campoPrincipal] ?? null) : null;
    if (!$def) {
        return;
    }
    ?>
    <div class="card mb-3 filtro-principal-bloco">
        <div class="card-header bg-white">
            <strong><?= e($def['label']) ?></strong>
        </div>
        <div class="card-body">
            <p class="text-muted small">Marque pra filtrar o relatório por <?= e(mb_strtolower($def['label'])) ?> — pode marcar mais de um. Deixe tudo desmarcado pra trazer qualquer um.</p>
            <div class="d-flex flex-wrap gap-3">
                <?php foreach ($def['opcoes'] as $indiceOpcao => $opcao): ?>
                    <?php $qtd = $contagens[$opcao] ?? 0; ?>
                    <div class="form-check">
                        <input class="form-check-input filtro-valor-opcao" type="checkbox" value="<?= e($opcao) ?>"
                               <?= in_array($opcao, $valoresMarcados, true) ? 'checked' : '' ?>
                               id="filtroPrincipal_opt<?= (int) $indiceOpcao ?>">
                        <label class="form-check-label" for="filtroPrincipal_opt<?= (int) $indiceOpcao ?>">
                            <?= e($opcao) ?>
                            <span class="text-muted small">(<?= $qtd > 0 ? $qtd : 'nenhum cadastrado' ?>)</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="filtro_campo[]" value="<?= e($campoPrincipal) ?>">
            <input type="hidden" name="filtro_operador[]" value="igual">
            <input type="hidden" class="filtro-valor-oculto" name="filtro_valor[]" value="<?= e(implode(',', $valoresMarcados)) ?>">
        </div>
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
                        // Campo com opções (lista fixa ou texto com poucos
                        // valores distintos): "Tipo: Computador, Notebook"
                        // (uma ou mais opções marcadas, sem operador). Os
                        // demais: "Nome contém abc" / "Valor Atual maior que 100".
                        if (isset($opcoesPorCampo[$filtro['campo']])) {
                            $valoresBadge = array_filter(array_map('trim', explode(',', (string) $filtro['valor'])), fn ($v) => $v !== '');
                            if (empty($valoresBadge)) {
                                continue;
                            }
                            $textoBadge = e($defCampo['label']) . ': ' . e(implode(', ', $valoresBadge));
                        } else {
                            $labelOperador = $operadoresPorTipo[$defCampo['tipo']][$filtro['operador']] ?? $filtro['operador'];
                            $semValorBadge = in_array($filtro['operador'], ['vazio', 'nao_vazio'], true);
                            $textoBadge = e($defCampo['label']) . ' ' . e($labelOperador) . ($semValorBadge ? '' : ' ' . e((string) $filtro['valor']));
                        }
                    ?>
                    <span class="badge bg-light text-dark border"><?= $textoBadge ?></span>
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

    <?php renderBlocoCampoPrincipal($origemDefAtual, $valoresCampoPrincipal, $contagensCampoPrincipal); ?>

    <!-- Passo 3: Filtros -->
    <div class="card mb-3">
        <div class="card-header bg-white">
            <strong><span class="badge bg-primary rounded-circle me-2">3</span>Filtros</strong>
        </div>
        <div class="card-body">
            <p class="text-muted small">Opcional. Combine quantos filtros quiser — todos precisam ser verdadeiros ao mesmo tempo (E).</p>

            <div id="filtrosContainer">
                <?php foreach ($filtrosSecundarios as $filtro): ?>
                    <?php renderFiltroLinha($origemDefAtual, $operadoresPorTipo, $opcoesPorCampo, (string) $filtro['campo'], (string) $filtro['operador'], (string) $filtro['valor']); ?>
                <?php endforeach; ?>
            </div>

            <button class="btn btn-sm btn-outline-primary" type="button" id="btnAdicionarFiltro"><i class="bi bi-plus-lg"></i> Adicionar filtro</button>

            <template id="filtroLinhaTemplate">
                <?php renderFiltroLinha($origemDefAtual, $operadoresPorTipo, $opcoesPorCampo, '', '', ''); ?>
            </template>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="bi bi-bar-chart-line"></i> Gerar Relatório</button>
    </div>
</form>

<script>
    window.SCATI_COLUNAS_ORIGEM = <?php
        // array_map() com dois arrays não preserva as chaves originais (vira
        // lista sequencial) — por isso o array_combine(), pra manter a chave
        // de cada campo (nome, tipo, marca...) como chave do objeto JS.
        $colunasParaJs = array_combine(
            array_keys($origemDefAtual['colunas']),
            array_map(
                fn (array $def, string $chave): array => [
                    'label' => $def['label'],
                    'tipo' => $def['tipo'],
                    'opcoes' => $opcoesPorCampo[$chave]['opcoes'] ?? null,
                    'contagens' => $opcoesPorCampo[$chave]['contagens'] ?? null,
                ],
                $origemDefAtual['colunas'],
                array_keys($origemDefAtual['colunas'])
            )
        );
        echo json_encode($colunasParaJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    ?>;
    window.SCATI_OPERADORES_POR_TIPO = <?= json_encode($operadoresPorTipo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
