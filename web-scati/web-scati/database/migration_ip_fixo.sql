-- =====================================================================
-- Web SCATI - Migração: campo IP Fixo para equipamentos do tipo Computador
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e a tabela "equipamentos" ainda NÃO tem a coluna "ip_fixo".
-- Se for uma instalação nova, basta importar o scati.sql atualizado — não
-- é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_ip_fixo.sql
-- =====================================================================

USE scati;

ALTER TABLE equipamentos
    ADD COLUMN ip_fixo VARCHAR(45) NULL AFTER qtd_toners;
