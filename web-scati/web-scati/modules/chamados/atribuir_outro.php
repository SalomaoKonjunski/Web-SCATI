<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('chamados', 'alterar');

$usuarioAtual = usuarioLogado();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/chamados/index.php');
}

$pdo = db();
$id = (int) ($_POST['id'] ?? 0);
$responsavelId = (int) ($_POST['responsavel_id'] ?? 0);
$motivo = trim((string) ($_POST['motivo'] ?? ''));

$stmt = $pdo->prepare('SELECT id, titulo, responsavel_id, status FROM chamados WHERE id = :id');
$stmt->execute(['id' => $id]);
$chamado = $stmt->fetch();

if (!$chamado) {
    flash('danger', 'Chamado não encontrado.');
    redirect('/modules/chamados/index.php');
}

// Atribuir um chamado NOVO (ainda sem responsável) a outra pessoa continua
// exclusivo do Administrador. Já TRANSFERIR um chamado que já está sendo
// atendido pode ser feito pelo próprio responsável atual, além do
// Administrador — ver podeGerenciarChamado() — mas exige explicar o
// motivo, que fica registrado no histórico do chamado.
$transferencia = $chamado['responsavel_id'] !== null;

if ($transferencia) {
    if (!podeGerenciarChamado($chamado, $usuarioAtual)) {
        flash('danger', 'Só o responsável por este chamado (ou um Administrador) pode transferi-lo.');
        redirect('/modules/chamados/form.php?id=' . $id);
    }
    if ($motivo === '') {
        flash('danger', 'Explique o motivo da transferência antes de confirmar.');
        redirect('/modules/chamados/form.php?id=' . $id);
    }
} elseif (!$usuarioAtual['admin']) {
    flash('danger', 'Só o Administrador pode atribuir um chamado a outra pessoa.');
    redirect('/modules/chamados/index.php');
}

$stmtUsuario = $pdo->prepare('SELECT id, usuario, perfil FROM usuarios WHERE id = :id');
$stmtUsuario->execute(['id' => $responsavelId]);
$novoResponsavel = $stmtUsuario->fetch();

// Só pode receber um chamado quem tem acesso de suporte a Chamados
// (Chamados:Alterar) — nunca um cadastro que só acompanha os próprios
// chamados (perfil Solicitante), mesmo que alguém force o id pela URL.
if (!$novoResponsavel || !temPermissao('chamados', 'alterar', $novoResponsavel['perfil'])) {
    flash('danger', 'Só é possível atribuir ou transferir para cadastros com acesso de suporte a Chamados.');
    redirect('/modules/chamados/form.php?id=' . $id);
}

if ($transferencia && (int) $novoResponsavel['id'] === (int) $chamado['responsavel_id']) {
    flash('danger', 'Selecione uma pessoa diferente do responsável atual para transferir.');
    redirect('/modules/chamados/form.php?id=' . $id);
}

// Mesma regra de atribuir.php: sair de "sem responsável" já põe em
// andamento; transferir um chamado que já estava em atendimento mantém o
// andamento atual (só troca quem cuida dele).
$novoStatus = $chamado['status'] === 'Aberto' ? 'Em andamento' : $chamado['status'];
$pdo->prepare('UPDATE chamados SET responsavel_id = :responsavel_id, status = :status WHERE id = :id')
    ->execute(['responsavel_id' => $novoResponsavel['id'], 'status' => $novoStatus, 'id' => $id]);

if ($transferencia) {
    $nomeAntigo = $pdo->prepare('SELECT usuario FROM usuarios WHERE id = :id');
    $nomeAntigo->execute(['id' => $chamado['responsavel_id']]);
    $nomeAntigo = $nomeAntigo->fetchColumn() ?: 'alguém';

    registrarHistoricoChamado(
        $id,
        'Responsável',
        'Repassado de "' . $nomeAntigo . '" para "' . $novoResponsavel['usuario'] . '" — Motivo: "' . $motivo . '"'
    );
    flash('success', 'Chamado "' . $chamado['titulo'] . '" transferido para "' . $novoResponsavel['usuario'] . '".');
} else {
    registrarHistoricoChamado(
        $id,
        'Responsável',
        'Atribuído a "' . $novoResponsavel['usuario'] . '" por "' . $usuarioAtual['usuario'] . '"'
    );
    flash('success', 'Chamado "' . $chamado['titulo'] . '" atribuído a "' . $novoResponsavel['usuario'] . '".');
}

redirect('/modules/chamados/form.php?id=' . $id);
