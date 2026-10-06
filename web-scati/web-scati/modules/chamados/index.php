<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('chamados', 'ver');

$pdo = db();
$pageTitle = 'Chamados';
$usuarioAtual = usuarioLogado();
$souSolicitante = $usuarioAtual['solicitante'];

// Três estágios do ciclo de vida: Novos (sem responsável, fila de
// triagem — qualquer um da equipe pode assumir), Em Atendimento (já tem
// responsável — só ele ou um Administrador mexem no chamado) e
// Resolvidos (Concluído/Cancelado). Ver podeGerenciarChamado().
$abasValidas = ['novos', 'atendimento', 'resolvidos'];
$aba = in_array($_GET['aba'] ?? '', $abasValidas, true) ? $_GET['aba'] : 'novos';
$statusOpcoes = match ($aba) {
    'resolvidos' => ['Concluído', 'Cancelado'],
    'atendimento' => ['Em andamento', 'Aguardando'],
    default => ['Aberto'],
};

$busca = trim($_GET['busca'] ?? '');
$filtroStatus = $_GET['status'] ?? '';
$filtroPrioridade = $_GET['prioridade'] ?? '';
$filtroResponsavel = $_GET['responsavel_id'] ?? '';
$filtroDiasParado = in_array($aba, ['novos', 'atendimento'], true) ? trim($_GET['dias_parado'] ?? '') : '';
$filtroUrgentes = in_array($aba, ['novos', 'atendimento'], true) && isset($_GET['urgentes']) && $_GET['urgentes'] === '1';

$sql = "SELECT c.*, u.usuario AS responsavel_nome,
               v.visto_em AS meu_visto_em,
               (SELECT COUNT(*) FROM chamado_respostas r
                WHERE r.chamado_id = c.id AND (r.usuario_id IS NULL OR r.usuario_id != :uid_notif2)
                  AND r.criado_em > COALESCE(v.visto_em, '1970-01-01 00:00:00')) AS qtd_mensagens_novas
        FROM chamados c
        LEFT JOIN usuarios u ON u.id = c.responsavel_id
        LEFT JOIN chamado_visualizacoes v ON v.chamado_id = c.id AND v.usuario_id = :uid_notif1
        WHERE 1=1";
$params = ['uid_notif1' => $usuarioAtual['id'], 'uid_notif2' => $usuarioAtual['id']];

if ($aba === 'resolvidos') {
    $sql .= " AND c.status IN ('Concluído', 'Cancelado')";
} elseif ($aba === 'atendimento') {
    $sql .= " AND c.status NOT IN ('Concluído', 'Cancelado') AND c.responsavel_id IS NOT NULL";
} else {
    $sql .= " AND c.status NOT IN ('Concluído', 'Cancelado') AND c.responsavel_id IS NULL";
}
if ($busca !== '') {
    $sql .= " AND (c.titulo LIKE :busca1 OR c.descricao LIKE :busca2 OR c.solicitante LIKE :busca3)";
    $curingaBusca = '%' . $busca . '%';
    $params['busca1'] = $curingaBusca;
    $params['busca2'] = $curingaBusca;
    $params['busca3'] = $curingaBusca;
}
if ($filtroStatus !== '') {
    $sql .= " AND c.status = :status";
    $params['status'] = $filtroStatus;
}
if ($filtroPrioridade !== '') {
    $sql .= " AND c.prioridade = :prioridade";
    $params['prioridade'] = $filtroPrioridade;
}
if ($aba !== 'novos') {
    if ($filtroResponsavel === 'nenhum') {
        $sql .= " AND c.responsavel_id IS NULL";
    } elseif ($filtroResponsavel !== '') {
        $sql .= " AND c.responsavel_id = :responsavel_id";
        $params['responsavel_id'] = $filtroResponsavel;
    }
}
if ($filtroDiasParado !== '' && is_numeric($filtroDiasParado) && (int) $filtroDiasParado >= 0) {
    $sql .= " AND c.criado_em <= DATE_SUB(NOW(), INTERVAL :dias_parado DAY)";
    $params['dias_parado'] = (int) $filtroDiasParado;
}
if ($filtroUrgentes) {
    $sql .= " AND c.prioridade = 'Urgente'";
}
if ($souSolicitante) {
    // O perfil Solicitante só enxerga os chamados que ele mesmo abriu.
    $sql .= " AND c.criado_por_id = :meu_id";
    $params['meu_id'] = $usuarioAtual['id'];
}

