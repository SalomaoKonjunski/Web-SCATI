-- =====================================================================
-- Web SCATI - Migração: registra qual usuário fez cada alteração
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e as tabelas "historico_equipamentos"/"historico_estoque"
-- ainda NÃO têm a coluna "usuario_nome". Se for uma instalação nova,
-- basta importar o scati.sql atualizado — não é necessário rodar este
-- arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_historico_usuario.sql
-- =====================================================================

USE scati;

ALTER TABLE historico_equipamentos
    ADD COLUMN usuario_nome VARCHAR(50) NULL AFTER descricao;

ALTER TABLE historico_estoque
    ADD COLUMN usuario_nome VARCHAR(50) NULL AFTER data_hora;
