-- =====================================================================
-- Web SCATI - Migração: Locais de Estoque (campo "Localização" do
-- Estoque deixa de ser texto livre e vira um cadastro próprio, com
-- dropdown no cadastro do item e filtro na listagem).
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e a tabela "estoque" ainda tem a coluna "localizacao"
-- (texto livre), não "local_id". Se for uma instalação nova, basta
-- importar o scati.sql atualizado — não é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_locais_estoque.sql
--
-- Os nomes de localização já digitados nos itens de Estoque são
-- aproveitados automaticamente: cada valor distinto vira um registro em
-- "locais_estoque", e cada item passa a apontar pra ele — nenhum dado é
-- perdido.
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS locais_estoque (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(120) NOT NULL UNIQUE,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Aproveita os nomes de localização já cadastrados no Estoque (um
-- registro por valor distinto, sem duplicar).
INSERT IGNORE INTO locais_estoque (nome)
SELECT DISTINCT TRIM(localizacao) FROM estoque
WHERE localizacao IS NOT NULL AND TRIM(localizacao) <> '';

ALTER TABLE estoque
    ADD COLUMN local_id INT NULL AFTER localizacao,
    ADD CONSTRAINT fk_estoque_local
        FOREIGN KEY (local_id) REFERENCES locais_estoque(id) ON DELETE SET NULL;

UPDATE estoque es
JOIN locais_estoque lo ON lo.nome = TRIM(es.localizacao)
SET es.local_id = lo.id
WHERE es.localizacao IS NOT NULL AND TRIM(es.localizacao) <> '';

ALTER TABLE estoque DROP COLUMN localizacao;
