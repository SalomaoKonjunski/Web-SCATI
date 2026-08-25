-- =====================================================================
-- Web SCATI - Migração: Email corporativo no cadastro de Usuários
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização. Se for uma instalação nova, basta importar o scati.sql
-- atualizado — não é necessário rodar este arquivo.
--
-- Adiciona ao cadastro de Usuários um campo de email corporativo e a
-- senha desse email — diferente da senha de login (que continua em hash
-- bcrypt, irreversível), esta é cifrada com ENCRYPTION_KEY (ver
-- config/database.php) para poder ser consultada de volta pelo
-- administrador no cadastro do usuário. Também remove o campo
-- "Confirmar Senha" do formulário (a senha de login ganhou um botão de
-- mostrar/ocultar em vez disso).
--
-- IMPORTANTE: antes de rodar esta migração, defina ENCRYPTION_KEY em
-- config/database.php (o arquivo já vem com uma chave de exemplo — troque
-- por uma própria gerada com "php -r 'echo bin2hex(random_bytes(32));'"
-- antes de cadastrar qualquer senha de email).
--
-- Rode sempre com --default-character-set=utf8mb4:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_usuarios_email_corporativo.sql
-- =====================================================================

USE scati;

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS email_corporativo   VARCHAR(150) NULL AFTER telefone,
    ADD COLUMN IF NOT EXISTS senha_email_cifrada  TEXT         NULL AFTER email_corporativo;
