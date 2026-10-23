-- =====================================================================
-- Web SCATI - Migração: Anexos em Transferências
-- Permite anexar arquivos (PDF do formulário físico de transferência,
-- fotos, notas fiscais, etc.) a um registro de Transferência já
-- existente.
-- =====================================================================
-- Rode uma única vez, se o banco "scati" já existia antes desta
-- atualização (e já tiver passado por database/migration_transferencias.sql):
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_anexos_transferencias.sql
--
-- Se for uma instalação nova, basta importar o scati.sql atualizado —
-- não é necessário rodar este arquivo (a tabela já vem criada).
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS anexos_transferencias (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    transferencia_id    INT NOT NULL,
    nome_original       VARCHAR(255) NOT NULL,
    nome_arquivo        VARCHAR(255) NOT NULL,
    tipo_mime           VARCHAR(100) NULL,
    tamanho             INT NOT NULL,
    descricao           VARCHAR(255) NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_anexo_transferencia
        FOREIGN KEY (transferencia_id) REFERENCES transferencias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_anexo_transferencia ON anexos_transferencias(transferencia_id);
