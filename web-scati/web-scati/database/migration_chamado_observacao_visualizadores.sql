-- =====================================================================
-- Web SCATI - Migração: escolher quem mais vê uma observação
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e ainda NÃO tem a tabela "chamado_observacao_visualizadores".
-- Se for uma instalação nova, basta importar o scati.sql atualizado — não
-- é necessário rodar este arquivo. Também exige que a tabela
-- "chamado_observacoes" já exista (veja migration_chamado_observacoes.sql).
--
-- Por padrão, uma observação continua visível só para quem a escreveu;
-- esta tabela guarda os cadastros extras que o autor escolheu para
-- também poderem ver aquela observação específica.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_chamado_observacao_visualizadores.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS chamado_observacao_visualizadores (
    observacao_id   INT NOT NULL,
    usuario_id      INT NOT NULL,

    PRIMARY KEY (observacao_id, usuario_id),
    CONSTRAINT fk_obsvis_observacao
        FOREIGN KEY (observacao_id) REFERENCES chamado_observacoes(id) ON DELETE CASCADE,
    CONSTRAINT fk_obsvis_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
