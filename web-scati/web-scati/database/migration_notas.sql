-- =====================================================================
-- Web SCATI - Migração: Bloco de Notas pessoal
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização. Se for uma instalação nova, basta importar o scati.sql
-- atualizado — não é necessário rodar este arquivo.
--
-- Cria a tabela notas, usada pela nova tela "Bloco de Notas" (menu
-- lateral) — cada nota pertence a um usuário e só aparece para ele mesmo.
--
-- IMPORTANTE: rode sempre com --default-character-set=utf8mb4, senão
-- nomes com acento podem ser gravados corrompidos:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_notas.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS notas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT          NOT NULL,
    titulo          VARCHAR(150) NOT NULL,
    conteudo        TEXT         NULL,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_nota_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
