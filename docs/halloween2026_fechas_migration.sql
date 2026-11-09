-- Halloween 2026: el evento dura hasta el 17 de noviembre (inclusive).
-- earn_end_utc = fin de la obtencion de Calabazas: 18 nov a las 06:00 de Madrid (05:00 UTC), igual que el cambio de dia.
-- claims_end_utc y store_end_utc = fin del 17 nov en Madrid (23:00 UTC), cuando cierran reclamos y tienda.
-- Ejecutar una sola vez (se puede repetir sin problema). Las votaciones (18-23 nov) y los ganadores (24 nov) solo se muestran en el calendario de la pagina.

UPDATE `mybb_op_halloween2026_config`
   SET `earn_end_utc` = '2026-11-18 05:00:00',
       `claims_end_utc` = '2026-11-17 23:00:00',
       `store_end_utc` = '2026-11-17 23:00:00'
 WHERE `id` = 1;
