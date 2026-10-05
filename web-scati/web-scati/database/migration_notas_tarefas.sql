-- =====================================================================
-- Web SCATI - Migration: Compartilhamento de Notas + Tarefas Periódicas
-- Rode este script no banco de dados já existente (ex.: phpMyAdmin >
-- aba SQL) para habilitar as duas funcionalidades novas:
--   1) Compartilhar notas do Bloco de Notas com outras pessoas.
--   2) Tarefas Periódicas (avisos recorrentes, ex.: troca de toner,
--      limpeza do cortador de papel, reinicialização de servidor) que
--      aparecem na Central de Alertas do Dashboard quando vencem.
-- Seguro de rodar mais de uma vez (idempotente).
-- =====================================================================

CREATE TABLE IF NOT EXISTS nota_compartilhamentos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nota_id     INT NOT NULL,
    usuario_id  INT NOT NULL,
    pode_editar TINYINT(1) NOT NULL DEFAULT 0,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_compartilhamento_nota
        FOREIGN KEY (nota_id) REFERENCES notas(id) ON DELETE CASCADE,
    CONSTRAINT fk_compartilhamento_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cria o índice único só se ainda não existir.
SET @idx_nc_existe = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE() AND table_name = 'nota_compartilhamentos' AND index_name = 'uq_nota_usuario'
);
SET @sql_idx_nc = IF(@idx_nc_existe = 0,
    'CREATE UNIQUE INDEX uq_nota_usuario ON nota_compartilhamentos(nota_id, usuario_id)',
    'SELECT 1');
PREPARE stmt_idx_nc FROM @sql_idx_nc;
EXECUTE stmt_idx_nc;
DEALLOCATE PREPARE stmt_idx_nc;

CREATE TABLE IF NOT EXISTS tarefas_periodicas (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    titulo                  VARCHAR(150) NOT NULL,
    descricao               TEXT NULL,
    frequencia_tipo         ENUM('dias','semanas','meses') NOT NULL DEFAULT 'dias',
    frequencia_valor        INT NOT NULL DEFAULT 1,
    dias_aviso_antecedencia INT NOT NULL DEFAULT 3,
    ultima_execucao         DATE NULL,
    proxima_execucao        DATE NOT NULL,
    responsavel_id          INT NULL,
    ativo                   TINYINT(1) NOT NULL DEFAULT 1,
    criado_em               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_tarefa_responsavel
        FOREIGN KEY (responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historico_tarefas_periodicas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tarefa_id       INT NOT NULL,
    data_hora       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    evento          VARCHAR(60) NOT NULL,
    descricao       VARCHAR(255) NOT NULL,
    usuario_nome    VARCHAR(50) NULL,

    CONSTRAINT fk_historico_tarefa
        FOREIGN KEY (tarefa_id) REFERENCES tarefas_periodicas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Dá acesso completo ao novo módulo "Tarefas Periódicas" para o perfil
-- Padrão (mesmo critério usado quando os outros módulos operacionais
-- foram liberados pra ele). INSERT IGNORE: se rodar de novo, não duplica.
INSERT IGNORE INTO perfil_permissoes (perfil_id, modulo, visualizar, alterar)
VALUES ((SELECT id FROM perfis_acesso WHERE nome = 'Padrão'), 'tarefas', 1, 1);
