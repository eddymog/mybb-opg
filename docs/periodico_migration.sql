CREATE TABLE IF NOT EXISTS mybb_op_periodicos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(100) NOT NULL,
  numero INT UNSIGNED NOT NULL,
  titulo VARCHAR(255) NOT NULL,
  subtitulo VARCHAR(500) NOT NULL DEFAULT '',
  portada VARCHAR(500) NOT NULL DEFAULT '',
  tema VARCHAR(32) NOT NULL DEFAULT 'clasico',
  estado ENUM('borrador','publicado','archivado') NOT NULL DEFAULT 'borrador',
  fecha_publicacion DATETIME NULL,
  autor_uid INT UNSIGNED NOT NULL,
  firma_visible VARCHAR(120) NOT NULL DEFAULT 'Big News Morgans',
  fecha_creacion DATETIME NOT NULL,
  fecha_actualizacion DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug),
  UNIQUE KEY numero (numero),
  KEY estado_fecha (estado, fecha_publicacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mybb_op_periodico_paginas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  periodico_id INT UNSIGNED NOT NULL,
  orden SMALLINT UNSIGNED NOT NULL,
  titulo VARCHAR(255) NOT NULL DEFAULT '',
  tema VARCHAR(32) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY periodico_orden (periodico_id, orden),
  KEY periodico_id (periodico_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mybb_op_periodico_bloques (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pagina_id INT UNSIGNED NOT NULL,
  orden SMALLINT UNSIGNED NOT NULL,
  tipo VARCHAR(32) NOT NULL,
  contenido_json MEDIUMTEXT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY pagina_orden (pagina_id, orden),
  KEY pagina_id (pagina_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mybb_op_periodico_auditoria (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  periodico_id INT UNSIGNED NOT NULL,
  pagina_id INT UNSIGNED NULL,
  bloque_id INT UNSIGNED NULL,
  uid INT UNSIGNED NOT NULL,
  accion VARCHAR(32) NOT NULL,
  detalle_json MEDIUMTEXT NOT NULL,
  fecha DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY periodico_fecha (periodico_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
