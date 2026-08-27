-- =====================================================================
-- Web SCATI - Migração: Notificações push (app instalável + PWA)
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização. Se for uma instalação nova, basta importar o scati.sql
-- atualizado — não é necessário rodar este arquivo.
--
-- Cria a tabela push_subscriptions, usada para guardar em quais
-- navegadores/dispositivos cada usuário ativou notificação push (uma
-- linha por dispositivo). Nenhuma tabela existente é alterada.
--
-- IMPORTANTE: rode sempre com --default-character-set=utf8mb4:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_push_subscriptions.sql
--
-- Requer também MariaDB/MySQL com innodb_default_row_format=dynamic (ou
-- innodb_large_prefix=ON em versões mais antigas) — praticamente qualquer
-- instalação dos últimos anos já vem assim por padrão.
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT          NOT NULL,
    endpoint        VARCHAR(512) NOT NULL,
    p256dh          VARCHAR(255) NOT NULL,
    auth            VARCHAR(255) NOT NULL,
    user_agent      VARCHAR(255) NULL,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_push_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_push_endpoint (endpoint)
) ENGINE=InnoDB;
