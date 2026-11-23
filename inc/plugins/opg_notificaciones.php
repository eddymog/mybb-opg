<?php
/**
 * OPG - Sistema global de notificaciones: plugin de instalacion.
 *
 * Crea las tablas mybb_op_notificaciones y mybb_op_notificaciones_contador
 * (docs/208_DesignPlan_Notificaciones.md apartado 4). Las definiciones deben ser
 * identicas a las de docs/notificaciones_migration.sql; docs/ no se despliega,
 * por eso el SQL se repite aqui como cadenas. La prueba
 * tests/notificaciones/plugin_instalacion_db.php compara ambas.
 *
 * Este archivo solo define funciones: no registra hooks (el del header es de la
 * issue #6) ni tiene efectos al incluirse. Desinstalar NO borra tablas ni datos.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

function opg_notificaciones_info()
{
    return array(
        'name' => 'OPG - Notificaciones',
        'description' => 'Sistema global de notificaciones del foro: crea las tablas y, mas adelante, la campana del header.',
        'website' => '',
        'author' => 'OPG',
        'authorsite' => '',
        'version' => '1.0',
        'compatibility' => '18*',
    );
}

/**
 * Sentencias CREATE TABLE IF NOT EXISTS, por nombre de tabla sin prefijo.
 * Compatibles con MySQL 5.7.
 */
function opg_notificaciones_tablas_sql()
{
    $p = TABLE_PREFIX;
    $opciones = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return array(
        'op_notificaciones' => "CREATE TABLE IF NOT EXISTS `{$p}op_notificaciones` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid`          INT UNSIGNED NOT NULL,
  `tipo`         VARCHAR(48)  NOT NULL,
  `categoria`    VARCHAR(24)  NOT NULL,
  `origen`       VARCHAR(8)   NOT NULL,
  `importancia`  VARCHAR(10)  NOT NULL DEFAULT 'normal',
  `agrupable`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `actor_uid`    INT UNSIGNED NOT NULL DEFAULT 0,
  `clave_evento` VARCHAR(128) NOT NULL,
  `url`          VARCHAR(255) NULL,
  `cantidad`     INT UNSIGNED NOT NULL DEFAULT 1,
  `datos_json`   TEXT NULL,
  `created_at`   INT UNSIGNED NOT NULL,
  `leida_en`     INT UNSIGNED NULL,
  `sent_at`      INT UNSIGNED NULL,
  `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evento_destinatario` (`uid`, `clave_evento`),
  KEY `idx_uid_fecha` (`uid`, `created_at`, `id`),
  KEY `idx_uid_categoria` (`uid`, `categoria`, `created_at`, `id`),
  KEY `idx_uid_origen` (`uid`, `origen`, `created_at`, `id`),
  KEY `idx_uid_leida` (`uid`, `leida_en`, `created_at`, `id`),
  KEY `idx_created` (`created_at`)
) {$opciones}",
        'op_notificaciones_contador' => "CREATE TABLE IF NOT EXISTS `{$p}op_notificaciones_contador` (
  `uid`         INT UNSIGNED NOT NULL,
  `sin_leer`    INT UNSIGNED NOT NULL DEFAULT 0,
  `importantes` INT UNSIGNED NOT NULL DEFAULT 0,
  `actualizado` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`uid`)
) {$opciones}",
    );
}

function opg_notificaciones_is_installed()
{
    global $db;
    foreach (array_keys(opg_notificaciones_tablas_sql()) as $tabla) {
        if (!$db->table_exists($tabla)) {
            return false;
        }
    }
    return true;
}

/** Crea las tablas que falten; las que ya existen y sus datos no se tocan. */
function opg_notificaciones_install()
{
    global $db;
    foreach (opg_notificaciones_tablas_sql() as $tabla => $sql) {
        if (!$db->table_exists($tabla)) {
            $db->write_query($sql);
        }
    }
}

/** No borra tablas ni datos: las notificaciones se conservan. */
function opg_notificaciones_uninstall()
{
}

/** Garantiza las tablas (idempotente); no modifica datos existentes. */
function opg_notificaciones_activate()
{
    opg_notificaciones_install();
}

function opg_notificaciones_deactivate()
{
}
