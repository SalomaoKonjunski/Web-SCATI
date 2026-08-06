<?php
/**
 * Web SCATI - Funções auxiliares
 */

declare(strict_types=1);

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
        'INSERT INTO historico_equipamentos (equipamento_id, evento, descricao)
         VALUES (:equipamento_id, :evento, :descricao)'
    );
    $stmt->execute([
        'equipamento_id' => $equipamentoId,
        'evento'         => $evento,
        'descricao'      => $descricao,
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
 * Lista fixa dos tipos de manutenção aceitos ao registrar uma manutenção no histórico.
 */
function tiposManutencao(): array
{
    return ['Manutenção Preventiva', 'Limpeza', 'Troca de Componente', 'Outro'];
}

/**
 * Registra um evento no histórico de cadastro/exclusão de um item de estoque.
 * Nome e categoria são gravados na própria linha (além do estoque_id) para que
 * o registro continue legível mesmo depois que o item seja excluído.
 */
function registrarHistoricoEstoque(?int $estoqueId, string $itemNome, ?string $categoriaNome, string $evento, ?string $descricao = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO historico_estoque (estoque_id, item_nome, categoria_nome, evento, descricao)
         VALUES (:estoque_id, :item_nome, :categoria_nome, :evento, :descricao)'
    );
    $stmt->execute([
        'estoque_id' => $estoqueId,
        'item_nome' => $itemNome,
        'categoria_nome' => $categoriaNome,
        'evento' => $evento,
        'descricao' => $descricao,
    ]);
}
