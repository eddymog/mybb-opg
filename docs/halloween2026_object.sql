-- Objeto estacional de Halloween 2026.
-- No modifica filas existentes y puede ejecutarse de nuevo sin duplicarlo.

INSERT INTO `mybb_op_objetos` (
    `objeto_id`, `categoria`, `subcategoria`, `nombre`, `tier`,
    `imagen_id`, `imagen_avatar`, `berries`, `cantidadMaxima`,
    `dano`, `bloqueo`, `efecto`, `alcance`, `exclusivo`, `invisible`,
    `espacios`, `imagen`, `desbloquear`, `desbloqueado`, `oficio`,
    `nivel`, `tiempo_creacion`, `requisitos`, `escalado`, `editable`,
    `custom`, `descripcion`, `comerciable`, `crafteo_usuarios`, `negro`,
    `negro_berries`, `fusion_tipo`, `engastes`, `berriesCrafteo`
)
SELECT
    'CFRHLW',
    'Especiales',
    'Cofres',
    'Cofre Calabaza',
    0,
    0,
    'https://i.imgur.com/87wW3Xt.jpeg',
    2,
    1,
    '', '', '', '',
    1,
    0,
    1,
    '',
    0,
    1,
    '',
    0,
    0,
    '[No Intercambiable]',
    '',
    0,
    0,
    'Un cofre estacional cubierto por la inquietante influencia de Halloween. Contiene una recompensa de la Cosecha de Calabazas Malditas.',
    1,
    '',
    0,
    9999999,
    '',
    0,
    0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `mybb_op_objetos` WHERE `objeto_id` = 'CFRHLW'
);

UPDATE `mybb_op_objetos`
SET `imagen_avatar` = 'https://i.imgur.com/87wW3Xt.jpeg'
WHERE `objeto_id` = 'CFRHLW'
  AND `imagen_avatar` <> 'https://i.imgur.com/87wW3Xt.jpeg';
