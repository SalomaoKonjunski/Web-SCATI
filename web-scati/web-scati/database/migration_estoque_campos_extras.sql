-- =====================================================================
-- Web SCATI - Migração: campos extras por categoria de estoque
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e ainda NÃO tem as colunas "campo_*" na tabela
-- categorias_estoque. Se for uma instalação nova, basta importar o
-- scati.sql atualizado — não é necessário rodar este arquivo.
--
-- Cada categoria de estoque agora pode habilitar grupos de campos extras
-- (os mesmos campos que já existem no cadastro de Equipamentos: Hardware,
-- Dados da Impressora, Rede do Computador, Informações do Servidor,
-- Localização e Uso, Financeiro). Com tudo desabilitado, a categoria
-- continua funcionando exatamente como hoje — só os campos padrão do
-- Estoque.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_estoque_campos_extras.sql
-- =====================================================================

USE scati;

ALTER TABLE categorias_estoque
    ADD COLUMN IF NOT EXISTS campo_hardware        TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS campo_impressora       TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS campo_rede_computador  TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS campo_servidor         TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS campo_localizacao_uso  TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS campo_financeiro       TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE estoque
    ADD COLUMN IF NOT EXISTS processador          VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS memoria_ram           VARCHAR(60)  NULL,
    ADD COLUMN IF NOT EXISTS armazenamento         VARCHAR(60)  NULL,
    ADD COLUMN IF NOT EXISTS sistema_operacional   VARCHAR(80)  NULL,
    ADD COLUMN IF NOT EXISTS placa_mae             VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS placa_video           VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS ip                    VARCHAR(45)  NULL,
    ADD COLUMN IF NOT EXISTS modelo_toner          VARCHAR(80)  NULL,
    ADD COLUMN IF NOT EXISTS qtd_toners            INT NULL,
    ADD COLUMN IF NOT EXISTS toner_duracao_dias    INT NULL,
    ADD COLUMN IF NOT EXISTS ip_fixo               VARCHAR(45)  NULL,
    ADD COLUMN IF NOT EXISTS funcao_servidor       VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS servidor_status       ENUM('Ativo','Inativo') NULL,
    ADD COLUMN IF NOT EXISTS servidor_observacoes  TEXT NULL,
    ADD COLUMN IF NOT EXISTS status                ENUM('Em uso','Disponível','Em manutenção','Com defeito','Descartado') NULL,
    ADD COLUMN IF NOT EXISTS usuario_responsavel   VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS rede_id               INT NULL,
    ADD COLUMN IF NOT EXISTS acesso_usb            TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS valor_aquisicao       DECIMAL(12,2) NULL,
    ADD COLUMN IF NOT EXISTS valor_atual           DECIMAL(12,2) NULL,
    ADD COLUMN IF NOT EXISTS data_compra           DATE NULL,
    ADD COLUMN IF NOT EXISTS garantia              VARCHAR(60) NULL;

-- A constraint de FK não aceita "IF NOT EXISTS" no MySQL/MariaDB — se você
-- rodar este script uma segunda vez por engano, comente a linha abaixo
-- (o erro será só "Duplicate foreign key constraint").
ALTER TABLE estoque
    ADD CONSTRAINT fk_estoque_rede FOREIGN KEY (rede_id) REFERENCES redes(id) ON DELETE SET NULL;
