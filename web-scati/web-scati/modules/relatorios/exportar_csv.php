<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();

$origemChave = (string) ($_GET['origem'] ?? '');
$colunasChaves = is_array($_GET['colunas'] ?? null) ? $_GET['colunas'] : [];

$filtros = [];
$camposGet = is_array($_GET['filtro_campo'] ?? null) ? $_GET['filtro_campo'] : [];
$operadoresGet = is_array($_GET['filtro_operador'] ?? null) ? $_GET['filtro_operador'] : [];
$valoresGet = is_array($_GET['filtro_valor'] ?? null) ? $_GET['filtro_valor'] : [];
foreach ($camposGet as $i => $campo) {
    if ($campo === '') {
        continue;
    }
    $filtros[] = ['campo' => $campo, 'operador' => $operadoresGet[$i] ?? '', 'valor' => $valoresGet[$i] ?? ''];
}

$consulta = montarRelatorioPersonalizado($origemChave, $colunasChaves, $filtros);

if ($consulta === null) {
    flash('danger', 'Selecione a origem e ao menos uma coluna antes de exportar.');
    redirect('/modules/relatorios/construtor.php');
}

$stmt = $pdo->prepare($consulta['sql']);
$stmt->execute($consulta['params']);
$linhas = $stmt->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="relatorio_' . $origemChave . '_' . date('Y-m-d') . '.csv"');

$saida = fopen('php://output', 'w');
fwrite($saida, "\xEF\xBB\xBF"); // BOM para o Excel reconhecer UTF-8 corretamente

fputcsv($saida, array_map(fn (array $def): string => $def['label'], array_values($consulta['colunas'])), ';');

foreach ($linhas as $linha) {
    $linhaFormatada = [];
    foreach ($consulta['colunas'] as $chave => $def) {
        $linhaFormatada[] = formatarValorRelatorioPersonalizado($linha[$chave] ?? null, $def['tipo']);
    }
    fputcsv($saida, $linhaFormatada, ';');
}

fclose($saida);
exit;
