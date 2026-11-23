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
 * Campana del header (issue #6, 208 apartado 7): el hook global_intermediate
 * construye $op_notificaciones_header con la plantilla op_notificaciones_header.
 * Los invitados no consultan nada; los usuarios, una sola consulta por clave
 * primaria del contador (opg_notif_contar, cacheada por peticion). La plantilla
 * se precarga con las globales (hook global_start), sin consulta propia.
 *
 * Al incluirse solo registra esos dos hooks; la libreria se carga dentro del
 * hook del header. Desinstalar NO borra tablas ni datos.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

$plugins->add_hook('global_start', 'opg_notificaciones_hook_precargar');
$plugins->add_hook('global_intermediate', 'opg_notificaciones_hook_header');

/** Plantilla de la campana y archivo de referencia en templates/One_Piece_Gaiden_Templates/. */
function opg_notificaciones_plantillas()
{
    return array('op_notificaciones_header' => 'op_notificaciones_header.html');
}

function opg_notificaciones_info()
{
    return array(
        'name' => 'OPG - Notificaciones',
        'description' => 'Sistema global de notificaciones del foro: crea las tablas y muestra la campana del header.',
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
    global $db;
    $p = $db->table_prefix;
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

/**
 * Garantiza las tablas, la plantilla de la campana y su marcador en el header
 * (idempotente; no modifica datos existentes). MyBB no llama a _install si las
 * tablas ya existian, por eso la plantilla y el marcador se instalan aqui.
 */
function opg_notificaciones_activate()
{
    opg_notificaciones_install();
    opg_notificaciones_instalar_plantillas();
    opg_notificaciones_insertar_marcador_header();
}

/** El marcador y la plantilla quedan inertes (el hook ya no corre), para convivir con template_sync. */
function opg_notificaciones_deactivate()
{
}

// ── Plantillas ──────────────────────────────────────────────────────────────

/** sid del juego de plantillas One_Piece_Gaiden_Templates (0 si no existe), como op_bitacora. */
function opg_notificaciones_sid_tema()
{
    global $db;
    $query = $db->simple_select('templatesets', 'sid,title');
    while ($set = $db->fetch_array($query)) {
        if (preg_replace('/[^a-zA-Z0-9_\-]/', '_', $set['title']) === 'One_Piece_Gaiden_Templates') {
            return (int)$set['sid'];
        }
    }
    return 0;
}

/** Copia las plantillas de referencia a la base (maestra -2 y juego del tema), creando o actualizando. */
function opg_notificaciones_instalar_plantillas()
{
    global $db;
    $sids = array(-2);
    $tema = opg_notificaciones_sid_tema();
    if ($tema > 0) {
        $sids[] = $tema;
    }
    foreach (opg_notificaciones_plantillas() as $titulo => $archivo) {
        $ruta = MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/' . $archivo;
        $contenido = is_file($ruta) ? rtrim((string)file_get_contents($ruta)) : '';
        if ($contenido === '') {
            continue;
        }
        $titulo_sql = $db->escape_string($titulo);
        foreach ($sids as $sid) {
            $query = $db->simple_select('templates', 'tid', "title='{$titulo_sql}' AND sid='" . (int)$sid . "'", array('limit' => 1));
            $tid = (int)$db->fetch_field($query, 'tid');
            $datos = array(
                'template' => $db->escape_string($contenido),
                'version' => '1800',
                'status' => '',
                'dateline' => TIME_NOW,
            );
            if ($tid > 0) {
                $db->update_query('templates', $datos, "tid='{$tid}'");
            } else {
                $datos['title'] = $titulo_sql;
                $datos['sid'] = (int)$sid;
                $db->insert_query('templates', $datos);
            }
        }
    }
}

/**
 * Pone la campana junto al bloque de la Bitacora: envuelve {$op_temas_header}
 * en la fila .op-barra-usuario con {$op_notificaciones_header}. Pura e
 * idempotente: si el marcador ya esta, o falta el de la Bitacora, no cambia nada.
 */
function opg_notificaciones_header_con_marcador($plantilla)
{
    $plantilla = (string)$plantilla;
    if (strpos($plantilla, '{$op_notificaciones_header}') !== false || strpos($plantilla, '{$op_temas_header}') === false) {
        return $plantilla;
    }
    $pos = strpos($plantilla, '{$op_temas_header}');
    return substr($plantilla, 0, $pos)
        . '<div class="op-barra-usuario">{$op_temas_header}{$op_notificaciones_header}</div>'
        . substr($plantilla, $pos + strlen('{$op_temas_header}'));
}

/** Aplica opg_notificaciones_header_con_marcador a todas las plantillas header de la base. */
function opg_notificaciones_insertar_marcador_header()
{
    global $db;
    $query = $db->simple_select('templates', 'tid,template', "title='header'");
    while ($fila = $db->fetch_array($query)) {
        $nueva = opg_notificaciones_header_con_marcador($fila['template']);
        if ($nueva !== $fila['template']) {
            $db->update_query('templates', array(
                'template' => $db->escape_string($nueva),
                'dateline' => TIME_NOW,
            ), "tid='" . (int)$fila['tid'] . "'");
        }
    }
}

// ── Campana del header ──────────────────────────────────────────────────────

/** Numero de la insignia: '' en cero (insignia oculta), el numero hasta 99 y '99+' desde 100. */
function opg_notificaciones_formato_numero($n)
{
    $n = (int)$n;
    if ($n <= 0) {
        return '';
    }
    return $n >= 100 ? '99+' : (string)$n;
}

/**
 * Variables de la plantilla de la campana a partir del contador. Solo numeros:
 * el header no muestra textos de notificaciones (requisito 23). La etiqueta
 * accesible lleva el numero exacto (requisito 22) y, si hay importantes, lo dice
 * tambien con texto, no solo con color.
 */
function opg_notificaciones_campana_variables($sin_leer, $importantes)
{
    $sin_leer = max(0, (int)$sin_leer);
    $importantes = min(max(0, (int)$importantes), $sin_leer);
    $numero = opg_notificaciones_formato_numero($sin_leer);

    if ($sin_leer === 0) {
        $etiqueta = 'Notificaciones: no tienes avisos sin leer';
    } else {
        $etiqueta = 'Notificaciones: ' . $sin_leer . ' sin leer';
        if ($importantes > 0) {
            $etiqueta .= ', ' . $importantes . ($importantes === 1 ? ' importante' : ' importantes');
        }
    }

    return array(
        'clase' => $importantes > 0 ? ' op-notif-campana--importante' : '',
        'insignia' => $numero === '' ? '' : '<span class="op-notif-campana__insignia" aria-hidden="true">' . $numero . '</span>',
        'etiqueta' => htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'),
    );
}

/** HTML de la campana con la plantilla op_notificaciones_header ('' si la plantilla no esta en la base). */
function opg_notificaciones_campana_html($sin_leer, $importantes)
{
    global $templates, $mybb;
    if (!is_object($templates)) {
        return '';
    }
    $plantilla = $templates->get('op_notificaciones_header');
    if (trim((string)$plantilla) === '') {
        return '';
    }
    $v = opg_notificaciones_campana_variables($sin_leer, $importantes);
    $op_notif_campana_clase = $v['clase'];
    $op_notif_campana_insignia = $v['insignia'];
    $op_notif_campana_etiqueta = $v['etiqueta'];
    $html = '';
    eval('$html = "' . $plantilla . '";');
    return $html;
}

/** global_start: precarga la plantilla con las globales, para no gastar una consulta propia. */
function opg_notificaciones_hook_precargar()
{
    global $templatelist;
    $templatelist = (isset($templatelist) && $templatelist !== '' ? $templatelist . ',' : '') . 'op_notificaciones_header';
}

/** global_intermediate: $op_notificaciones_header para header.html. Invitados: vacio y sin consultas. */
function opg_notificaciones_hook_header()
{
    global $mybb, $op_notificaciones_header;
    $op_notificaciones_header = '';
    $uid = isset($mybb->user['uid']) ? (int)$mybb->user['uid'] : 0;
    if ($uid <= 0) {
        return;
    }
    require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones.php';
    $contador = opg_notif_contar($uid);
    $op_notificaciones_header = opg_notificaciones_campana_html($contador['sin_leer'], $contador['importantes']);
}
