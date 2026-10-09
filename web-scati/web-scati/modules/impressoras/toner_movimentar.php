<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('impressoras', 'alterar');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/impressoras/toners.php');
}

$id = (int) ($_POST['id'] ?? 0);
$acao = $_POST['acao'] ?? '';
$quantidade = (int) ($_POST['quantidade'] ?? 0);
$deImpressoraId = isset($_POST['de_impressora']) ? (int) $_POST['de_impressora'] : null;
$redirectDestino = '/modules/impressoras/toner.php?id=' . $id . ($deImpressoraId !== null ? '&de_impressora=' . $deImpressoraId : '');

$stmt = $pdo->prepare('SELECT * FROM toners WHERE id = :id');
$stmt->execute(['id' => $id]);
$toner = $stmt->fetch();

if (!$toner) {
    flash('danger', 'Toner/Tinta não encontrado.');
    redirect('/modules/impressoras/toners.php');
}

if (!in_array($acao, ['baixa', 'reposicao'], true) || $quantidade < 1) {
    flash('danger', 'Informe uma quantidade válida (maior que zero).');
    redirect($redirectDestino);
}

if ($acao === 'reposicao') {
    $pdo->prepare('UPDATE toners SET quantidade = quantidade + :quantidade WHERE id = :id')
        ->execute(['quantidade' => $quantidade, 'id' => $id]);
    registrarMovimentacaoToner($id, 'Reposição', $quantidade);
    flash('success', 'Reposição de ' . $quantidade . ' unidade(s) registrada para "' . $toner['nome'] . '".');
    redirect($redirectDestino);
}

// Baixa: precisa de uma impressora vinculada de destino — é o que vira o
// evento automático de troca de toner nela.
$equipamentoId = (int) ($_POST['equipamento_id'] ?? 0);

$stmtVinc = $pdo->prepare('SELECT e.id, e.nome, e.patrimonio FROM toner_impressoras ti JOIN equipamentos e ON e.id = ti.equipamento_id WHERE ti.toner_id = :toner_id AND ti.equipamento_id = :equipamento_id');
$stmtVinc->execute(['toner_id' => $id, 'equipamento_id' => $equipamentoId]);
$impressora = $stmtVinc->fetch();

if (!$impressora) {
    flash('danger', 'Selecione uma impressora vinculada a este toner para registrar a baixa.');
    redirect($redirectDestino);
}

$quantidadeAtual = (int) $toner['quantidade'];
if ($quantidade > $quantidadeAtual) {
    flash('danger', 'Não é possível dar baixa em mais unidades do que há em estoque (' . $quantidadeAtual . ').');
    redirect($redirectDestino);
}

$stmtDecr = $pdo->prepare('UPDATE toners SET quantidade = quantidade - :quantidade WHERE id = :id AND quantidade >= :quantidade2');
$stmtDecr->execute(['quantidade' => $quantidade, 'id' => $id, 'quantidade2' => $quantidade]);

if ($stmtDecr->rowCount() === 0) {
    flash('danger', 'Não foi possível dar baixa — quantidade em estoque mudou, tente novamente.');
    redirect($redirectDestino);
}

registrarMovimentacaoToner($id, 'Baixa', $quantidade, $equipamentoId);

// Unifica baixa + troca de toner num passo só: reinicia o prazo do
// alerta de troca a partir de hoje, igual ao botão manual "Registrar
// Troca de Toner" na ficha do equipamento.
$pdo->prepare('UPDATE equipamentos SET toner_ultima_troca = CURDATE() WHERE id = :id')->execute(['id' => $equipamentoId]);
registrarHistorico(
    $equipamentoId,
    'Manutenção',
    'Troca de toner registrada (baixa de "' . $toner['nome'] . '", ' . $quantidade . ' unidade(s)) — prazo de alerta reiniciado'
);

flash('success', 'Baixa de ' . $quantidade . ' unidade(s) registrada e troca de toner atualizada em "' . nomeEquipamento($impressora['nome'], $impressora['patrimonio']) . '".');
redirect('/modules/impressoras/toner.php?id=' . $id);
