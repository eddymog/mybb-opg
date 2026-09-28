-- Punk Records (RAG): tablas de MySQL del foro.
-- Registro, caché y cuota. Los fragmentos y vectores viven en Supabase
-- (docs/punkrecords_supabase.sql), no aquí.
-- Ver docs/200_DesignPlan_Asistente.md, sección 4.
--
-- Este sistema reemplaza al MVP (docs/punkrecords_mvp_migration.sql), que ya
-- creó mybb_op_punkrecords_log con menos columnas. En vez de recrearla, se
-- amplía con ALTER TABLE para no perder el registro de preguntas ya hecho.

-- 'resultado' ya admitía VARCHAR(12), donde cabe el nuevo valor 'cache' sin
-- cambiar el tipo de columna.
ALTER TABLE mybb_op_punkrecords_log
  ADD COLUMN fuentes VARCHAR(255) NOT NULL DEFAULT '' AFTER resultado;  -- IDs de fragmento citados, separados por coma

-- Username al lado del uid: mismo largo que mybb_users.username (VARCHAR(100)).
-- Se guarda tal cual al momento de preguntar (no es un JOIN en vivo) — si el
-- jugador cambia de nombre después, el registro viejo se queda con el
-- nombre de ese momento, útil justamente para auditar quién preguntó qué.
ALTER TABLE mybb_op_punkrecords_log
  ADD COLUMN username VARCHAR(100) NOT NULL DEFAULT '' AFTER uid;

CREATE TABLE mybb_op_punkrecords_cache (
  hash CHAR(40) NOT NULL,  -- SHA-1 de la pregunta normalizada
  respuesta_html MEDIUMTEXT NOT NULL,
  fuentes VARCHAR(255) NOT NULL DEFAULT '',
  creado_en INT UNSIGNED NOT NULL,
  PRIMARY KEY (hash),
  KEY fecha (creado_en)
) ENGINE=InnoDB;

-- Se sacó el caché por completo: el costo real de Gemini resultó
-- insignificante (ver conversación de diseño — centavos/día incluso con
-- mucho volumen), y la clave de caché no incluía la facción del jugador,
-- así que una respuesta cacheada podía mostrarse con el operador/tono de
-- OTRA facción (ej. la respuesta de Corsario Croco servida tal cual a un
-- Marino) — rompía la inmersión del sistema de personalidad. Correr esto
-- para borrar la tabla que ya no se usa.
DROP TABLE IF EXISTS mybb_op_punkrecords_cache;

CREATE TABLE mybb_op_punkrecords_cuota (
  dia DATE NOT NULL,
  llamadas_gen INT UNSIGNED NOT NULL DEFAULT 0,
  llamadas_emb INT UNSIGNED NOT NULL DEFAULT 0,
  llamadas_staff INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (dia)
) ENGINE=InnoDB;
