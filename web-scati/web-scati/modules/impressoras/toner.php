<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('impressoras', 'ver');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$deImpressoraId = isset($_GET['de_impressora']) ? (int) $_GET['de_impressora'] : null;

$stmt = $pdo->prepare('SELECT * FROM toners WHERE id = :id');
$stmt->execute(['id' => $id]);
$toner = $stmt->fetch();

if (!$toner) {
    flash('danger', 'Toner/Tinta não encontrado.');
    redirect('/modules/impressoras/toners.php');
}

$stmtImpressoras = $pdo->prepare(
    "SELECT e.id, e.nome, e.patrimonio, e.localizacao
     FROM toner_impressoras ti JOIN equipamentos e ON e.id = ti.equipamento_id
     WHERE ti.toner_id = :id ORDER BY e.nome ASC"
);
$stmtImpressoras->execute(['id' => $id]);
$impressorasVinculadas = $stmtImpressoras->fetchAll();

// Se veio de uma ficha de impressora específica (botão "Gerenciar neste
// Toner"), e ela realmente está vinculada a este toner, a baixa já nasce
// travada nela — não faz sentido perguntar de novo quem recebeu a
// unidade. Sem esse contexto, só auto-seleciona quando não há outra
// opção possível (uma única impressora vinculada).
$impressoraFixa = null;
foreach ($impressorasVinculadas as $imp) {
    if ($deImpressoraId !== null && (int) $imp['id'] === $deImpressoraId) {
        $impressoraFixa = $imp;
        break;
    }
}
if ($impressoraFixa === null && count($impressorasVinculadas) === 1) {
    $impressoraFixa = $impressorasVinculadas[0];
}

$stmtMov = $pdo->prepare(
    "SELECT tm.*, e.nome AS equipamento_nome, e.patrimonio AS equipamento_patrimonio
     FROM toner_movimentacoes tm LEFT JOIN equipamentos e ON e.id = tm.equipamento_id
     WHERE tm.toner_id = :id ORDER BY tm.criado_em DESC, tm.id DESC LIMIT 15"
);
$stmtMov->execute(['id' => $id]);
$movimentacoes = $stmtMov->fetchAll();

