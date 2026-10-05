CREATE TABLE IF NOT EXISTS chamado_resposta_anexos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    resposta_id     INT NOT NULL,
    nome_original   VARCHAR(255) NOT NULL,
    nome_arquivo    VARCHAR(255) NOT NULL,
    tipo_mime       VARCHAR(100) NULL,
    tamanho         INT NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_anexo_resposta
        FOREIGN KEY (resposta_id) REFERENCES chamado_respostas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_anexo_resposta ON chamado_resposta_anexos(resposta_id);
