-- =====================================================================
-- Web SCATI - Migração: alerta de troca de toner por tempo de uso
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e a tabela "equipamentos" ainda NÃO tem as colunas
-- "toner_duracao_dias" e "toner_ultima_troca". Se for uma instalação
-- nova, basta importar o scati.sql atualizado — não é necessário rodar
-- este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_toner_troca.sql
-- =====================================================================

USE scati;

ALTER TABLE equipamentos
    ADD COLUMN toner_duracao_dias INT NULL AFTER qtd_toners,
    ADD COLUMN toner_ultima_troca DATE NULL AFTER toner_duracao_dias;

INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('dias_alerta_toner', '7');
