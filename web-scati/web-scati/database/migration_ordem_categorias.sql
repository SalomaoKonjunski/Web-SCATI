ALTER TABLE categorias_estoque
    ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER nome;

ALTER TABLE categorias_equipamento
    ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER nome;

ALTER TABLE categorias_senha
    ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER nome;
