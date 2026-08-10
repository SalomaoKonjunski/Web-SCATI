-- =====================================================================
-- Web SCATI - Migração: campo Acesso a Dispositivos USB (todos os equipamentos)
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e a tabela "equipamentos" ainda NÃO tem a coluna "acesso_usb".
-- Se for uma instalação nova, basta importar o scati.sql atualizado — não
-- é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_acesso_usb.sql
-- =====================================================================

USE scati;

ALTER TABLE equipamentos
    ADD COLUMN acesso_usb TINYINT(1) NOT NULL DEFAULT 0 AFTER rede_id;