if ($aba === 'resolvidos') {
    // Resolvidos mais recentes primeiro.
    $sql .= " ORDER BY COALESCE(c.concluido_em, c.atualizado_em) DESC";
} elseif ($aba === 'novos') {
    // Fila de triagem: mais urgente primeiro, depois os mais antigos.
    $sql .= " ORDER BY FIELD(c.prioridade, 'Urgente', 'Alta', 'Média', 'Baixa') ASC, c.criado_em ASC";
} else {
    // Em Atendimento: chamados com novidade não vista (nunca abertos por
    // mim, ou com resposta nova) sempre em primeiro lugar, ordenados por
    // prioridade entre eles; os demais mantêm prioridade/data normais.
    $sql .= " ORDER BY
                (CASE WHEN v.visto_em IS NULL AND (c.criado_por_id IS NULL OR c.criado_por_id != :uid_notif3) THEN 0 ELSE 1 END) ASC,
                (CASE WHEN v.visto_em IS NULL AND (c.criado_por_id IS NULL OR c.criado_por_id != :uid_notif4)
                      THEN FIELD(c.prioridade, 'Baixa', 'Média', 'Alta', 'Urgente') END) DESC,
                c.prioridade DESC, c.criado_em ASC";
    $params['uid_notif3'] = $usuarioAtual['id'];
    $params['uid_notif4'] = $usuarioAtual['id'];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$chamados = $stmt->fetchAll();

$usuarios = $pdo->query('SELECT id, usuario FROM usuarios ORDER BY usuario')->fetchAll();

// Cartões de resumo e contagem do rodapé: para o perfil Solicitante,
// consideram apenas os chamados abertos por ele mesmo; para os demais,
// consideram o sistema todo (sem levar em conta os filtros da tela).
$condicaoMeus = $souSolicitante ? ' AND criado_por_id = :meu_id' : '';
$paramsMeus = $souSolicitante ? ['meu_id' => $usuarioAtual['id']] : [];

$stmtTotalNovos = $pdo->prepare("SELECT COUNT(*) FROM chamados WHERE status NOT IN ('Concluído', 'Cancelado') AND responsavel_id IS NULL" . $condicaoMeus);
$stmtTotalNovos->execute($paramsMeus);
$totalNovos = (int) $stmtTotalNovos->fetchColumn();

$stmtPorStatus = $pdo->prepare("SELECT status, COUNT(*) AS total FROM chamados WHERE status NOT IN ('Concluído', 'Cancelado') AND responsavel_id IS NOT NULL" . $condicaoMeus . ' GROUP BY status');
$stmtPorStatus->execute($paramsMeus);
$totalPorStatus = $stmtPorStatus->fetchAll(PDO::FETCH_KEY_PAIR);
$kpiAndamento = (int) ($totalPorStatus['Em andamento'] ?? 0);
$kpiAguardando = (int) ($totalPorStatus['Aguardando'] ?? 0);
$totalAtendimento = $kpiAndamento + $kpiAguardando;

$stmtUrgentesNovos = $pdo->prepare("SELECT COUNT(*) FROM chamados WHERE prioridade = 'Urgente' AND status NOT IN ('Concluído', 'Cancelado') AND responsavel_id IS NULL" . $condicaoMeus);
$stmtUrgentesNovos->execute($paramsMeus);
$kpiUrgentesNovos = (int) $stmtUrgentesNovos->fetchColumn();

$stmtUrgentesAtendimento = $pdo->prepare("SELECT COUNT(*) FROM chamados WHERE prioridade = 'Urgente' AND status NOT IN ('Concluído', 'Cancelado') AND responsavel_id IS NOT NULL" . $condicaoMeus);
$stmtUrgentesAtendimento->execute($paramsMeus);
$kpiUrgentesAtendimento = (int) $stmtUrgentesAtendimento->fetchColumn();

/**
 * Retorna "há N dia(s)"/"hoje" a partir de uma data, para indicar o tempo
 * em aberto (ou tempo desde a conclusão) de um chamado.
 */
function tempoDecorrido(string $dataHora): string
{
    $dias = (int) floor((strtotime('today') - strtotime(date('Y-m-d', strtotime($dataHora)))) / 86400);
    if ($dias <= 0) {
        return 'hoje';
    }
    return 'há ' . $dias . ' dia' . ($dias > 1 ? 's' : '');
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-life-preserver me-2"></i>Chamados</h1>
    <div class="d-flex gap-2">
        <?php if (!$souSolicitante): ?>
        <a href="index.php?aba=atendimento&responsavel_id=<?= (int) $usuarioAtual['id'] ?>" class="btn btn-outline-secondary">
            <i class="bi bi-person-check"></i> Meus Chamados
        </a>
        <?php endif; ?>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Chamado</a>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'novos' ? 'active' : '' ?>" href="index.php?aba=novos">
            <i class="bi bi-inbox"></i> Novos
            <?php if ($totalNovos > 0): ?><span class="badge bg-danger ms-1"><?= $totalNovos ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'atendimento' ? 'active' : '' ?>" href="index.php?aba=atendimento">
            <i class="bi bi-arrow-repeat"></i> Em Atendimento
            <?php if ($totalAtendimento > 0): ?><span class="badge bg-secondary ms-1"><?= $totalAtendimento ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'resolvidos' ? 'active' : '' ?>" href="index.php?aba=resolvidos">
            <i class="bi bi-check-circle"></i> Resolvidos
        </a>
    </li>
</ul>

<?php if ($aba === 'novos' && !$souSolicitante): ?>
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-info-circle fs-5"></i>
        <div>
            <strong>Fila de triagem.</strong> Chamados aqui ainda não têm um responsável — qualquer pessoa da
            equipe pode assumir um. Enviar mensagens só é liberado depois que alguém assume o chamado.
        </div>
    </div>
<?php endif; ?>

<?php if ($aba === 'atendimento'): ?>
<div class="row g-3 mb-3">
    <a href="index.php?aba=atendimento&status=<?= urlencode('Em andamento') ?>" class="col-6 col-md-4 scati-kpi-card-link" title="Ver chamados Em Andamento">
        <div class="card scati-kpi-card border-start border-4 border-warning <?= ($filtroStatus === 'Em andamento' && !$filtroUrgentes) ? 'kpi-ativo' : '' ?>">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Em Andamento</div>
                    <div class="kpi-value"><?= $kpiAndamento ?></div>
                </div>
                <i class="bi bi-arrow-repeat kpi-icon text-warning"></i>
            </div>
        </div>
    </a>
    <a href="index.php?aba=atendimento&status=<?= urlencode('Aguardando') ?>" class="col-6 col-md-4 scati-kpi-card-link" title="Ver chamados Aguardando">
        <div class="card scati-kpi-card border-start border-4 border-secondary <?= ($filtroStatus === 'Aguardando' && !$filtroUrgentes) ? 'kpi-ativo' : '' ?>">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Aguardando</div>
                    <div class="kpi-value"><?= $kpiAguardando ?></div>
                </div>
                <i class="bi bi-hourglass-split kpi-icon text-secondary"></i>
            </div>
        </div>
    </a>
    <a href="index.php?aba=atendimento&urgentes=1" class="col-6 col-md-4 scati-kpi-card-link" title="Ver chamados Urgentes em Atendimento">
        <div class="card scati-kpi-card border-start border-4 border-danger <?= $filtroUrgentes ? 'kpi-ativo' : '' ?>">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Urgentes em Atendimento</div>
                    <div class="kpi-value"><?= $kpiUrgentesAtendimento ?></div>
                </div>
                <i class="bi bi-exclamation-triangle kpi-icon text-danger"></i>
            </div>
        </div>
    </a>
</div>
<p class="text-muted small"><i class="bi bi-info-circle"></i> Só o responsável de cada chamado (ou um Administrador) pode mudar prioridade/andamento ou enviar mensagens nele — os demais podem acompanhar, mas não interferir.</p>
<?php endif; ?>

<?php if ($aba === 'novos' && $kpiUrgentesNovos > 0): ?>
<div class="row g-3 mb-3">
    <a href="index.php?aba=novos&urgentes=1" class="col-6 col-md-3 scati-kpi-card-link" title="Ver chamados Urgentes sem responsável">
        <div class="card scati-kpi-card border-start border-4 border-danger <?= $filtroUrgentes ? 'kpi-ativo' : '' ?>">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Urgentes sem Responsável</div>
                    <div class="kpi-value"><?= $kpiUrgentesNovos ?></div>
                </div>
                <i class="bi bi-exclamation-triangle kpi-icon text-danger"></i>
            </div>
        </div>
    </a>
</div>
<?php endif; ?>

<?php if (($filtroStatus !== '' || $filtroUrgentes) && $aba !== 'resolvidos'): ?>
    <div class="mb-3">
        <a href="index.php?aba=<?= e($aba) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> Limpar filtro dos cartões</a>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="aba" value="<?= e($aba) ?>">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Pesquisar</label>
                <input type="text" name="busca" class="form-control" placeholder="Título, descrição ou usuário..." value="<?= e($busca) ?>">
            </div>
            <?php if ($aba !== 'novos'): ?>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($statusOpcoes as $status): ?>
                        <option value="<?= e($status) ?>" <?= $filtroStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Prioridade</label>
                <select name="prioridade" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach (prioridadesChamado() as $prioridade): ?>
                        <option value="<?= e($prioridade) ?>" <?= $filtroPrioridade === $prioridade ? 'selected' : '' ?>><?= e($prioridade) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!$souSolicitante && $aba !== 'novos'): ?>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Responsável</label>
                <select name="responsavel_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= (string) $filtroResponsavel === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['usuario']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if (in_array($aba, ['novos', 'atendimento'], true)): ?>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Parado há mais de (dias)</label>
                <input type="number" name="dias_parado" min="0" class="form-control" placeholder="Ex: 5" value="<?= e($filtroDiasParado) ?>">
            </div>
            <?php endif; ?>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
                <a href="index.php?aba=<?= e($aba) ?>" class="btn btn-outline-secondary" title="Limpar filtros"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
        <?php if ($filtroDiasParado !== '' && is_numeric($filtroDiasParado)): ?>
            <div class="form-text mt-2 mb-0">
                <i class="bi bi-info-circle"></i> Mostrando chamados em aberto (não concluídos/cancelados) criados há mais de <?= (int) $filtroDiasParado ?> dia(s).
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Título</th>
                    <th>Prioridade</th>
                    <?php if ($aba !== 'novos'): ?><th>Andamento</th><th>Responsável</th><?php endif; ?>
                    <?php if ($aba === 'resolvidos'): ?>
                        <th>Solicitado em</th>
                        <th>Concluído em</th>
                    <?php else: ?>
                        <th>Aberto em</th>
                    <?php endif; ?>
                    <?php if (!$souSolicitante): ?><th class="text-end">Ações</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                    $totalColunas = 2 + ($aba !== 'novos' ? 2 : 0) + ($aba === 'resolvidos' ? 2 : 1) + ($souSolicitante ? 0 : 1);
                ?>
                <?php if (empty($chamados)): ?>
                    <tr><td colspan="<?= $totalColunas ?>" class="text-center text-muted py-4">Nenhum chamado encontrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($chamados as $chamado): ?>
                    <?php
                        $emAberto = !in_array($chamado['status'], ['Concluído', 'Cancelado'], true);
                        $podeGerenciarEste = podeGerenciarChamado($chamado, $usuarioAtual);
                        $descricaoResumo = $chamado['descricao'] !== null ? trim($chamado['descricao']) : '';
                        if (mb_strlen($descricaoResumo) > 90) {
                            $descricaoResumo = mb_substr($descricaoResumo, 0, 90) . '…';
                        }
                        // Mesmo critério de "novidade" do sininho: solicitação nunca
                        // aberta por mim, ou resposta(s) de outra pessoa ainda não
                        // vista(s) desde a última vez que eu vi este chamado.
                        $ehSolicitacaoNova = $chamado['meu_visto_em'] === null
                            && (int) ($chamado['criado_por_id'] ?? 0) !== (int) $usuarioAtual['id'];
                        $qtdMensagensNovas = (int) $chamado['qtd_mensagens_novas'];

                        if ($ehSolicitacaoNova) {
                            $classeLinha = 'chamado-novo';
                        } elseif ($emAberto && $chamado['prioridade'] === 'Urgente') {
                            $classeLinha = 'chamado-urgente';
                        } elseif ($emAberto && $chamado['prioridade'] === 'Alta') {
                            $classeLinha = 'chamado-alta';
                        } else {
                            $classeLinha = '';
                        }
                    ?>
                    <tr data-href="form.php?id=<?= (int) $chamado['id'] ?>" class="<?= $classeLinha ?>">
                        <td>
                            <strong><?= e($chamado['titulo']) ?></strong>
                            <?php if ($qtdMensagensNovas > 0): ?>
                                <span class="badge rounded-pill bg-primary ms-1"><?= $qtdMensagensNovas > 9 ? '9+' : $qtdMensagensNovas ?></span>
                            <?php endif; ?>
                            <?php if ($descricaoResumo !== ''): ?>
                                <div class="small text-muted"><?= e($descricaoResumo) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($chamado['solicitante'])): ?>
                                <div class="small text-muted"><i class="bi bi-person"></i> <?= e($chamado['solicitante']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                                // Prioridade é editável por qualquer um da equipe enquanto o
                                // chamado está sem responsável (triagem); depois de atribuído,
                                // só quem pode gerenciar (responsável/Admin).
                                $podeMudarPrioridade = !$souSolicitante && ($chamado['responsavel_id'] === null || $podeGerenciarEste);
                            ?>
                            <?php if ($podeMudarPrioridade): ?>
                                <form method="post" action="atualizar_campo.php" class="js-auto-submit">
                                    <input type="hidden" name="id" value="<?= (int) $chamado['id'] ?>">
                                    <input type="hidden" name="campo" value="prioridade">
                                    <select name="valor" class="form-select form-select-sm border-0 fw-semibold <?= prioridadeChamadoBadgeClass($chamado['prioridade']) ?>" style="min-width: 100px;">
                                        <?php foreach (prioridadesChamado() as $prioridade): ?>
                                            <option value="<?= e($prioridade) ?>" <?= $chamado['prioridade'] === $prioridade ? 'selected' : '' ?>><?= e($prioridade) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="badge <?= prioridadeChamadoBadgeClass($chamado['prioridade']) ?>"><?= e($chamado['prioridade']) ?></span>
                            <?php endif; ?>
                        </td>
                        <?php if ($aba !== 'novos'): ?>
                        <td>
                            <?php if (!$souSolicitante && $podeGerenciarEste): ?>
                                <form method="post" action="atualizar_campo.php" class="js-auto-submit">
                                    <input type="hidden" name="id" value="<?= (int) $chamado['id'] ?>">
                                    <input type="hidden" name="campo" value="status">
                                    <select name="valor" class="form-select form-select-sm border-0 fw-semibold <?= statusChamadoBadgeClass($chamado['status']) ?>" style="min-width: 130px;">
                                        <?php foreach (statusChamado() as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $chamado['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="badge <?= statusChamadoBadgeClass($chamado['status']) ?>" <?= (!$souSolicitante && !$podeGerenciarEste) ? 'title="Só o responsável ou um Administrador podem alterar"' : '' ?>><?= e($chamado['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $chamado['responsavel_id'] === (int) $usuarioAtual['id']): ?>
                                <span class="badge bg-light text-dark border">Você</span>
                            <?php else: ?>
                                <?= e($chamado['responsavel_nome']) ?>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <?php if ($aba === 'resolvidos'): ?>
                            <?php $dataConclusao = $chamado['concluido_em'] ?? $chamado['atualizado_em']; ?>
                            <td><?= formatDateTime($chamado['criado_em']) ?></td>
                            <td>
                                <?= formatDateTime($dataConclusao) ?>
                                <div class="small text-muted">
                                    <?= e(tempoDecorrido($dataConclusao)) ?><?= $chamado['status'] === 'Cancelado' ? ' · cancelado' : '' ?>
                                </div>
                            </td>
                        <?php else: ?>
                            <td>
                                <?= formatDateTime($chamado['criado_em']) ?>
                                <div class="small text-muted"><?= e(tempoDecorrido($chamado['criado_em'])) ?></div>
                            </td>
                        <?php endif; ?>
                        <?php if (!$souSolicitante): ?>
                        <td class="text-end">
                            <?php if ($aba === 'novos'): ?>
                                <form method="get" action="atribuir.php" class="d-inline">
                                    <input type="hidden" name="id" value="<?= (int) $chamado['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> Atribuir para mim</button>
                                </form>
                            <?php endif; ?>
                            <a href="form.php?id=<?= (int) $chamado['id'] ?>" class="btn btn-sm btn-outline-primary" title="Abrir"><i class="bi bi-chat-dots"></i></a>
                            <?php if ($aba === 'resolvidos' && $podeGerenciarEste): ?>
                                <a href="delete.php?id=<?= (int) $chamado['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                                   data-confirm-msg="Excluir o chamado &quot;<?= e($chamado['titulo']) ?>&quot;? Esta ação não pode ser desfeita." title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted small mt-2"><?= count($chamados) ?> chamado(s) encontrado(s).</p>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
