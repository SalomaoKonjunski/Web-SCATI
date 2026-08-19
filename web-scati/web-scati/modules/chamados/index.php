<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$pageTitle = 'Chamados';
$usuarioAtual = usuarioLogado();
$souSolicitante = $usuarioAtual['solicitante'];

$busca = trim($_GET['busca'] ?? '');
$filtroStatus = $_GET['status'] ?? '';
$filtroPrioridade = $_GET['prioridade'] ?? '';
$filtroResponsavel = $_GET['responsavel_id'] ?? '';
$filtroDiasParado = trim($_GET['dias_parado'] ?? '');

$sql = "SELECT c.*, e.nome AS equipamento_nome, e.patrimonio AS equipamento_patrimonio, u.usuario AS responsavel_nome
        FROM chamados c
        LEFT JOIN equipamentos e ON e.id = c.equipamento_id
        LEFT JOIN usuarios u ON u.id = c.responsavel_id
        WHERE 1=1";
$params = [];

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
if ($filtroResponsavel === 'nenhum') {
    $sql .= " AND c.responsavel_id IS NULL";
} elseif ($filtroResponsavel !== '') {
    $sql .= " AND c.responsavel_id = :responsavel_id";
    $params['responsavel_id'] = $filtroResponsavel;
}
if ($filtroDiasParado !== '' && is_numeric($filtroDiasParado) && (int) $filtroDiasParado >= 0) {
    // Só considera chamados ainda em aberto — um chamado concluído há muito
    // tempo não está "parado", só está resolvido e arquivado.
    $sql .= " AND c.status NOT IN ('Concluído', 'Cancelado')
              AND c.criado_em <= DATE_SUB(NOW(), INTERVAL :dias_parado DAY)";
    $params['dias_parado'] = (int) $filtroDiasParado;
}
if ($souSolicitante) {
    // O perfil Solicitante só enxerga os chamados que ele mesmo abriu.
    $sql .= " AND c.criado_por_id = :meu_id";
    $params['meu_id'] = $usuarioAtual['id'];
}

// Abertos primeiro (respeitando a ordem definida no ENUM: Aberto > Em andamento >
// Aguardando > Concluído > Cancelado), depois os mais urgentes, depois os mais antigos.
$sql .= " ORDER BY c.status ASC, c.prioridade DESC, c.criado_em ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$chamados = $stmt->fetchAll();

$usuarios = $pdo->query('SELECT id, usuario FROM usuarios ORDER BY usuario')->fetchAll();

// Cartões de resumo e contagem do rodapé: para o perfil Solicitante,
// consideram apenas os chamados abertos por ele mesmo; para os demais,
// consideram o sistema todo (sem levar em conta os filtros da tela).
$condicaoMeus = $souSolicitante ? ' WHERE criado_por_id = :meu_id' : '';
$paramsMeus = $souSolicitante ? ['meu_id' => $usuarioAtual['id']] : [];

$stmtTotalAbertos = $pdo->prepare("SELECT COUNT(*) FROM chamados WHERE status NOT IN ('Concluído', 'Cancelado')" . ($souSolicitante ? ' AND criado_por_id = :meu_id' : ''));
$stmtTotalAbertos->execute($paramsMeus);
$totalAbertos = (int) $stmtTotalAbertos->fetchColumn();

$stmtPorStatus = $pdo->prepare('SELECT status, COUNT(*) AS total FROM chamados' . $condicaoMeus . ' GROUP BY status');
$stmtPorStatus->execute($paramsMeus);
$totalPorStatus = $stmtPorStatus->fetchAll(PDO::FETCH_KEY_PAIR);
$kpiAberto = (int) ($totalPorStatus['Aberto'] ?? 0);
$kpiAndamento = (int) ($totalPorStatus['Em andamento'] ?? 0);
$kpiAguardando = (int) ($totalPorStatus['Aguardando'] ?? 0);

