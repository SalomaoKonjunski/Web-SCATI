-- =====================================================================
-- Web SCATI - Migração: Mapeamento de Portas do Switch
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização. Se for uma instalação nova, basta importar o scati.sql
-- atualizado — não é necessário rodar este arquivo.
--
-- Adiciona o novo grupo de campos "switch" (mesma lógica dos grupos
-- Hardware/Impressora/Rede do Computador/Servidor já existentes em
-- Configurações > Categorias de Equipamento), o campo "Quantidade de
-- Portas" no cadastro de equipamentos, e a tabela que guarda o status de
-- cada porta (livre / ocupada / inativa) e qual equipamento está
-- conectado nela.
--
-- IMPORTANTE: rode sempre com --default-character-set=utf8mb4:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_portas_switch.sql
-- =====================================================================

USE scati;

ALTER TABLE categorias_equipamento
    ADD COLUMN campo_switch TINYINT(1) NOT NULL DEFAULT 0 AFTER campo_servidor;

UPDATE categorias_equipamento SET campo_switch = 1 WHERE nome = 'Switch';

ALTER TABLE equipamentos
    ADD COLUMN qtd_portas_switch INT NULL AFTER servidor_observacoes;

CREATE TABLE IF NOT EXISTS portas_switch (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    switch_id       INT NOT NULL,
    numero          INT NOT NULL,
    status          ENUM('Livre','Ocupada','Inativa') NOT NULL DEFAULT 'Livre',
    equipamento_id  INT NULL,
    observacao      VARCHAR(120) NULL,

    CONSTRAINT fk_porta_switch
        FOREIGN KEY (switch_id) REFERENCES equipamentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_porta_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE SET NULL,
    UNIQUE KEY uq_porta_switch_numero (switch_id, numero)
) ENGINE=InnoDB;
