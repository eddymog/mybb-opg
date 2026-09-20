-- =====================================================================
-- Migración: Sistema de Tripulaciones (One Piece Gaiden) — Página 2
-- Ver docs/200_Design_Tripulacion.md
--
-- Ejecutar UNA vez en la base del foro (phpMyAdmin o CLI). Prefijo mybb_.
-- No usa foreign keys (convención OPG: relaciones por código).
--
-- Solo las tablas que necesita la Página 2 (Solicitud de Creación):
-- mybb_op_tripulaciones_solicitudes (donde vive la solicitud en sí) y
-- mybb_op_tripulaciones (todavía sin poblar por esta página — hace falta
-- para la validación de nombre único contra tripulaciones ya existentes).
-- Las demás tablas del diseño (miembros, baúl, audit, columna en barcos)
-- se agregan cuando se implementen las páginas 3 y 4.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `mybb_op_tripulaciones` (
  `id` int(11) NOT NULL,
  `nombre` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `faccion` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `logo` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `lider_fid` int(11) NOT NULL,
  `mensaje_publico` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `buscando_miembros` tinyint(1) NOT NULL DEFAULT '0',
  `estado` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0=Activa, 1=Inactiva, 2=Disuelta',
  `ideales` text COLLATE utf8_unicode_ci,
  `jerarquia_texto` text COLLATE utf8_unicode_ci,
  `otros` text COLLATE utf8_unicode_ci,
  `link_formacion` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `berries` int(11) NOT NULL DEFAULT '0',
  `nika` int(11) NOT NULL DEFAULT '0',
  `kuro` int(11) NOT NULL DEFAULT '0',
  `created_at` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `mybb_op_tripulaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD KEY `faccion` (`faccion`),
  ADD KEY `estado` (`estado`);

ALTER TABLE `mybb_op_tripulaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

CREATE TABLE IF NOT EXISTS `mybb_op_tripulaciones_solicitudes` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `nombre_propuesto` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `faccion` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `bandera` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `link` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `usuarios_iniciales_json` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `lider_fid` int(11) NOT NULL,
  `jerarquia_texto` text COLLATE utf8_unicode_ci,
  `ideales` text COLLATE utf8_unicode_ci,
  `detalles` text COLLATE utf8_unicode_ci,
  `detalles_staff` text COLLATE utf8_unicode_ci,
  `estado` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0=pendiente, 1=aprobada, 2=rechazada',
  `comentario_staff` text COLLATE utf8_unicode_ci,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `resuelto_at` int(11) DEFAULT NULL,
  `resuelto_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `mybb_op_tripulaciones_solicitudes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uid_estado` (`uid`, `estado`),
  ADD KEY `estado` (`estado`);

ALTER TABLE `mybb_op_tripulaciones_solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- =====================================================================
-- Actualización: la Página 2 sacó el campo de bandera (se sube más
-- adelante, no en la solicitud) y separó el antiguo "Otros" en dos campos:
-- "Detalles" (información general de la tripulación, visible para el
-- creador) y un "Otros" nuevo pensado para el staff (integrantes
-- iniciales además del capitán, diferencias de facción, etc.).
--
-- Si la tabla ya se creó con el CREATE TABLE de arriba (columna `otros`
-- en vez de `detalles`/`detalles_staff`), correr esto una vez para
-- ponerla al día. La columna `bandera` se deja como está — sigue
-- existiendo por si se usa más adelante, solo que esta página ya no
-- escribe en ella.
-- =====================================================================

ALTER TABLE `mybb_op_tripulaciones_solicitudes`
  CHANGE `otros` `detalles` text COLLATE utf8_unicode_ci;

ALTER TABLE `mybb_op_tripulaciones_solicitudes`
  ADD `detalles_staff` text COLLATE utf8_unicode_ci AFTER `detalles`;

-- =====================================================================
-- Página 3 (Página de la Tripulación): roster de miembros.
--
-- Alcance de esta tanda: identidad (nombre/logo) + miembros + reputación
-- total. El baúl, los barcos y el historial de auditoría del diseño
-- original (docs/200_Design_Tripulacion.md) todavía no se implementan —
-- se agregan cuando se construyan esas partes.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `mybb_op_tripulaciones_miembros` (
  `id` int(11) NOT NULL,
  `tripulacion_id` int(11) NOT NULL,
  `fid` int(11) NOT NULL,
  `rango` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0=Miembro, 1=Vicecapitan, 2=Capitan',
  `rol_decorativo` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `fecha_ingreso` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `mybb_op_tripulaciones_miembros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fid` (`fid`),
  ADD KEY `tripulacion_id` (`tripulacion_id`);

ALTER TABLE `mybb_op_tripulaciones_miembros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
