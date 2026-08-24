-- =====================================================================
-- Web SCATI - Migração: categoria de estoque pode virar Equipamento
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e ainda NÃO tem a coluna "equipamento_tipo" na tabela
-- categorias_estoque. Se for uma instalação nova, basta importar o
-- scati.sql atualizado — não é necessário rodar este arquivo.
--
-- Uma categoria de estoque agora pode ser marcada como "Equipamento":
-- em vez de virar uma linha em Estoque (com quantidade), "Novo Item"
-- nessa categoria leva direto para o cadastro de Equipamentos, com o
-- tipo já pré-selecionado. Categorias sem isso marcado continuam
-- funcionando exatamente como hoje.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_categoria_equipamento_tipo.sql
-- =====================================================================

USE scati;

ALTER TABLE categorias_estoque
    ADD COLUMN IF NOT EXISTS equipamento_tipo VARCHAR(30) NULL;
