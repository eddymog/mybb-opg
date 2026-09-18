<?php
/**
 * OPG - Banner rotativo
 *
 * Elige al azar un banner de images/op/banners/ (serie
 * Banner<N>_One_Piece_Gaiden_Foro_Rol.jpg) y lo mantiene durante
 * OP_BANNER_ROTATIVO_INTERVALO segundos, guardado en el datacache de MyBB.
 * header.html lo usa como banner por defecto de #logo vía
 * $op_banner_rotativo. Para añadir un banner basta con subirlo por FTP con
 * el nombre correcto: entra en la rotación en el siguiente ciclo.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

define('OP_BANNER_ROTATIVO_INTERVALO', 300);
define('OP_BANNER_ROTATIVO_DIR',       'images/op/banners/');
define('OP_BANNER_ROTATIVO_PATRON',    '/^Banner(\d+)_One_Piece_Gaiden_Foro_Rol\.jpg$/');
define('OP_BANNER_ROTATIVO_CACHE',     'op_banner_rotativo');
// Banner fijado desde op/staff/banners.php (nombre de archivo). Va aparte de
// OP_BANNER_ROTATIVO_CACHE para que la rotación nunca lo pise al reescribir la suya.
define('OP_BANNER_ROTATIVO_FIJO',      'op_banner_rotativo_fijo');

$plugins->add_hook('global_intermediate', 'op_banner_rotativo_run');

function op_banner_rotativo_info()
{
    return array(
        'name'          => 'OPG - Banner rotativo',
        'description'   => 'Rota el banner del header entre los Banner<N>_One_Piece_Gaiden_Foro_Rol.jpg cada 5 minutos (cacheado), o muestra siempre el banner fijado desde op/staff/banners.php.',
        'website'       => '',
        'author'        => 'OPG',
        'authorsite'    => '',
        'version'       => '1.0',
        'compatibility' => '18*',
    );
}

function op_banner_rotativo_activate() {}

function op_banner_rotativo_deactivate()
{
    global $cache;
    $cache->delete(OP_BANNER_ROTATIVO_CACHE);
    $cache->delete(OP_BANNER_ROTATIVO_FIJO);
}

/**
 * Nombres de archivo de la serie Banner<N>_..., ordenados por número.
 */
function op_banner_rotativo_lista()
{
    $archivos = @scandir(MYBB_ROOT . OP_BANNER_ROTATIVO_DIR);
    if (!$archivos) {
        return array();
    }

    $banners = array();
    foreach ($archivos as $archivo) {
        if (preg_match(OP_BANNER_ROTATIVO_PATRON, $archivo, $m)) {
            $banners[(int)$m[1]] = $archivo;
        }
    }
    ksort($banners);

    return array_values($banners);
}

function op_banner_rotativo_run()
{
    global $cache, $op_banner_rotativo;

    $op_banner_rotativo = '';

    // Un banner fijado manda sobre la rotación mientras siga activo (en la raíz).
    $fijo = op_banner_rotativo_fijo();
    if ($fijo !== '') {
        $op_banner_rotativo = op_banner_rotativo_url($fijo);
        return;
    }

    $data = $cache->read(OP_BANNER_ROTATIVO_CACHE);

    $vigente = is_array($data)
        && !empty($data['actual'])
        && TIME_NOW < (int)$data['expira']
        && file_exists(MYBB_ROOT . OP_BANNER_ROTATIVO_DIR . $data['actual']);

    if (!$vigente) {
        $banners = op_banner_rotativo_lista();
        if (empty($banners)) {
            return;
        }

        $anterior = is_array($data) ? $data['actual'] : '';
        $candidatos = count($banners) > 1 ? array_values(array_diff($banners, array($anterior))) : $banners;

        $data = array(
            'actual' => $candidatos[array_rand($candidatos)],
            'expira' => TIME_NOW + OP_BANNER_ROTATIVO_INTERVALO,
            'total'  => count($banners),
        );
        $cache->update(OP_BANNER_ROTATIVO_CACHE, $data);
    }

    $op_banner_rotativo = op_banner_rotativo_url($data['actual']);
}

/**
 * Nombre de archivo del banner fijado, o '' si no hay ninguno o ya no está
 * activo (se desactivó o se borró por FTP): en ese caso vuelve la rotación.
 */
function op_banner_rotativo_fijo()
{
    global $cache;

    $fijo = $cache->read(OP_BANNER_ROTATIVO_FIJO);
    if (!is_string($fijo) || !preg_match(OP_BANNER_ROTATIVO_PATRON, $fijo)
        || !file_exists(MYBB_ROOT . OP_BANNER_ROTATIVO_DIR . $fijo)) {
        return '';
    }
    return $fijo;
}

function op_banner_rotativo_url($archivo)
{
    // ?v= fuerza a los navegadores a recargar un banner sobrescrito (mismo nombre, p. ej. por FTP).
    return '/' . OP_BANNER_ROTATIVO_DIR . $archivo
        . '?v=' . @filemtime(MYBB_ROOT . OP_BANNER_ROTATIVO_DIR . $archivo);
}
