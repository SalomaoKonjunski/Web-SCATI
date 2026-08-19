-- =====================================================================
-- Web SCATI - Migração: ramal e telefone do usuário
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e a tabela "usuarios" ainda NÃO tem as colunas "ramal" e
-- "telefone". Se for uma instalação nova, basta importar o scati.sql
-- atualizado — não é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_ramal_telefone_usuario.sql
-- =====================================================================

USE scati;

ALTER TABLE usuarios
    ADD COLUMN ramal    VARCHAR(20) NULL AFTER perfil,
    ADD COLUMN telefone VARCHAR(20) NULL AFTER ramal;

-- Ambos os campos são opcionais; usuários já cadastrados ficam sem
-- ramal/telefone até serem editados.
