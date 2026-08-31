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
 * Lista os tipos de equipamento cadastrados em Configurações > Categorias
 * de Equipamentos (tabela categorias_equipamento).
 */
function tiposEquipamento(): array
{
    return db()->query('SELECT nome FROM categorias_equipamento ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Nomes de categoria de equipamento usados por literal em várias partes do
 * sistema (campos específicos do formulário de Equipamentos, filtro de
 * impressoras, compartilhamentos de rede, KPIs do dashboard) — não podem
 * ser renomeados nem excluídos em Categorias de Equipamentos.
 */
function tiposEquipamentoProtegidos(): array
{
    return ['Computador', 'Notebook', 'Impressora', 'Servidor'];
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
 * Fusos horários que o sistema aceita configurar em Configurações (chave
 * "fuso_horario"). Fica restrito a este whitelist — usado tanto para
 * montar o <select> da tela quanto para validar o valor salvo — porque é
 * lido bem cedo em config/database.php, antes de qualquer coisa do
 * sistema rodar, e um valor de fuso inválido ali quebraria a página
 * inteira sem essa validação.
 */
function fusosHorariosDisponiveis(): array
{
    return [
        'America/Noronha' => 'Fernando de Noronha (UTC-2)',
        'America/Sao_Paulo' => 'Brasília — SP, RJ, MG, Sul e demais estados (UTC-3)',
        'America/Manaus' => 'Amazonas, Mato Grosso, Rondônia (UTC-4)',
        'America/Rio_Branco' => 'Acre, sudoeste do Amazonas (UTC-5)',
    ];
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
 * Definição única dos grupos de campos que uma categoria de equipamento
 * pode habilitar — controla quais cards aparecem no cadastro/ficha de
 * Equipamentos para os tipos não protegidos (Computador, Notebook,
 * Impressora e Servidor continuam com seu comportamento fixo de sempre).
 * Usada tanto para montar os checkboxes em Categorias de Equipamentos
 * quanto para mostrar/ocultar os cards no formulário e na ficha.
 */
function gruposCamposEquipamento(): array
{
    return [
        'hardware' => [
            'coluna' => 'campo_hardware',
            'label' => 'Hardware',
            'icone' => 'bi-cpu',
            'campos' => [
                'processador' => ['label' => 'Processador'],
                'memoria_ram' => ['label' => 'Memória RAM'],
                'armazenamento' => ['label' => 'Armazenamento'],
                'sistema_operacional' => ['label' => 'Sistema Operacional'],
                'placa_mae' => ['label' => 'Placa Mãe'],
                'placa_video' => ['label' => 'Placa de Vídeo'],
            ],
        ],
        'impressora' => [
            'coluna' => 'campo_impressora',
            'label' => 'Dados da Impressora',
            'icone' => 'bi-printer',
            'campos' => [
                'ip' => ['label' => 'Endereço IP'],
                'modelo_toner' => ['label' => 'Modelo do Toner'],
                'qtd_toners' => ['label' => 'Qtd. de Toners Disponíveis'],
                'toner_duracao_dias' => ['label' => 'Duração estimada do toner (dias)'],
            ],
        ],
        'rede_computador' => [
            'coluna' => 'campo_rede_computador',
            'label' => 'Rede do Computador',
            'icone' => 'bi-ethernet',
            'campos' => [
                'ip_fixo' => ['label' => 'IP Fixo'],
            ],
        ],
        'servidor' => [
            'coluna' => 'campo_servidor',
            'label' => 'Informações do Servidor',
            'icone' => 'bi-hdd-rack',
            'campos' => [
                'funcao_servidor' => ['label' => 'Função do Servidor'],
                'servidor_status' => ['label' => 'Status do Servidor'],
                'servidor_observacoes' => ['label' => 'Observações'],
            ],
        ],
        'switch' => [
            'coluna' => 'campo_switch',
            'label' => 'Portas do Switch',
            'icone' => 'bi-diagram-3',
            'campos' => [
                'qtd_portas_switch' => ['label' => 'Quantidade de Portas'],
            ],
        ],
    ];
}

/**
 * Retorna true se o tipo de equipamento tem o grupo "switch" habilitado
 * (ver gruposCamposEquipamento()) — decide se a aba "Mapeamento de Portas"
 * aparece na ficha e se o campo "Quantidade de Portas" aparece no cadastro.
 * Baseado no flag da categoria (não no nome "Switch" travado), para
 * continuar funcionando mesmo se o admin renomear a categoria ou habilitar
 * o mesmo recurso para outro tipo de equipamento de rede.
 */
function temMapeamentoPortas(string $tipo): bool
{
    return in_array('switch', gruposHabilitadosParaTipo($tipo), true);
}

/**
 * Lista fixa dos status aceitos para uma porta de switch (deve refletir o ENUM do banco).
 */
function statusPortaSwitch(): array
{
    return ['Livre', 'Ocupada', 'Inativa'];
}

function statusPortaBadgeClass(string $status): string
{
    return match ($status) {
        'Ocupada' => 'bg-primary',
        'Inativa' => 'bg-danger',
        default   => 'bg-secondary',
    };
}

/**
 * Garante que existam linhas em portas_switch para os números 1..$qtdPortas
 * deste switch, sem tocar nas que já existem (INSERT IGNORE aproveita a
 * chave única switch_id+numero). Se a quantidade configurada for reduzida
 * depois, as portas além do novo limite simplesmente não são mais
 * exibidas — não são apagadas, então voltam a aparecer (com o vínculo que
 * tinham) se a quantidade for aumentada de novo.
 */
function sincronizarPortasSwitch(int $switchId, ?int $qtdPortas): void
{
    if (!$qtdPortas || $qtdPortas < 1) {
        return;
    }

    $stmt = db()->prepare('INSERT IGNORE INTO portas_switch (switch_id, numero) VALUES (:switch_id, :numero)');
    for ($numero = 1; $numero <= $qtdPortas; $numero++) {
        $stmt->execute(['switch_id' => $switchId, 'numero' => $numero]);
    }
}

/**
 * Chaves dos grupos de campos (ver gruposCamposEquipamento()) habilitados
 * para um tipo de equipamento, consultando os flags salvos em
 * categorias_equipamento para aquele nome. Usada tanto para decidir quais
 * cards aparecem no formulário/ficha quanto para, ao salvar, zerar
 * server-side os campos de grupos desabilitados (nunca confiar em quais
 * campos vieram no POST).
 */
function gruposHabilitadosParaTipo(string $tipo): array
{
    $stmt = db()->prepare('SELECT * FROM categorias_equipamento WHERE nome = :nome');
    $stmt->execute(['nome' => $tipo]);
    $categoria = $stmt->fetch();

    if (!$categoria) {
        return [];
    }

    $grupos = [];
    foreach (gruposCamposEquipamento() as $chave => $grupo) {
        if (!empty($categoria[$grupo['coluna']])) {
            $grupos[] = $chave;
        }
    }

    return $grupos;
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
 * Whitelist única de origens de dados e colunas disponíveis no Construtor
 * de Relatório (Relatórios > Construtor de Relatório). Cada origem define
 * a tabela/junção ("from") e a ordenação padrão em SQL fixo (nunca vindo
 * do usuário), e cada coluna define o rótulo exibido, a expressão SQL e o
 * tipo (que decide os operadores de filtro e a formatação do valor).
 *
 * Esta função é a ÚNICA fonte de nomes de tabela/coluna aceitos ao montar
 * a consulta em montarRelatorioPersonalizado() — a origem e as colunas que
 * vêm da requisição são sempre usadas só como CHAVE de busca neste array,
 * nunca interpoladas direto no SQL. Isso é o que torna o construtor livre
 * de risco de SQL injection apesar de ser configurável pelo usuário.
 *
 * Tipos de coluna suportados: 'texto', 'select' (usa 'opcoes'), 'numero',
 * 'dinheiro', 'data', 'datahora' e 'booleano' (também usa 'opcoes', fixo
 * em Sim/Não).
 */
function origensRelatorioPersonalizado(): array
{
    return [
        'equipamentos' => [
            'label' => 'Equipamentos',
            'icone' => 'bi-pc-display',
            'descricao' => 'Nome, patrimônio, tipo, status, responsável, financeiro...',
            'from' => 'equipamentos e LEFT JOIN redes r ON r.id = e.rede_id',
            'ordem_padrao' => 'e.nome',
            'colunas' => [
                'nome'                => ['label' => 'Nome', 'sql' => 'e.nome', 'tipo' => 'texto'],
                'patrimonio'          => ['label' => 'Patrimônio', 'sql' => 'e.patrimonio', 'tipo' => 'texto'],
                'tipo'                => ['label' => 'Tipo', 'sql' => 'e.tipo', 'tipo' => 'select', 'opcoes' => tiposEquipamento()],
                'marca'               => ['label' => 'Marca', 'sql' => 'e.marca', 'tipo' => 'texto'],
                'modelo'              => ['label' => 'Modelo', 'sql' => 'e.modelo', 'tipo' => 'texto'],
                'numero_serie'        => ['label' => 'Número de Série', 'sql' => 'e.numero_serie', 'tipo' => 'texto'],
                'status'              => ['label' => 'Status', 'sql' => 'e.status', 'tipo' => 'select', 'opcoes' => statusEquipamento()],
                'localizacao'         => ['label' => 'Localização', 'sql' => 'e.localizacao', 'tipo' => 'texto'],
                'usuario_responsavel' => ['label' => 'Responsável', 'sql' => 'e.usuario_responsavel', 'tipo' => 'texto'],
                'rede_nome'           => ['label' => 'Rede', 'sql' => 'r.nome', 'tipo' => 'texto'],
                'data_compra'         => ['label' => 'Data da Compra', 'sql' => 'e.data_compra', 'tipo' => 'data'],
                'garantia'            => ['label' => 'Garantia', 'sql' => 'e.garantia', 'tipo' => 'texto'],
                'fornecedor'          => ['label' => 'Fornecedor', 'sql' => 'e.fornecedor', 'tipo' => 'texto'],
                'valor_atual'         => ['label' => 'Valor Atual', 'sql' => 'e.valor_atual', 'tipo' => 'dinheiro'],
                'acesso_usb'          => ['label' => 'Acesso USB', 'sql' => 'e.acesso_usb', 'tipo' => 'booleano', 'opcoes' => ['Sim', 'Não']],
            ],
        ],
        'estoque' => [
            'label' => 'Estoque',
            'icone' => 'bi-box-seam',
            'descricao' => 'Itens, categorias, quantidades, localização...',
            'from' => 'estoque es JOIN categorias_estoque c ON c.id = es.categoria_id',
            'ordem_padrao' => 'es.nome',
            'colunas' => [
                'nome'              => ['label' => 'Nome', 'sql' => 'es.nome', 'tipo' => 'texto'],
                'categoria_nome'    => ['label' => 'Categoria', 'sql' => 'c.nome', 'tipo' => 'texto'],
                'marca'             => ['label' => 'Marca', 'sql' => 'es.marca', 'tipo' => 'texto'],
                'modelo'            => ['label' => 'Modelo', 'sql' => 'es.modelo', 'tipo' => 'texto'],
                'quantidade'        => ['label' => 'Quantidade', 'sql' => 'es.quantidade', 'tipo' => 'numero'],
                'quantidade_minima' => ['label' => 'Quantidade Mínima', 'sql' => 'es.quantidade_minima', 'tipo' => 'numero'],
                'localizacao'       => ['label' => 'Localização', 'sql' => 'es.localizacao', 'tipo' => 'texto'],
                'observacoes'       => ['label' => 'Observações', 'sql' => 'es.observacoes', 'tipo' => 'texto'],
            ],
        ],
        'licencas' => [
            'label' => 'Licenças',
            'icone' => 'bi-key',
            'descricao' => 'Software, fabricante, validade, vínculo...',
            'from' => 'licencas l LEFT JOIN equipamentos eq ON eq.id = l.equipamento_id',
            'ordem_padrao' => 'l.software',
            'colunas' => [
                'software'               => ['label' => 'Software', 'sql' => 'l.software', 'tipo' => 'texto'],
                'fabricante'             => ['label' => 'Fabricante', 'sql' => 'l.fabricante', 'tipo' => 'texto'],
                'tipo'                   => ['label' => 'Tipo', 'sql' => 'l.tipo', 'tipo' => 'select', 'opcoes' => tiposLicenca()],
                'versao'                 => ['label' => 'Versão', 'sql' => 'l.versao', 'tipo' => 'texto'],
                'data_validade'          => ['label' => 'Validade', 'sql' => 'l.data_validade', 'tipo' => 'data'],
                'equipamento_patrimonio' => ['label' => 'Equipamento Vinculado', 'sql' => 'eq.patrimonio', 'tipo' => 'texto'],
            ],
        ],
        'chamados' => [
            'label' => 'Chamados',
            'icone' => 'bi-life-preserver',
            'descricao' => 'Título, solicitante, prioridade, status...',
            'from' => 'chamados ch LEFT JOIN usuarios u ON u.id = ch.responsavel_id',
            'ordem_padrao' => 'ch.criado_em DESC',
            'colunas' => [
                'titulo'           => ['label' => 'Título', 'sql' => 'ch.titulo', 'tipo' => 'texto'],
                'solicitante'      => ['label' => 'Solicitante', 'sql' => 'ch.solicitante', 'tipo' => 'texto'],
                'prioridade'       => ['label' => 'Prioridade', 'sql' => 'ch.prioridade', 'tipo' => 'select', 'opcoes' => prioridadesChamado()],
                'status'           => ['label' => 'Status', 'sql' => 'ch.status', 'tipo' => 'select', 'opcoes' => statusChamado()],
                'responsavel_nome' => ['label' => 'Responsável', 'sql' => 'u.usuario', 'tipo' => 'texto'],
                'criado_em'        => ['label' => 'Aberto em', 'sql' => 'ch.criado_em', 'tipo' => 'datahora'],
                'concluido_em'     => ['label' => 'Concluído em', 'sql' => 'ch.concluido_em', 'tipo' => 'datahora'],
            ],
        ],
    ];
}

/**
 * Operadores de filtro disponíveis para cada tipo de coluna do Construtor
 * de Relatório (chave => rótulo exibido).
 */
function operadoresRelatorioPersonalizado(): array
{
    return [
        'texto' => [
            'contem' => 'contém',
            'igual' => 'é igual a',
            'vazio' => 'está vazio',
            'nao_vazio' => 'não está vazio',
        ],
        'select' => [
            'igual' => 'é igual a',
            'diferente' => 'é diferente de',
        ],
        'booleano' => [
            'igual' => 'é igual a',
        ],
        'numero' => [
            'igual' => 'é igual a',
            'maior' => 'maior que',
            'menor' => 'menor que',
        ],
        'dinheiro' => [
            'igual' => 'é igual a',
            'maior' => 'maior que',
            'menor' => 'menor que',
        ],
        'data' => [
            'igual' => 'é igual a',
            'a_partir' => 'a partir de',
            'ate' => 'até',
        ],
        'datahora' => [
            'a_partir' => 'a partir de',
            'ate' => 'até',
        ],
    ];
}

/**
 * Monta a consulta SQL do Construtor de Relatório a partir da origem,
 * colunas e filtros escolhidos (tipicamente vindos de $_GET/$_POST) —
 * validando tudo contra origensRelatorioPersonalizado(). Nomes de coluna
 * inválidos (fora do whitelist) são simplesmente ignorados, nunca chegam
 * a entrar no SQL. Retorna null se a origem for inválida ou nenhuma
 * coluna válida tiver sido informada.
 *
 * $filtros é uma lista de ['campo' => string, 'operador' => string, 'valor' => string].
 */
function montarRelatorioPersonalizado(string $origemChave, array $colunasChaves, array $filtros): ?array
{
    $origens = origensRelatorioPersonalizado();
    if (!isset($origens[$origemChave])) {
        return null;
    }
    $origemDef = $origens[$origemChave];

    $colunasValidas = [];
    foreach ($colunasChaves as $chave) {
        if (is_string($chave) && isset($origemDef['colunas'][$chave])) {
            $colunasValidas[$chave] = $origemDef['colunas'][$chave];
        }
    }
    if (empty($colunasValidas)) {
        return null;
    }

    $selectSql = [];
    foreach ($colunasValidas as $chave => $def) {
        $selectSql[] = $def['sql'] . ' AS ' . $chave;
    }

    $operadoresPorTipo = operadoresRelatorioPersonalizado();
    $condicoes = [];
    $params = [];
    $indice = 0;

    foreach ($filtros as $filtro) {
        $campo = $filtro['campo'] ?? '';
        $operador = $filtro['operador'] ?? '';
        $valor = trim((string) ($filtro['valor'] ?? ''));

        if (!isset($origemDef['colunas'][$campo])) {
            continue;
        }
        $colDef = $origemDef['colunas'][$campo];
        $operadoresValidos = $operadoresPorTipo[$colDef['tipo']] ?? [];
        if (!isset($operadoresValidos[$operador])) {
            continue;
        }
        if ($valor === '' && !in_array($operador, ['vazio', 'nao_vazio'], true)) {
            continue;
        }

        $indice++;
        $paramKey = 'f' . $indice;
        // Comparações de data/hora usam DATE() para "a partir de"/"até"
        // funcionarem pelo dia inteiro, mesmo em colunas DATETIME.
        $sqlCol = $colDef['tipo'] === 'datahora' ? 'DATE(' . $colDef['sql'] . ')' : $colDef['sql'];
        $valorFinal = $colDef['tipo'] === 'booleano' ? ($valor === 'Sim' ? 1 : 0) : $valor;

        switch ($operador) {
            case 'contem':
                $condicoes[] = "$sqlCol LIKE :$paramKey";
                $params[$paramKey] = '%' . $valorFinal . '%';
                break;
            case 'igual':
                $condicoes[] = "$sqlCol = :$paramKey";
                $params[$paramKey] = $valorFinal;
                break;
            case 'diferente':
                $condicoes[] = "$sqlCol <> :$paramKey";
                $params[$paramKey] = $valorFinal;
                break;
            case 'maior':
                $condicoes[] = "$sqlCol > :$paramKey";
                $params[$paramKey] = $valorFinal;
                break;
            case 'menor':
                $condicoes[] = "$sqlCol < :$paramKey";
                $params[$paramKey] = $valorFinal;
                break;
            case 'a_partir':
                $condicoes[] = "$sqlCol >= :$paramKey";
                $params[$paramKey] = $valorFinal;
                break;
            case 'ate':
                $condicoes[] = "$sqlCol <= :$paramKey";
                $params[$paramKey] = $valorFinal;
                break;
            case 'vazio':
                $condicoes[] = "($sqlCol IS NULL OR $sqlCol = '')";
                break;
            case 'nao_vazio':
                $condicoes[] = "($sqlCol IS NOT NULL AND $sqlCol <> '')";
                break;
        }
    }

    $sql = 'SELECT ' . implode(', ', $selectSql) . ' FROM ' . $origemDef['from'];
    if (!empty($condicoes)) {
        $sql .= ' WHERE ' . implode(' AND ', $condicoes);
    }
    $sql .= ' ORDER BY ' . $origemDef['ordem_padrao'];

    return [
        'sql' => $sql,
        'params' => $params,
        'colunas' => $colunasValidas,
        'origem_def' => $origemDef,
    ];
}

/**
 * Formata um valor de coluna do Construtor de Relatório para exibição,
 * de acordo com o tipo da coluna (ver origensRelatorioPersonalizado()).
 */
function formatarValorRelatorioPersonalizado($valor, string $tipo): string
{
    return match ($tipo) {
        'dinheiro' => formatMoney($valor),
        'data' => formatDate($valor),
        'datahora' => formatDateTime($valor),
        'booleano' => empty($valor) ? 'Não' : 'Sim',
        default => ($valor !== null && $valor !== '') ? (string) $valor : '-',
    };
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
        'Cancelado'     => 'bg-dark text-white',
        default         => 'bg-secondary',
    };
}

function perfisUsuario(): array
{
    return ['Administrador', 'Padrão', 'Usuário'];
}

/**
 * Cifra um texto com AES-256-CBC usando ENCRYPTION_KEY (config/database.php)
 * — usado só para a senha de email corporativo no cadastro de Usuários.
 * Diferente da senha de login (hash bcrypt, irreversível), este valor
 * precisa poder ser lido de volta, então usa criptografia reversível em
 * vez de hash. Guarda o IV junto com o texto cifrado (necessário para
 * descriptografar), tudo em base64 num único campo.
 */
function criptografar(string $texto): string
{
    $iv = random_bytes(16);
    $cifrado = openssl_encrypt($texto, 'aes-256-cbc', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cifrado);
}

/**
 * Reverte criptografar(). Retorna null se o valor estiver vazio ou não
 * puder ser decifrado (ex.: ENCRYPTION_KEY foi trocada depois de salvo).
 */
function descriptografar(?string $valorCifrado): ?string
{
    if ($valorCifrado === null || $valorCifrado === '') {
        return null;
    }
    $dados = base64_decode($valorCifrado, true);
    if ($dados === false || strlen($dados) <= 16) {
        return null;
    }
    $iv = substr($dados, 0, 16);
    $cifrado = substr($dados, 16);
    $texto = openssl_decrypt($cifrado, 'aes-256-cbc', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    return $texto !== false ? $texto : null;
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
 * Conta em quantos chamados existe uma "novidade" ainda não vista pelo
 * usuário informado: ou uma resposta de outra pessoa, ou o próprio chamado
 * (uma solicitação nova que ele nunca abriu). Usado para o sininho de
 * notificação e para o aviso em vermelho do menu lateral.
 *
 * $verTodos = true considera todos os chamados do sistema (usado para
 * Administrador/Padrão, que enxergam a listagem inteira); false restringe
 * aos chamados em que o usuário é o solicitante ou o responsável (usado
 * para o perfil Usuário, que só vê os próprios chamados).
 */
function contarChamadosNaoLidos(int $usuarioId, bool $verTodos = false): int
{
    $condicaoVisibilidade = $verTodos ? '1=1' : '(c.criado_por_id = :uid2 OR c.responsavel_id = :uid3)';
    $stmt = db()->prepare(
        "SELECT COUNT(DISTINCT c.id)
         FROM chamados c
         LEFT JOIN chamado_visualizacoes v ON v.chamado_id = c.id AND v.usuario_id = :uid1
         WHERE $condicaoVisibilidade
           AND (
               (v.visto_em IS NULL AND (c.criado_por_id IS NULL OR c.criado_por_id != :uid5))
               OR EXISTS (
                   SELECT 1 FROM chamado_respostas r
                   WHERE r.chamado_id = c.id
                     AND (r.usuario_id IS NULL OR r.usuario_id != :uid4)
                     AND r.criado_em > COALESCE(v.visto_em, '1970-01-01 00:00:00')
               )
           )"
    );
    $params = ['uid1' => $usuarioId, 'uid4' => $usuarioId, 'uid5' => $usuarioId];
    if (!$verTodos) {
        $params['uid2'] = $usuarioId;
        $params['uid3'] = $usuarioId;
    }
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * Lista (no máximo 10, mais recentes primeiro) os chamados com alguma
 * novidade não vista pelo usuário informado — nova solicitação (chamado
 * nunca aberto por ele) ou resposta nova — usado para preencher o menu de
 * notificações. Mesmo critério de visibilidade de contarChamadosNaoLidos().
 * Cada item vem com 'tipo' = 'solicitacao' ou 'mensagem', e 'qtd_mensagens_novas'
 * com a quantidade de respostas ainda não vistas nesse chamado.
 */
function listarChamadosNaoLidos(int $usuarioId, bool $verTodos = false): array
{
    $condicaoVisibilidade = $verTodos ? '1=1' : '(c.criado_por_id = :uid2 OR c.responsavel_id = :uid3)';
    $stmt = db()->prepare(
        "SELECT c.id AS chamado_id, c.titulo, c.descricao, c.solicitante, c.criado_em,
                CASE WHEN v.visto_em IS NULL THEN 'solicitacao' ELSE 'mensagem' END AS tipo,
                ultima.mensagem AS ultima_mensagem, ultima.usuario_nome AS ultima_usuario_nome,
                ultima.criado_em AS ultima_criado_em,
                (SELECT COUNT(*) FROM chamado_respostas r3
                 WHERE r3.chamado_id = c.id
                   AND (r3.usuario_id IS NULL OR r3.usuario_id != :uid6)
                   AND r3.criado_em > COALESCE(v.visto_em, '1970-01-01 00:00:00')) AS qtd_mensagens_novas
         FROM chamados c
         LEFT JOIN chamado_visualizacoes v ON v.chamado_id = c.id AND v.usuario_id = :uid1
         LEFT JOIN chamado_respostas ultima ON ultima.id = (
             SELECT MAX(r.id) FROM chamado_respostas r WHERE r.chamado_id = c.id
         )
         WHERE $condicaoVisibilidade
           AND (
               (v.visto_em IS NULL AND (c.criado_por_id IS NULL OR c.criado_por_id != :uid5))
               OR EXISTS (
                   SELECT 1 FROM chamado_respostas r2
                   WHERE r2.chamado_id = c.id
                     AND (r2.usuario_id IS NULL OR r2.usuario_id != :uid4)
                     AND r2.criado_em > COALESCE(v.visto_em, '1970-01-01 00:00:00')
               )
           )
         ORDER BY COALESCE(ultima.criado_em, c.criado_em) DESC
         LIMIT 10"
    );
    $params = ['uid1' => $usuarioId, 'uid4' => $usuarioId, 'uid5' => $usuarioId, 'uid6' => $usuarioId];
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
