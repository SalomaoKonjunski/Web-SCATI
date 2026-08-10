-- =====================================================================
-- Web SCATI - Migração: tela de Configurações (Categorias de Estoque,
-- Tipos de Manutenção e dias de alerta de licença)
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e ainda NÃO tem as tabelas "tipos_manutencao" e
-- "configuracoes". Se for uma instalação nova, basta importar o
-- scati.sql atualizado — não é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_configuracoes.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS tipos_manutencao (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nome    VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT IGNORE INTO tipos_manutencao (nome) VALUES
('Manutenção Preventiva'), ('Limpeza'), ('Troca de Componente'), ('Outro');

CREATE TABLE IF NOT EXISTS configuracoes (
    chave   VARCHAR(60) PRIMARY KEY,
    valor   VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO configuracoes (chave, valor) VALUES
('dias_alerta_licenca', '30');

-- Observação: a categoria_estoque já existe desde a instalação inicial
-- do sistema — a tela de Configurações apenas passa a gerenciá-la (CRUD),
-- nenhuma mudança de estrutura é necessária nessa tabela.
