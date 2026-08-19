-- =====================================================================
-- Web SCATI - Migração: perfil de usuário (Administrador / Padrão / Usuário)
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e a tabela "usuarios" ainda tem a antiga coluna "admin"
-- (não tem a coluna "perfil"). Se for uma instalação nova, basta
-- importar o scati.sql atualizado — não é necessário rodar este arquivo.
--
-- Se você já rodou uma versão anterior desta migração (com o perfil
-- chamado "Solicitante" em vez de "Usuário"), NÃO rode este arquivo de
-- novo — rode em vez disso database/migration_renomeia_perfil_usuario.sql.
--
-- O perfil "Usuário" só tem acesso à aba de Chamados: pode registrar
-- chamados e acompanhar os que ele mesmo abriu, mas não enxerga o
-- restante do sistema nem pode editar/excluir chamados.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_perfil_usuario.sql
-- =====================================================================

USE scati;

ALTER TABLE usuarios
    ADD COLUMN perfil ENUM('Administrador','Padrão','Usuário') NOT NULL DEFAULT 'Padrão' AFTER admin;

-- Converte os administradores existentes (coluna "admin" antiga) para o novo perfil.
UPDATE usuarios SET perfil = 'Administrador' WHERE admin = 1;

-- A coluna "admin" não é mais usada pelo sistema.
ALTER TABLE usuarios DROP COLUMN admin;
