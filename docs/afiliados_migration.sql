-- =====================================================================
-- Migración: sistema de Afiliados (One Piece Gaiden)
-- Ver docs/afiliados_implementacion_opg.md §2
--
-- Ejecutar UNA vez en la base del foro (phpMyAdmin o CLI). Prefijo mybb_.
-- No usa foreign keys (convención OPG: relaciones por código).
-- =====================================================================

CREATE TABLE IF NOT EXISTS `mybb_op_afiliados` (
  `id` int(11) NOT NULL,
  `tipo` enum('hermano','grande','pequeno') COLLATE utf8_unicode_ci NOT NULL,
  `nombre` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `imagen` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `agregado_por` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `mybb_op_afiliados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tipo_orden` (`tipo`, `orden`);

ALTER TABLE `mybb_op_afiliados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- Rate-limit del formulario público (no exige login).
CREATE TABLE IF NOT EXISTS `mybb_op_afiliados_rate_limit` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `mybb_op_afiliados_rate_limit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_tiempo` (`ip`, `tiempo`);

ALTER TABLE `mybb_op_afiliados_rate_limit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
