-- =====================================================================
-- Web SCATI - Migração: autor do chamado + regras do perfil Solicitante
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e a tabela "chamados" ainda NÃO tem a coluna
-- "criado_por_id". Se for uma instalação nova, basta importar o
-- scati.sql atualizado — não é necessário rodar este arquivo.
--
-- Pré-requisito: rode antes a migração migration_perfil_usuario.sql
-- (ou já tenha a coluna "perfil" em "usuarios"), pois o perfil
-- Solicitante depende dela.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_chamados_solicitante.sql
-- =====================================================================

USE scati;

ALTER TABLE chamados
    ADD COLUMN criado_por_id INT NULL AFTER solicitante,
    ADD CONSTRAINT fk_chamado_criado_por
        FOREIGN KEY (criado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL;

-- Chamados já cadastrados ficam sem autor registrado (criado_por_id NULL);
-- só passam a ter autor os chamados abertos a partir de agora. Isso não
-- afeta usuários Administrador/Padrão, apenas limita quais chamados um
-- usuário Solicitante enxerga (somente os que ele mesmo abriu).
