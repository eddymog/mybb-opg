-- Conserva en cada entrega el narrador asignado al enviarla.
-- Ejecutar una vez antes de desplegar el PHP de la revisión 2026-10-08.

SET @tabla_entregas = 'mybb_op_aventuras_entregas';

SET @sql = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @tabla_entregas
      AND COLUMN_NAME = 'narrador_uid'
  ),
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tabla_entregas,
    '` ADD `narrador_uid` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `intentos`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `mybb_op_aventuras_entregas`
SET `narrador_uid` = `enviado_por`
WHERE `narrador_uid` = 0;

SET @sql = IF(
  EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @tabla_entregas
      AND INDEX_NAME = 'idx_narrador_uid'
  ),
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tabla_entregas,
    '` ADD KEY `idx_narrador_uid` (`narrador_uid`)')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
