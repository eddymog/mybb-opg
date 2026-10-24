-- Mensaje de la oferta de narrador: de 200 a 500 caracteres.
-- Ejecutar una sola vez ANTES de desplegar el cambio (OPG_AV_MAX_MENSAJE_OFERTA = 500).

ALTER TABLE `mybb_op_narradores_disponibles`
  MODIFY COLUMN `mensaje` VARCHAR(500) NOT NULL DEFAULT '';