$pageTitle = $toner['nome'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-droplet-half me-2"></i><?= e($toner['nome']) ?></h1>
    <a href="toners.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if ($impressoraFixa !== null && $deImpressoraId !== null): ?>
<div class="alert alert-primary d-flex align-items-center gap-2">
    <i class="bi bi-printer fs-5"></i>
    <div>
        Ajustando a partir da ficha de <strong><?= e(nomeEquipamento($impressoraFixa['nome'], $impressoraFixa['patrimonio'])) ?></strong>.
        Se você <strong>diminuir</strong> a quantidade agora, o sistema já registra a troca nesta impressora automaticamente.
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <div class="text-muted small"><?= e(trim(($toner['marca'] ?? '') . ' · ' . ($toner['modelo'] ?? ''), ' ·')) ?: '-' ?></div>
                <?php if (!empty($toner['localizacao'])): ?>
                    <div class="text-muted small">Localização: <?= e($toner['localizacao']) ?></div>
                <?php endif; ?>
            </div>
            <span class="badge <?= (int) $toner['quantidade'] <= 0 ? 'bg-danger' : 'bg-success' ?> fs-5"><?= (int) $toner['quantidade'] ?> em estoque</span>
        </div>

        <?php if (temPermissao('impressoras', 'alterar')): ?>
        <div class="row g-3">
            <div class="col-md-6">
                <form method="post" action="toner_movimentar.php" class="p-3 bg-light rounded h-100 d-flex flex-column">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="acao" value="baixa">
                    <?php if ($deImpressoraId !== null): ?><input type="hidden" name="de_impressora" value="<?= (int) $deImpressoraId ?>"><?php endif; ?>
                    <strong class="mb-2"><i class="bi bi-dash-lg text-danger"></i> Dar baixa (instalar)</strong>
                    <?php if (empty($impressorasVinculadas)): ?>
                        <p class="text-muted small mb-0">
                            Vincule ao menos uma impressora na edição deste toner pra poder dar baixa.
                        </p>
                    <?php else: ?>
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-1">Quantidade</label>
                            <input type="number" min="1" max="<?= (int) $toner['quantidade'] ?>" name="quantidade" class="form-control form-control-sm" value="1" <?= (int) $toner['quantidade'] < 1 ? 'disabled' : '' ?>>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-1">Instalado em</label>
                            <?php if ($impressoraFixa !== null): ?>
                                <input type="hidden" name="equipamento_id" value="<?= (int) $impressoraFixa['id'] ?>">
                                <div class="p-2 px-3 bg-white border rounded d-flex align-items-center gap-2 small">
                                    <i class="bi bi-printer"></i> <strong><?= e(nomeEquipamento($impressoraFixa['nome'], $impressoraFixa['patrimonio'])) ?></strong>
                                </div>
                            <?php else: ?>
                                <select name="equipamento_id" class="form-select form-select-sm" required>
                                    <option value="">Selecione a impressora...</option>
                                    <?php foreach ($impressorasVinculadas as $imp): ?>
                                        <option value="<?= (int) $imp['id'] ?>"><?= e(nomeEquipamento($imp['nome'], $imp['patrimonio'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-outline-danger btn-sm mt-auto js-confirm-delete" <?= (int) $toner['quantidade'] < 1 ? 'disabled' : '' ?>
                                data-confirm-msg="Dar baixa nesta unidade e registrar a troca de toner na impressora selecionada?">
                            <i class="bi bi-check-lg"></i> Confirmar baixa e registrar troca
                        </button>
                    <?php endif; ?>
                </form>
            </div>
            <div class="col-md-6">
                <form method="post" action="toner_movimentar.php" class="p-3 bg-light rounded h-100 d-flex flex-column">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="acao" value="reposicao">
                    <strong class="mb-2"><i class="bi bi-plus-lg text-success"></i> Adicionar (repor)</strong>
                    <p class="text-muted small">Chegou reposição nova — não mexe em nenhuma impressora.</p>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">Quantidade</label>
                        <input type="number" min="1" name="quantidade" class="form-control form-control-sm" value="1">
                    </div>
                    <button type="submit" class="btn btn-outline-success btn-sm mt-auto"><i class="bi bi-check-lg"></i> Adicionar ao estoque</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-printer me-1"></i> Impressoras vinculadas</span>
        <?php if (temPermissao('impressoras', 'alterar')): ?>
            <a href="toner_form.php?id=<?= (int) $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar vínculos</a>
        <?php endif; ?>
    </div>
    <div class="list-group list-group-flush">
        <?php if (empty($impressorasVinculadas)): ?>
            <div class="list-group-item text-muted small">Nenhuma impressora vinculada.</div>
        <?php endif; ?>
        <?php foreach ($impressorasVinculadas as $imp): ?>
            <a href="../equipamentos/view.php?id=<?= (int) $imp['id'] ?>" class="list-group-item list-group-item-action">
                <i class="bi bi-printer me-1"></i> <?= e(nomeEquipamento($imp['nome'], $imp['patrimonio'])) ?>
                <?php if (!empty($imp['localizacao'])): ?><span class="text-muted small"> — <?= e($imp['localizacao']) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card mb-5">
    <div class="card-header bg-white"><i class="bi bi-clock-history me-1"></i> Últimas movimentações</div>
    <div class="list-group list-group-flush">
        <?php if (empty($movimentacoes)): ?>
            <div class="list-group-item text-muted small">Nenhuma movimentação registrada ainda.</div>
        <?php endif; ?>
        <?php foreach ($movimentacoes as $mov): ?>
            <?php
                $iconeMov = match ($mov['tipo']) {
                    'Baixa' => 'bi-dash-circle text-danger',
                    'Reposição' => 'bi-plus-circle text-success',
                    default => 'bi-box-seam text-primary',
                };
                $textoMov = match ($mov['tipo']) {
                    'Baixa' => 'Baixa de ' . (int) $mov['quantidade'] . ' unidade(s)' . ($mov['equipamento_nome'] !== null
                        ? ' — instalado em ' . nomeEquipamento($mov['equipamento_nome'], $mov['equipamento_patrimonio'])
                        : ''),
                    'Reposição' => 'Reposição de ' . (int) $mov['quantidade'] . ' unidade(s)',
                    default => 'Toner cadastrado, ' . (int) $mov['quantidade'] . ' unidade(s) inicial(is)',
                };
            ?>
            <div class="list-group-item py-2">
                <div class="small"><i class="bi <?= $iconeMov ?> me-1"></i> <?= e($textoMov) ?></div>
                <div class="small text-muted"><?= formatDateTime($mov['criado_em']) ?><?= $mov['usuario_nome'] ? ' · ' . e($mov['usuario_nome']) : '' ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
