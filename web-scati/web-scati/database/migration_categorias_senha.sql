-- =====================================================================
-- Web SCATI - Migração: Categorias de Senha editáveis
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização. Se for uma instalação nova, basta importar o scati.sql
-- atualizado — não é necessário rodar este arquivo.
--
-- Cria a tabela categorias_senha (mesmo padrão de categorias_estoque),
-- usada para tornar editável a lista de categorias da tela Senhas — antes
-- era uma lista fixa no código (Rede, Servidor, Sistema, Wi-Fi, Licença,
-- Outro), que agora vem pré-cadastrada como ponto de partida editável em
-- Configurações > Categorias de Senhas.
--
-- IMPORTANTE: rode sempre com --default-character-set=utf8mb4:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_categorias_senha.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS categorias_senha (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nome    VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT IGNORE INTO categorias_senha (nome) VALUES
('Rede'), ('Servidor'), ('Sistema'), ('Wi-Fi'), ('Licença'), ('Outro');
