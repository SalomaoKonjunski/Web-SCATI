-- =====================================================================
-- Web SCATI - Migração: Transferências de Equipamentos
-- Novo módulo "Transferências": formulário com Origem, Destino,
-- Responsável e Data (todos obrigatórios), permitindo registrar vários
-- itens de uma vez — equipamentos já cadastrados (que mudam de local) ou
-- novos (recebidos de uma obra/fornecedor e cadastrados na hora).
-- =====================================================================
-- Rode uma única vez, se o banco "scati" já existia antes desta
-- atualização:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_transferencias.sql
--
-- Se for uma instalação nova, basta importar o scati.sql atualizado —
-- não é necessário rodar este arquivo (as tabelas já vêm criadas).
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS transferencias (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    origem              VARCHAR(120) NOT NULL,
    destino             VARCHAR(120) NOT NULL,
    responsavel_id      INT NULL,
    responsavel_nome    VARCHAR(50) NOT NULL,
    data_transferencia  DATE NOT NULL,
    observacoes         TEXT NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_transferencia_responsavel
        FOREIGN KEY (responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transferencia_itens (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    transferencia_id    INT NOT NULL,
    equipamento_id      INT NULL,
    equipamento_nome    VARCHAR(120) NOT NULL,
    equipamento_novo    TINYINT(1) NOT NULL DEFAULT 0,

    CONSTRAINT fk_transferencia_item_transferencia
        FOREIGN KEY (transferencia_id) REFERENCES transferencias(id) ON DELETE CASCADE,
    CONSTRAINT fk_transferencia_item_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_transferencia_responsavel ON transferencias(responsavel_id);
CREATE INDEX idx_transferencia_item_transferencia ON transferencia_itens(transferencia_id);
CREATE INDEX idx_transferencia_item_equipamento ON transferencia_itens(equipamento_id);

-- Depois de rodar esta migração, abra Configurações > Perfis de Acesso e
-- marque "Transferências" para os perfis que devem ter acesso (o
-- Administrador já tem acesso total automaticamente).
