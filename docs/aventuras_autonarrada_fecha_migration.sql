-- Año y estación de la solicitud: las crónicas autonarradas pueden ser de años anteriores.
-- NULL = Verano 725 (el valor de todas las solicitudes existentes y de las aventuras normales).
-- Ejecutar una sola vez ANTES de desplegar el cambio.

ALTER TABLE `mybb_op_peticionAventuras`
  ADD COLUMN `ano` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `dia`,
  ADD COLUMN `estacion` VARCHAR(10) NULL DEFAULT NULL AFTER `ano`;
