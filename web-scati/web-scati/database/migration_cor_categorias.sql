ALTER TABLE categorias_estoque
    ADD COLUMN cor VARCHAR(7) NOT NULL DEFAULT '#6c757d' AFTER ordem;

ALTER TABLE categorias_equipamento
    ADD COLUMN cor VARCHAR(7) NOT NULL DEFAULT '#6c757d' AFTER ordem;

ALTER TABLE categorias_senha
    ADD COLUMN cor VARCHAR(7) NOT NULL DEFAULT '#6c757d' AFTER ordem;

ALTER TABLE tipos_manutencao
    ADD COLUMN cor VARCHAR(7) NOT NULL DEFAULT '#6c757d' AFTER ordem;
