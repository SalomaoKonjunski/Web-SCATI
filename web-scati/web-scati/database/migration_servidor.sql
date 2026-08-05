-- =====================================================================
-- Web SCATI - Migração: suporte a equipamentos do tipo Servidor
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização (ou seja, foi criado com uma versão anterior de scati.sql).
-- Se for uma instalação nova, basta importar o scati.sql atualizado —
-- não é necessário rodar este arquivo.
--
-- Rode uma única vez:
--   mysql -u root -p scati < database/migration_servidor.sql
-- =====================================================================

USE scati;

-- ---------------------------------------------------------------------
-- 1) Adiciona 'Servidor' à lista de tipos de equipamento aceitos
-- ---------------------------------------------------------------------
ALTER TABLE equipamentos
    MODIFY tipo ENUM('Computador','Notebook','Impressora','Monitor','Switch',
                      'Roteador','Access Point','Nobreak','Servidor','Outros') NOT NULL;

-- ---------------------------------------------------------------------
-- 2) Novas colunas específicas de servidor
-- ---------------------------------------------------------------------
ALTER TABLE equipamentos
    ADD COLUMN funcao_servidor      VARCHAR(100) NULL AFTER qtd_toners,
    ADD COLUMN servidor_status      ENUM('Ativo','Inativo') NULL AFTER funcao_servidor,
    ADD COLUMN servidor_observacoes TEXT NULL AFTER servidor_status;

-- ---------------------------------------------------------------------
-- 3) Tabela: compartilhamentos_servidor
-- ---------------------------------------------------------------------
CREATE TABLE compartilhamentos_servidor (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    equipamento_id      INT NOT NULL,
    nome                VARCHAR(120) NOT NULL,
    caminho_pasta       VARCHAR(255) NOT NULL,
    descricao           TEXT NULL,
    permissoes          VARCHAR(255) NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_compartilhamento_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4) Tabela: compartilhamento_computadores (vínculo N:N com equipamentos)
-- ---------------------------------------------------------------------
CREATE TABLE compartilhamento_computadores (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    compartilhamento_id INT NOT NULL,
    equipamento_id      INT NOT NULL,

    CONSTRAINT fk_compcomp_compartilhamento
        FOREIGN KEY (compartilhamento_id) REFERENCES compartilhamentos_servidor(id) ON DELETE CASCADE,
    CONSTRAINT fk_compcomp_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE,
    CONSTRAINT uq_compcomp UNIQUE (compartilhamento_id, equipamento_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5) Índice auxiliar
-- ---------------------------------------------------------------------
CREATE INDEX idx_compart_equipamento ON compartilhamentos_servidor(equipamento_id);
