-- =====================================================================
-- Web SCATI - Sistema de Controle de Ativos de TI
-- Script de criação do banco de dados
-- =====================================================================

CREATE DATABASE IF NOT EXISTS scati CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scati;

-- ---------------------------------------------------------------------
-- Tabela: usuarios
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario         VARCHAR(50)  NOT NULL UNIQUE,
    senha_hash      VARCHAR(255) NOT NULL,
    perfil          ENUM('Administrador','Padrão','Usuário') NOT NULL DEFAULT 'Padrão',
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Perfis de acesso:
--   Administrador: acesso completo, inclusive gerenciar outros usuários.
--   Padrão:        acesso completo ao sistema, exceto gerenciar usuários.
--   Usuário:       só acessa a aba de Chamados; pode registrar chamados e
--                  acompanhar os que ele mesmo abriu, mas não edita/exclui
--                  chamados nem enxerga o restante do sistema.
--
-- Usuário administrador padrão (usuario: Salomao / senha: scati2026).
-- Recomenda-se trocar a senha após o primeiro acesso.
INSERT INTO usuarios (usuario, senha_hash, perfil) VALUES
('Salomao', '$2y$12$PgslwwvkfvXprsqnXjYlJu2XD2sQ742Dlmji7mmp9/rOzB8lEE0u.', 'Administrador');

-- ---------------------------------------------------------------------
-- Tabela: redes
-- ---------------------------------------------------------------------
CREATE TABLE redes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(100) NOT NULL,
    faixa_ip        VARCHAR(50)  NULL,
    gateway         VARCHAR(50)  NULL,
    observacoes     TEXT         NULL,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: categorias_estoque
