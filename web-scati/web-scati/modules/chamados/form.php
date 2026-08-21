<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;
$usuarioAtual = usuarioLogado();

$chamado = [
    'titulo' => '', 'descricao' => '', 'solicitante' => '',
    'prioridade' => 'Média', 'status' => 'Aberto', 'responsavel_id' => '',
];

// Só o Administrador pode escolher quem é o solicitante ao abrir um chamado;
// para os demais perfis, o solicitante é sempre o próprio usuário logado.
$podeEscolherSolicitante = $usuarioAtual['admin'];
if (!$edicao && !$podeEscolherSolicitante) {
    $chamado['solicitante'] = $usuarioAtual['usuario'];
}

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM chamados WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Chamado não encontrado.');
        redirect('/modules/chamados/index.php');
    }
    // O perfil Usuário só pode acompanhar (não editar) os próprios chamados.
    if ($usuarioAtual['solicitante'] && (int) $registro['criado_por_id'] !== (int) $usuarioAtual['id']) {
        flash('danger', 'Você só pode acompanhar os próprios chamados.');
        redirect('/modules/chamados/index.php');
    }
    $chamado = array_merge($chamado, $registro);
    marcarChamadoVisto($id, $usuarioAtual['id']);
}

// O perfil Usuário nunca edita os campos do chamado, só acompanha e responde.
$somenteLeitura = $edicao && $usuarioAtual['solicitante'];

$usuarios = $pdo->query('SELECT id, usuario FROM usuarios ORDER BY usuario')->fetchAll();

$erros = [];

