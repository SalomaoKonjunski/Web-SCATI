-- =====================================================================
-- Web SCATI - Migração: anexos na ficha do equipamento
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e ainda NÃO tem a tabela "anexos_equipamentos". Se for uma
-- instalação nova, basta importar o scati.sql atualizado — não é
-- necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_anexos.sql
-- =====================================================================

USE scati;

CREATE TABLE anexos_equipamentos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    equipamento_id  INT NOT NULL,
    nome_original   VARCHAR(255) NOT NULL,
    nome_arquivo    VARCHAR(255) NOT NULL,
    tipo_mime       VARCHAR(100) NULL,
    tamanho         INT NOT NULL,
    descricao       VARCHAR(255) NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_anexo_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_anexo_equipamento ON anexos_equipamentos(equipamento_id);
