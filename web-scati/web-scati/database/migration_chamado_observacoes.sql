-- =====================================================================
-- Web SCATI - Migração: observações privadas do chamado
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e ainda NÃO tem a tabela "chamado_observacoes". Se for
-- uma instalação nova, basta importar o scati.sql atualizado — não é
-- necessário rodar este arquivo.
--
-- Cada observação só aparece para o próprio usuário que a escreveu —
-- nem outros administradores, nem o solicitante do chamado a veem.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_chamado_observacoes.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS chamado_observacoes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    chamado_id      INT NOT NULL,
    usuario_id      INT NOT NULL,
    texto           TEXT NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_obs_chamado_chamado
        FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE CASCADE,
    CONSTRAINT fk_obs_chamado_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_obs_chamado ON chamado_observacoes(chamado_id, usuario_id);
