<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push.php';
exigirPermissao('chamados', 'ver');

$pdo = db();
$usuarioAtual = usuarioLogado();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/chamados/index.php');
}

$chamadoId = (int) ($_POST['chamado_id'] ?? 0);
$mensagem = trim($_POST['mensagem'] ?? '');
$arquivos = $_FILES['anexos'] ?? null;

$stmt = $pdo->prepare('SELECT id, titulo, criado_por_id, responsavel_id FROM chamados WHERE id = :id');
$stmt->execute(['id' => $chamadoId]);
$chamado = $stmt->fetch();

if (!$chamado) {
    flash('danger', 'Chamado não encontrado.');
    redirect('/modules/chamados/index.php');
}

// O perfil Usuário só pode responder aos próprios chamados.
if ($usuarioAtual['solicitante'] && (int) $chamado['criado_por_id'] !== (int) $usuarioAtual['id']) {
    flash('danger', 'Você só pode responder aos próprios chamados.');
    redirect('/modules/chamados/index.php');
}

// Monta a lista de arquivos enviados (ignora os campos "vazios" do input
// múltiplo quando nenhum arquivo foi escolhido naquele slot).
$tamanhoMaximo = 10 * 1024 * 1024; // 10 MB
$arquivosValidos = [];
if (is_array($arquivos) && is_array($arquivos['error'] ?? null)) {
    foreach ($arquivos['error'] as $indice => $erro) {
        if ($erro === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($erro !== UPLOAD_ERR_OK) {
            flash('danger', 'Erro ao enviar o arquivo "' . basename($arquivos['name'][$indice]) . '".');
            redirect('/modules/chamados/form.php?id=' . $chamadoId);
        }
        $tamanho = (int) $arquivos['size'][$indice];
        if ($tamanho > $tamanhoMaximo || $tamanho <= 0) {
            flash('danger', 'O arquivo "' . basename($arquivos['name'][$indice]) . '" excede o tamanho máximo permitido (10 MB).');
            redirect('/modules/chamados/form.php?id=' . $chamadoId);
        }
        $extensao = strtolower(pathinfo($arquivos['name'][$indice], PATHINFO_EXTENSION));
        if (!in_array($extensao, extensoesAnexoPermitidas(), true)) {
            flash('danger', 'Tipo de arquivo não permitido: "' . basename($arquivos['name'][$indice]) . '". Extensões aceitas: ' . implode(', ', extensoesAnexoPermitidas()) . '.');
            redirect('/modules/chamados/form.php?id=' . $chamadoId);
        }
        if (!is_uploaded_file($arquivos['tmp_name'][$indice])) {
            flash('danger', 'Falha ao processar o envio do arquivo "' . basename($arquivos['name'][$indice]) . '".');
            redirect('/modules/chamados/form.php?id=' . $chamadoId);
        }
        $arquivosValidos[] = [
            'tmp_name' => $arquivos['tmp_name'][$indice],
            'nome_original' => basename($arquivos['name'][$indice]),
            'extensao' => $extensao,
            'tamanho' => $tamanho,
        ];
    }
}

if ($mensagem === '' && empty($arquivosValidos)) {
    flash('danger', 'Digite uma mensagem ou anexe um arquivo antes de enviar.');
    redirect('/modules/chamados/form.php?id=' . $chamadoId);
}

$pdo->prepare(
    'INSERT INTO chamado_respostas (chamado_id, usuario_id, usuario_nome, mensagem)
     VALUES (:chamado_id, :usuario_id, :usuario_nome, :mensagem)'
)->execute([
    'chamado_id' => $chamadoId,
    'usuario_id' => $usuarioAtual['id'],
    'usuario_nome' => $usuarioAtual['usuario'],
    'mensagem' => $mensagem,
]);
$respostaId = (int) $pdo->lastInsertId();

if (!empty($arquivosValidos)) {
    $pastaUploads = __DIR__ . '/../../uploads/anexos_chamados';
    if (!is_dir($pastaUploads)) {
        mkdir($pastaUploads, 0755, true);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $stmtAnexo = $pdo->prepare(
        'INSERT INTO chamado_resposta_anexos (resposta_id, nome_original, nome_arquivo, tipo_mime, tamanho)
         VALUES (:resposta_id, :nome_original, :nome_arquivo, :tipo_mime, :tamanho)'
    );
    foreach ($arquivosValidos as $arquivo) {
        // Nome gerado aleatoriamente para o arquivo em disco: nunca usa o
        // nome original enviado pelo usuário, evitando path traversal e
        // colisões.
        $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $arquivo['extensao'];
        $caminhoDestino = $pastaUploads . '/' . $nomeArquivo;
        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
            continue;
        }
        $tipoMime = $finfo->file($caminhoDestino) ?: 'application/octet-stream';
        $stmtAnexo->execute([
            'resposta_id' => $respostaId,
            'nome_original' => $arquivo['nome_original'],
            'nome_arquivo' => $nomeArquivo,
            'tipo_mime' => $tipoMime,
            'tamanho' => $arquivo['tamanho'],
        ]);
    }
}

// Quem acabou de responder já viu o chamado até este momento.
marcarChamadoVisto($chamadoId, $usuarioAtual['id']);

// Notifica quem está diretamente envolvido no chamado (solicitante e
// responsável), exceto quem acabou de mandar a mensagem.
$destinatarios = array_unique(array_filter([
    $chamado['criado_por_id'] !== null ? (int) $chamado['criado_por_id'] : null,
    $chamado['responsavel_id'] !== null ? (int) $chamado['responsavel_id'] : null,
]));
$destinatarios = array_diff($destinatarios, [(int) $usuarioAtual['id']]);
$resumoMensagem = $mensagem !== '' ? $mensagem : (count($arquivosValidos) > 1 ? 'enviou ' . count($arquivosValidos) . ' anexos' : 'enviou um anexo');
foreach ($destinatarios as $destinatarioId) {
    enviarPushParaUsuario(
        $destinatarioId,
        'Nova mensagem no chamado',
        $chamado['titulo'] . ' — ' . $usuarioAtual['usuario'] . ': ' . $resumoMensagem,
        BASE_URL . '/modules/chamados/form.php?id=' . $chamadoId
    );
}

flash('success', 'Resposta enviada.');
redirect('/modules/chamados/form.php?id=' . $chamadoId);
