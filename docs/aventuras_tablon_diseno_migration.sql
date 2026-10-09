-- Rediseño del tablón: preferencias estructuradas de narradores.
-- Ejecutar una sola vez en una instalación que ya aplicó aventuras_migration.sql.

ALTER TABLE `mybb_op_narradores_disponibles`
  ADD COLUMN `frecuencia` VARCHAR(32) NOT NULL DEFAULT '' AFTER `mensaje`,
  ADD COLUMN `tiers` VARCHAR(32) NOT NULL DEFAULT '' AFTER `frecuencia`,
  ADD COLUMN `estilo` VARCHAR(64) NOT NULL DEFAULT '' AFTER `tiers`;
