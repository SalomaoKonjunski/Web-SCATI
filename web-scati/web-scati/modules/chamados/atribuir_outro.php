<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('chamados', 'alterar');

$usuarioAtual = usuarioLogado();

// Atribuir (ou reatribuir) um chamado a OUTRA pessoa é exclusivo do
// Administrador — qualquer um da equipe pode assumir um chamado pra si
// mesmo (ver atribuir.php), mas só o Administrador decide quem cuida do
// chamado de outra pessoa.
if (!$usuarioAtual['admin']) {
    flash('danger', 'Só o Administrador pode atribuir um chamado a outra pessoa.');
    redirect('/modules/chamados/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/chamados/index.php');
}

$pdo = db();
$id = (int) ($_POST['id'] ?? 0);
$responsavelId = (int) ($_POST['responsavel_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, titulo, responsavel_id, status FROM chamados WHERE id = :id');
$stmt->execute(['id' => $id]);
$chamado = $stmt->fetch();

if (!$chamado) {
    flash('danger', 'Chamado não encontrado.');
    redirect('/modules/chamados/index.php');
}

$stmtUsuario = $pdo->prepare('SELECT id, usuario FROM usuarios WHERE id = :id');
$stmtUsuario->execute(['id' => $responsavelId]);
$novoResponsavel = $stmtUsuario->fetch();

if (!$novoResponsavel) {
    flash('danger', 'Selecione uma pessoa válida para atribuir o chamado.');
    redirect('/modules/chamados/form.php?id=' . $id);
}

// Mesma regra de atribuir.php: sair de "sem responsável" já põe em
// andamento; reatribuir um chamado que já estava em atendimento mantém o
// andamento atual (só troca quem cuida dele).
$novoStatus = $chamado['status'] === 'Aberto' ? 'Em andamento' : $chamado['status'];
$pdo->prepare('UPDATE chamados SET responsavel_id = :responsavel_id, status = :status WHERE id = :id')
    ->execute(['responsavel_id' => $novoResponsavel['id'], 'status' => $novoStatus, 'id' => $id]);

registrarHistoricoChamado(
    $id,
    'Responsável',
    $chamado['responsavel_id'] === null
        ? 'Atribuído a "' . $novoResponsavel['usuario'] . '" por "' . $usuarioAtual['usuario'] . '"'
        : 'Repassado para "' . $novoResponsavel['usuario'] . '" por "' . $usuarioAtual['usuario'] . '"'
);

flash('success', 'Chamado "' . $chamado['titulo'] . '" atribuído a "' . $novoResponsavel['usuario'] . '".');
redirect('/modules/chamados/form.php?id=' . $id);
