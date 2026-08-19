-- =====================================================================
-- Web SCATI - Migração: respostas do chamado + notificação de não lidas
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e ainda NÃO tem as tabelas "chamado_respostas" e
-- "chamado_visualizacoes". Se for uma instalação nova, basta importar
-- o scati.sql atualizado — não é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_chamado_respostas.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS chamado_respostas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    chamado_id      INT NOT NULL,
    usuario_id      INT NULL,
    usuario_nome    VARCHAR(50) NOT NULL,
    mensagem        TEXT NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_resposta_chamado
        FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE CASCADE,
    CONSTRAINT fk_resposta_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_resposta_chamado ON chamado_respostas(chamado_id);

CREATE TABLE IF NOT EXISTS chamado_visualizacoes (
    usuario_id      INT NOT NULL,
    chamado_id      INT NOT NULL,
    visto_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, chamado_id),

    CONSTRAINT fk_visualizacao_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_visualizacao_chamado
        FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE CASCADE
) ENGINE=InnoDB;
