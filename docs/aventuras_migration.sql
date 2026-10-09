-- ============================================================================
-- Tablón de aventuras: migración
--
-- Documentos: docs/101_Requirements_Registro.md, docs/201_DesignPlan_Registro.md
--             y docs/301_ImplementationPlan_Registro.md (fase 3).
--
-- Reglas de esta migración:
--   * Es ADITIVA: no modifica ni borra ninguna fila, y no renombra columnas.
--   * Se ejecuta UNA sola vez. MySQL no admite ADD COLUMN IF NOT EXISTS, así que
--     ejecutar el archivo dos veces falla en la primera sentencia repetida.
--   * ANTES de ejecutarla: respaldar mybb_op_peticionAventuras y
--     mybb_op_peticionAventuras_meta, y anotar el resultado de las consultas de
--     verificación del final.
--   * Índices que ya existen en mybb_op_peticionAventuras (no se repiten):
--     PRIMARY (id), uid_created_at (uid, created_at), tier_idx (tier_seleccionado)
--     y narrador_idx (narrador_uid, narrador_fid, que ya cubre narrador_uid).
--   * Las dos tablas MyISAM del juego (mybb_users, mybb_threads) no se tocan.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Columnas nuevas de mybb_op_peticionAventuras (diseño 3.1)
-- ----------------------------------------------------------------------------
ALTER TABLE `mybb_op_peticionAventuras`
  ADD COLUMN `isla_fid` SMALLINT UNSIGNED NULL AFTER `narrador_nombre`,
  ADD COLUMN `dia` TINYINT UNSIGNED NULL AFTER `isla_fid`,
  ADD COLUMN `motivo_denegacion` VARCHAR(500) NULL AFTER `comentario_staff`,
  ADD COLUMN `idea_narrador` VARCHAR(500) NULL AFTER `motivo_denegacion`,
  ADD COLUMN `motivo_renuncia` VARCHAR(500) NULL AFTER `idea_narrador`,
  ADD COLUMN `editado_at` INT UNSIGNED NULL AFTER `motivo_renuncia`;

-- ----------------------------------------------------------------------------
-- 2. Columnas antiguas que el insert nuevo no llena (diseño 3.2)
--    Las filas existentes no cambian. TEXT/MEDIUMTEXT pasan a admitir NULL
--    porque no admiten DEFAULT en todas las versiones. La calculadora actual
--    sigue insertando todos estos campos, así que no se rompe.
--    tier_seleccionado SIGUE usándose (de 1 a 10).
-- ----------------------------------------------------------------------------
ALTER TABLE `mybb_op_peticionAventuras`
  MODIFY `tier_seleccionado` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `dificultad_texto` VARCHAR(20) NOT NULL DEFAULT '',
  MODIFY `dificultad_color` VARCHAR(7) NOT NULL DEFAULT '',
  MODIFY `descripcion_tier` TEXT NULL,
  MODIFY `enemigos_json` MEDIUMTEXT NULL,
  MODIFY `inframundo` TINYINT(1) NOT NULL DEFAULT 0;

-- ----------------------------------------------------------------------------
-- 3. Índices (diseño 3.3)
-- ----------------------------------------------------------------------------
ALTER TABLE `mybb_op_peticionAventuras`
  ADD INDEX `idx_estado` (`estado`),
  ADD INDEX `idx_isla_fid` (`isla_fid`),
  ADD INDEX `idx_created_at` (`created_at`);

-- ----------------------------------------------------------------------------
-- 4. Tabla meta (la calculadora actual la crea en runtime; aquí pasa a migración)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mybb_op_peticionAventuras_meta` (
  `peticion_id` INT UNSIGNED NOT NULL,
  `staff_note` TEXT NULL,
  `public_comment` TEXT NULL,
  `updated_by` INT UNSIGNED NULL,
  `updated_at` INT UNSIGNED NULL,
  PRIMARY KEY (`peticion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 5. Auditoría (diseño 3.4)
