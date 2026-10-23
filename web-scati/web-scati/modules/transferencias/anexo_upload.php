<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirPermissao('transferencias', 'alterar');

$pdo = db();
$transferenciaId = (int) ($_POST['transferencia_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id FROM transferencias WHERE id = :id');
$stmt->execute(['id' => $transferenciaId]);
if (!$stmt->fetch()) {
    flash('danger', 'Transferência não encontrada.');
    redirect('/modules/transferencias/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['arquivo'])) {
    redirect('/modules/transferencias/view.php?id=' . $transferenciaId . '#anexos');
}

$descricao = trim($_POST['descricao'] ?? '');
$arquivo = $_FILES['arquivo'];
$tamanhoMaximo = 10 * 1024 * 1024; // 10 MB

if ($arquivo['error'] === UPLOAD_ERR_NO_FILE) {
    flash('danger', 'Selecione um arquivo para anexar.');
    redirect('/modules/transferencias/view.php?id=' . $transferenciaId . '#anexos');
}

if ($arquivo['error'] !== UPLOAD_ERR_OK) {
    flash('danger', 'Erro ao enviar o arquivo.');
    redirect('/modules/transferencias/view.php?id=' . $transferenciaId . '#anexos');
}

if ($arquivo['size'] > $tamanhoMaximo || $arquivo['size'] <= 0) {
    flash('danger', 'O arquivo excede o tamanho máximo permitido (10 MB).');
    redirect('/modules/transferencias/view.php?id=' . $transferenciaId . '#anexos');
}

$extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
if (!in_array($extensao, extensoesAnexoPermitidas(), true)) {
    flash('danger', 'Tipo de arquivo não permitido. Extensões aceitas: ' . implode(', ', extensoesAnexoPermitidas()) . '.');
    redirect('/modules/transferencias/view.php?id=' . $transferenciaId . '#anexos');
}

if (!is_uploaded_file($arquivo['tmp_name'])) {
    flash('danger', 'Falha ao processar o envio do arquivo.');
    redirect('/modules/transferencias/view.php?id=' . $transferenciaId . '#anexos');
}

$pastaUploads = __DIR__ . '/../../uploads/anexos_transferencias';
if (!is_dir($pastaUploads)) {
    mkdir($pastaUploads, 0755, true);
}

// Nome gerado aleatoriamente para o arquivo em disco: nunca usa o nome original
// enviado pelo usuário, evitando path traversal e colisões.
$nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensao;
$caminhoDestino = $pastaUploads . '/' . $nomeArquivo;

if (!move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
    flash('danger', 'Não foi possível salvar o arquivo no servidor.');
    redirect('/modules/transferencias/view.php?id=' . $transferenciaId . '#anexos');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$tipoMime = $finfo->file($caminhoDestino) ?: 'application/octet-stream';

$nomeOriginal = basename($arquivo['name']);

$stmtInsert = $pdo->prepare(
    'INSERT INTO anexos_transferencias (transferencia_id, nome_original, nome_arquivo, tipo_mime, tamanho, descricao)
     VALUES (:transferencia_id, :nome_original, :nome_arquivo, :tipo_mime, :tamanho, :descricao)'
);
$stmtInsert->execute([
    'transferencia_id' => $transferenciaId,
    'nome_original' => $nomeOriginal,
    'nome_arquivo' => $nomeArquivo,
    'tipo_mime' => $tipoMime,
    'tamanho' => $arquivo['size'],
    'descricao' => $descricao ?: null,
]);

flash('success', 'Arquivo anexado com sucesso.');
redirect('/modules/transferencias/view.php?id=' . $transferenciaId . '#anexos');
