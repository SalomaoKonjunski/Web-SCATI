-- =====================================================================
-- Web SCATI - Migration: "Ver Todos os Chamados" em Perfis de Acesso
-- Rode este script no banco de dados já existente (ex.: phpMyAdmin >
-- aba SQL) para habilitar, em Configurações > Perfis de Acesso, uma
-- permissão extra específica do módulo Chamados: "Ver Todos os
-- Chamados" — sem ela, cada perfil só enxerga (na aba Em Atendimento)
-- os chamados em que é o responsável; com ela, também pode ver (sem
-- poder alterar) os chamados atribuídos a qualquer pessoa da equipe.
-- Seguro de rodar mais de uma vez (idempotente).
-- =====================================================================

SET @coluna_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE() AND table_name = 'perfil_permissoes' AND column_name = 'ver_todos'
);
SET @sql_coluna = IF(@coluna_existe = 0,
    'ALTER TABLE perfil_permissoes ADD COLUMN ver_todos TINYINT(1) NOT NULL DEFAULT 0 AFTER alterar',
    'SELECT 1');
PREPARE stmt_coluna FROM @sql_coluna;
EXECUTE stmt_coluna;
DEALLOCATE PREPARE stmt_coluna;
