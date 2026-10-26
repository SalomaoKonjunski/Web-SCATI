<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('transferencias', 'ver');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM transferencias WHERE id = :id');
$stmt->execute(['id' => $id]);
$transferencia = $stmt->fetch();

if (!$transferencia) {
    flash('danger', 'Transferência não encontrada.');
    redirect('/modules/transferencias/index.php');
}

$stmtItens = $pdo->prepare(
    'SELECT ti.*,
            e.tipo AS equipamento_tipo, e.patrimonio AS equipamento_patrimonio,
            ce.nome AS estoque_categoria_nome
     FROM transferencia_itens ti
     LEFT JOIN equipamentos e ON e.id = ti.equipamento_id
     LEFT JOIN estoque es ON es.id = ti.estoque_id
     LEFT JOIN categorias_estoque ce ON ce.id = es.categoria_id
     WHERE ti.transferencia_id = :id ORDER BY ti.id ASC'
);
$stmtItens->execute(['id' => $id]);
$itens = $stmtItens->fetchAll();

$stmtAnexos = $pdo->prepare('SELECT * FROM anexos_transferencias WHERE transferencia_id = :id ORDER BY criado_em DESC');
$stmtAnexos->execute(['id' => $id]);
$anexos = $stmtAnexos->fetchAll();

$pageTitle = 'Transferência #' . $id;
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-truck me-2"></i>Transferência #<?= (int) $transferencia['id'] ?></h1>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        <a href="delete.php?id=<?= (int) $transferencia['id'] ?>" class="btn btn-outline-danger js-confirm-delete"
           data-confirm-msg="Excluir esta transferência? Os itens não voltam ao local de origem automaticamente.">
            <i class="bi bi-trash"></i> Excluir
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">Origem</div>
                <div class="fw-semibold"><?= e($transferencia['origem']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Destino</div>
                <div class="fw-semibold"><?= e($transferencia['destino']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Responsável</div>
                <div class="fw-semibold"><?= e($transferencia['responsavel_nome']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Data</div>
                <div class="fw-semibold"><?= formatDate($transferencia['data_transferencia']) ?></div>
            </div>
            <?php if (!empty($transferencia['observacoes'])): ?>
            <div class="col-12">
                <div class="text-muted small">Observações</div>
                <div><?= nl2br(e($transferencia['observacoes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light"><strong>Itens (<?= count($itens) ?>)</strong></div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Item</th><th>Tipo</th><th>Quantidade</th><th>Patrimônio</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                    <?php $ehEstoque = $item['tipo_item'] === 'estoque'; ?>
                    <tr>
                        <td>
                            <?php if ($ehEstoque): ?>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle me-1"><i class="bi bi-box-seam"></i> Estoque</span>
                            <?php else: ?>
                                <span class="badge bg-secondary me-1"><i class="bi bi-pc-display"></i> Equipamento</span>
                            <?php endif; ?>
                            <?= e($item['item_nome']) ?>
                            <?php if (!$ehEstoque && (int) $item['equipamento_novo'] === 1): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1">Novo</span>
                            <?php endif; ?>
                            <?php if (($ehEstoque && !$item['estoque_id']) || (!$ehEstoque && !$item['equipamento_id'])): ?>
                                <span class="badge bg-secondary ms-1">Excluído</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $ehEstoque ? e($item['estoque_categoria_nome'] ?? '-') : e($item['equipamento_tipo'] ?? '-') ?></td>
                        <td><?= $ehEstoque ? (int) $item['quantidade'] : '-' ?></td>
                        <td><?= (!$ehEstoque && $item['equipamento_patrimonio']) ? e($item['equipamento_patrimonio']) : '-' ?></td>
                        <td class="text-end">
                            <?php if ($ehEstoque && $item['estoque_id']): ?>
                                <a href="../estoque/form.php?id=<?= (int) $item['estoque_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-box-arrow-up-right"></i> Ver item
                                </a>
                            <?php elseif (!$ehEstoque && $item['equipamento_id']): ?>
                                <a href="../equipamentos/view.php?id=<?= (int) $item['equipamento_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-box-arrow-up-right"></i> Ver ficha
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3" id="anexos">
    <div class="card-header bg-light"><strong>Anexos (<?= count($anexos) ?>)</strong></div>
    <div class="card-body">
        <form method="post" action="anexo_upload.php" enctype="multipart/form-data" class="mb-4">
            <label class="form-label fw-semibold">+ Novo Anexo</label>
            <input type="hidden" name="transferencia_id" value="<?= (int) $transferencia['id'] ?>">
            <div class="row g-2">
                <div class="col-md-5">
                    <div class="d-flex gap-2">
                        <input type="file" name="arquivo" id="anexoArquivo" class="form-control" required>
                        <label for="anexoCamera" class="btn btn-outline-secondary d-md-none flex-shrink-0 mb-0" title="Tirar foto">
                            <i class="bi bi-camera"></i>
                        </label>
                        <input type="file" id="anexoCamera" accept="image/*" capture="environment" class="d-none">
                    </div>
                </div>
                <div class="col-md-5">
                    <input type="text" name="descricao" class="form-control" placeholder="Descrição (opcional, ex: PDF do formulário de transferência)">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload"></i> Enviar</button>
                </div>
            </div>
            <div class="form-text">
                Tamanho máximo 10 MB. Formatos aceitos: <?= e(implode(', ', extensoesAnexoPermitidas())) ?>.
                <span class="d-md-none">No celular, use <i class="bi bi-camera"></i> para tirar uma foto na hora.</span>
            </div>
        </form>

        <?php if (empty($anexos)): ?>
            <p class="text-muted mb-0">Nenhum anexo cadastrado para esta transferência.</p>
        <?php else: ?>
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Arquivo</th><th>Descrição</th><th>Tamanho</th><th>Enviado em</th><th class="text-end">Ações</th></tr>
                </thead>
                <tbody>
                <?php foreach ($anexos as $anexo): ?>
                    <?php $extensaoAnexo = pathinfo($anexo['nome_original'], PATHINFO_EXTENSION); ?>
                    <tr>
                        <td><i class="bi <?= iconeAnexo($extensaoAnexo) ?> me-1 text-muted"></i><?= e($anexo['nome_original']) ?></td>
                        <td><?= $anexo['descricao'] ? e($anexo['descricao']) : '-' ?></td>
                        <td><?= formatBytes((int) $anexo['tamanho']) ?></td>
                        <td><?= formatDateTime($anexo['criado_em']) ?></td>
                        <td class="text-end">
                            <a href="anexo_download.php?id=<?= (int) $anexo['id'] ?>" class="btn btn-sm btn-outline-primary" title="Baixar">
                                <i class="bi bi-download"></i>
                            </a>
                            <a href="anexo_excluir.php?id=<?= (int) $anexo['id'] ?>" class="btn btn-sm btn-outline-danger js-confirm-delete"
                               data-confirm-msg="Excluir o anexo &quot;<?= e($anexo['nome_original']) ?>&quot;? Esta ação não pode ser desfeita." title="Excluir">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
