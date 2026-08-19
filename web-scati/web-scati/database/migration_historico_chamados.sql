-- =====================================================================
-- Web SCATI - Migração: histórico automático do chamado
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e ainda NÃO tem a tabela "historico_chamados". Se for
-- uma instalação nova, basta importar o scati.sql atualizado — não é
-- necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_historico_chamados.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS historico_chamados (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    chamado_id      INT NOT NULL,
    data_hora       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    evento          VARCHAR(60) NOT NULL,
    descricao       VARCHAR(255) NOT NULL,
    usuario_nome    VARCHAR(50) NULL,

    CONSTRAINT fk_historico_chamado
        FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_historico_chamado ON historico_chamados(chamado_id);

-- Chamados já existentes não ganham retroativamente um evento "Aberto";
-- o histórico passa a ser registrado a partir de agora.
