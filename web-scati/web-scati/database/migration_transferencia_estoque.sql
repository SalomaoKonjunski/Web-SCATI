-- =====================================================================
-- Web SCATI - Migração: Transferência de itens de Estoque
-- Até agora uma Transferência só podia ter Equipamentos. Agora também
-- aceita Itens de Estoque (com quantidade), filtrados pelo local de
-- Origem escolhido.
-- =====================================================================
-- Rode uma única vez, se o banco "scati" já existia antes desta
-- atualização (e já tiver passado pelas migrações anteriores de
-- Transferências):
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_transferencia_estoque.sql
--
-- Se for uma instalação nova, basta importar o scati.sql atualizado —
-- não é necessário rodar este arquivo.
-- =====================================================================

USE scati;

ALTER TABLE transferencia_itens
    CHANGE COLUMN equipamento_nome item_nome VARCHAR(120) NOT NULL,
    ADD COLUMN tipo_item ENUM('equipamento','estoque') NOT NULL DEFAULT 'equipamento' AFTER transferencia_id,
    ADD COLUMN estoque_id INT NULL AFTER equipamento_id,
    ADD COLUMN quantidade INT NOT NULL DEFAULT 1 AFTER equipamento_novo,
    ADD CONSTRAINT fk_transferencia_item_estoque
        FOREIGN KEY (estoque_id) REFERENCES estoque(id) ON DELETE SET NULL;

CREATE INDEX idx_transferencia_item_estoque ON transferencia_itens(estoque_id);
