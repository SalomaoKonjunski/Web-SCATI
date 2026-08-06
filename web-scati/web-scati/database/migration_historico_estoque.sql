-- =====================================================================
-- Web SCATI - Migração: histórico de cadastro/exclusão de itens de estoque
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e ainda NÃO tem a tabela "historico_estoque". Se for uma
-- instalação nova, basta importar o scati.sql atualizado — não é
-- necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_historico_estoque.sql
-- =====================================================================

USE scati;

CREATE TABLE historico_estoque (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    estoque_id      INT NULL,
    item_nome       VARCHAR(120) NOT NULL,
    categoria_nome  VARCHAR(60) NULL,
    evento          VARCHAR(60) NOT NULL,
    descricao       VARCHAR(255) NULL,
    data_hora       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_historico_estoque_item
        FOREIGN KEY (estoque_id) REFERENCES estoque(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_historico_estoque_item ON historico_estoque(estoque_id);
CREATE INDEX idx_historico_estoque_data ON historico_estoque(data_hora);
