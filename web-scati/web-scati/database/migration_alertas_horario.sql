-- =====================================================================
-- Web SCATI - Migration: Horário nos Alertas (Central de Alertas)
-- Rode este script no banco de dados já existente (ex.: phpMyAdmin >
-- aba SQL) para habilitar:
--   1) Horário (não só data) na "Próxima Execução" de cada alerta.
--   2) Antecedência do aviso em horas (antes era só em dias inteiros).
-- Alertas já cadastrados são preservados: a data vira meia-noite (pode
-- editar depois pra ajustar o horário) e a antecedência em dias vira o
-- equivalente em horas (ex.: 3 dias -> 72 horas), mantendo o mesmo
-- comportamento de antes.
-- Seguro de rodar mais de uma vez (idempotente).
-- =====================================================================

-- proxima_execucao passa de DATE para DATETIME (datas já cadastradas
-- ganham o horário 00:00:00 automaticamente).
ALTER TABLE tarefas_periodicas MODIFY COLUMN proxima_execucao DATETIME NOT NULL;

-- Renomeia dias_aviso_antecedencia para horas_aviso_antecedencia,
-- convertendo os valores já cadastrados (dias * 24 = horas). Só roda se
-- a coluna antiga ainda existir — se rodar de novo, não duplica o
-- cálculo.
SET @coluna_antiga_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE() AND table_name = 'tarefas_periodicas' AND column_name = 'dias_aviso_antecedencia'
);

SET @sql_rename = IF(@coluna_antiga_existe > 0,
    'ALTER TABLE tarefas_periodicas CHANGE COLUMN dias_aviso_antecedencia horas_aviso_antecedencia INT NOT NULL DEFAULT 72',
    'SELECT 1');
PREPARE stmt_rename FROM @sql_rename;
EXECUTE stmt_rename;
DEALLOCATE PREPARE stmt_rename;

SET @sql_multiplicar = IF(@coluna_antiga_existe > 0,
    'UPDATE tarefas_periodicas SET horas_aviso_antecedencia = horas_aviso_antecedencia * 24',
    'SELECT 1');
PREPARE stmt_multiplicar FROM @sql_multiplicar;
EXECUTE stmt_multiplicar;
DEALLOCATE PREPARE stmt_multiplicar;
