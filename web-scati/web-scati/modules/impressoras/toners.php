<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('impressoras', 'ver');

$pdo = db();
$pageTitle = 'Toners e Tintas';

$busca = trim($_GET['busca'] ?? '');

$sql = "SELECT t.*,
               GROUP_CONCAT(DISTINCT CONCAT(e.nome, ' — ', COALESCE(e.localizacao, 'sem localização'))
                            ORDER BY e.nome SEPARATOR '||') AS impressoras_nomes
        FROM toners t
        LEFT JOIN toner_impressoras ti ON ti.toner_id = t.id
        LEFT JOIN equipamentos e ON e.id = ti.equipamento_id
        WHERE 1=1";
$params = [];
if ($busca !== '') {
    $sql .= ' AND (t.nome LIKE :busca1 OR t.marca LIKE :busca2 OR t.modelo LIKE :busca3)';
    $curinga = '%' . $busca . '%';
    $params['busca1'] = $curinga;
    $params['busca2'] = $curinga;
    $params['busca3'] = $curinga;
}
$sql .= ' GROUP BY t.id ORDER BY t.nome ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$toners = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-printer me-2"></i>Impressoras</h1>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-list-ul"></i> Impressoras</a></li>
    <li class="nav-item"><a class="nav-link active" href="toners.php"><i class="bi bi-droplet-half"></i> Toners e Tintas</a></li>
</ul>

<div class="alert alert-info d-flex align-items-start gap-2">
    <i class="bi bi-info-circle fs-5"></i>
    <div>
        <strong>Catálogo próprio de Toners e Tintas</strong> — cada modelo é cadastrado uma vez aqui e pode ser
        vinculado a <strong>várias impressoras</strong> que o usam. Toda vez que a quantidade <strong>diminui</strong>,
        o sistema registra automaticamente um evento de troca de toner na impressora que recebeu a unidade.
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form method="get" class="d-flex gap-2">
        <input type="text" name="busca" class="form-control" placeholder="Nome, marca ou modelo..." value="<?= e($busca) ?>" style="width: 280px;">
        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
    </form>
    <?php if (temPermissao('impressoras', 'alterar')): ?>
        <a href="toner_form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Toner/Tinta</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Marca/Modelo</th>
                    <th class="text-center">Em Estoque</th>
                    <th>Impressoras Vinculadas</th>
                    <?php if (temPermissao('impressoras', 'alterar')): ?><th class="text-end">Ações</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($toners)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhum toner ou tinta cadastrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($toners as $toner): ?>
                    <?php
                        $qtd = (int) $toner['quantidade'];
                        $qtdMinima = (int) $toner['quantidade_minima'];
                        if ($qtd <= 0) {
                            $classeLinha = 'table-danger';
                            $classeBadge = 'bg-danger';
                        } elseif ($qtdMinima > 0 && $qtd < $qtdMinima) {
                            $classeLinha = 'table-warning';
                            $classeBadge = 'bg-warning text-dark';
                        } else {
                            $classeLinha = '';
                            $classeBadge = 'bg-success';
                        }
                        $impressoras = $toner['impressoras_nomes'] !== null ? explode('||', $toner['impressoras_nomes']) : [];
                    ?>
                    <tr class="<?= $classeLinha ?>">
                        <td><strong><?= e($toner['nome']) ?></strong></td>
                        <td><span class="badge <?= $toner['tipo'] === 'Tinta' ? 'bg-info text-dark' : 'bg-dark' ?>"><?= e($toner['tipo']) ?></span></td>
                        <td><?= e(trim(($toner['marca'] ?? '') . ' · ' . ($toner['modelo'] ?? ''), ' ·')) ?: '-' ?></td>
                        <td class="text-center">
                            <span class="badge <?= $classeBadge ?> fs-6"><?= $qtd ?></span>
                            <?php if ($qtdMinima > 0 && $qtd < $qtdMinima): ?>
                                <div class="small text-muted">mín. <?= $qtdMinima ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($impressoras)): ?>
                                <span class="text-muted small">Nenhuma vinculada</span>
                            <?php else: ?>
                                <?php foreach ($impressoras as $nomeImpressora): ?>
                                    <span class="badge bg-light text-dark border"><?= e($nomeImpressora) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <?php if (temPermissao('impressoras', 'alterar')): ?>
                        <td class="text-end">
                            <a href="toner.php?id=<?= (int) $toner['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Ajustar quantidade"><i class="bi bi-dash-lg"></i> <i class="bi bi-plus-lg"></i></a>
                            <a href="toner_form.php?id=<?= (int) $toner['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <a href="toner_delete.php?id=<?= (int) $toner['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir &quot;<?= e($toner['nome']) ?>&quot;? Isso também remove os vínculos com impressoras e o histórico de movimentações. Esta ação não pode ser desfeita." title="Excluir">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted small mt-2"><i class="bi bi-exclamation-triangle text-danger"></i> Linhas em vermelho estão zeradas; em amarelo, abaixo da quantidade mínima.</p>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
