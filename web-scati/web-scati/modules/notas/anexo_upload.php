<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirNaoSolicitante();

$pdo = db();
$usuarioId = usuarioLogado()['id'];
$notaId = (int) ($_POST['nota_id'] ?? 0);

// Notas são pessoais — só o dono pode anexar arquivo na própria nota.
$stmt = $pdo->prepare('SELECT id FROM notas WHERE id = :id AND usuario_id = :usuario_id');
$stmt->execute(['id' => $notaId, 'usuario_id' => $usuarioId]);
if (!$stmt->fetch()) {
    flash('danger', 'Nota não encontrada.');
    redirect('/modules/notas/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['arquivo'])) {
    redirect('/modules/notas/form.php?id=' . $notaId . '#anexos');
}

$arquivo = $_FILES['arquivo'];
$tamanhoMaximo = 10 * 1024 * 1024; // 10 MB

if ($arquivo['error'] === UPLOAD_ERR_NO_FILE) {
    flash('danger', 'Selecione um arquivo para anexar.');
    redirect('/modules/notas/form.php?id=' . $notaId . '#anexos');
}

if ($arquivo['error'] !== UPLOAD_ERR_OK) {
    flash('danger', 'Erro ao enviar o arquivo.');
    redirect('/modules/notas/form.php?id=' . $notaId . '#anexos');
}

if ($arquivo['size'] > $tamanhoMaximo || $arquivo['size'] <= 0) {
    flash('danger', 'O arquivo excede o tamanho máximo permitido (10 MB).');
    redirect('/modules/notas/form.php?id=' . $notaId . '#anexos');
}

$extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
if (!in_array($extensao, extensoesAnexoPermitidas(), true)) {
    flash('danger', 'Tipo de arquivo não permitido. Extensões aceitas: ' . implode(', ', extensoesAnexoPermitidas()) . '.');
    redirect('/modules/notas/form.php?id=' . $notaId . '#anexos');
}

if (!is_uploaded_file($arquivo['tmp_name'])) {
    flash('danger', 'Falha ao processar o envio do arquivo.');
    redirect('/modules/notas/form.php?id=' . $notaId . '#anexos');
}

$pastaUploads = __DIR__ . '/../../uploads/anexos_notas';
if (!is_dir($pastaUploads)) {
    mkdir($pastaUploads, 0755, true);
}

// Nome gerado aleatoriamente para o arquivo em disco: nunca usa o nome original
// enviado pelo usuário, evitando path traversal e colisões.
$nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensao;
$caminhoDestino = $pastaUploads . '/' . $nomeArquivo;

if (!move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
    flash('danger', 'Não foi possível salvar o arquivo no servidor.');
    redirect('/modules/notas/form.php?id=' . $notaId . '#anexos');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$tipoMime = $finfo->file($caminhoDestino) ?: 'application/octet-stream';

$nomeOriginal = basename($arquivo['name']);

$pdo->prepare(
    'INSERT INTO anexos_notas (nota_id, nome_original, nome_arquivo, tipo_mime, tamanho)
     VALUES (:nota_id, :nome_original, :nome_arquivo, :tipo_mime, :tamanho)'
)->execute([
    'nota_id' => $notaId,
    'nome_original' => $nomeOriginal,
    'nome_arquivo' => $nomeArquivo,
    'tipo_mime' => $tipoMime,
    'tamanho' => $arquivo['size'],
]);

flash('success', 'Arquivo anexado com sucesso.');
redirect('/modules/notas/form.php?id=' . $notaId . '#anexos');
