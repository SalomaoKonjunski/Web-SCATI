<?php
/**
 * Web SCATI - Funções auxiliares
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * Redireciona para outra URL relativa à BASE_URL e encerra o script.
 */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Guarda uma mensagem "flash" na sessão para exibir após um redirect.
 */
function flash(string $tipo, string $mensagem): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

/**
 * Recupera (e remove) a mensagem flash armazenada, se existir.
 */
function getFlash(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Escapa texto para saída segura em HTML.
 */
function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Retorna o patrimônio para exibição, com o rótulo "Indefinido" quando o
 * equipamento foi cadastrado sem um patrimônio definido.
 */
function patrimonioOuIndefinido(?string $patrimonio): string
{
    return ($patrimonio !== null && trim($patrimonio) !== '') ? $patrimonio : 'Indefinido';
}

/**
 * Retorna o nome do equipamento para exibição. Equipamentos cadastrados
 * antes do campo "Nome" existir (ou ainda não editados) não têm nome
 * preenchido — nesse caso, cai para o patrimônio (ou "Indefinido").
 */
function nomeEquipamento(?string $nome, ?string $patrimonio): string
{
    return ($nome !== null && trim($nome) !== '') ? $nome : patrimonioOuIndefinido($patrimonio);
}

/**
 * Formata um valor monetário no padrão brasileiro. Retorna "-" se nulo.
 */
function formatMoney($valor): string
{
    if ($valor === null || $valor === '') {
        return '-';
    }
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

/**
 * Formata um tamanho em bytes para KB/MB legível.
 */
function formatBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return $bytes . ' bytes';
}

/**
 * Formata uma data (Y-m-d) para o padrão brasileiro (d/m/Y).
 */
function formatDate(?string $data): string
{
    if (empty($data) || $data === '0000-00-00') {
        return '-';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $data);
    return $dt ? $dt->format('d/m/Y') : '-';
}

/**
 * Formata data e hora (Y-m-d H:i:s) para d/m/Y H:i.
 */
function formatDateTime(?string $dataHora): string
{
    if (empty($dataHora)) {
        return '-';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $dataHora);
    return $dt ? $dt->format('d/m/Y H:i') : '-';
}

/**
 * Retorna a classe de badge Bootstrap correspondente ao status do equipamento.
 */
function statusBadgeClass(string $status): string
{
    return match ($status) {
        'Em uso'          => 'bg-primary',
        'Disponível'      => 'bg-success',
        'Em manutenção'   => 'bg-warning text-dark',
        'Com defeito'     => 'bg-danger',
        'Descartado'      => 'bg-secondary',
        default           => 'bg-secondary',
    };
}

/**
 * Registra um evento no histórico automático de um equipamento.
 */
function registrarHistorico(int $equipamentoId, string $evento, string $descricao): void
{
    $stmt = db()->prepare(
        'INSERT INTO historico_equipamentos (equipamento_id, evento, descricao, usuario_nome)
         VALUES (:equipamento_id, :evento, :descricao, :usuario_nome)'
    );
    $stmt->execute([
        'equipamento_id' => $equipamentoId,
        'evento'         => $evento,
        'descricao'      => $descricao,
        'usuario_nome'   => usuarioLogado()['usuario'] ?? null,
    ]);
}

/**
 * Lista fixa dos tipos de equipamento aceitos (deve refletir o ENUM do banco).
 */
function tiposEquipamento(): array
{
    return ['Computador', 'Notebook', 'Impressora', 'Monitor', 'Switch', 'Roteador', 'Access Point', 'Nobreak', 'Servidor', 'Outros'];
}

/**
 * Lista fixa dos status aceitos (deve refletir o ENUM do banco).
 */
function statusEquipamento(): array
{
    return ['Em uso', 'Disponível', 'Em manutenção', 'Com defeito', 'Descartado'];
}

/**
 * Lista fixa dos tipos de licença aceitos.
 */
function tiposLicenca(): array
{
    return ['OEM', 'Retail', 'Volume', 'Assinatura', 'Trial'];
}

/**
 * Retorna true se o equipamento é do tipo Impressora (para exibir campos extras).
 */
function ehImpressora(?string $tipo): bool
{
    return $tipo === 'Impressora';
}

/**
 * Retorna true se o equipamento é do tipo Servidor (para exibir campos extras).
 */
function ehServidor(?string $tipo): bool
{
    return $tipo === 'Servidor';
}

/**
 * Retorna true se o equipamento é do tipo Computador (para exibir campos extras).
 */
function ehComputador(?string $tipo): bool
{
    return $tipo === 'Computador';
}

/**
 * Lista fixa dos status operacionais aceitos para servidores.
 */
function statusServidor(): array
{
    return ['Ativo', 'Inativo'];
}

/**
 * Sugestões de função do servidor (lista aberta, exibida em um datalist).
 */
function funcoesServidor(): array
{
    return [
        'Servidor de Arquivos', 'Active Directory', 'Banco de Dados', 'Backup',
        'Virtualização', 'Aplicação', 'Servidor Web', 'DNS/DHCP', 'Outros',
    ];
}

/**
 * Retorna a classe de badge Bootstrap correspondente ao status do servidor.
 */
function statusServidorBadgeClass(?string $status): string
{
    return $status === 'Ativo' ? 'bg-success' : 'bg-secondary';
}

/**
 * Tipos de manutenção aceitos ao registrar uma manutenção no histórico.
 * Gerenciados pela tela de Configurações (tabela tipos_manutencao).
 */
function tiposManutencao(): array
{
    return array_column(db()->query('SELECT nome FROM tipos_manutencao ORDER BY nome')->fetchAll(), 'nome');
}

/**
 * Lê um parâmetro salvo na tabela configuracoes. Retorna $padrao se a chave
 * ainda não tiver sido definida.
 */
function configGet(string $chave, ?string $padrao = null): ?string
{
    $stmt = db()->prepare('SELECT valor FROM configuracoes WHERE chave = :chave');
    $stmt->execute(['chave' => $chave]);
    $valor = $stmt->fetchColumn();
    return $valor !== false ? $valor : $padrao;
}

/**
 * Salva (ou atualiza) um parâmetro na tabela configuracoes.
 */
function configSet(string $chave, string $valor): void
{
    db()->prepare(
        'INSERT INTO configuracoes (chave, valor) VALUES (:chave, :valor)
         ON DUPLICATE KEY UPDATE valor = :valor2'
    )->execute(['chave' => $chave, 'valor' => $valor, 'valor2' => $valor]);
}

/**
 * Lista fixa das extensões de arquivo aceitas como anexo de equipamento.
 */
function extensoesAnexoPermitidas(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip'];
}

/**
 * Retorna o ícone Bootstrap Icons correspondente à extensão do anexo.
 */
function iconeAnexo(string $extensao): string
{
    return match (strtolower($extensao)) {
        'jpg', 'jpeg', 'png', 'gif', 'webp' => 'bi-file-earmark-image',
        'pdf' => 'bi-file-earmark-pdf',
        'doc', 'docx' => 'bi-file-earmark-word',
        'xls', 'xlsx', 'csv' => 'bi-file-earmark-excel',
        'ppt', 'pptx' => 'bi-file-earmark-ppt',
        'zip' => 'bi-file-earmark-zip',
        default => 'bi-file-earmark',
    };
}

/**
 * Registra um evento no histórico de cadastro/exclusão de um item de estoque.
 * Nome e categoria são gravados na própria linha (além do estoque_id) para que
 * o registro continue legível mesmo depois que o item seja excluído.
 */
function registrarHistoricoEstoque(?int $estoqueId, string $itemNome, ?string $categoriaNome, string $evento, ?string $descricao = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO historico_estoque (estoque_id, item_nome, categoria_nome, evento, descricao, usuario_nome)
         VALUES (:estoque_id, :item_nome, :categoria_nome, :evento, :descricao, :usuario_nome)'
    );
    $stmt->execute([
        'estoque_id' => $estoqueId,
        'item_nome' => $itemNome,
        'categoria_nome' => $categoriaNome,
        'evento' => $evento,
        'descricao' => $descricao,
        'usuario_nome' => usuarioLogado()['usuario'] ?? null,
    ]);
}

