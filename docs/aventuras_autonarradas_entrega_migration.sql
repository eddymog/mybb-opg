-- Entrega explicita de cronicas autonarradas por parte del jugador.
-- Ejecutar despues de aventuras_autonarradas_migration.sql.

ALTER TABLE `mybb_op_aventuras_autonarradas`
  ADD COLUMN `entregada_at` INT UNSIGNED NULL AFTER `reputacion`,
  ADD INDEX `idx_auto_entrega` (`entregada_at`, `estado_pago`);
