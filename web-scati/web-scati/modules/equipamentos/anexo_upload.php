<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();
$equipamentoId = (int) ($_POST['equipamento_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id FROM equipamentos WHERE id = :id');
$stmt->execute(['id' => $equipamentoId]);
if (!$stmt->fetch()) {
    flash('danger', 'Equipamento não encontrado.');
    redirect('/modules/equipamentos/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['arquivo'])) {
    redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#anexos');
}

$descricao = trim($_POST['descricao'] ?? '');
$arquivo = $_FILES['arquivo'];
$tamanhoMaximo = 10 * 1024 * 1024; // 10 MB

if ($arquivo['error'] === UPLOAD_ERR_NO_FILE) {
    flash('danger', 'Selecione um arquivo para anexar.');
    redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#anexos');
}

if ($arquivo['error'] !== UPLOAD_ERR_OK) {
    flash('danger', 'Erro ao enviar o arquivo.');
    redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#anexos');
}

if ($arquivo['size'] > $tamanhoMaximo || $arquivo['size'] <= 0) {
    flash('danger', 'O arquivo excede o tamanho máximo permitido (10 MB).');
    redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#anexos');
}

$extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
if (!in_array($extensao, extensoesAnexoPermitidas(), true)) {
    flash('danger', 'Tipo de arquivo não permitido. Extensões aceitas: ' . implode(', ', extensoesAnexoPermitidas()) . '.');
    redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#anexos');
}

if (!is_uploaded_file($arquivo['tmp_name'])) {
    flash('danger', 'Falha ao processar o envio do arquivo.');
    redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#anexos');
}

$pastaUploads = __DIR__ . '/../../uploads/anexos';
if (!is_dir($pastaUploads)) {
    mkdir($pastaUploads, 0755, true);
}

// Nome gerado aleatoriamente para o arquivo em disco: nunca usa o nome original
// enviado pelo usuário, evitando path traversal e colisões.
$nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensao;
$caminhoDestino = $pastaUploads . '/' . $nomeArquivo;

if (!move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
    flash('danger', 'Não foi possível salvar o arquivo no servidor.');
    redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#anexos');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$tipoMime = $finfo->file($caminhoDestino) ?: 'application/octet-stream';

$nomeOriginal = basename($arquivo['name']);

$stmtInsert = $pdo->prepare(
    'INSERT INTO anexos_equipamentos (equipamento_id, nome_original, nome_arquivo, tipo_mime, tamanho, descricao)
     VALUES (:equipamento_id, :nome_original, :nome_arquivo, :tipo_mime, :tamanho, :descricao)'
);
$stmtInsert->execute([
    'equipamento_id' => $equipamentoId,
    'nome_original' => $nomeOriginal,
    'nome_arquivo' => $nomeArquivo,
    'tipo_mime' => $tipoMime,
    'tamanho' => $arquivo['size'],
    'descricao' => $descricao ?: null,
]);

registrarHistorico($equipamentoId, 'Anexo', 'Arquivo "' . $nomeOriginal . '" anexado a este equipamento');

flash('success', 'Arquivo anexado com sucesso.');
redirect('/modules/equipamentos/view.php?id=' . $equipamentoId . '#anexos');