/**
 * Lista fixa das prioridades aceitas para um chamado (deve refletir o ENUM do banco).
 */
function prioridadesChamado(): array
{
    return ['Baixa', 'Média', 'Alta', 'Urgente'];
}

/**
 * Lista fixa dos status aceitos para o andamento de um chamado (deve refletir o ENUM do banco).
 */
function statusChamado(): array
{
    return ['Aberto', 'Em andamento', 'Aguardando', 'Concluído', 'Cancelado'];
}

/**
 * Retorna a classe de badge Bootstrap correspondente à prioridade do chamado.
 */
function prioridadeChamadoBadgeClass(string $prioridade): string
{
    return match ($prioridade) {
        'Baixa'   => 'bg-secondary',
        'Média'   => 'bg-info text-dark',
        'Alta'    => 'bg-warning text-dark',
        'Urgente' => 'bg-danger',
        default   => 'bg-secondary',
    };
}

/**
 * Retorna a classe de badge Bootstrap correspondente ao status (andamento) do chamado.
 */
function statusChamadoBadgeClass(string $status): string
{
    return match ($status) {
        'Aberto'        => 'bg-primary',
        'Em andamento'  => 'bg-warning text-dark',
        'Aguardando'    => 'bg-secondary',
        'Concluído'     => 'bg-success',
        'Cancelado'     => 'bg-dark',
        default         => 'bg-secondary',
    };
}

