-- La Cosecha de Calabazas Malditas - Halloween 2026.
-- Migracion no destructiva compatible con MySQL 5.7.

CREATE TABLE IF NOT EXISTS `mybb_op_halloween2026_config` (
    `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `timezone` VARCHAR(64) NOT NULL,
    `earn_start_utc` DATETIME NOT NULL,
    `earn_end_utc` DATETIME NOT NULL,
    `claims_end_utc` DATETIME NULL,
    `store_start_utc` DATETIME NULL,
    `store_end_utc` DATETIME NULL,
    `status` VARCHAR(24) NOT NULL DEFAULT 'draft',
    `config_json` JSON NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_halloween2026_saldos` (
    `uid` INT UNSIGNED NOT NULL,
    `ganado_total` INT NOT NULL DEFAULT 0,
    `gastado_total` INT NOT NULL DEFAULT 0,
    `saldo` INT NOT NULL DEFAULT 0,
    `fecha_ultimo_total` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`uid`),
    KEY `idx_halloween_earned` (`ganado_total`, `fecha_ultimo_total`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_halloween2026_movimientos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uid` INT UNSIGNED NOT NULL,
    `amount` INT NOT NULL,
    `bucket` VARCHAR(16) NOT NULL,
    `source_type` VARCHAR(32) NOT NULL,
    `source_id` VARCHAR(128) NOT NULL,
    `source_pid` INT UNSIGNED NULL,
    `source_tid` INT UNSIGNED NULL,
    `idempotency_key` VARCHAR(191) NOT NULL,
    `reverses_movement_id` BIGINT UNSIGNED NULL,
    `actor_uid` INT UNSIGNED NULL,
    `note` VARCHAR(255) NULL,
    `metadata_json` JSON NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_halloween_idempotency` (`idempotency_key`),
    UNIQUE KEY `uq_halloween_reversal` (`reverses_movement_id`),
    KEY `idx_halloween_user_date` (`uid`, `created_at`),
    KEY `idx_halloween_source` (`source_type`, `source_id`),
    KEY `idx_halloween_pid` (`source_pid`),
    KEY `idx_halloween_tid` (`source_tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_halloween2026_publicaciones` (
    `pid` INT UNSIGNED NOT NULL,
    `tid` INT UNSIGNED NOT NULL,
    `uid` INT UNSIGNED NOT NULL,
    `original_fid` INT UNSIGNED NOT NULL,
    `original_dateline` INT UNSIGNED NOT NULL,
    `original_role_eligible` TINYINT(1) NOT NULL DEFAULT 0,
    `is_first_post` TINYINT(1) NOT NULL DEFAULT 0,
    `original_prefix_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `post_visible` TINYINT NOT NULL DEFAULT 0,
    `thread_visible` TINYINT NOT NULL DEFAULT 0,
    `reconcile_status` VARCHAR(24) NOT NULL DEFAULT 'observed',
    `last_reason` VARCHAR(64) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`pid`),
    KEY `idx_halloween_publication_thread` (`tid`, `pid`),
    KEY `idx_halloween_publication_user_date` (`uid`, `original_dateline`),
    KEY `idx_halloween_publication_origin` (`original_role_eligible`, `original_dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_halloween2026_bonos_diarios` (
    `uid` INT UNSIGNED NOT NULL,
    `event_day` DATE NOT NULL,
    `pid` INT UNSIGNED NULL,
    `movement_id` BIGINT UNSIGNED NULL,
    `status` VARCHAR(16) NOT NULL DEFAULT 'assigned',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`uid`, `event_day`),
    UNIQUE KEY `uq_halloween_daily_pid` (`pid`),
    KEY `idx_halloween_daily_movement` (`movement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_halloween2026_tienda_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(64) NOT NULL,
    `nombre` VARCHAR(120) NOT NULL,
    `descripcion` VARCHAR(500) NOT NULL DEFAULT '',
    `imagen` VARCHAR(500) NULL,
    `categoria` VARCHAR(64) NOT NULL DEFAULT 'Recompensas',
    `coste` INT UNSIGNED NOT NULL,
    `reward_type` VARCHAR(32) NOT NULL,
    `reward_json` JSON NOT NULL,
    `stock_global` INT UNSIGNED NULL,
    `stock_vendido` INT UNSIGNED NOT NULL DEFAULT 0,
    `limite_personaje` INT UNSIGNED NULL,
    `starts_at_utc` DATETIME NULL,
    `ends_at_utc` DATETIME NULL,
    `orden` INT NOT NULL DEFAULT 0,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_halloween_store_slug` (`slug`),
    KEY `idx_halloween_store_active` (`activo`, `orden`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_halloween2026_compras` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uid` INT UNSIGNED NOT NULL,
    `total` INT UNSIGNED NOT NULL,
    `estado` VARCHAR(24) NOT NULL DEFAULT 'pending',
    `idempotency_key` VARCHAR(191) NOT NULL,
    `error_internal` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `completed_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_halloween_purchase_idempotency` (`idempotency_key`),
    KEY `idx_halloween_purchase_user_date` (`uid`, `created_at`),
    KEY `idx_halloween_purchase_status` (`estado`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mybb_op_halloween2026_compra_lineas` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_id` BIGINT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `cantidad` INT UNSIGNED NOT NULL,
    `precio_unitario` INT UNSIGNED NOT NULL,
    `nombre_snapshot` VARCHAR(120) NOT NULL,
    `reward_json` JSON NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_halloween_line_purchase` (`purchase_id`, `id`),
    KEY `idx_halloween_line_item` (`item_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `mybb_op_halloween2026_config` (
    `id`, `timezone`, `earn_start_utc`, `earn_end_utc`, `claims_end_utc`,
    `store_start_utc`, `store_end_utc`, `status`, `config_json`, `updated_at`
) VALUES (
    1,
    'Europe/Madrid',
    '2026-10-26 05:00:00',
    '2026-11-10 05:00:00',
    '2026-11-16 23:00:00',
    '2026-10-26 05:00:00',
    '2026-11-16 23:00:00',
    'draft',
    JSON_OBJECT(
        'role_root_fid', 10,
        'common_prefix_id', 1,
        'adventure_prefix_id', 3,
        'spooky_prefix_id', 15,
        'store_enabled', false,
        'main_thread_url', 'https://onepiecegaiden.com/showthread.php?tid=9471',
        'stories_thread_url', 'https://onepiecegaiden.com/showthread.php?tid=9535',
        'monsters_thread_url', 'https://onepiecegaiden.com/showthread.php?tid=9536'
    ),
    UTC_TIMESTAMP()
);

INSERT IGNORE INTO `mybb_op_halloween2026_tienda_items` (
    `slug`, `nombre`, `descripcion`, `imagen`, `categoria`, `coste`,
    `reward_type`, `reward_json`, `stock_global`, `limite_personaje`,
    `orden`, `activo`, `created_at`, `updated_at`
) VALUES
(
    'cofre-calabaza',
    'Cofre Calabaza',
    'Un cofre especial de la Cosecha de Calabazas Malditas.',
    'https://i.imgur.com/87wW3Xt.jpeg',
    'Cofres',
    7,
    'object',
    JSON_OBJECT('objects', JSON_ARRAY(JSON_OBJECT('id', 'CFRHLW', 'quantity', 1))),
    NULL,
    NULL,
    10,
    1,
    UTC_TIMESTAMP(),
    UTC_TIMESTAMP()
),
(
    'nika',
    '1 Nika',
    'Entrega 1 Nika al personaje.',
    NULL,
    'Recursos',
    5,
    'currency',
    JSON_OBJECT('nikas', 1),
    NULL,
    NULL,
    20,
    1,
    UTC_TIMESTAMP(),
    UTC_TIMESTAMP()
),
(
    'oficio-100',
    '100 puntos de oficio',
    'Entrega 100 puntos de oficio al personaje.',
    NULL,
    'Recursos',
    3,
    'currency',
    JSON_OBJECT('craft_points', 100),
    NULL,
    NULL,
    30,
    1,
    UTC_TIMESTAMP(),
    UTC_TIMESTAMP()
),
(
    'berries-50000',
    '50.000 Berries',
    'Entrega 50.000 Berries al personaje.',
    NULL,
    'Recursos',
    1,
    'currency',
    JSON_OBJECT('berries', 50000),
    NULL,
    NULL,
    40,
    1,
    UTC_TIMESTAMP(),
    UTC_TIMESTAMP()
);

UPDATE `mybb_op_halloween2026_tienda_items`
SET `imagen` = 'https://i.imgur.com/87wW3Xt.jpeg',
    `updated_at` = UTC_TIMESTAMP()
WHERE `slug` = 'cofre-calabaza'
  AND (`imagen` IS NULL OR `imagen` <> 'https://i.imgur.com/87wW3Xt.jpeg');
