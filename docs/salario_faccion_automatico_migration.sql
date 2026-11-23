-- Salario semanal automático de facción.
-- Idempotente: puede ejecutarse más de una vez.

CREATE TABLE IF NOT EXISTS `mybb_op_salario_faccion_repartos` (
    `semana_inicio` date NOT NULL,
    `origen` varchar(16) NOT NULL,
    `ejecutor_uid` int(11) NOT NULL DEFAULT 0,
    `ejecutor` varchar(100) NOT NULL,
    `estado` varchar(16) NOT NULL DEFAULT 'procesando',
    `pagados` int(11) NOT NULL DEFAULT 0,
    `total` bigint(20) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completado_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`semana_inicio`),
    KEY `estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `mybb_op_salario_faccion_pagos` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `semana_inicio` date NOT NULL,
    `fid` int(11) NOT NULL,
    `faccion` varchar(30) NOT NULL,
    `rango` varchar(255) NOT NULL,
    `monto` int(11) NOT NULL,
    `saldo_antes` int(11) NOT NULL,
    `saldo_despues` int(11) NOT NULL,
    `origen` varchar(16) NOT NULL,
    `ejecutor_uid` int(11) NOT NULL DEFAULT 0,
    `ejecutor` varchar(100) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `semana_fid` (`semana_inicio`,`fid`),
    KEY `fid` (`fid`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `mybb_tasks`
    (`title`,`description`,`file`,`minute`,`hour`,`day`,`month`,`weekday`,`nextrun`,`lastrun`,`enabled`,`logging`,`locked`)
SELECT
    'OPG: Salario semanal de facción',
    'Entrega los salarios cada lunes a las 06:00 de Madrid.',
    'op_salario_faccion','0','*','*','*','*',0,0,1,1,0
WHERE NOT EXISTS (SELECT 1 FROM `mybb_tasks` WHERE `file`='op_salario_faccion');

UPDATE `mybb_tasks`
SET `title`='OPG: Salario semanal de facción',
    `description`='Entrega los salarios cada lunes a las 06:00 de Madrid.',
    `minute`='0', `hour`='*', `day`='*', `month`='*', `weekday`='*',
    `enabled`=1, `logging`=1
WHERE `file`='op_salario_faccion';

-- Reversión no destructiva:
-- UPDATE `mybb_tasks` SET `enabled`=0 WHERE `file`='op_salario_faccion';
-- Las tablas de historial se conservan para no perder auditoría económica.
