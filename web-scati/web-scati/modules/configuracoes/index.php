<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$pdo = db();
$pageTitle = 'Configurações';

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_dias_alerta'])) {
    $diasAlerta = $_POST['dias_alerta_licenca'] ?? '';

    if (!is_numeric($diasAlerta) || (int) $diasAlerta < 0) {
        $erros[] = 'Informe um número de dias válido (0 ou mais) para o alerta de licenças.';
    } else {
        configSet('dias_alerta_licenca', (string) (int) $diasAlerta);
        flash('success', 'Configuração de alerta de licenças salva com sucesso.');
        redirect('/modules/configuracoes/index.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_dias_alerta_toner'])) {
    $diasAlertaToner = $_POST['dias_alerta_toner'] ?? '';

    if (!is_numeric($diasAlertaToner) || (int) $diasAlertaToner < 0) {
        $erros[] = 'Informe um número de dias válido (0 ou mais) para o alerta de troca de toner.';
    } else {
        configSet('dias_alerta_toner', (string) (int) $diasAlertaToner);
        flash('success', 'Configuração de alerta de troca de toner salva com sucesso.');
        redirect('/modules/configuracoes/index.php');
    }
}

$diasAlertaLicenca = configGet('dias_alerta_licenca', '30');
$diasAlertaToner = configGet('dias_alerta_toner', '7');
$totalCategorias = (int) $pdo->query('SELECT COUNT(*) FROM categorias_estoque')->fetchColumn();
$totalTiposManutencao = (int) $pdo->query('SELECT COUNT(*) FROM tipos_manutencao')->fetchColumn();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-gear me-2"></i>Configurações</h1>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title"><i class="bi bi-tags me-1"></i> Categorias de Estoque</h5>
                <p class="card-text text-muted">
                    Cadastre, renomeie ou exclua as categorias usadas para classificar os itens do Estoque
                    (Mouse, Teclado, Toner, Cabos, etc.).
                </p>
                <p class="text-muted small mb-3"><?= $totalCategorias ?> categoria(s) cadastrada(s).</p>
                <a href="../categorias_estoque/index.php" class="btn btn-outline-primary mt-auto">
                    <i class="bi bi-arrow-right"></i> Gerenciar Categorias
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title"><i class="bi bi-tools me-1"></i> Tipos de Manutenção</h5>
                <p class="card-text text-muted">
                    Cadastre, renomeie ou exclua os tipos de manutenção disponíveis ao registrar uma
                    manutenção no histórico de um equipamento.
                </p>
                <p class="text-muted small mb-3"><?= $totalTiposManutencao ?> tipo(s) cadastrado(s).</p>
                <a href="../tipos_manutencao/index.php" class="btn btn-outline-primary mt-auto">
                    <i class="bi bi-arrow-right"></i> Gerenciar Tipos de Manutenção
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-bell me-1"></i> Alerta de Licenças</h5>
                <p class="card-text text-muted">
                    Define com quantos dias de antecedência uma licença prestes a vencer aparece na
                    Central de Alertas do Dashboard.
                </p>
                <form method="post" class="row g-2 align-items-end">
                    <div class="col-6">
                        <label class="form-label">Dias de antecedência</label>
                        <input type="number" min="0" name="dias_alerta_licenca" class="form-control" value="<?= e($diasAlertaLicenca) ?>">
                    </div>
                    <div class="col-6">
                        <button type="submit" name="salvar_dias_alerta" value="1" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg"></i> Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-inkbottle me-1"></i> Alerta de Troca de Toner</h5>
                <p class="card-text text-muted">
                    Define com quantos dias de antecedência uma impressora com a troca de toner se
                    aproximando (com base na duração estimada configurada em cada impressora) aparece
                    na Central de Alertas do Dashboard.
                </p>
                <form method="post" class="row g-2 align-items-end">
                    <div class="col-6">
                        <label class="form-label">Dias de antecedência</label>
                        <input type="number" min="0" name="dias_alerta_toner" class="form-control" value="<?= e($diasAlertaToner) ?>">
                    </div>
                    <div class="col-6">
                        <button type="submit" name="salvar_dias_alerta_toner" value="1" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg"></i> Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
