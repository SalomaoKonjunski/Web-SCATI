-- =====================================================================
-- Web SCATI - Migração: sistema de login (usuário e senha)
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e ainda NÃO tem a tabela "usuarios". Se for uma instalação
-- nova, basta importar o scati.sql atualizado — não é necessário rodar
-- este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_usuarios.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS usuarios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario         VARCHAR(50)  NOT NULL UNIQUE,
    senha_hash      VARCHAR(255) NOT NULL,
    perfil          ENUM('Administrador','Padrão','Usuário') NOT NULL DEFAULT 'Padrão',
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Usuário administrador padrão (usuario: Salomao / senha: scati2026).
-- Recomenda-se trocar a senha após o primeiro acesso.
INSERT IGNORE INTO usuarios (usuario, senha_hash, perfil) VALUES
('Salomao', '$2y$12$PgslwwvkfvXprsqnXjYlJu2XD2sQ742Dlmji7mmp9/rOzB8lEE0u.', 'Administrador');
