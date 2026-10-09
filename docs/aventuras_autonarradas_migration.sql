-- Cronicas autonarradas. Ejecutar despues de aventuras_ux_migration.sql.
-- Migracion aditiva para MySQL 5.7; no intenta clasificar datos historicos.

ALTER TABLE `mybb_op_peticionAventuras`
  ADD COLUMN `tipo` ENUM('normal','autonarrada') NOT NULL DEFAULT 'normal' AFTER `estado`,
  ADD INDEX `idx_tipo_estado` (`tipo`, `estado`);

CREATE TABLE IF NOT EXISTS `mybb_op_aventuras_autonarradas` (
  `peticion_id` INT UNSIGNED NOT NULL,
  `fid` INT UNSIGNED NOT NULL,
  `semana_inicio` INT UNSIGNED NOT NULL,
  `nivel_solicitud` SMALLINT UNSIGNED NOT NULL,
  `tid` INT UNSIGNED NULL,
  `publicada_at` INT UNSIGNED NULL,
  `nivel_publicacion` SMALLINT UNSIGNED NULL,
  `palabras` INT UNSIGNED NULL,
  `reputacion` ENUM('positiva','negativa') NULL,
  `estado_pago` ENUM('pendiente','aplicando','aplicado','dudoso') NOT NULL DEFAULT 'pendiente',
  `xp_aplicada` TINYINT(1) NOT NULL DEFAULT 0,
  `nikas_aplicadas` TINYINT(1) NOT NULL DEFAULT 0,
  `reputacion_aplicada` TINYINT(1) NOT NULL DEFAULT 0,
  `berries_aplicados` TINYINT(1) NOT NULL DEFAULT 0,
  `pagada_por` INT UNSIGNED NULL,
  `pagada_at` INT UNSIGNED NULL,
  PRIMARY KEY (`peticion_id`),
  UNIQUE KEY `uq_auto_fid_semana` (`fid`, `semana_inicio`),
  UNIQUE KEY `uq_auto_tid` (`tid`),
  KEY `idx_auto_pago` (`estado_pago`, `publicada_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Antes de desplegar, revisar manualmente las candidatas historicas. No se
-- actualizan de forma automatica porque la heuristica anterior producia falsos
-- positivos (narrador = participante y tier bajo).
-- SELECT id, uid, narrador_uid, tier_seleccionado, created_at
-- FROM mybb_op_peticionAventuras
-- WHERE narrador_uid = uid AND tier_seleccionado BETWEEN 1 AND 3;

