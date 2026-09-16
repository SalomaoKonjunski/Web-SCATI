-- =====================================================================
-- Web SCATI - Migração: Ordem das notas no Bloco de Notas
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização. Se for uma instalação nova, basta importar o scati.sql
-- atualizado — não é necessário rodar este arquivo.
--
-- Adiciona a coluna "ordem" em notas, usada para guardar a posição
-- escolhida ao arrastar os cartões no Bloco de Notas. Não precisa de
-- backfill: enquanto uma nota nunca foi arrastada ela fica com ordem = 0
-- (empatada com as demais ainda não mexidas), e a consulta usa
-- "atualizado_em DESC" como critério de desempate — ou seja, a ordem que
-- já aparece hoje na tela continua exatamente igual até o usuário
-- arrastar algum cartão pela primeira vez.
--
-- IMPORTANTE: rode sempre com --default-character-set=utf8mb4:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_notas_ordem.sql
-- =====================================================================

USE scati;

ALTER TABLE notas ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER conteudo;
