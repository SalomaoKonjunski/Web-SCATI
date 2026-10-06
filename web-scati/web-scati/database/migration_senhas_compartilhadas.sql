-- =====================================================================
-- Web SCATI - Migration: Compartilhamento de Senhas específicas
-- Rode este script no banco de dados já existente (ex.: phpMyAdmin >
-- aba SQL) para permitir liberar uma senha específica (Configurações >
-- Senhas) para uma pessoa escolhida, mesmo que ela não tenha a
-- permissão "Senhas" de forma geral em Perfis de Acesso.
-- Seguro de rodar mais de uma vez (idempotente).
-- =====================================================================

CREATE TABLE IF NOT EXISTS senha_compartilhamentos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    senha_id    INT NOT NULL,
    usuario_id  INT NOT NULL,
    pode_editar TINYINT(1) NOT NULL DEFAULT 0,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_senha_compartilhamento_senha
        FOREIGN KEY (senha_id) REFERENCES senhas(id) ON DELETE CASCADE,
    CONSTRAINT fk_senha_compartilhamento_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cria o índice único só se ainda não existir.
SET @idx_sc_existe = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE() AND table_name = 'senha_compartilhamentos' AND index_name = 'uq_senha_usuario'
);
SET @sql_idx_sc = IF(@idx_sc_existe = 0,
    'CREATE UNIQUE INDEX uq_senha_usuario ON senha_compartilhamentos(senha_id, usuario_id)',
    'SELECT 1');
PREPARE stmt_idx_sc FROM @sql_idx_sc;
EXECUTE stmt_idx_sc;
DEALLOCATE PREPARE stmt_idx_sc;
