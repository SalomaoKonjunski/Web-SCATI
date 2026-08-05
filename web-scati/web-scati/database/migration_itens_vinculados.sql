-- =====================================================================
-- Web SCATI - Migração: vinculação de itens de estoque a equipamentos
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização (ou seja, a tabela "estoque" ainda não tem as colunas
-- "status" e "equipamento_id"). Se for uma instalação nova, basta
-- importar o scati.sql atualizado — não é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql -u root -p scati < database/migration_itens_vinculados.sql
-- =====================================================================

USE scati;

ALTER TABLE estoque
    ADD COLUMN status         ENUM('Disponível','Em uso') NOT NULL DEFAULT 'Disponível' AFTER observacoes,
    ADD COLUMN equipamento_id INT NULL AFTER status;

ALTER TABLE estoque
    ADD CONSTRAINT fk_estoque_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE SET NULL;

CREATE INDEX idx_estoque_equipamento ON estoque(equipamento_id);
