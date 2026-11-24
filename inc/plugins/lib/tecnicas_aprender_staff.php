<?php
/**
 * Lógica de op/staff/tecnicas_aprender.php (añadir o remover técnicas para aprender), separada
 * de la página para poder probarla sin MyBB (issue #30).
 *
 * Seguridad que garantiza:
 * - No escribe nada si la clave CSRF no es válida (la página la comprueba con verify_post_check).
 * - Todo valor del formulario llega escapado a las consultas.
 * - El staff que queda en la auditoría es el uid de la sesión, nunca un campo del formulario.
 *
 * La lógica de negocio es la de antes: mismas tablas, mismos textos de log y mismo cálculo de
 * lo que se avisa al dueño (issue #21).
 */

if (!defined('OPG_TEC_APRENDER_ACCIONES')) {
    define('OPG_TEC_APRENDER_ACCIONES', 'Añadir,Remover');
}

/**
 * Normaliza la entrada del formulario. Devuelve null si falta algún dato o si la acción no es
 * una de la lista fija (en ese caso la página no hace nada, igual que antes).
 * El campo "staff" del formulario se ignora a propósito.
 */
function opg_tec_aprender_entrada(array $post)
{
    $ficha_id = isset($post['ficha_id']) && is_string($post['ficha_id']) ? trim($post['ficha_id']) : '';
    $accion   = isset($post['accion']) && is_string($post['accion']) ? $post['accion'] : '';
    $tecnicas = isset($post['tecnicas']) && is_string($post['tecnicas']) ? $post['tecnicas'] : '';
    $razon    = isset($post['razon']) && is_string($post['razon']) ? $post['razon'] : '';

    if ($ficha_id === '' || trim($tecnicas) === '' || trim($razon) === '') { return null; }
    if (!in_array($accion, explode(',', OPG_TEC_APRENDER_ACCIONES), true)) { return null; }

    return array('ficha_id' => $ficha_id, 'accion' => $accion, 'tecnicas' => $tecnicas, 'razon' => $razon);
}

/**
 * Ejecuta el cambio. $db es el objeto de base de datos de MyBB (o uno falso en las pruebas) con
 * query, fetch_array, escape_string, affected_rows e insert_id.
 *
 * Devuelve array('estado' => 'sin_accion' | 'token_invalido' | 'hecho', 'f_var', 'log',
 * 'avisar_ids', 'avisar_huellas').
 */
function opg_tec_aprender_procesar($entrada, $token_ok, $staff_uid, $db)
{
    $res = array('estado' => 'sin_accion', 'f_var' => null, 'log' => '', 'avisar_ids' => array(), 'avisar_huellas' => array());
    if (!is_array($entrada)) { return $res; }
    if ($token_ok !== true) { $res['estado'] = 'token_invalido'; return $res; }

    $ficha_id = $entrada['ficha_id'];
    $accion = $entrada['accion'];
    $ficha_id_esc = $db->escape_string($ficha_id);
    $razon_esc = $db->escape_string($entrada['razon']);
    $staff = (int) $staff_uid;

    $f_var = null;
    $query_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='{$ficha_id_esc}'");
    while ($f = $db->fetch_array($query_ficha)) {
        $f_var = $f;
    }
    $nombre = is_array($f_var) && isset($f_var['nombre']) ? $f_var['nombre'] : '';

    // split comma delimited string to an array
    $tecnicas_array = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', trim($entrada['tecnicas']));
    $log = "Cambios de técnicas para entrenar para ficha de UID: $ficha_id (" . $nombre . "):\n";
    foreach ($tecnicas_array as $tec) {
        $clean_tec = trim($tec);
        if ($clean_tec == "") { continue; }
        $clean_tec_esc = $db->escape_string($clean_tec);

        if ($accion === 'Añadir') {
            $db->query("INSERT INTO `mybb_op_tec_para_aprender` (`tid`, `uid`) VALUES ('{$clean_tec_esc}', '{$ficha_id_esc}')");
            if ((int) $db->affected_rows() > 0) { $res['avisar_ids'][] = $clean_tec; $res['avisar_huellas'][] = 'i' . (int) $db->insert_id(); }
        } else { // Remover
            $db->query("DELETE FROM `mybb_op_tec_para_aprender` WHERE tid='{$clean_tec_esc}' AND uid='{$ficha_id_esc}'");
            $borradas = (int) $db->affected_rows();
            if ($borradas > 0) { $res['avisar_ids'][] = $clean_tec; $res['avisar_huellas'][] = 'd' . $clean_tec . ':' . $borradas; }
        }
        $log .= "-- $accion técnica ID: $tec\n";
        $log_short = "Cambios de técnicas para aprender para ficha de UID: $ficha_id (" . $nombre . "):\n" . "-- $accion técnica ID: $tec\n";

        $db->query("INSERT INTO `mybb_op_audit_consola_tec_mod` (`staff`, `razon`, `log`) VALUES "
            . "('{$staff}', '{$razon_esc}', '" . $db->escape_string($log_short) . "')");
    }

    $res['estado'] = 'hecho';
    $res['f_var'] = $f_var;
    $res['log'] = $log;
    return $res;
}

/**
 * Escapa un texto para ponerlo dentro de un literal de JavaScript entre comillas invertidas
 * (la plantilla muestra el log con alert(`...`)). Neutraliza \, `, ${ y el cierre de </script>.
 */
function opg_tec_aprender_js_literal($texto)
{
    return strtr((string) $texto, array('\\' => '\\\\', '`' => '\\`', '$' => '\\$', '<' => '\\x3C'));
}

/**
 * Añade la clave CSRF como campo oculto a cada <form method="post"> del HTML, para no depender de
 * que la plantilla la incluya. Si el HTML ya trae un my_post_key, no lo toca.
 */
function opg_tec_aprender_inyectar_post_key($html, $post_key)
{
    if (stripos($html, 'name="my_post_key"') !== false || stripos($html, "name='my_post_key'") !== false) { return $html; }
    $campo = '<input type="hidden" name="my_post_key" value="' . htmlspecialchars((string) $post_key, ENT_QUOTES, 'UTF-8') . '">';
    return preg_replace_callback('#<form\b[^>]*\bmethod\s*=\s*["\']?post\b[^>]*>#i', function ($m) use ($campo) { return $m[0] . $campo; }, $html);
}
