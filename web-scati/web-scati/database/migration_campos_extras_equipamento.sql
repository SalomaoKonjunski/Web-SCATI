-- =====================================================================
-- Web SCATI - Migração: Campos extras por Categoria de Equipamento
-- =====================================================================
-- Use este script SOMENTE se o banco "scati" já existia antes desta
-- atualização. Se for uma instalação nova, basta importar o scati.sql
-- atualizado — não é necessário rodar este arquivo.
--
-- O que muda:
--  - Categorias de Equipamentos ganha 4 flags (campo_hardware,
--    campo_impressora, campo_rede_computador, campo_servidor) que
--    controlam quais cards aparecem no cadastro/ficha de Equipamentos
--    para aquele tipo — no lugar da regra fixa que só mostrava campos
--    extras para "Computador"/"Impressora"/"Servidor". As 4 categorias
--    protegidas (Computador, Notebook, Impressora, Servidor) recebem os
--    flags que já refletem o comportamento de sempre do sistema; as
--    demais (Monitor, Switch, Roteador, Access Point, Nobreak, Outros e
--    categorias novas que você criar) começam com "Hardware" habilitado,
--    igual já acontecia hoje para qualquer tipo que não fosse Impressora.
--  - Categorias de Estoque perde os campos extras por categoria — volta a
--    ter só o nome. O cadastro de Estoque volta a ser um formulário
--    simples (Nome, Categoria, Marca, Modelo, Quantidade, Quantidade
--    Mínima, Localização, Observações), sem cards dinâmicos.
--
-- As colunas extras que haviam sido adicionadas à tabela "estoque" (ex.:
-- processador, ip, funcao_servidor) NÃO são removidas por este script —
-- ficam paradas sem uso, para não haver risco de perder algum dado já
-- cadastrado nelas. Se tiver certeza de que não há nada de útil nelas e
-- quiser removê-las também, peça a migração de limpeza correspondente.
--
-- IMPORTANTE: rode sempre com --default-character-set=utf8mb4, senão
-- nomes com acento podem ser gravados corrompidos:
--   mysql --default-character-set=utf8mb4 -u root -p scati < database/migration_campos_extras_equipamento.sql
-- =====================================================================

USE scati;

ALTER TABLE categorias_equipamento
    ADD COLUMN IF NOT EXISTS campo_hardware        TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS campo_impressora       TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS campo_rede_computador  TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS campo_servidor         TINYINT(1) NOT NULL DEFAULT 0;

UPDATE categorias_equipamento SET campo_hardware = 1, campo_rede_computador = 1 WHERE nome = 'Computador';
UPDATE categorias_equipamento SET campo_hardware = 1 WHERE nome = 'Notebook';
UPDATE categorias_equipamento SET campo_impressora = 1, campo_hardware = 0 WHERE nome = 'Impressora';
UPDATE categorias_equipamento SET campo_hardware = 1, campo_servidor = 1 WHERE nome = 'Servidor';
UPDATE categorias_equipamento SET campo_hardware = 1
    WHERE nome NOT IN ('Computador', 'Notebook', 'Impressora', 'Servidor');

ALTER TABLE categorias_estoque
    DROP COLUMN IF EXISTS campo_hardware,
    DROP COLUMN IF EXISTS campo_impressora,
    DROP COLUMN IF EXISTS campo_rede_computador,
    DROP COLUMN IF EXISTS campo_servidor,
    DROP COLUMN IF EXISTS campo_localizacao_uso,
    DROP COLUMN IF EXISTS campo_financeiro;
