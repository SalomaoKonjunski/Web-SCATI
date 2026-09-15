-- =====================================================================
-- Web SCATI - Migração: Senhas (cofre simples de credenciais de TI)
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização. Se for uma instalação nova, basta importar o scati.sql
-- atualizado — não é necessário rodar este arquivo.
--
-- Cria a tabela senhas, usada pela nova tela "Senhas" (menu lateral) para
-- guardar login/senha de roteadores, servidores, sistemas, Wi-Fi,
-- licenças, etc. A senha é sempre guardada cifrada (AES-256-CBC, mesma
-- ENCRYPTION_KEY já usada para a senha de email corporativo em
-- Usuários) — nunca em texto puro no banco.
--
-- IMPORTANTE: rode sempre com --default-character-set=utf8mb4:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_senhas.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS senhas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(120) NOT NULL,
    categoria       VARCHAR(30)  NOT NULL,
    usuario         VARCHAR(120) NULL,
    senha_cifrada   TEXT         NOT NULL,
    observacoes     TEXT         NULL,
    criado_por      VARCHAR(50)  NULL,
    atualizado_por  VARCHAR(50)  NULL,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
