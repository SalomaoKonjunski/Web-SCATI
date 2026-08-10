<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Equipamentos';
$pdo = db();

// --- Filtros e pesquisa -------------------------------------------------
$busca      = trim($_GET['busca'] ?? '');
$filtroTipo = $_GET['tipo'] ?? '';
$filtroStatus = $_GET['status'] ?? '';
$filtroRede = $_GET['rede_id'] ?? '';

$sql = "SELECT e.*, r.nome AS rede_nome
        FROM equipamentos e
        LEFT JOIN redes r ON r.id = e.rede_id
        WHERE 1=1";
$params = [];

if ($busca !== '') {
    // Pesquisa por patrimônio, hostname, número de série, marca, modelo ou responsável (seção 13)
    $sql .= " AND (e.patrimonio LIKE :busca1 OR e.hostname LIKE :busca2
              OR e.numero_serie LIKE :busca3 OR e.marca LIKE :busca4
              OR e.modelo LIKE :busca5 OR e.usuario_responsavel LIKE :busca6)";
    $curingaBusca = '%' . $busca . '%';
    $params['busca1'] = $curingaBusca;
    $params['busca2'] = $curingaBusca;
    $params['busca3'] = $curingaBusca;
    $params['busca4'] = $curingaBusca;
    $params['busca5'] = $curingaBusca;
    $params['busca6'] = $curingaBusca;
}
if ($filtroTipo !== '') {
    $sql .= " AND e.tipo = :tipo";
    $params['tipo'] = $filtroTipo;
}
if ($filtroStatus !== '') {
    $sql .= " AND e.status = :status";
    $params['status'] = $filtroStatus;
}
if ($filtroRede !== '') {
    $sql .= " AND e.rede_id = :rede_id";
    $params['rede_id'] = $filtroRede;
}

$sql .= " ORDER BY e.patrimonio ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipamentos = $stmt->fetchAll();

$redes = $pdo->query("SELECT id, nome FROM redes ORDER BY nome")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-pc-display me-2"></i>Equipamentos</h1>
    <a href="form.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Novo Equipamento
    </a>
</div>

<!-- Pesquisa e filtros -->
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Pesquisar</label>
                <input type="text" name="busca" class="form-control" placeholder="Patrimônio, hostname, série, marca, modelo, responsável..."
                       value="<?= e($busca) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (tiposEquipamento() as $tipo): ?>
                        <option value="<?= e($tipo) ?>" <?= $filtroTipo === $tipo ? 'selected' : '' ?>><?= e($tipo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (statusEquipamento() as $status): ?>
                        <option value="<?= e($status) ?>" <?= $filtroStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Rede</label>
                <select name="rede_id" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($redes as $rede): ?>
                        <option value="<?= (int) $rede['id'] ?>" <?= (string) $filtroRede === (string) $rede['id'] ? 'selected' : '' ?>><?= e($rede['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
                <a href="index.php" class="btn btn-outline-secondary" title="Limpar filtros"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Patrimônio</th>
                    <th>Tipo</th>
                    <th>Marca / Modelo</th>
                    <th>Hostname</th>
                    <th>Responsável</th>
                    <th>Rede</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($equipamentos)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Nenhum equipamento encontrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($equipamentos as $eq): ?>
                    <tr data-href="view.php?id=<?= (int) $eq['id'] ?>">
                        <td><strong><?= e($eq['patrimonio']) ?></strong></td>
                        <td><?= e($eq['tipo']) ?></td>
                        <td><?= e(trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? ''))) ?: '-' ?></td>
                        <td><?= e($eq['hostname']) ?: '-' ?></td>
                        <td><?= e($eq['usuario_responsavel']) ?: '-' ?></td>
                        <td><?= e($eq['rede_nome']) ?: '-' ?></td>
                        <td><span class="badge <?= statusBadgeClass($eq['status']) ?>"><?= e($eq['status']) ?></span></td>
                        <td class="text-end">
                            <a href="view.php?id=<?= (int) $eq['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Visualizar">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="form.php?id=<?= (int) $eq['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="delete.php?id=<?= (int) $eq['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir o equipamento <?= e($eq['patrimonio']) ?>? Esta ação não pode ser desfeita." title="Excluir">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted small mt-2"><?= count($equipamentos) ?> equipamento(s) encontrado(s).</p>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
