-- =====================================================================
-- Web SCATI - Correção: vinculação de itens de estoque a equipamentos
-- =====================================================================
-- Use este script SOMENTE se você já rodou a migração ANTERIOR de itens
-- vinculados (a que adicionava as colunas "status" e "equipamento_id"
-- diretamente na tabela "estoque"). Aquele modelo tinha um bug: um item
-- com quantidade > 1 (ex.: "Adaptador de Vídeo", quantidade = 4) ficava
-- marcado como "Em uso" por inteiro ao vincular apenas 1 unidade,
-- travando as outras unidades para uso em outros equipamentos.
--
-- Este script corrige o problema:
--   1. Cria a nova tabela "itens_vinculados", que registra cada unidade
--      vinculada individualmente (permitindo que um mesmo item seja
--      distribuído entre vários equipamentos).
--   2. Migra os vínculos que já existiam (linhas com equipamento_id
--      preenchido) para a nova tabela, devolvendo 1 unidade à
--      quantidade disponível para cada vínculo migrado.
--   3. Remove as colunas antigas "status" e "equipamento_id" da tabela
--      "estoque", que não são mais usadas.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_itens_vinculados_fix.sql
-- =====================================================================

USE scati;

-- 1) Nova tabela de vínculos (uma linha por unidade vinculada)
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

-- 2) Migra os vínculos existentes (modelo antigo: 1 linha "Em uso" = 1 unidade vinculada)
INSERT INTO itens_vinculados (estoque_id, equipamento_id)
SELECT id, equipamento_id FROM estoque WHERE equipamento_id IS NOT NULL;

UPDATE estoque SET quantidade = GREATEST(quantidade - 1, 0) WHERE equipamento_id IS NOT NULL;

-- 3) Remove a estrutura antiga (não suportava múltiplas unidades do mesmo item)
ALTER TABLE estoque DROP FOREIGN KEY fk_estoque_equipamento;
ALTER TABLE estoque DROP INDEX idx_estoque_equipamento;
ALTER TABLE estoque DROP COLUMN equipamento_id;
ALTER TABLE estoque DROP COLUMN status;
