-- =====================================================================
-- Migracion: sistema global de notificaciones (One Piece Gaiden)
-- Ver docs/108_Requirements_Notificaciones.md (apartados 9 y 31) y
-- docs/208_DesignPlan_Notificaciones.md (apartado 4).
--
-- Crea la tabla de notificaciones y el contador materializado de no leidas.
-- Es idempotente: se puede ejecutar varias veces sin error ni perdida de
-- datos. Si la instalacion usa otro prefijo, sustituir `mybb_`.
--
-- No usa foreign keys: la integridad se valida en PHP, como en el resto de
-- las tablas mybb_op_*.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `mybb_op_notificaciones` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid`          INT UNSIGNED NOT NULL,                 -- destinatario
  `tipo`         VARCHAR(48)  NOT NULL,                 -- clave del catalogo
  `categoria`    VARCHAR(24)  NOT NULL,                 -- copia del catalogo
  `origen`       VARCHAR(8)   NOT NULL,                 -- gestion | usuario | sistema
  `importancia`  VARCHAR(10)  NOT NULL DEFAULT 'normal', -- normal | importante (copia del catalogo)
  `agrupable`    TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- copia del catalogo
  `actor_uid`    INT UNSIGNED NOT NULL DEFAULT 0,
  `clave_evento` VARCHAR(128) NOT NULL,
  `url`          VARCHAR(255) NULL,
  `cantidad`     INT UNSIGNED NOT NULL DEFAULT 1,
  `datos_json`   TEXT NULL,
  `created_at`   INT UNSIGNED NOT NULL,
  `leida_en`     INT UNSIGNED NULL,
  `sent_at`      INT UNSIGNED NULL,                     -- reservado para envios externos
  `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evento_destinatario` (`uid`, `clave_evento`),
  KEY `idx_uid_fecha` (`uid`, `created_at`, `id`),
  KEY `idx_uid_categoria` (`uid`, `categoria`, `created_at`, `id`),
  KEY `idx_uid_origen` (`uid`, `origen`, `created_at`, `id`),
  KEY `idx_uid_leida` (`uid`, `leida_en`, `created_at`, `id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mybb_op_notificaciones_contador` (
  `uid`         INT UNSIGNED NOT NULL,
  `sin_leer`    INT UNSIGNED NOT NULL DEFAULT 0,
  `importantes` INT UNSIGNED NOT NULL DEFAULT 0,        -- de las no leidas, cuantas son importantes
  `actualizado` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollback manual, solo mientras ninguna otra parte del sistema use las tablas
-- (borra todas las notificaciones y los contadores):
-- DROP TABLE IF EXISTS `mybb_op_notificaciones_contador`;
-- DROP TABLE IF EXISTS `mybb_op_notificaciones`;