-- ---------------------------------------------------------------------
CREATE TABLE categorias_estoque (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nome    VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO categorias_estoque (nome) VALUES
('Memória RAM'), ('SSD'), ('HD'), ('Mouse'), ('Teclado'), ('Monitor'),
('Computador'), ('Notebook'), ('Toner'), ('Tinta'), ('Cabos'),
('Adaptadores'), ('Outros');

-- ---------------------------------------------------------------------
-- Tabela: equipamentos
-- ---------------------------------------------------------------------
CREATE TABLE equipamentos (
    id                      INT AUTO_INCREMENT PRIMARY KEY,

    -- Identificação
    nome                    VARCHAR(120) NULL,
    patrimonio              VARCHAR(50)  NULL UNIQUE,
    tipo                    ENUM('Computador','Notebook','Impressora','Monitor','Switch',
                                  'Roteador','Access Point','Nobreak','Servidor','Outros') NOT NULL,
    marca                   VARCHAR(80)  NULL,
    modelo                  VARCHAR(80)  NULL,
    numero_serie            VARCHAR(100) NULL,
    hostname                VARCHAR(100) NULL,

    -- Hardware
    processador             VARCHAR(120) NULL,
    memoria_ram              VARCHAR(60)  NULL,
    armazenamento            VARCHAR(60)  NULL,
    sistema_operacional      VARCHAR(80)  NULL,

    -- Localização e uso
    status                   ENUM('Em uso','Disponível','Em manutenção','Com defeito','Descartado')
                             NOT NULL DEFAULT 'Disponível',
    localizacao              VARCHAR(120) NULL,
    usuario_responsavel      VARCHAR(120) NULL,
    rede_id                  INT NULL,
    acesso_usb               TINYINT(1) NOT NULL DEFAULT 0,

    -- Campos específicos de impressoras
    ip                       VARCHAR(45)  NULL,
    modelo_toner             VARCHAR(80)  NULL,
    qtd_toners               INT NULL,
    toner_duracao_dias       INT NULL,
    toner_ultima_troca       DATE NULL,

    -- Campos específicos de computadores
    ip_fixo                  VARCHAR(45)  NULL,
    placa_mae                VARCHAR(120) NULL,
    placa_video              VARCHAR(120) NULL,

    -- Campos específicos de servidores
    funcao_servidor          VARCHAR(100) NULL,
    servidor_status          ENUM('Ativo','Inativo') NULL,
    servidor_observacoes     TEXT NULL,

    -- Financeiro (opcional)
    valor_aquisicao          DECIMAL(12,2) NULL,
    data_compra              DATE NULL,
    fornecedor               VARCHAR(120) NULL,
    numero_nota_fiscal       VARCHAR(60) NULL,
    garantia                 VARCHAR(60) NULL,
    valor_atual              DECIMAL(12,2) NULL,
    observacoes_financeiras  TEXT NULL,

    criado_em                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_equipamentos_rede
        FOREIGN KEY (rede_id) REFERENCES redes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: estoque
-- ---------------------------------------------------------------------
CREATE TABLE estoque (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(120) NOT NULL,
    categoria_id        INT NOT NULL,
    marca               VARCHAR(80) NULL,
    modelo              VARCHAR(80) NULL,
    quantidade          INT NOT NULL DEFAULT 0,
    quantidade_minima   INT NOT NULL DEFAULT 0,
    localizacao         VARCHAR(120) NULL,
    observacoes         TEXT NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_estoque_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias_estoque(id),
    CONSTRAINT chk_estoque_qtd_nao_negativa CHECK (quantidade >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: itens_vinculados
-- Vincula unidades de um item de estoque a um equipamento (seção 5 da
-- atualização: Itens Vinculados). Cada linha representa UMA unidade
-- vinculada, permitindo que um mesmo item do estoque (ex.: "Adaptador de
-- Vídeo", quantidade = 4) seja distribuído entre vários equipamentos ao
-- mesmo tempo. A coluna "quantidade" em estoque reflete sempre as
-- unidades ainda disponíveis (não vinculadas).
-- ---------------------------------------------------------------------
CREATE TABLE itens_vinculados (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    estoque_id      INT NOT NULL,
    equipamento_id  INT NOT NULL,
    data_vinculo    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_itemvinc_estoque
        FOREIGN KEY (estoque_id) REFERENCES estoque(id) ON DELETE CASCADE,
    CONSTRAINT fk_itemvinc_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: licencas
-- Regra: EQUIPAMENTO (1) -------- (N) LICENÇAS
-- Uma licença pertence a, no máximo, um único equipamento.
-- ---------------------------------------------------------------------
CREATE TABLE licencas (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    equipamento_id      INT NULL,
    software            VARCHAR(120) NOT NULL,
    fabricante          VARCHAR(80) NULL,
    tipo                ENUM('OEM','Retail','Volume','Assinatura','Trial') NOT NULL DEFAULT 'OEM',
    chave               VARCHAR(120) NULL,
    versao              VARCHAR(40) NULL,
    data_aquisicao      DATE NULL,
    data_validade       DATE NULL,
    observacoes         TEXT NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_licencas_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: historico_equipamentos (registro automático de alterações)
-- ---------------------------------------------------------------------
CREATE TABLE historico_equipamentos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    equipamento_id  INT NOT NULL,
    data_hora       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    evento          VARCHAR(60) NOT NULL,
    descricao       VARCHAR(255) NOT NULL,
    usuario_nome    VARCHAR(50) NULL,

    CONSTRAINT fk_historico_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: historico_estoque (registro de cadastro/exclusão de itens)
-- estoque_id fica NULL quando o item é excluído (ON DELETE SET NULL),
-- mas o nome/categoria ficam gravados na própria linha para que o
-- registro continue legível mesmo após a exclusão do item.
-- ---------------------------------------------------------------------
CREATE TABLE historico_estoque (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    estoque_id      INT NULL,
    item_nome       VARCHAR(120) NOT NULL,
    categoria_nome  VARCHAR(60) NULL,
    evento          VARCHAR(60) NOT NULL,
    descricao       VARCHAR(255) NULL,
    data_hora       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_nome    VARCHAR(50) NULL,

    CONSTRAINT fk_historico_estoque_item
        FOREIGN KEY (estoque_id) REFERENCES estoque(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: observacoes_equipamentos (registro manual, cronológico)
-- ---------------------------------------------------------------------
CREATE TABLE observacoes_equipamentos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    equipamento_id  INT NOT NULL,
    data_hora       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    texto           TEXT NOT NULL,

    CONSTRAINT fk_observacoes_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: anexos_equipamentos
-- Arquivos (nota fiscal, foto, manual em PDF, etc.) anexados à ficha do
-- equipamento. O arquivo em si fica salvo em uploads/anexos/ com um nome
-- gerado aleatoriamente (nome_arquivo); nome_original é só para exibição
-- e download.
-- ---------------------------------------------------------------------
CREATE TABLE anexos_equipamentos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    equipamento_id  INT NOT NULL,
    nome_original   VARCHAR(255) NOT NULL,
    nome_arquivo    VARCHAR(255) NOT NULL,
    tipo_mime       VARCHAR(100) NULL,
    tamanho         INT NOT NULL,
    descricao       VARCHAR(255) NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_anexo_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: compartilhamentos_servidor
-- Pastas de rede compartilhadas por um equipamento do tipo Servidor.
-- ---------------------------------------------------------------------
CREATE TABLE compartilhamentos_servidor (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    equipamento_id      INT NOT NULL,
    nome                VARCHAR(120) NOT NULL,
    caminho_pasta       VARCHAR(255) NOT NULL,
    descricao           TEXT NULL,
    permissoes          VARCHAR(255) NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_compartilhamento_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: compartilhamento_computadores
-- Vincula cada pasta compartilhada aos computadores que a utilizam,
-- reaproveitando o cadastro de equipamentos já existente (N:N).
-- ---------------------------------------------------------------------
CREATE TABLE compartilhamento_computadores (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    compartilhamento_id INT NOT NULL,
    equipamento_id      INT NOT NULL,

    CONSTRAINT fk_compcomp_compartilhamento
        FOREIGN KEY (compartilhamento_id) REFERENCES compartilhamentos_servidor(id) ON DELETE CASCADE,
    CONSTRAINT fk_compcomp_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE,
    CONSTRAINT uq_compcomp UNIQUE (compartilhamento_id, equipamento_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: tipos_manutencao
-- Tipos de manutenção disponíveis para registro no Histórico dos
-- equipamentos, editáveis pela tela de Configurações.
-- ---------------------------------------------------------------------
CREATE TABLE tipos_manutencao (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nome    VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO tipos_manutencao (nome) VALUES
('Manutenção Preventiva'), ('Limpeza'), ('Troca de Componente'), ('Outro');

-- ---------------------------------------------------------------------
-- Tabela: configuracoes
-- Armazenamento simples de chave/valor para parâmetros ajustáveis pela
-- tela de Configurações (ex.: dias de antecedência do alerta de
-- licenças vencendo).
-- ---------------------------------------------------------------------
CREATE TABLE configuracoes (
    chave   VARCHAR(60) PRIMARY KEY,
    valor   VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO configuracoes (chave, valor) VALUES
('dias_alerta_licenca', '30'),
('dias_alerta_toner', '7');

-- ---------------------------------------------------------------------
-- Tabela: chamados (pendências/solicitações de TI)
-- ---------------------------------------------------------------------
CREATE TABLE chamados (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    titulo              VARCHAR(150) NOT NULL,
    descricao           TEXT NULL,
    solicitante         VARCHAR(100) NULL,
    criado_por_id       INT NULL,
    equipamento_id      INT NULL,
    prioridade          ENUM('Baixa','Média','Alta','Urgente') NOT NULL DEFAULT 'Média',
    status              ENUM('Aberto','Em andamento','Aguardando','Concluído','Cancelado') NOT NULL DEFAULT 'Aberto',
    responsavel_id      INT NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    concluido_em        DATETIME NULL,

    CONSTRAINT fk_chamado_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_chamado_responsavel
        FOREIGN KEY (responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_chamado_criado_por
        FOREIGN KEY (criado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Índices auxiliares para pesquisa (seção 13 da documentação)
-- ---------------------------------------------------------------------
CREATE INDEX idx_equip_hostname   ON equipamentos(hostname);
CREATE INDEX idx_equip_serie      ON equipamentos(numero_serie);
CREATE INDEX idx_equip_marca      ON equipamentos(marca);
CREATE INDEX idx_equip_modelo     ON equipamentos(modelo);
CREATE INDEX idx_equip_responsavel ON equipamentos(usuario_responsavel);
CREATE INDEX idx_equip_status     ON equipamentos(status);
CREATE INDEX idx_compart_equipamento ON compartilhamentos_servidor(equipamento_id);
CREATE INDEX idx_itemvinc_estoque ON itens_vinculados(estoque_id);
CREATE INDEX idx_itemvinc_equipamento ON itens_vinculados(equipamento_id);
CREATE INDEX idx_historico_estoque_item ON historico_estoque(estoque_id);
CREATE INDEX idx_historico_estoque_data ON historico_estoque(data_hora);
CREATE INDEX idx_chamado_status ON chamados(status);
CREATE INDEX idx_chamado_prioridade ON chamados(prioridade);
CREATE INDEX idx_chamado_responsavel ON chamados(responsavel_id);
CREATE INDEX idx_anexo_equipamento ON anexos_equipamentos(equipamento_id);
