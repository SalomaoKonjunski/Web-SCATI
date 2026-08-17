-- =====================================================================
-- Web SCATI - Migração: campo Nome do Equipamento
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e a tabela "equipamentos" ainda NÃO tem a coluna "nome".
-- Se for uma instalação nova, basta importar o scati.sql atualizado —
-- não é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_nome_equipamento.sql
-- =====================================================================

USE scati;

ALTER TABLE equipamentos
    ADD COLUMN nome VARCHAR(120) NULL AFTER id;

-- Equipamentos já cadastrados ficam sem nome até serem editados; nas
-- listagens, o sistema mostra o patrimônio (ou "Indefinido") no lugar do
-- nome até que ele seja preenchido.
