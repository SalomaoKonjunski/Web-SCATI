-- =====================================================================
-- Web SCATI - Migração: Categorias de Equipamentos separadas de
--                        Categorias de Estoque
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização. Se for uma instalação nova, basta importar o scati.sql
-- atualizado — não é necessário rodar este arquivo.
--
-- O que muda:
--  - Cria a tabela categorias_equipamento: uma tela própria em
--    Configurações ("Categorias de Equipamentos"), independente de
--    Categorias de Estoque, já populada com os 10 tipos que hoje ficam
--    fixos no código (Computador, Notebook, Impressora, Monitor, Switch,
--    Roteador, Access Point, Nobreak, Servidor, Outros).
--  - A coluna equipamentos.tipo deixa de ser uma lista fixa (ENUM) e passa
--    a aceitar qualquer nome cadastrado em categorias_equipamento.
--  - Remove a coluna equipamento_tipo de categorias_estoque (usada pela
--    versão anterior, onde uma categoria de estoque podia "virar"
--    equipamento) — agora Categorias de Estoque só cuida de itens de
--    estoque, e Categorias de Equipamentos é uma tela separada.
--
-- IMPORTANTE: rode sempre com --default-character-set=utf8mb4, senão
-- nomes com acento podem ser gravados corrompidos:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_categorias_equipamento_separadas.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS categorias_equipamento (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nome    VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT IGNORE INTO categorias_equipamento (nome) VALUES
('Computador'), ('Notebook'), ('Impressora'), ('Monitor'), ('Switch'),
('Roteador'), ('Access Point'), ('Nobreak'), ('Servidor'), ('Outros');

ALTER TABLE equipamentos
    MODIFY COLUMN tipo VARCHAR(30) NOT NULL;

ALTER TABLE categorias_estoque
    DROP COLUMN IF EXISTS equipamento_tipo;
