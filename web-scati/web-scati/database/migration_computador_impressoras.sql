-- Migração: vínculo N:N entre Computadores e Impressoras ("Computadores
-- Vinculados" na ficha da impressora / "Impressoras Vinculadas" na ficha
-- do computador). Segura pra rodar mais de uma vez — cria a tabela só se
-- não existir.

CREATE TABLE IF NOT EXISTS computador_impressoras (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    computador_id   INT NOT NULL,
    impressora_id   INT NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_compimp_computador
        FOREIGN KEY (computador_id) REFERENCES equipamentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_compimp_impressora
        FOREIGN KEY (impressora_id) REFERENCES equipamentos(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_computador_impressora (computador_id, impressora_id)
) ENGINE=InnoDB;
