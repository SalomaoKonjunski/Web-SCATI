-- =====================================================================
-- Web SCATI - Migração: campo Placa Mãe para equipamentos do tipo Computador
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e a tabela "equipamentos" ainda NÃO tem a coluna "placa_mae".
-- Se for uma instalação nova, basta importar o scati.sql atualizado — não
-- é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_placa_mae.sql
-- =====================================================================

USE scati;

ALTER TABLE equipamentos
    ADD COLUMN placa_mae VARCHAR(120) NULL AFTER ip_fixo;