$stmtUrgentes = $pdo->prepare(
    "SELECT COUNT(*) FROM chamados WHERE prioridade IN ('Alta', 'Urgente') AND status NOT IN ('Concluído', 'Cancelado')"
    . ($souSolicitante ? ' AND criado_por_id = :meu_id' : '')
);
$stmtUrgentes->execute($paramsMeus);
$kpiUrgentes = (int) $stmtUrgentes->fetchColumn();

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
        <a href="index.php?responsavel_id=<?= (int) $usuarioAtual['id'] ?>" class="btn btn-outline-secondary">
            <i class="bi bi-person-check"></i> Meus Chamados
        </a>
        <?php endif; ?>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Chamado</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card scati-kpi-card border-start border-4 border-primary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Abertos</div>
                    <div class="kpi-value"><?= $kpiAberto ?></div>
                </div>
                <i class="bi bi-inbox kpi-icon text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card scati-kpi-card border-start border-4 border-warning">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Em Andamento</div>
                    <div class="kpi-value"><?= $kpiAndamento ?></div>
                </div>
                <i class="bi bi-arrow-repeat kpi-icon text-warning"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card scati-kpi-card border-start border-4 border-secondary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Aguardando</div>
                    <div class="kpi-value"><?= $kpiAguardando ?></div>
                </div>
                <i class="bi bi-hourglass-split kpi-icon text-secondary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card scati-kpi-card border-start border-4 border-danger">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Urgentes em Aberto</div>
                    <div class="kpi-value"><?= $kpiUrgentes ?></div>
                </div>
                <i class="bi bi-exclamation-triangle kpi-icon text-danger"></i>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Pesquisar</label>
                <input type="text" name="busca" class="form-control" placeholder="Título, descrição ou solicitante..." value="<?= e($busca) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (statusChamado() as $status): ?>
                        <option value="<?= e($status) ?>" <?= $filtroStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Prioridade</label>
                <select name="prioridade" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach (prioridadesChamado() as $prioridade): ?>
                        <option value="<?= e($prioridade) ?>" <?= $filtroPrioridade === $prioridade ? 'selected' : '' ?>><?= e($prioridade) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!$souSolicitante): ?>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Responsável</label>
                <select name="responsavel_id" class="form-select">
                    <option value="">Todos</option>
                    <option value="nenhum" <?= $filtroResponsavel === 'nenhum' ? 'selected' : '' ?>>Não atribuído</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= (string) $filtroResponsavel === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['usuario']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Parado há mais de (dias)</label>
                <input type="number" name="dias_parado" min="0" class="form-control" placeholder="Ex: 5" value="<?= e($filtroDiasParado) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
                <a href="index.php" class="btn btn-outline-secondary" title="Limpar filtros"><i class="bi bi-x-lg"></i></a>
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
                    <th>Equipamento</th>
                    <th>Prioridade</th>
                    <th>Andamento</th>
                    <th>Responsável</th>
                    <th>Aberto em</th>
                    <?php if (!$souSolicitante): ?><th class="text-end">Ações</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($chamados)): ?>
                    <tr><td colspan="<?= $souSolicitante ? 6 : 7 ?>" class="text-center text-muted py-4">Nenhum chamado encontrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($chamados as $chamado): ?>
                    <?php
                        $emAberto = !in_array($chamado['status'], ['Concluído', 'Cancelado'], true);
                        $classeLinha = '';
                        if ($emAberto && $chamado['prioridade'] === 'Urgente') {
                            $classeLinha = 'chamado-urgente';
                        } elseif ($emAberto && $chamado['prioridade'] === 'Alta') {
                            $classeLinha = 'chamado-alta';
                        }
                        $descricaoResumo = $chamado['descricao'] !== null ? trim($chamado['descricao']) : '';
                        if (mb_strlen($descricaoResumo) > 90) {
                            $descricaoResumo = mb_substr($descricaoResumo, 0, 90) . '…';
                        }
                    ?>
                    <tr <?= $souSolicitante ? '' : 'data-href="form.php?id=' . (int) $chamado['id'] . '"' ?> class="<?= $classeLinha ?>">
                        <td>
                            <strong><?= e($chamado['titulo']) ?></strong>
                            <?php if ($descricaoResumo !== ''): ?>
                                <div class="small text-muted"><?= e($descricaoResumo) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($chamado['solicitante'])): ?>
                                <div class="small text-muted"><i class="bi bi-person"></i> <?= e($chamado['solicitante']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($chamado['equipamento_id']): ?>
                                <?= e(nomeEquipamento($chamado['equipamento_nome'], $chamado['equipamento_patrimonio'])) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($souSolicitante): ?>
                                <span class="badge <?= prioridadeChamadoBadgeClass($chamado['prioridade']) ?>"><?= e($chamado['prioridade']) ?></span>
                            <?php else: ?>
                                <form method="post" action="atualizar_campo.php" class="js-auto-submit">
                                    <input type="hidden" name="id" value="<?= (int) $chamado['id'] ?>">
                                    <input type="hidden" name="campo" value="prioridade">
                                    <select name="valor" class="form-select form-select-sm border-0 fw-semibold <?= prioridadeChamadoBadgeClass($chamado['prioridade']) ?>" style="min-width: 100px;">
                                        <?php foreach (prioridadesChamado() as $prioridade): ?>
                                            <option value="<?= e($prioridade) ?>" <?= $chamado['prioridade'] === $prioridade ? 'selected' : '' ?>><?= e($prioridade) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($souSolicitante): ?>
                                <span class="badge <?= statusChamadoBadgeClass($chamado['status']) ?>"><?= e($chamado['status']) ?></span>
                            <?php else: ?>
                                <form method="post" action="atualizar_campo.php" class="js-auto-submit">
                                    <input type="hidden" name="id" value="<?= (int) $chamado['id'] ?>">
                                    <input type="hidden" name="campo" value="status">
                                    <select name="valor" class="form-select form-select-sm border-0 fw-semibold <?= statusChamadoBadgeClass($chamado['status']) ?>" style="min-width: 130px;">
                                        <?php foreach (statusChamado() as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $chamado['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($chamado['responsavel_id']): ?>
                                <?php if ((int) $chamado['responsavel_id'] === (int) $usuarioAtual['id']): ?>
                                    <span class="badge bg-light text-dark border">Você</span>
                                <?php else: ?>
                                    <?= e($chamado['responsavel_nome']) ?>
                                <?php endif; ?>
                            <?php elseif ($souSolicitante): ?>
                                <span class="text-muted">Não atribuído</span>
                            <?php else: ?>
                                <a href="atribuir.php?id=<?= (int) $chamado['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-person-plus"></i> Atribuir para mim
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= formatDateTime($chamado['criado_em']) ?>
                            <?php if ($emAberto): ?>
                                <div class="small text-muted"><?= e(tempoDecorrido($chamado['criado_em'])) ?></div>
                            <?php elseif (!empty($chamado['concluido_em'])): ?>
                                <div class="small text-muted">Concluído <?= e(tempoDecorrido($chamado['concluido_em'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <?php if (!$souSolicitante): ?>
                        <td class="text-end">
                            <a href="form.php?id=<?= (int) $chamado['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <?php if ($emAberto): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Não é possível excluir um chamado em aberto. Marque como Concluído ou Cancelado primeiro.">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php else: ?>
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
<p class="text-muted small mt-2"><?= count($chamados) ?> chamado(s) encontrado(s) · <?= $totalAbertos ?> em aberto no total.</p>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
