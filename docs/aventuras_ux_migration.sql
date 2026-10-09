-- Tablón de aventuras: participantes normalizados, propuestas y notificaciones.
-- Requiere docs/aventuras_migration.sql y MySQL 5.7 o superior.
-- Es aditiva. Ejecutar una sola vez antes de desplegar el código UX 2026-10-07.

CREATE TABLE IF NOT EXISTS `mybb_op_aventuras_participantes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `peticion_id` INT UNSIGNED NOT NULL,
  `uid` INT UNSIGNED NULL,
  `nombre_snapshot` VARCHAR(255) NOT NULL DEFAULT '',
  `orden` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_peticion_uid` (`peticion_id`, `uid`),
  UNIQUE KEY `uq_peticion_orden` (`peticion_id`, `orden`),
  KEY `idx_uid_peticion` (`uid`, `peticion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_aventuras_propuestas_narrador` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `peticion_id` INT UNSIGNED NOT NULL,
  `narrador_uid` INT UNSIGNED NOT NULL,
  `iniciada_por_uid` INT UNSIGNED NOT NULL,
  `tipo` ENUM('invitacion','candidatura') NOT NULL,
  `estado` ENUM('pendiente','aceptada','rechazada','retirada','cancelada','vencida') NOT NULL DEFAULT 'pendiente',
  `mensaje` VARCHAR(500) NOT NULL DEFAULT '',
  `created_at` INT UNSIGNED NOT NULL,
  `expires_at` INT UNSIGNED NOT NULL,
  `resolved_at` INT UNSIGNED NULL,
  `resolved_by_uid` INT UNSIGNED NULL,
  `pendiente_narrador_uid` INT UNSIGNED GENERATED ALWAYS AS
    (CASE WHEN `estado` = 'pendiente' THEN `narrador_uid` ELSE NULL END) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pendiente_par` (`peticion_id`, `pendiente_narrador_uid`),
  KEY `idx_peticion_estado` (`peticion_id`, `estado`),
  KEY `idx_narrador_estado` (`narrador_uid`, `estado`),
  KEY `idx_estado_vence` (`estado`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_aventuras_notificaciones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave_evento` VARCHAR(96) NOT NULL,
  `peticion_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `uid` INT UNSIGNED NOT NULL,
  `actor_uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `tipo` VARCHAR(40) NOT NULL,
  `datos_json` TEXT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  `read_at` INT UNSIGNED NULL,
  `sent_at` INT UNSIGNED NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evento_destinatario` (`clave_evento`, `uid`),
  KEY `idx_uid_read` (`uid`, `read_at`, `created_at`),
  KEY `idx_envio` (`sent_at`, `attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mantenimiento horario: backfill gradual, vencimiento de propuestas y avisos.
INSERT INTO `mybb_tasks`
  (`title`, `description`, `file`, `minute`, `hour`, `day`, `month`, `weekday`, `nextrun`, `lastrun`, `enabled`, `logging`, `locked`)
SELECT
  'OPG: Mantenimiento de aventuras',
  'Normaliza participantes y procesa vencimientos y avisos del tablón de aventuras.',
  'aventurasux', '15', '*', '*', '*', '*', 0, 0, 1, 1, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `mybb_tasks` WHERE `file` = 'aventurasux');

-- El backfill de jugadores_json se ejecuta por lotes mediante
-- task_aventurasux(). Esto evita depender de JSON_TABLE, ausente en MySQL 5.7.
