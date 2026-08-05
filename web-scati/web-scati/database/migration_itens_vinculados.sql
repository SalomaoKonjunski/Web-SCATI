-- =====================================================================
-- Web SCATI - Migração: vinculação de itens de estoque a equipamentos
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e ainda NÃO tem a tabela "itens_vinculados". Se for uma
-- instalação nova, basta importar o scati.sql atualizado — não é
-- necessário rodar este arquivo.
--
-- Se você já rodou uma versão ANTERIOR desta migração (que adicionava as
-- colunas "status" e "equipamento_id" diretamente na tabela "estoque"),
-- NÃO use este arquivo — use database/migration_itens_vinculados_fix.sql
-- em vez dele.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_itens_vinculados.sql
-- =====================================================================

USE scati;

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

CREATE INDEX idx_itemvinc_estoque ON itens_vinculados(estoque_id);
CREATE INDEX idx_itemvinc_equipamento ON itens_vinculados(equipamento_id);
