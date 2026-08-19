-- =====================================================================
-- Web SCATI - Migração: renomeia o perfil "Solicitante" para "Usuário"
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já tem a coluna "perfil"
-- na tabela "usuarios" com a opção antiga "Solicitante" (em vez de
-- "Usuário") — ou seja, se você já rodou migration_perfil_usuario.sql
-- antes desta renomeação. Se for uma instalação nova, basta importar o
-- scati.sql atualizado — não é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_renomeia_perfil_usuario.sql
-- =====================================================================

USE scati;

-- Passo 1: adiciona "Usuário" como opção válida no ENUM, mantendo
-- "Solicitante" temporariamente para não travar as linhas já existentes.
ALTER TABLE usuarios
    MODIFY COLUMN perfil ENUM('Administrador','Padrão','Solicitante','Usuário') NOT NULL DEFAULT 'Padrão';

-- Passo 2: migra quem estava cadastrado como "Solicitante" para "Usuário".
UPDATE usuarios SET perfil = 'Usuário' WHERE perfil = 'Solicitante';

-- Passo 3: remove a opção antiga "Solicitante" do ENUM.
ALTER TABLE usuarios
    MODIFY COLUMN perfil ENUM('Administrador','Padrão','Usuário') NOT NULL DEFAULT 'Padrão';