// Depois de criado, o pedido original (título/descrição/usuário) não pode
// mais ser alterado por ninguém — só prioridade, andamento e responsável
// continuam editáveis, e a comunicação passa a ser pelas respostas.
$camposTravadosNaEdicao = ['titulo', 'descricao', 'solicitante'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$somenteLeitura) {
    foreach ($chamado as $campo => $valorPadrao) {
        if ($edicao && in_array($campo, $camposTravadosNaEdicao, true)) {
            continue;
        }
        $chamado[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    if (!$edicao && !$podeEscolherSolicitante) {
        $chamado['solicitante'] = $usuarioAtual['usuario'];
    }

    if ($usuarioAtual['solicitante']) {
        // O campo Andamento não é exibido para esse perfil (o formulário nem
        // envia "status"), então o valor precisa ser preenchido aqui, antes
        // da validação abaixo — senão a validação rejeita o campo vazio.
        $chamado['status'] = 'Aberto';
    }

    if ($chamado['titulo'] === '') {
        $erros[] = 'O campo Título é obrigatório.';
    }
    if ($chamado['descricao'] === '') {
        $erros[] = 'O campo Descrição (a solicitação) é obrigatório.';
    }
    if (!in_array($chamado['prioridade'], prioridadesChamado(), true)) {
        $erros[] = 'Prioridade inválida.';
    }
    if (!in_array($chamado['status'], statusChamado(), true)) {
        $erros[] = 'Status inválido.';
    }

    if (empty($erros)) {
        // O Solicitante não escolhe andamento/responsável: todo chamado novo
        // criado por esse perfil nasce "Aberto" e sem responsável atribuído.
        $status = $usuarioAtual['solicitante'] ? 'Aberto' : $chamado['status'];
        $responsavelId = $usuarioAtual['solicitante']
            ? null
            : ($chamado['responsavel_id'] !== '' ? (int) $chamado['responsavel_id'] : null);
        $concluidoEm = ($edicao ? $registro['concluido_em'] : null);
        if ($status === 'Concluído' && $concluidoEm === null) {
            $concluidoEm = date('Y-m-d H:i:s');
        } elseif ($status !== 'Concluído') {
            $concluidoEm = null;
        }

        $dados = [
            'titulo' => $chamado['titulo'],
            'descricao' => $chamado['descricao'] ?: null,
            'solicitante' => $chamado['solicitante'] ?: null,
            'prioridade' => $chamado['prioridade'],
            'status' => $status,
            'responsavel_id' => $responsavelId,
            'concluido_em' => $concluidoEm,
        ];

        if ($edicao) {
            // Monta a lista de mudanças de estado antes de salvar, para
            // registrar no histórico automático do chamado.
            $mudancas = [];
            if ((string) $registro['prioridade'] !== (string) $chamado['prioridade']) {
                $mudancas[] = ['Prioridade', 'Prioridade alterada de "' . $registro['prioridade'] . '" para "' . $chamado['prioridade'] . '"'];
            }
            if ((string) $registro['status'] !== (string) $status) {
                $mudancas[] = ['Andamento', $status === 'Concluído'
                    ? 'Chamado marcado como concluído'
                    : 'Andamento alterado de "' . $registro['status'] . '" para "' . $status . '"'];
            }
            $responsavelAntigo = $registro['responsavel_id'] !== null ? (int) $registro['responsavel_id'] : null;
            if ($responsavelAntigo !== $responsavelId) {
                if ($responsavelId === null) {
                    $mudancas[] = ['Responsável', 'Responsável removido'];
                } else {
                    $nomeResponsavel = '';
                    foreach ($usuarios as $u) {
                        if ((int) $u['id'] === $responsavelId) {
                            $nomeResponsavel = $u['usuario'];
                            break;
                        }
                    }
                    $mudancas[] = ['Responsável', 'Atribuído a "' . $nomeResponsavel . '"'];
                }
            }

            $dados['id'] = $id;
            $pdo->prepare(
                'UPDATE chamados SET titulo = :titulo, descricao = :descricao, solicitante = :solicitante,
                        prioridade = :prioridade, status = :status,
                        responsavel_id = :responsavel_id, concluido_em = :concluido_em
                 WHERE id = :id'
            )->execute($dados);

            foreach ($mudancas as [$evento, $descricaoEvento]) {
                registrarHistoricoChamado($id, $evento, $descricaoEvento);
            }

            flash('success', 'Chamado atualizado com sucesso.');
        } else {
            $dados['criado_por_id'] = $usuarioAtual['id'];
            $pdo->prepare(
                'INSERT INTO chamados (titulo, descricao, solicitante, criado_por_id, prioridade, status, responsavel_id, concluido_em)
                 VALUES (:titulo, :descricao, :solicitante, :criado_por_id, :prioridade, :status, :responsavel_id, :concluido_em)'
            )->execute($dados);
            $novoId = (int) $pdo->lastInsertId();
            registrarHistoricoChamado($novoId, 'Aberto', $chamado['descricao']);
            // Quem abriu o chamado já sabe que ele existe, então não deve
            // aparecer como "nova solicitação" não lida para o próprio criador.
            marcarChamadoVisto($novoId, $usuarioAtual['id']);
            flash('success', 'Chamado registrado com sucesso.');
        }
        redirect('/modules/chamados/index.php');
    }
}

$respostas = [];
$observacoes = [];
if ($edicao) {
    $stmtRespostas = $pdo->prepare('SELECT * FROM chamado_respostas WHERE chamado_id = :id ORDER BY criado_em ASC');
    $stmtRespostas->execute(['id' => $id]);
    $respostas = $stmtRespostas->fetchAll();

    // Observações: anotação interna, visível só para administradores
    // (qualquer um deles vê as observações de todos, não só as próprias).
    if ($usuarioAtual['admin']) {
        $stmtObs = $pdo->prepare(
            'SELECT o.id, o.texto, o.criado_em, o.usuario_id AS autor_id, autor.usuario AS autor_nome
             FROM chamado_observacoes o
             JOIN usuarios autor ON autor.id = o.usuario_id
             WHERE o.chamado_id = :id
             ORDER BY o.criado_em DESC'
        );
        $stmtObs->execute(['id' => $id]);
        $observacoes = $stmtObs->fetchAll();
    }
}

$tituloPagina = $somenteLeitura ? 'Acompanhar Chamado' : ($edicao ? 'Editar Chamado' : 'Novo Chamado');
$pageTitle = $tituloPagina;

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-life-preserver me-2"></i><?= e($tituloPagina) ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($edicao): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h5"><?= e($chamado['titulo']) ?></h2>
            <p class="mb-2" style="white-space: pre-wrap;"><?= e($chamado['descricao']) ?></p>
            <?php if (!empty($chamado['solicitante'])): ?>
                <div class="small text-muted mb-2"><i class="bi bi-person"></i> <?= e($chamado['solicitante']) ?></div>
            <?php endif; ?>
            <?php if ($somenteLeitura): ?>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="badge <?= prioridadeChamadoBadgeClass($chamado['prioridade']) ?>">Prioridade: <?= e($chamado['prioridade']) ?></span>
                    <span class="badge <?= statusChamadoBadgeClass($chamado['status']) ?>">Andamento: <?= e($chamado['status']) ?></span>
                </div>
            <?php endif; ?>
            <div class="text-muted small">Aberto em <?= formatDateTime($chamado['criado_em']) ?></div>
        </div>
    </div>

    <?php if (!$somenteLeitura): ?>
    <form method="post">
        <div class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Prioridade *</label>
                    <select name="prioridade" class="form-select" required>
                        <?php foreach (prioridadesChamado() as $prioridade): ?>
                            <option value="<?= e($prioridade) ?>" <?= $chamado['prioridade'] === $prioridade ? 'selected' : '' ?>><?= e($prioridade) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Andamento</label>
                    <select name="status" class="form-select">
                        <?php foreach (statusChamado() as $status): ?>
                            <option value="<?= e($status) ?>" <?= $chamado['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Responsável</label>
                    <div class="d-flex gap-2">
                        <select name="responsavel_id" id="responsavel_id" class="form-select">
                            <option value="">Não atribuído</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= (int) $u['id'] ?>" <?= (string) $chamado['responsavel_id'] === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['usuario']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($usuarioAtual['admin']): ?>
                        <button type="button" class="btn btn-outline-secondary text-nowrap" onclick="document.getElementById('responsavel_id').value = '<?= (int) $usuarioAtual['id'] ?>';">
                            <i class="bi bi-person-plus"></i> Atribuir para mim
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
    <?php endif; ?>
<?php else: ?>
<form method="post">
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-8">
                <label class="form-label">Título *</label>
                <input type="text" name="titulo" class="form-control" required autofocus placeholder="Ex: Impressora da recepção não imprime" value="<?= e($chamado['titulo']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Usuário</label>
                <?php if (!$podeEscolherSolicitante): ?>
                    <input type="text" name="solicitante" class="form-control" value="<?= e($chamado['solicitante']) ?>" readonly>
                    <div class="form-text">Definido automaticamente como o seu usuário.</div>
                <?php else: ?>
                    <input type="text" name="solicitante" class="form-control" placeholder="Quem pediu / nome, setor..." value="<?= e($chamado['solicitante']) ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-12">
                <label class="form-label">Descrição (a solicitação) *</label>
                <textarea name="descricao" class="form-control" rows="3" required placeholder="Detalhes do problema ou solicitação"><?= e($chamado['descricao']) ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Prioridade *</label>
                <select name="prioridade" class="form-select" required>
                    <?php foreach (prioridadesChamado() as $prioridade): ?>
                        <option value="<?= e($prioridade) ?>" <?= $chamado['prioridade'] === $prioridade ? 'selected' : '' ?>><?= e($prioridade) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!$usuarioAtual['solicitante']): ?>
            <div class="col-md-4">
                <label class="form-label">Andamento</label>
                <select name="status" class="form-select">
                    <?php foreach (statusChamado() as $status): ?>
                        <option value="<?= e($status) ?>" <?= $chamado['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Responsável</label>
                <div class="d-flex gap-2">
                    <select name="responsavel_id" id="responsavel_id" class="form-select">
                        <option value="">Não atribuído</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (string) $chamado['responsavel_id'] === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['usuario']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($usuarioAtual['admin']): ?>
                    <button type="button" class="btn btn-outline-secondary text-nowrap" onclick="document.getElementById('responsavel_id').value = '<?= (int) $usuarioAtual['id'] ?>';">
                        <i class="bi bi-person-plus"></i> Atribuir para mim
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
<?php endif; ?>

<?php if ($edicao && $usuarioAtual['admin']): ?>
    <div class="card mb-3">
        <div class="card-header bg-white d-flex align-items-center gap-2">
            <i class="bi bi-eye-slash me-1"></i> Observações
            <span class="badge bg-secondary">só administradores veem</span>
        </div>
        <div class="card-body">
            <p class="text-muted small">Anotação interna — visível para qualquer Administrador, mas não para o solicitante nem para o perfil Padrão/Usuário.</p>
            <?php if (empty($observacoes)): ?>
                <p class="text-muted small mb-3">Nenhuma observação ainda.</p>
            <?php else: ?>
                <ul class="list-group mb-3">
                    <?php foreach ($observacoes as $obs): ?>
                        <li class="list-group-item">
                            <div class="small text-muted"><i class="bi bi-person"></i> <?= e($obs['autor_nome']) ?> · <?= formatDateTime($obs['criado_em']) ?></div>
                            <div style="white-space: pre-wrap;"><?= e($obs['texto']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form method="post" action="observar.php" class="d-flex gap-2">
                <input type="hidden" name="chamado_id" value="<?= (int) $id ?>">
                <textarea name="texto" class="form-control" rows="2" placeholder="Escreva uma observação..." required></textarea>
                <button type="submit" class="btn btn-outline-secondary text-nowrap"><i class="bi bi-plus-lg"></i> Adicionar</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($edicao): ?>
    <div class="card mb-5">
        <div class="card-header bg-white">
            <i class="bi bi-chat-left-text me-1"></i> Respostas
        </div>
        <div class="card-body">
            <?php if (empty($respostas)): ?>
                <p class="text-muted small mb-3">Nenhuma resposta ainda.</p>
            <?php else: ?>
                <div class="mb-3">
                    <?php foreach ($respostas as $resposta): ?>
                        <?php $minhaMensagem = $resposta['usuario_id'] !== null && (int) $resposta['usuario_id'] === (int) $usuarioAtual['id']; ?>
                        <div class="d-flex <?= $minhaMensagem ? 'justify-content-end' : 'justify-content-start' ?> mb-2">
                            <div class="p-2 px-3 rounded-3 <?= $minhaMensagem ? 'bg-primary text-white' : 'bg-light border' ?>" style="max-width: 75%;">
                                <div class="small fw-semibold <?= $minhaMensagem ? '' : 'text-muted' ?>"><?= e($resposta['usuario_nome']) ?></div>
                                <div style="white-space: pre-wrap;"><?= e($resposta['mensagem']) ?></div>
                                <div class="small <?= $minhaMensagem ? 'text-white-50' : 'text-muted' ?>"><?= formatDateTime($resposta['criado_em']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="responder.php" class="d-flex gap-2">
                <input type="hidden" name="chamado_id" value="<?= (int) $id ?>">
                <textarea name="mensagem" class="form-control" rows="2" placeholder="Escreva uma resposta..." required></textarea>
                <button type="submit" class="btn btn-primary text-nowrap"><i class="bi bi-send"></i> Enviar</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
