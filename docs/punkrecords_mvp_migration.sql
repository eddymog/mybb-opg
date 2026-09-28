-- Punk Records (MVP): tabla de registro de preguntas.
-- Ver docs/200_DesignPlan_Asistente_MVP.md, sección 6.4.

CREATE TABLE mybb_op_punkrecords_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uid INT UNSIGNED NOT NULL,
  pregunta VARCHAR(500) NOT NULL,
  resultado VARCHAR(12) NOT NULL,   -- ok, sin_datos, limite, error
  ms INT UNSIGNED NOT NULL DEFAULT 0,
  creado_en INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY uid_fecha (uid, creado_en),
  KEY fecha (creado_en)
) ENGINE=InnoDB;
