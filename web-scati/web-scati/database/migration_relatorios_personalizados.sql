-- =====================================================================
-- Web SCATI - Migração: Construtor de Relatório (relatórios configuráveis)
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização. Se for uma instalação nova, basta importar o scati.sql
-- atualizado — não é necessário rodar este arquivo.
--
-- Cria a tabela relatorios_personalizados, usada pelo novo
-- "Relatórios > Construtor de Relatório" para salvar relatórios
-- personalizados (origem dos dados, colunas e filtros escolhidos pelo
-- usuário) como favoritos reutilizáveis. Nenhuma tabela existente é
-- alterada.
--
-- IMPORTANTE: rode sempre com --default-character-set=utf8mb4, senão
-- nomes com acento podem ser gravados corrompidos:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_relatorios_personalizados.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS relatorios_personalizados (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(120) NOT NULL,
    origem          VARCHAR(30)  NOT NULL,
    colunas         TEXT         NOT NULL,
    filtros         TEXT         NULL,
    criado_por      VARCHAR(50)  NULL,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
