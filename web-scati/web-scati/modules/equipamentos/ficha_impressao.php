<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT e.*, r.nome AS rede_nome
     FROM equipamentos e LEFT JOIN redes r ON r.id = e.rede_id
     WHERE e.id = :id'
);
$stmt->execute(['id' => $id]);
$eq = $stmt->fetch();

if (!$eq) {
    flash('danger', 'Equipamento não encontrado.');
    redirect('/modules/equipamentos/index.php');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Impressão · <?= e($eq['patrimonio']) ?> · Web SCATI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .ficha-card {
            max-width: 720px;
            margin: 2rem auto;
            border: 2px solid #1e3a5f;
            border-radius: 0.5rem;
            background: #fff;
        }
        .ficha-header {
            background: #1e3a5f;
            color: #fff;
            border-radius: 0.4rem 0.4rem 0 0;
            padding: 1rem 1.5rem;
        }
        .ficha-secao h6 {
            color: #1e3a5f;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.25rem;
            margin-bottom: 0.5rem;
        }
        .ficha-campo { font-size: 0.92rem; margin-bottom: 0.35rem; }
        .ficha-campo strong { display: inline-block; min-width: 150px; color: #495057; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .ficha-card { margin: 0 auto; border-width: 1.5px; box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="container no-print py-3 d-flex justify-content-between align-items-center" style="max-width: 720px;">
    <a href="view.php?id=<?= (int) $eq['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar à ficha</a>
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir / Salvar em PDF</button>
</div>

<div class="ficha-card">
    <div class="ficha-header d-flex justify-content-between align-items-center">
        <div>
            <div class="small text-uppercase" style="opacity:.75;">Web SCATI · Ficha do Equipamento</div>
            <h2 class="mb-0"><?= e($eq['patrimonio']) ?></h2>
        </div>
        <span class="badge <?= statusBadgeClass($eq['status']) ?> fs-6"><?= e($eq['status']) ?></span>
    </div>

    <div class="p-4">
        <p class="text-muted mb-4">
            <?= e($eq['tipo']) ?><?= trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? '')) ? ' · ' . e(trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? ''))) : '' ?>
        </p>

        <div class="row">
            <div class="col-md-6 ficha-secao">
                <h6 class="text-uppercase small">Identificação</h6>
                <div class="ficha-campo"><strong>Patrimônio:</strong> <?= e($eq['patrimonio']) ?></div>
                <div class="ficha-campo"><strong>Tipo:</strong> <?= e($eq['tipo']) ?></div>
                <div class="ficha-campo"><strong>Marca:</strong> <?= e($eq['marca']) ?: '-' ?></div>
                <div class="ficha-campo"><strong>Modelo:</strong> <?= e($eq['modelo']) ?: '-' ?></div>
                <div class="ficha-campo"><strong>Número de Série:</strong> <?= e($eq['numero_serie']) ?: '-' ?></div>
                <div class="ficha-campo"><strong>Hostname:</strong> <?= e($eq['hostname']) ?: '-' ?></div>

                <h6 class="text-uppercase small mt-4">Hardware</h6>
                <div class="ficha-campo"><strong>Processador:</strong> <?= e($eq['processador']) ?: '-' ?></div>
                <div class="ficha-campo"><strong>Memória RAM:</strong> <?= e($eq['memoria_ram']) ?: '-' ?></div>
                <div class="ficha-campo"><strong>Armazenamento:</strong> <?= e($eq['armazenamento']) ?: '-' ?></div>
                <div class="ficha-campo"><strong>Sistema Operacional:</strong> <?= e($eq['sistema_operacional']) ?: '-' ?></div>
                <?php if (ehComputador($eq['tipo'])): ?>
                    <div class="ficha-campo"><strong>Placa Mãe:</strong> <?= e($eq['placa_mae']) ?: '-' ?></div>
                    <div class="ficha-campo"><strong>Placa de Vídeo:</strong> <?= e($eq['placa_video']) ?: '-' ?></div>
                <?php endif; ?>

                <?php if (ehImpressora($eq['tipo'])): ?>
                    <h6 class="text-uppercase small mt-4">Dados da Impressora</h6>
                    <div class="ficha-campo"><strong>Endereço IP:</strong> <?= e($eq['ip']) ?: '-' ?></div>
                    <div class="ficha-campo"><strong>Modelo do Toner:</strong> <?= e($eq['modelo_toner']) ?: '-' ?></div>
                <?php endif; ?>

                <?php if (ehComputador($eq['tipo'])): ?>
                    <h6 class="text-uppercase small mt-4">Rede do Computador</h6>
                    <div class="ficha-campo"><strong>IP Fixo:</strong> <?= e($eq['ip_fixo']) ?: '-' ?></div>
                <?php endif; ?>

                <?php if (ehServidor($eq['tipo'])): ?>
                    <h6 class="text-uppercase small mt-4">Informações do Servidor</h6>
                    <div class="ficha-campo"><strong>Função:</strong> <?= e($eq['funcao_servidor']) ?: '-' ?></div>
                    <div class="ficha-campo"><strong>Status do Servidor:</strong> <?= e($eq['servidor_status']) ?: '-' ?></div>
                    <?php if ($eq['servidor_observacoes']): ?>
                        <div class="ficha-campo"><strong>Observações:</strong> <?= nl2br(e($eq['servidor_observacoes'])) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="col-md-6 ficha-secao">
                <h6 class="text-uppercase small">Localização e Uso</h6>
                <div class="ficha-campo"><strong>Status:</strong> <?= e($eq['status']) ?></div>
                <div class="ficha-campo"><strong>Localização:</strong> <?= e($eq['localizacao']) ?: '-' ?></div>
                <div class="ficha-campo"><strong>Responsável:</strong> <?= e($eq['usuario_responsavel']) ?: '-' ?></div>
                <div class="ficha-campo"><strong>Rede:</strong> <?= e($eq['rede_nome']) ?: '-' ?></div>

                <h6 class="text-uppercase small mt-4">Garantia</h6>
                <div class="ficha-campo"><strong>Garantia:</strong> <?= e($eq['garantia']) ?: '-' ?></div>
                <div class="ficha-campo"><strong>Fornecedor:</strong> <?= e($eq['fornecedor']) ?: '-' ?></div>
            </div>
        </div>

        <hr class="mt-4">
        <p class="text-muted small mb-0">
            Cadastrado em <?= formatDateTime($eq['criado_em']) ?> ·
            Ficha gerada em <?= (new DateTime())->format('d/m/Y H:i') ?>
        </p>
    </div>
</div>

</body>
</html>
