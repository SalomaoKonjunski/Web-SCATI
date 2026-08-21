-- =====================================================================
-- Web SCATI - Migração: observações voltam a ser só de administrador
-- =====================================================================
-- Use este script SOMENTE se você chegou a rodar
-- migration_chamado_observacao_visualizadores.sql (a versão em que o
-- autor de uma observação escolhia individualmente quais outros
-- cadastros também podiam vê-la). Esse recurso foi substituído por uma
-- regra fixa: toda observação agora é visível para qualquer
-- Administrador, e não aparece para o solicitante nem para os perfis
-- Padrão/Usuário — então a tabela de "quem mais pode ver" não é mais
-- necessária.
--
-- Se você nunca rodou aquela migração (ou já importou o scati.sql
-- atualizado), não precisa rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_remove_chamado_observacao_visualizadores.sql
-- =====================================================================

USE scati;

DROP TABLE IF EXISTS chamado_observacao_visualizadores;
