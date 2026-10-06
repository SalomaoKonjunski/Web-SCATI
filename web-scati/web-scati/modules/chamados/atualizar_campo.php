<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('chamados', 'alterar');

$pdo = db();
$usuarioAtual = usuarioLogado();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/chamados/index.php');
}

$id = (int) ($_POST['id'] ?? 0);
$campo = $_POST['campo'] ?? '';
$valor = $_POST['valor'] ?? '';

$camposPermitidos = [
    'status' => fn() => in_array($valor, statusChamado(), true),
    'prioridade' => fn() => in_array($valor, prioridadesChamado(), true),
];

if ($id <= 0 || !array_key_exists($campo, $camposPermitidos) || !$camposPermitidos[$campo]()) {
    flash('danger', 'Não foi possível atualizar o chamado — valor inválido.');
    redirect('/modules/chamados/index.php');
}

$stmt = $pdo->prepare('SELECT titulo, status, prioridade, concluido_em, responsavel_id FROM chamados WHERE id = :id');
$stmt->execute(['id' => $id]);
$chamado = $stmt->fetch();

if (!$chamado) {
    flash('danger', 'Chamado não encontrado.');
    redirect('/modules/chamados/index.php');
}

// Prioridade: enquanto o chamado está sem responsável (fila de triagem),
// qualquer um da equipe pode ajustar. Depois de atribuído, só o
// responsável (ou um Administrador) mexe em prioridade ou andamento —
// é o que evita que gente de fora fique interferindo em chamado alheio.
$podeTriagem = $campo === 'prioridade' && $chamado['responsavel_id'] === null;
if (!$podeTriagem && !podeGerenciarChamado($chamado, $usuarioAtual)) {
    flash('danger', 'Só o responsável por este chamado (ou um Administrador) pode alterá-lo.');
    redirect('/modules/chamados/index.php');
}

if ($campo === 'status') {
    // Marca (ou desmarca) a data de conclusão automaticamente conforme o novo andamento.
    $concluidoEm = $chamado['concluido_em'];
    if ($valor === 'Concluído' && $concluidoEm === null) {
        $concluidoEm = date('Y-m-d H:i:s');
    } elseif ($valor !== 'Concluído') {
        $concluidoEm = null;
    }
    $pdo->prepare('UPDATE chamados SET status = :valor, concluido_em = :concluido_em WHERE id = :id')
        ->execute(['valor' => $valor, 'concluido_em' => $concluidoEm, 'id' => $id]);
    if ($valor !== $chamado['status']) {
        registrarHistoricoChamado($id, 'Andamento', $valor === 'Concluído'
            ? 'Chamado marcado como concluído'
            : 'Andamento alterado de "' . $chamado['status'] . '" para "' . $valor . '"');
    }
    flash('success', 'Andamento de "' . $chamado['titulo'] . '" atualizado para "' . $valor . '".');
} else {
    $pdo->prepare('UPDATE chamados SET prioridade = :valor WHERE id = :id')
        ->execute(['valor' => $valor, 'id' => $id]);
    if ($valor !== $chamado['prioridade']) {
        registrarHistoricoChamado($id, 'Prioridade', 'Prioridade alterada de "' . $chamado['prioridade'] . '" para "' . $valor . '"');
    }
    flash('success', 'Prioridade de "' . $chamado['titulo'] . '" atualizada para "' . $valor . '".');
}

redirect('/modules/chamados/index.php');
