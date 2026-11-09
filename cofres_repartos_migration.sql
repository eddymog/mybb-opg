-- Historial e idempotencia para el reparto masivo de cofres.
-- Migracion no destructiva compatible con MySQL 5.7.

CREATE TABLE IF NOT EXISTS `mybb_op_cofres_repartos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reparto_key` VARCHAR(64) NOT NULL,
    `objeto_id` VARCHAR(255) NOT NULL,
    `cantidad` INT UNSIGNED NOT NULL,
    `umbral_dateline` INT UNSIGNED NOT NULL,
    `total_destinatarios` INT UNSIGNED NOT NULL DEFAULT 0,
    `actor_uid` INT UNSIGNED NOT NULL,
    `actor_username` VARCHAR(120) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cofres_reparto_key` (`reparto_key`),
    KEY `idx_cofres_reparto_fecha` (`created_at`),
    KEY `idx_cofres_reparto_objeto` (`objeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_cofres_repartos_detalle` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reparto_id` BIGINT UNSIGNED NOT NULL,
    `uid` INT UNSIGNED NOT NULL,
    `nombre` VARCHAR(255) NOT NULL,
    `username` VARCHAR(120) NOT NULL,
    `cantidad_anterior` INT NOT NULL,
    `cantidad_entregada` INT UNSIGNED NOT NULL,
    `cantidad_final` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cofres_reparto_destinatario` (`reparto_id`, `uid`),
    KEY `idx_cofres_reparto_usuario` (`uid`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_cofres_repartos_reversiones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reparto_id` BIGINT UNSIGNED NOT NULL,
    `actor_uid` INT UNSIGNED NOT NULL,
    `actor_username` VARCHAR(120) NOT NULL,
    `motivo` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cofres_reversion_reparto` (`reparto_id`),
    KEY `idx_cofres_reversion_fecha` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_cofres_repartos_reversiones_detalle` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reversion_id` BIGINT UNSIGNED NOT NULL,
    `reparto_id` BIGINT UNSIGNED NOT NULL,
    `uid` INT UNSIGNED NOT NULL,
    `cantidad_anterior` INT NOT NULL,
    `cantidad_retirada` INT UNSIGNED NOT NULL,
    `cantidad_final` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cofres_reversion_destinatario` (`reversion_id`, `uid`),
    KEY `idx_cofres_reversion_reparto` (`reparto_id`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Casos que una anulacion parcial deja para resolucion manual.
CREATE TABLE IF NOT EXISTS `mybb_op_cofres_repartos_reversiones_incidencias` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reversion_id` BIGINT UNSIGNED NOT NULL,
    `reparto_id` BIGINT UNSIGNED NOT NULL,
    `uid` INT UNSIGNED NOT NULL,
    `aperturas_detectadas` INT UNSIGNED NOT NULL DEFAULT 0,
    `cantidad_no_retirada` INT UNSIGNED NOT NULL DEFAULT 0,
    `descripcion` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cofres_reversion_incidencia` (`reversion_id`, `uid`),
    KEY `idx_cofres_reversion_incidencia_reparto` (`reparto_id`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
