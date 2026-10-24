-- Halloween 2026: el evento dura hasta el 16 de noviembre (inclusive).
-- earn_end_utc = fin de la obtencion de Calabazas: 17 nov a las 06:00 de Madrid (05:00 UTC), igual que el cambio de dia.
-- Ejecutar una sola vez. Las votaciones (17-22 nov) y los ganadores (23 nov) solo se muestran en el calendario de la pagina.

UPDATE `mybb_op_halloween2026_config`
   SET `earn_end_utc` = '2026-11-17 05:00:00'
 WHERE `id` = 1;