--    peticion_id = 0 para las acciones del mercado de narradores.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mybb_op_audit_aventuras` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `peticion_id` INT UNSIGNED NOT NULL,
  `uid` INT UNSIGNED NOT NULL,
  `accion` VARCHAR(32) NOT NULL,
  `detalle_json` MEDIUMTEXT NULL,
  `fecha` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_peticion` (`peticion_id`),
  KEY `idx_uid_fecha` (`uid`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 6. Narradores disponibles (diseño 3.5)
--    La presencia de la fila es la oferta. Vigente mientras
--    updated_at >= ahora - 30 días.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mybb_op_narradores_disponibles` (
  `uid` INT UNSIGNED NOT NULL,
  `discord` VARCHAR(64) NOT NULL,
  `mensaje` VARCHAR(200) NOT NULL DEFAULT '',
  `frecuencia` VARCHAR(32) NOT NULL DEFAULT '',
  `tiers` VARCHAR(32) NOT NULL DEFAULT '',
  `estilo` VARCHAR(64) NOT NULL DEFAULT '',
  `created_at` INT UNSIGNED NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`uid`),
  KEY `idx_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 7. Entregas y pagos (diseño 3.6)
--    tid_activo vale el tid mientras la entrega está pendiente o entregada y
--    NULL cuando está rechazada; los NULL no chocan en un índice único.
--    El UNIQUE (entrega_id, uid) de los pagos garantiza un pago por destinatario.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mybb_op_aventuras_entregas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `peticion_id` INT UNSIGNED NOT NULL,
  `tid` INT UNSIGNED NOT NULL,
  `tid_activo` INT UNSIGNED NULL,
  `tema_nombre` VARCHAR(255) NOT NULL,
  `resumen` TEXT NOT NULL,
  `reputacion` ENUM('positiva','negativa') NOT NULL,
  `estado` ENUM('pendiente','rechazada','entregada') NOT NULL DEFAULT 'pendiente',
  `tier` TINYINT UNSIGNED NULL,
  `infra` TINYINT(1) NULL,
  `ajustes_json` MEDIUMTEXT NULL,
  `intentos` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `narrador_uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `enviado_por` INT UNSIGNED NOT NULL,
  `enviado_at` INT UNSIGNED NOT NULL,
  `revisado_por` INT UNSIGNED NULL,
  `revisado_at` INT UNSIGNED NULL,
  `motivo_rechazo` VARCHAR(500) NULL,
  `procesando_por` INT UNSIGNED NULL,
  `procesando_at` INT UNSIGNED NULL,
  `entregado_at` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_peticion` (`peticion_id`),
  UNIQUE KEY `uq_tid_activo` (`tid_activo`),
  KEY `idx_estado` (`estado`, `enviado_at`),
  KEY `idx_narrador_uid` (`narrador_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_aventuras_entregas_pagos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entrega_id` INT UNSIGNED NOT NULL,
  `uid` INT UNSIGNED NOT NULL,
  `rol` ENUM('jugador','narrador') NOT NULL,
  `porcentaje` DECIMAL(6,2) NOT NULL,
  `detalle_json` MEDIUMTEXT NULL,
  `aplicado_at` INT UNSIGNED NULL,
  `xp_cantidad` INT UNSIGNED NOT NULL DEFAULT 0,
  `xp_estado` ENUM('pendiente','aplicando','aplicado','dudoso') NOT NULL DEFAULT 'pendiente',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_entrega_uid` (`entrega_id`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 8. Verificación (ejecutar ANTES y DESPUÉS; los resultados deben coincidir)
-- ----------------------------------------------------------------------------
-- SELECT COUNT(*) AS filas, MAX(id) AS max_id, SUM(id) AS suma_ids,
--        CRC32(GROUP_CONCAT(id ORDER BY id)) AS checksum_ids
--   FROM mybb_op_peticionAventuras;
-- SELECT COUNT(*) FROM mybb_op_peticionAventuras_meta;
-- SHOW INDEX FROM mybb_op_peticionAventuras;
