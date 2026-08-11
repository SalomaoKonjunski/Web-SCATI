-- =====================================================================
-- Web SCATI - Migração: permitir patrimônio indefinido nos equipamentos
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e a coluna "patrimonio" da tabela "equipamentos" ainda
-- está como NOT NULL. Se for uma instalação nova, basta importar o
-- scati.sql atualizado — não é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_patrimonio_indefinido.sql
-- =====================================================================

USE scati;

ALTER TABLE equipamentos
    MODIFY COLUMN patrimonio VARCHAR(50) NULL;