function perfisUsuario(): array
{
    return ['Administrador', 'Padrão', 'Usuário'];
}

function perfilUsuarioBadgeClass(string $perfil): string
{
    return match ($perfil) {
        'Administrador' => 'bg-primary',
        'Usuário'       => 'bg-info text-dark',
        default         => 'bg-secondary',
    };
}

/**
 * Conta em quantos chamados existe pelo menos uma resposta de outra
 * pessoa ainda não vista pelo usuário informado. Usado para o sininho de
 * notificação no topo da tela.
 *
 * $verTodos = true considera todos os chamados do sistema (usado para
 * Administrador/Padrão, que enxergam a listagem inteira); false restringe
 * aos chamados em que o usuário é o solicitante ou o responsável (usado
 * para o perfil Usuário, que só vê os próprios chamados).
 */
function contarChamadosComRespostaNaoLida(int $usuarioId, bool $verTodos = false): int
{
    $condicaoVisibilidade = $verTodos ? '1=1' : '(c.criado_por_id = :uid2 OR c.responsavel_id = :uid3)';
    $stmt = db()->prepare(
        "SELECT COUNT(DISTINCT c.id)
         FROM chamados c
         JOIN chamado_respostas r ON r.chamado_id = c.id
         LEFT JOIN chamado_visualizacoes v ON v.chamado_id = c.id AND v.usuario_id = :uid1
         WHERE $condicaoVisibilidade
           AND (r.usuario_id IS NULL OR r.usuario_id != :uid4)
           AND r.criado_em > COALESCE(v.visto_em, '1970-01-01 00:00:00')"
    );
    $params = ['uid1' => $usuarioId, 'uid4' => $usuarioId];
    if (!$verTodos) {
        $params['uid2'] = $usuarioId;
        $params['uid3'] = $usuarioId;
    }
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * Lista (no máximo 10, mais recentes primeiro) os chamados com resposta
 * não lida pelo usuário informado, junto com a última mensagem de cada
 * um — usado para preencher o menu de notificações. Mesmo critério de
 * visibilidade de contarChamadosComRespostaNaoLida().
 */
function listarChamadosComRespostaNaoLida(int $usuarioId, bool $verTodos = false): array
{
    $condicaoVisibilidade = $verTodos ? '1=1' : '(c.criado_por_id = :uid2 OR c.responsavel_id = :uid3)';
    $stmt = db()->prepare(
        "SELECT c.id AS chamado_id, c.titulo, ultima.mensagem, ultima.usuario_nome, ultima.criado_em
         FROM chamados c
         JOIN chamado_respostas ultima ON ultima.id = (
             SELECT MAX(r.id) FROM chamado_respostas r WHERE r.chamado_id = c.id
         )
         LEFT JOIN chamado_visualizacoes v ON v.chamado_id = c.id AND v.usuario_id = :uid1
         WHERE $condicaoVisibilidade
           AND EXISTS (
               SELECT 1 FROM chamado_respostas r2
               WHERE r2.chamado_id = c.id
                 AND (r2.usuario_id IS NULL OR r2.usuario_id != :uid4)
                 AND r2.criado_em > COALESCE(v.visto_em, '1970-01-01 00:00:00')
           )
         ORDER BY ultima.criado_em DESC
         LIMIT 10"
    );
    $params = ['uid1' => $usuarioId, 'uid4' => $usuarioId];
    if (!$verTodos) {
        $params['uid2'] = $usuarioId;
        $params['uid3'] = $usuarioId;
    }
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Marca um chamado como visto agora pelo usuário informado (some da
 * contagem de não lidos até que chegue uma resposta nova).
 */
function marcarChamadoVisto(int $chamadoId, int $usuarioId): void
{
    db()->prepare(
        'INSERT INTO chamado_visualizacoes (usuario_id, chamado_id, visto_em) VALUES (:uid, :cid, NOW())
         ON DUPLICATE KEY UPDATE visto_em = NOW()'
    )->execute(['uid' => $usuarioId, 'cid' => $chamadoId]);
}

/**
 * Registra um evento no histórico automático do chamado (aberto, mudança
 * de andamento/prioridade/responsável), com o usuário logado no momento.
 */
function registrarHistoricoChamado(int $chamadoId, string $evento, string $descricao): void
{
    $stmt = db()->prepare(
        'INSERT INTO historico_chamados (chamado_id, evento, descricao, usuario_nome)
         VALUES (:chamado_id, :evento, :descricao, :usuario_nome)'
    );
    $stmt->execute([
        'chamado_id'   => $chamadoId,
        'evento'       => $evento,
        'descricao'    => mb_substr($descricao, 0, 255),
        'usuario_nome' => usuarioLogado()['usuario'] ?? null,
    ]);
}
