-- =====================================================================
-- Migracion: bitacora de temas y rondas de rol (One Piece Gaiden)
-- Ver docs/100_Requirements_Temas.md y docs/200_DesignPlan_Temas.md.
--
-- El plugin op_bitacora.php crea estas tablas automaticamente al
-- instalarse. Este archivo permite crearlas manualmente desde phpMyAdmin
-- o la CLI. Si la instalacion usa otro prefijo, sustituir `mybb_`.
--
-- No usa foreign keys porque las tablas core de esta instalacion pueden
-- utilizar MyISAM. La propiedad y la integridad se validan en PHP.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `mybb_op_temas_seguidos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `personaje_uid` int unsigned NOT NULL,
  `tid` int unsigned NOT NULL,
  `ronda_inicio_pid` int unsigned NOT NULL DEFAULT '0',
  `override_estado` varchar(12) NOT NULL DEFAULT 'auto',
  `narrador_uid` int unsigned NOT NULL DEFAULT '0',
  `estado_grupo` varchar(12) NOT NULL DEFAULT '',
  `estado_desde` int unsigned NOT NULL DEFAULT '0',
  `creado_en` int unsigned NOT NULL,
  `actualizado_en` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personaje_tema` (`personaje_uid`, `tid`),
  KEY `tema` (`tid`),
  KEY `personaje_actualizado` (`personaje_uid`, `actualizado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Actualizacion idempotente para instalaciones creadas antes del soporte
-- de rondas narradas.
SET @op_temas_narrador_sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `mybb_op_temas_seguidos` ADD `narrador_uid` int unsigned NOT NULL DEFAULT 0 AFTER `override_estado`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mybb_op_temas_seguidos'
    AND COLUMN_NAME = 'narrador_uid'
);
PREPARE op_temas_narrador_stmt FROM @op_temas_narrador_sql;
EXECUTE op_temas_narrador_stmt;
DEALLOCATE PREPARE op_temas_narrador_stmt;

-- Actualizacion idempotente para conservar desde cuando esta vigente el
-- grupo visible Tu turno / Al dia.
SET @op_bitacora_estado_grupo_sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `mybb_op_temas_seguidos` ADD `estado_grupo` varchar(12) NOT NULL DEFAULT '''' AFTER `narrador_uid`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mybb_op_temas_seguidos'
    AND COLUMN_NAME = 'estado_grupo'
);
PREPARE op_bitacora_estado_grupo_stmt FROM @op_bitacora_estado_grupo_sql;
EXECUTE op_bitacora_estado_grupo_stmt;
DEALLOCATE PREPARE op_bitacora_estado_grupo_stmt;

SET @op_bitacora_estado_desde_sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `mybb_op_temas_seguidos` ADD `estado_desde` int unsigned NOT NULL DEFAULT 0 AFTER `estado_grupo`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mybb_op_temas_seguidos'
    AND COLUMN_NAME = 'estado_desde'
);
PREPARE op_bitacora_estado_desde_stmt FROM @op_bitacora_estado_desde_sql;
EXECUTE op_bitacora_estado_desde_stmt;
DEALLOCATE PREPARE op_bitacora_estado_desde_stmt;

CREATE TABLE IF NOT EXISTS `mybb_op_temas_participantes` (
  `seguimiento_id` int unsigned NOT NULL,
  `participante_uid` int unsigned NOT NULL,
  `origen` varchar(10) NOT NULL DEFAULT 'auto',
  `creado_en` int unsigned NOT NULL,
  PRIMARY KEY (`seguimiento_id`, `participante_uid`),
  KEY `participante` (`participante_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mybb_op_bitacora_eventos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `seguimiento_id` int unsigned NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `actor_uid` int unsigned NOT NULL DEFAULT '0',
  `relacionado_uid` int unsigned NOT NULL DEFAULT '0',
  `pid` int unsigned DEFAULT NULL,
  `datos` text NULL,
  `creado_en` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `evento_post` (`seguimiento_id`, `tipo`, `pid`),
  KEY `seguimiento_fecha` (`seguimiento_id`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollback manual, solo si se desea borrar todos los seguimientos:
-- DROP TABLE IF EXISTS `mybb_op_bitacora_eventos`;
-- DROP TABLE IF EXISTS `mybb_op_temas_participantes`;
-- DROP TABLE IF EXISTS `mybb_op_temas_seguidos`;
