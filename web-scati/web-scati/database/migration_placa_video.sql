-- =====================================================================
-- Web SCATI - Migração: campo Placa de Vídeo para equipamentos do tipo Computador
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e a tabela "equipamentos" ainda NÃO tem a coluna "placa_video".
-- Se for uma instalação nova, basta importar o scati.sql atualizado — não
-- é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_placa_video.sql
-- =====================================================================

USE scati;

ALTER TABLE equipamentos
    ADD COLUMN placa_video VARCHAR(120) NULL AFTER placa_mae;
