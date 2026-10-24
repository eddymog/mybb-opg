-- La recompensa del entrenamiento tiene decimales (p. ej. 25.44), pero la columna era INT y la redondeaba a entero.
-- Ejecutar una sola vez. Los entrenamientos en curso conservan su valor entero.

ALTER TABLE `mybb_op_entrenamientos_usuarios`
  MODIFY COLUMN `recompensa` DECIMAL(10,2) NOT NULL DEFAULT 0;
