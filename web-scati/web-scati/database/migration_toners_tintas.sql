-- Migração: catálogo de Toners e Tintas (aba própria em Impressoras).
-- Segura pra rodar mais de uma vez — cria as tabelas só se não existirem
-- e não duplica os dados migrados dos itens de Estoque.

CREATE TABLE IF NOT EXISTS toners (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    nome                    VARCHAR(120) NOT NULL,
    tipo                    ENUM('Toner','Tinta') NOT NULL DEFAULT 'Toner',
    marca                   VARCHAR(80) NULL,
    modelo                  VARCHAR(80) NULL,
    quantidade              INT NOT NULL DEFAULT 0,
    quantidade_minima       INT NOT NULL DEFAULT 0,
    localizacao             VARCHAR(120) NULL,
    observacoes             TEXT NULL,
    migrado_de_estoque_id   INT NULL,
    criado_em               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS toner_impressoras (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    toner_id        INT NOT NULL,
    equipamento_id  INT NOT NULL,

    CONSTRAINT fk_tonerimp_toner
        FOREIGN KEY (toner_id) REFERENCES toners(id) ON DELETE CASCADE,
    CONSTRAINT fk_tonerimp_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_toner_equipamento (toner_id, equipamento_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS toner_movimentacoes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    toner_id        INT NOT NULL,
    tipo            ENUM('Cadastro','Baixa','Reposição') NOT NULL,
    quantidade      INT NOT NULL,
    equipamento_id  INT NULL,
    usuario_nome    VARCHAR(50) NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_tonermov_toner
        FOREIGN KEY (toner_id) REFERENCES toners(id) ON DELETE CASCADE,
    CONSTRAINT fk_tonermov_equipamento
        FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET @indice_existe = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE() AND table_name = 'toner_movimentacoes' AND index_name = 'idx_tonermov_toner'
);
SET @sql_indice = IF(@indice_existe = 0,
    'CREATE INDEX idx_tonermov_toner ON toner_movimentacoes(toner_id)',
    'SELECT 1');
PREPARE stmt_indice FROM @sql_indice;
EXECUTE stmt_indice;
DEALLOCATE PREPARE stmt_indice;

-- Migra os itens de Estoque das categorias "Toner"/"Tinta" pro catálogo
-- novo (idempotente: cada item só migra uma vez, controlado por
-- migrado_de_estoque_id).
INSERT INTO toners (nome, tipo, marca, modelo, quantidade, quantidade_minima, localizacao, observacoes, migrado_de_estoque_id)
SELECT es.nome, IF(ce.nome = 'Tinta', 'Tinta', 'Toner'), es.marca, es.modelo,
       es.quantidade, es.quantidade_minima, es.localizacao, es.observacoes, es.id
FROM estoque es
JOIN categorias_estoque ce ON ce.id = es.categoria_id
WHERE ce.nome IN ('Toner', 'Tinta')
  AND NOT EXISTS (SELECT 1 FROM toners t WHERE t.migrado_de_estoque_id = es.id);

-- Recria os vínculos com impressoras a partir de quem já tinha unidades
-- desse item vinculadas (itens_vinculados) antes da migração.
INSERT INTO toner_impressoras (toner_id, equipamento_id)
SELECT DISTINCT t.id, iv.equipamento_id
FROM toners t
JOIN itens_vinculados iv ON iv.estoque_id = t.migrado_de_estoque_id
WHERE t.migrado_de_estoque_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM toner_impressoras ti WHERE ti.toner_id = t.id AND ti.equipamento_id = iv.equipamento_id
  );

-- Registra uma movimentação de "Cadastro" pra cada toner migrado, pra
-- não deixar o histórico vazio.
INSERT INTO toner_movimentacoes (toner_id, tipo, quantidade, criado_em)
SELECT t.id, 'Cadastro', t.quantidade, t.criado_em
FROM toners t
WHERE t.migrado_de_estoque_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM toner_movimentacoes tm WHERE tm.toner_id = t.id);
