-- =====================================================================
-- Web SCATI - Migração: Anexos no Bloco de Notas
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização. Se for uma instalação nova, basta importar o scati.sql
-- atualizado — não é necessário rodar este arquivo.
--
-- Cria a tabela anexos_notas, usada para vincular arquivos (PDF, Word,
-- Excel, imagens, etc.) a uma nota específica do Bloco de Notas. Mesmo
-- mecanismo de upload já usado nos anexos de Equipamentos — arquivo
-- guardado em uploads/anexos_notas/ com nome gerado aleatoriamente (nunca
-- o nome original), nunca acessado direto por URL, sempre baixado através
-- de modules/notas/anexo_download.php (que confere se a nota é do usuário
-- logado antes de liberar o download).
--
-- IMPORTANTE: rode sempre com --default-character-set=utf8mb4:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_anexos_notas.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS anexos_notas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nota_id         INT NOT NULL,
    nome_original   VARCHAR(255) NOT NULL,
    nome_arquivo    VARCHAR(255) NOT NULL,
    tipo_mime       VARCHAR(100) NULL,
    tamanho         INT NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_anexo_nota
        FOREIGN KEY (nota_id) REFERENCES notas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
