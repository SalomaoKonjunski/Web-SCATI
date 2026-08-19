-- =====================================================================
-- Web SCATI - Migração: aba de Chamados (pendências/solicitações de TI)
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização e ainda NÃO tem a tabela "chamados". Se for uma instalação
-- nova, basta importar o scati.sql atualizado — não é necessário rodar
-- este arquivo.
--
-- Rode uma única vez:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_chamados.sql
-- =====================================================================

USE scati;

CREATE TABLE IF NOT EXISTS chamados (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    titulo              VARCHAR(150) NOT NULL,
    descricao           TEXT NULL,
    solicitante         VARCHAR(100) NULL,
    equipamento_id      INT NULL,
    prioridade          ENUM('Baixa','Média','Alta','Urgente') NOT NULL DEFAULT 'Média',
    status              ENUM('Aberto','Em andamento','Aguardando','Concluído','Cancelado') NOT NULL DEFAULT 'Aberto',
    responsavel_id      INT NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    concluido_em        DATETIME NULL,

    CONSTRAINT fk_chamado_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_chamado_responsavel
        FOREIGN KEY (responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_chamado_status ON chamados(status);
CREATE INDEX idx_chamado_prioridade ON chamados(prioridade);
CREATE INDEX idx_chamado_responsavel ON chamados(responsavel_id);
