CREATE TABLE IF NOT EXISTS perfis_acesso (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(50) NOT NULL UNIQUE,
    protegido   TINYINT(1) NOT NULL DEFAULT 0,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO perfis_acesso (nome, protegido) VALUES
('Administrador', 1),
('Padrão', 0),
('Usuário', 0);

CREATE TABLE IF NOT EXISTS perfil_permissoes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    perfil_id   INT NOT NULL,
    modulo      VARCHAR(30) NOT NULL,
    visualizar  TINYINT(1) NOT NULL DEFAULT 0,
    alterar     TINYINT(1) NOT NULL DEFAULT 0,

    CONSTRAINT fk_permissao_perfil
        FOREIGN KEY (perfil_id) REFERENCES perfis_acesso(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cria o índice único só se ainda não existir (rodar este script mais de
-- uma vez, ex.: depois de um erro no meio do caminho, não quebra mais).
SET @idx_existe = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE() AND table_name = 'perfil_permissoes' AND index_name = 'uq_perfil_modulo'
);
SET @sql_idx = IF(@idx_existe = 0,
    'CREATE UNIQUE INDEX uq_perfil_modulo ON perfil_permissoes(perfil_id, modulo)',
    'SELECT 1');
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- Padrão: acesso completo a tudo, exceto Senhas e Usuários (restritos a
-- Administrador, igual já era antes desta migração). INSERT IGNORE: se
-- rodar de novo, não duplica o que já foi inserido.
INSERT IGNORE INTO perfil_permissoes (perfil_id, modulo, visualizar, alterar)
SELECT (SELECT id FROM perfis_acesso WHERE nome = 'Padrão'), modulos.modulo, 1, 1
FROM (
    SELECT 'dashboard' AS modulo UNION ALL SELECT 'chamados' UNION ALL SELECT 'equipamentos' UNION ALL
    SELECT 'impressoras' UNION ALL SELECT 'estoque' UNION ALL SELECT 'redes' UNION ALL
    SELECT 'licencas' UNION ALL SELECT 'relatorios' UNION ALL SELECT 'notas' UNION ALL
    SELECT 'configuracoes'
) AS modulos;

-- Usuário (antigo "solicitante"): só enxerga e participa dos próprios
-- chamados, igual já era antes desta migração.
INSERT IGNORE INTO perfil_permissoes (perfil_id, modulo, visualizar, alterar)
VALUES ((SELECT id FROM perfis_acesso WHERE nome = 'Usuário'), 'chamados', 1, 0);

-- usuarios.perfil deixa de ser uma lista fixa (ENUM) e passa a referenciar
-- o nome de um perfil em perfis_acesso — renomear um perfil atualiza
-- sozinho (ON UPDATE CASCADE) todo usuário que já usava o nome antigo, e
-- a tabela não deixa excluir um perfil ainda em uso (ON DELETE RESTRICT).
ALTER TABLE usuarios
    MODIFY COLUMN perfil VARCHAR(50) NOT NULL DEFAULT 'Padrão';

-- Adiciona a chave estrangeira só se ela ainda não existir.
SET @fk_existe = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND constraint_name = 'fk_usuario_perfil'
);
SET @sql_fk = IF(@fk_existe = 0,
    'ALTER TABLE usuarios ADD CONSTRAINT fk_usuario_perfil FOREIGN KEY (perfil) REFERENCES perfis_acesso(nome) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1');
PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;
