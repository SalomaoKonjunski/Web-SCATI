ALTER TABLE tipos_manutencao
    ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER nome;
