-- =====================================================================
-- Migracion: tarea diaria de retencion y reconciliacion de notificaciones
-- Ver docs/208_DesignPlan_Notificaciones.md (apartado 10) e issue #14.
--
-- Registra la tarea `notificaciones` (inc/tasks/notificaciones.php) en el
-- planificador de MyBB, todos los dias a las 04:30. Ejecutar SOLO despues de
-- subir inc/tasks/notificaciones.php, para que el planificador no apunte a un
-- archivo ausente. Requiere docs/notificaciones_migration.sql.
--
-- Es idempotente: si la tarea ya existe no se duplica. Si la instalacion usa
-- otro prefijo, sustituir `mybb_`.
--
-- nextrun = 0 hace que la primera ejecucion ocurra en la siguiente revision
-- del planificador; despues MyBB calcula la proxima segun la hora indicada.
-- =====================================================================

INSERT INTO `mybb_tasks`
  (`title`, `description`, `file`, `minute`, `hour`, `day`, `month`, `weekday`, `nextrun`, `lastrun`, `enabled`, `logging`, `locked`)
SELECT
  'OPG: Mantenimiento de notificaciones',
  'Borra las notificaciones de mas de 90 dias, deja 500 por usuario como mucho y reconcilia los contadores.',
  'notificaciones', '30', '4', '*', '*', '*', 0, 0, 1, 1, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `mybb_tasks` WHERE `file` = 'notificaciones');

-- Rollback manual (quita la tarea del planificador; no borra notificaciones):
-- DELETE FROM `mybb_tasks` WHERE `file` = 'notificaciones';
