-- =====================================================================
-- Migracion: rol de usuario en un tema (personaje / narrador)
-- Ver docs/100_Requirements_Cronologia.md (5.8) y docs/200_DesignPlan_Cronologia.md (3.4, 6.5).
--
-- op/cronologia.php crea esta tabla automaticamente al cargarse
-- (cron_asegurar_tabla_roles). Este archivo permite crearla manualmente
-- desde phpMyAdmin o la CLI. Si la instalacion usa otro prefijo,
-- sustituir `mybb_`.
--
-- Es una tabla global, sin prefijo `cron_`: no pertenece solo a la
-- cronologia. Cualquier otra herramienta (por ejemplo, recompensas) puede
-- leerla o escribirla igual. Un mismo tid puede tener varias filas con
-- rol = 'narrador' (co-narracion, o narrador que cambia a mitad de tema).
-- Sin fila para un (uid, tid), el rol efectivo es 'personaje'.
--
-- No usa foreign keys porque las tablas core de esta instalacion pueden
-- utilizar MyISAM. La propiedad y la integridad se validan en PHP.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `mybb_op_thread_roles` (
  `uid` int unsigned NOT NULL,
  `tid` int unsigned NOT NULL,
  `rol` enum('personaje','narrador') NOT NULL DEFAULT 'personaje',
  `dateline` int unsigned NOT NULL,
  PRIMARY KEY (`uid`, `tid`),
  KEY `idx_tid_rol` (`tid`, `rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Rollback manual, solo si se desea borrar todos los roles marcados:
-- DROP TABLE IF EXISTS `mybb_op_thread_roles`;
