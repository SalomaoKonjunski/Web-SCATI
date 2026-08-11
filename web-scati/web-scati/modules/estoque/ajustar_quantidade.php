<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/estoque/index.php');
}

$id = (int) ($_POST['id'] ?? 0);
$acao = $_POST['acao'] ?? '';
$valor = (int) ($_POST['valor'] ?? 0);

if ($id <= 0 || !in_array($acao, ['aumentar', 'diminuir'], true) || $valor < 1) {
    flash('danger', 'Informe uma quantidade válida (maior que zero) para ajustar o estoque.');
    redirect('/modules/estoque/index.php');
}

$stmt = $pdo->prepare('SELECT nome, categoria_id, quantidade FROM estoque WHERE id = :id');
$stmt->execute(['id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    flash('danger', 'Item de estoque não encontrado.');
    redirect('/modules/estoque/index.php');
}

$quantidadeAtual = (int) $item['quantidade'];

if ($acao === 'aumentar') {
    $novaQuantidade = $quantidadeAtual + $valor;
    $ajusteReal = $valor;
} else {
    $ajusteReal = min($valor, $quantidadeAtual);
    $novaQuantidade = $quantidadeAtual - $ajusteReal;
}

$pdo->prepare('UPDATE estoque SET quantidade = :quantidade WHERE id = :id')
    ->execute(['quantidade' => $novaQuantidade, 'id' => $id]);

$stmtCat = $pdo->prepare('SELECT nome FROM categorias_estoque WHERE id = :id');
$stmtCat->execute(['id' => $item['categoria_id']]);
$categoriaNome = $stmtCat->fetchColumn() ?: null;

$sinal = $acao === 'aumentar' ? '+' . $ajusteReal : '-' . $ajusteReal;
registrarHistoricoEstoque(
    $id,
    $item['nome'],
    $categoriaNome,
    'Alteração',
    'Quantidade alterada de ' . $quantidadeAtual . ' para ' . $novaQuantidade . ' (' . $sinal . ')'
);

if ($acao === 'diminuir' && $ajusteReal < $valor) {
    flash('success', 'Quantidade reduzida em ' . $ajusteReal . ' unidade(s) — limitado ao estoque disponível.');
} else {
    flash('success', 'Quantidade ' . ($acao === 'aumentar' ? 'aumentada' : 'reduzida') . ' em ' . $ajusteReal . ' unidade(s).');
}

redirect('/modules/estoque/index.php');
