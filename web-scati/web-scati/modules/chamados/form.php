<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push.php';
exigirPermissao('chamados', 'ver');

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;
$usuarioAtual = usuarioLogado();

$chamado = [
    'titulo' => '', 'descricao' => '', 'solicitante' => '',
    'prioridade' => 'Média', 'status' => 'Aberto', 'responsavel_id' => '',
];

// Só o Administrador pode escolher quem é o solicitante ao abrir um chamado
// (útil quando abre em nome de outra pessoa) — mas o campo já nasce
// preenchido com o próprio usuário logado, podendo ser trocado. Para os
// demais perfis, o solicitante é sempre o próprio usuário logado, sem
// poder ser alterado. Em ambos os casos, depois de criado esse dado
// (quem abriu) nunca muda — não existe caminho no sistema pra editá-lo.
$podeEscolherSolicitante = $usuarioAtual['admin'];
if (!$edicao) {
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
// "Atribuído" = já tem responsável definido. Enquanto não tem, é fila de
// triagem (ninguém "dono" ainda); depois de atribuído, só o responsável
// (ou um Administrador, que sempre pode intervir) mexe em prioridade,
// andamento, mensagens ou reatribuição — ver podeGerenciarChamado().
$atribuido = $edicao && $chamado['responsavel_id'] !== null;
$podeGerenciar = $edicao && podeGerenciarChamado($chamado, $usuarioAtual);

$usuarios = $pdo->query('SELECT id, usuario FROM usuarios ORDER BY usuario')->fetchAll();

$erros = [];

// Este formulário trata só a CRIAÇÃO de um chamado novo. Depois de criado,
// título/descrição/solicitante nunca mudam; prioridade e andamento mudam
// pelos miniformulários que chamam atualizar_campo.php (mesmo mecanismo
// da listagem); e o responsável muda por atribuir.php (assumir pra si) ou
// atribuir_outro.php (exclusivo do Administrador) — nunca por aqui.
if (!$edicao && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $chamado['titulo'] = trim((string) ($_POST['titulo'] ?? ''));
    $chamado['descricao'] = trim((string) ($_POST['descricao'] ?? ''));
    $chamado['prioridade'] = trim((string) ($_POST['prioridade'] ?? ''));
    $chamado['solicitante'] = $podeEscolherSolicitante
        ? trim((string) ($_POST['solicitante'] ?? ''))
        : $usuarioAtual['usuario'];
    // Só o Administrador pode já nascer o chamado atribuído a alguém —
    // os demais perfis sempre criam um chamado sem responsável, que
    // qualquer um da equipe (inclusive quem criou) pode assumir depois.
    $responsavelIdForm = $usuarioAtual['admin'] ? trim((string) ($_POST['responsavel_id'] ?? '')) : '';

    if ($chamado['titulo'] === '') {
        $erros[] = 'O campo Título é obrigatório.';
    }
    if ($chamado['descricao'] === '') {
        $erros[] = 'O campo Descrição (a solicitação) é obrigatório.';
    }
    if (!in_array($chamado['prioridade'], prioridadesChamado(), true)) {
        $erros[] = 'Prioridade inválida.';
    }
    if ($chamado['solicitante'] !== '' && !in_array($chamado['solicitante'], array_column($usuarios, 'usuario'), true)) {
        $erros[] = 'Selecione um usuário solicitante válido.';
    }
    $responsavelId = null;
    $responsavelNome = null;
    if ($responsavelIdForm !== '') {
        foreach ($usuarios as $u) {
            if ((string) $u['id'] === $responsavelIdForm) {
                $responsavelId = (int) $u['id'];
                $responsavelNome = $u['usuario'];
                break;
            }
        }
        if ($responsavelId === null) {
            $erros[] = 'Selecione um responsável válido.';
        }
    }

    if (empty($erros)) {
        $status = $responsavelId !== null ? 'Em andamento' : 'Aberto';
        $pdo->prepare(
            'INSERT INTO chamados (titulo, descricao, solicitante, criado_por_id, prioridade, status, responsavel_id)
             VALUES (:titulo, :descricao, :solicitante, :criado_por_id, :prioridade, :status, :responsavel_id)'
        )->execute([
            'titulo' => $chamado['titulo'],
            'descricao' => $chamado['descricao'],
            'solicitante' => $chamado['solicitante'] ?: null,
            'criado_por_id' => $usuarioAtual['id'],
            'prioridade' => $chamado['prioridade'],
            'status' => $status,
            'responsavel_id' => $responsavelId,
        ]);
        $novoId = (int) $pdo->lastInsertId();
        registrarHistoricoChamado($novoId, 'Aberto', $chamado['descricao']);
        if ($responsavelId !== null) {
            registrarHistoricoChamado($novoId, 'Responsável', 'Atribuído a "' . $responsavelNome . '" por "' . $usuarioAtual['usuario'] . '"');
        }
        // Quem abriu o chamado já sabe que ele existe, então não deve
        // aparecer como "nova solicitação" não lida para o próprio criador.
        marcarChamadoVisto($novoId, $usuarioAtual['id']);

        if ($responsavelId !== null) {
            // Já nasceu atribuído: só o responsável é notificado diretamente.
            enviarPushParaUsuario(
                $responsavelId,
                'Chamado atribuído a você',
                $chamado['titulo'] . ' — ' . ($chamado['solicitante'] ?: $usuarioAtual['usuario']),
                BASE_URL . '/modules/chamados/form.php?id=' . $novoId
            );
        } else {
            // Sem responsável ainda: avisa quem acompanha a fila de triagem
            // (mesmo público que vê a aba "Novos"), exceto quem acabou de abrir.
            enviarPushParaPerfis(
                ['Administrador', 'Padrão'],
                'Novo chamado',
                $chamado['titulo'] . ' — ' . ($chamado['solicitante'] ?: $usuarioAtual['usuario']),
                BASE_URL . '/modules/chamados/form.php?id=' . $novoId,
                (int) $usuarioAtual['id']
            );
        }

        flash('success', 'Chamado registrado com sucesso.');
        redirect('/modules/chamados/index.php');
    }
}

$respostas = [];
$anexosPorResposta = [];
$observacoes = [];
if ($edicao) {
    $stmtRespostas = $pdo->prepare('SELECT * FROM chamado_respostas WHERE chamado_id = :id ORDER BY criado_em ASC');
    $stmtRespostas->execute(['id' => $id]);
    $respostas = $stmtRespostas->fetchAll();

    $stmtAnexos = $pdo->prepare(
        'SELECT a.* FROM chamado_resposta_anexos a
         JOIN chamado_respostas r ON r.id = a.resposta_id
         WHERE r.chamado_id = :id
         ORDER BY a.id ASC'
    );
    $stmtAnexos->execute(['id' => $id]);
    foreach ($stmtAnexos->fetchAll() as $anexo) {
        $anexosPorResposta[(int) $anexo['resposta_id']][] = $anexo;
    }

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

$tituloPagina = $somenteLeitura ? 'Acompanhar Chamado' : ($edicao ? 'Chamado' : 'Novo Chamado');
$pageTitle = $tituloPagina;

// Nome do responsável atual, pra exibir no badge do cabeçalho.
$responsavelAtualNome = null;
if ($atribuido) {
    if ((int) $chamado['responsavel_id'] === (int) $usuarioAtual['id']) {
        $responsavelAtualNome = 'Você';
    } else {
        foreach ($usuarios as $u) {
            if ((int) $u['id'] === (int) $chamado['responsavel_id']) {
                $responsavelAtualNome = $u['usuario'];
                break;
            }
        }
    }
}

// Composer de mensagens só libera pra quem abriu o chamado, o responsável
// ou um Administrador — e só depois de atribuído (ver responder.php, que
// repete exatamente esta checagem no backend).
$podeConversar = $atribuido && (
    $podeGerenciar || (int) ($chamado['criado_por_id'] ?? 0) === (int) $usuarioAtual['id']
);

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
            <div class="d-flex justify-content-between align-items-start gap-2">
                <h2 class="h5 mb-0"><?= e($chamado['titulo']) ?></h2>
                <?php if ($atribuido): ?>
                    <span class="badge bg-light text-dark border">Responsável: <?= e($responsavelAtualNome) ?></span>
                <?php else: ?>
                    <span class="badge bg-primary">Novo · sem responsável</span>
                <?php endif; ?>
            </div>
            <p class="mb-2 mt-2" style="white-space: pre-wrap;"><?= e($chamado['descricao']) ?></p>
            <?php if (!empty($chamado['solicitante'])): ?>
                <div class="small text-muted mb-2"><i class="bi bi-person"></i> <?= e($chamado['solicitante']) ?></div>
            <?php endif; ?>

            <?php if ($somenteLeitura): ?>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="badge <?= prioridadeChamadoBadgeClass($chamado['prioridade']) ?>">Prioridade: <?= e($chamado['prioridade']) ?></span>
                    <span class="badge <?= statusChamadoBadgeClass($chamado['status']) ?>">Andamento: <?= e($chamado['status']) ?></span>
                </div>
            <?php elseif (!$atribuido): ?>
                <div class="row g-3 align-items-end mt-1">
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Prioridade</label>
                        <form method="post" action="atualizar_campo.php" class="js-auto-submit">
                            <input type="hidden" name="id" value="<?= (int) $id ?>">
                            <input type="hidden" name="campo" value="prioridade">
                            <select name="valor" class="form-select form-select-sm border-0 fw-semibold <?= prioridadeChamadoBadgeClass($chamado['prioridade']) ?>">
                                <?php foreach (prioridadesChamado() as $prioridade): ?>
                                    <option value="<?= e($prioridade) ?>" <?= $chamado['prioridade'] === $prioridade ? 'selected' : '' ?>><?= e($prioridade) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3 align-items-end mt-1">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Prioridade</label>
                        <?php if ($podeGerenciar): ?>
                            <form method="post" action="atualizar_campo.php" class="js-auto-submit">
                                <input type="hidden" name="id" value="<?= (int) $id ?>">
                                <input type="hidden" name="campo" value="prioridade">
                                <select name="valor" class="form-select form-select-sm border-0 fw-semibold <?= prioridadeChamadoBadgeClass($chamado['prioridade']) ?>">
                                    <?php foreach (prioridadesChamado() as $prioridade): ?>
                                        <option value="<?= e($prioridade) ?>" <?= $chamado['prioridade'] === $prioridade ? 'selected' : '' ?>><?= e($prioridade) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php else: ?>
                            <div><span class="badge <?= prioridadeChamadoBadgeClass($chamado['prioridade']) ?>"><?= e($chamado['prioridade']) ?></span></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Andamento</label>
                        <?php if ($podeGerenciar): ?>
                            <form method="post" action="atualizar_campo.php" class="js-auto-submit">
                                <input type="hidden" name="id" value="<?= (int) $id ?>">
                                <input type="hidden" name="campo" value="status">
                                <select name="valor" class="form-select form-select-sm border-0 fw-semibold <?= statusChamadoBadgeClass($chamado['status']) ?>">
                                    <?php foreach (statusChamado() as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $chamado['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php else: ?>
                            <div><span class="badge <?= statusChamadoBadgeClass($chamado['status']) ?>"><?= e($chamado['status']) ?></span></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($podeGerenciar): ?>
                        <div class="col-md-6 d-flex gap-2 justify-content-md-end flex-wrap">
                            <?php if ($usuarioAtual['admin']): ?>
                                <form method="post" action="atribuir_outro.php" class="d-flex gap-2">
                                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                                    <select name="responsavel_id" class="form-select form-select-sm" style="width: auto;">
                                        <option value="">Repassar para...</option>
                                        <?php foreach ($usuarios as $u): ?>
                                            <?php if ((int) $u['id'] !== (int) $chamado['responsavel_id']): ?>
                                                <option value="<?= (int) $u['id'] ?>"><?= e($u['usuario']) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-rotate"></i> Repassar</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($chamado['status'] !== 'Concluído'): ?>
                                <form method="post" action="atualizar_campo.php">
                                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                                    <input type="hidden" name="campo" value="status">
                                    <input type="hidden" name="valor" value="Concluído">
                                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Concluir Chamado</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($chamado['status'] !== 'Cancelado'): ?>
                                <form method="post" action="atualizar_campo.php">
                                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                                    <input type="hidden" name="campo" value="status">
                                    <input type="hidden" name="valor" value="Cancelado">
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i> Cancelar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="text-muted small mt-3">Aberto em <?= formatDateTime($chamado['criado_em']) ?></div>
        </div>
    </div>

    <?php if (!$somenteLeitura && !$atribuido): ?>
    <div class="card mb-3 border-primary">
        <div class="card-header bg-primary bg-opacity-10 border-primary">
            <strong><i class="bi bi-person-plus me-1"></i> Atribuir Responsável</strong>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Este chamado ainda não tem um responsável. Atribua a si mesmo pra assumir o andamento dele e
                liberar o envio de mensagens.
            </p>
            <div class="d-flex gap-2 flex-wrap">
                <form method="get" action="atribuir.php">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-person-check"></i> Atribuir para mim</button>
                </form>
                <?php if ($usuarioAtual['admin']): ?>
                <form method="post" action="atribuir_outro.php" class="d-flex gap-2">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <select name="responsavel_id" class="form-select">
                        <option value="">Atribuir a outra pessoa...</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= e($u['usuario']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary">Atribuir</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
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
                    <select class="form-select" disabled>
                        <option selected><?= e($chamado['solicitante']) ?></option>
                    </select>
                    <input type="hidden" name="solicitante" value="<?= e($chamado['solicitante']) ?>">
                    <div class="form-text">Definido automaticamente como o seu usuário.</div>
                <?php else: ?>
                    <select name="solicitante" class="form-select">
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= e($u['usuario']) ?>" <?= $chamado['solicitante'] === $u['usuario'] ? 'selected' : '' ?>><?= e($u['usuario']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Já vem com seu usuário — troque se estiver abrindo em nome de outra pessoa.</div>
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
            <?php if ($usuarioAtual['admin']): ?>
            <div class="col-md-8">
                <label class="form-label">Responsável</label>
                <select name="responsavel_id" class="form-select">
                    <option value="">Deixar sem responsável (fila de triagem)</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= e($u['usuario']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Opcional — só o Administrador pode criar um chamado já atribuído a alguém.</div>
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
                        <?php
                            $minhaMensagem = $resposta['usuario_id'] !== null && (int) $resposta['usuario_id'] === (int) $usuarioAtual['id'];
                            $anexosResposta = $anexosPorResposta[(int) $resposta['id']] ?? [];
                        ?>
                        <div class="d-flex <?= $minhaMensagem ? 'justify-content-end' : 'justify-content-start' ?> mb-2">
                            <div class="p-2 px-3 rounded-3 <?= $minhaMensagem ? 'bg-primary text-white' : 'bg-light border' ?>" style="max-width: 75%;">
                                <div class="small fw-semibold <?= $minhaMensagem ? '' : 'text-muted' ?>"><?= e($resposta['usuario_nome']) ?></div>
                                <?php foreach ($anexosResposta as $anexo): ?>
                                    <?php if (str_starts_with($anexo['tipo_mime'] ?? '', 'image/')): ?>
                                        <a href="anexo_download.php?id=<?= (int) $anexo['id'] ?>" target="_blank" rel="noopener">
                                            <img src="anexo_download.php?id=<?= (int) $anexo['id'] ?>" alt="<?= e($anexo['nome_original']) ?>"
                                                 class="scati-anexo-foto mt-1 mb-2">
                                        </a>
                                    <?php else: ?>
                                        <a href="anexo_download.php?id=<?= (int) $anexo['id'] ?>" class="scati-anexo-arquivo mt-1 mb-2 text-decoration-none <?= $minhaMensagem ? 'text-white' : 'text-body' ?>">
                                            <i class="bi <?= e(iconeAnexo(pathinfo($anexo['nome_original'], PATHINFO_EXTENSION))) ?> fs-4"></i>
                                            <span class="flex-grow-1 overflow-hidden">
                                                <span class="d-block text-truncate" style="max-width: 180px;"><?= e($anexo['nome_original']) ?></span>
                                                <span class="small opacity-75"><?= formatBytes((int) $anexo['tamanho']) ?></span>
                                            </span>
                                            <i class="bi bi-download"></i>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (trim($resposta['mensagem']) !== ''): ?>
                                    <div style="white-space: pre-wrap;"><?= e($resposta['mensagem']) ?></div>
                                <?php endif; ?>
                                <div class="small <?= $minhaMensagem ? 'text-white-50' : 'text-muted' ?>"><?= formatDateTime($resposta['criado_em']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($podeConversar): ?>
                <div class="d-flex flex-wrap gap-2 mb-2 js-anexos-pendentes" id="anexosPendentes"></div>
                <form method="post" action="responder.php" enctype="multipart/form-data" class="d-flex gap-2 align-items-end">
                    <input type="hidden" name="chamado_id" value="<?= (int) $id ?>">
                    <input type="file" name="anexos[]" id="inputAnexosResposta" class="d-none" multiple
                           accept=".<?= implode(',.', extensoesAnexoPermitidas()) ?>">
                    <button type="button" class="btn btn-outline-secondary js-anexar-resposta" title="Anexar foto ou arquivo">
                        <i class="bi bi-paperclip"></i>
                    </button>
                    <textarea name="mensagem" class="form-control" rows="2" placeholder="Escreva uma resposta..."></textarea>
                    <button type="submit" class="btn btn-primary text-nowrap"><i class="bi bi-send"></i> Enviar</button>
                </form>
                <div class="form-text">
                    <i class="bi bi-info-circle"></i> Fotos (JPG, PNG, GIF, WEBP) e arquivos (PDF, Word, Excel, PowerPoint, TXT, CSV, ZIP) até 10 MB cada.
                    Só quem abriu o chamado, o responsável e um Administrador recebem notificação das mensagens aqui.
                </div>
            <?php elseif (!$atribuido): ?>
                <div class="text-center py-4">
                    <i class="bi bi-lock fs-2 text-muted"></i>
                    <p class="text-muted mt-2 mb-0">
                        O envio de mensagens fica bloqueado até este chamado ser atribuído a alguém.
                        <?php if (!$somenteLeitura): ?><br>Atribua um responsável acima para liberar a conversa.<?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-lock fs-2 text-muted"></i>
                    <p class="text-muted mt-2 mb-0">Só quem abriu o chamado, o responsável por ele ou um Administrador participam desta conversa.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
