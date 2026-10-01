<?php
/**
 * Staff - Gestión de cofres (loot tables de mybb_op_cofres)
 *
 * Ver docs/100_Requirements_Cofres.md, docs/200_DesignPlan_Cofres.md y
 * docs/300_ImplementationPlan_Cofres.md (Tareas 3-6).
 *
 * Un cofre ES un ítem más de mybb_op_objetos (el mismo objeto_id/cofre_id
 * que el jugador tiene en inventario) — esta página no crea ítems nuevos,
 * crea/edita la loot table (filas de mybb_op_cofres) de un ítem que ya
 * existe en el catálogo.
 *
 * Sin `?cofre_id`: listado de todos los cofres. Con `?cofre_id=X`: editor
 * de las filas de recompensa de ese cofre. Mismo patrón de un archivo con
 * dos vistas que ya usa tecnicas_modificar.php.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'cofres_gestion.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_admin($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('COFRES_MSG_COOKIE', 'cofres_gestion_msg');

$COFRES_TIPOS = array('Objeto', 'Custom', 'Jackpot');

// Un solo token para toda la petición: lo necesitan tanto las respuestas
// parciales de htmx (el botón "Eliminar" de cada fila lo lleva en hx-vals)
// como el formulario normal de la vista GET más abajo.
$post_key = generate_post_check();

function cofres_url($cofre_id = '')
{
    return 'cofres_gestion.php' . ($cofre_id !== '' ? '?cofre_id=' . rawurlencode($cofre_id) : '');
}

function cofres_mensaje($tipo, $texto)
{
    my_setcookie(COFRES_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $tipo,
        'texto' => $texto,
    ))), 15, true);
}

// ── Typeahead: busca ítems del catálogo ──────────────────────────────────────
// Reusado por el selector de ítem de una fila tipo=Objeto y por el selector
// de alta de cofre nuevo (excluyendo ahí los que ya tienen loot table).
if ($mybb->get_input('buscar_objeto', MyBB::INPUT_STRING) !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($mybb->get_input('buscar_objeto', MyBB::INPUT_STRING));
    $like = $db->escape_string(addcslashes($q, '%_'));
    $excluir_cofres = $mybb->get_input('excluir_cofres', MyBB::INPUT_INT) == 1;

    $sql = "SELECT objeto_id, nombre FROM `mybb_op_objetos` WHERE (objeto_id LIKE '%{$like}%' OR nombre LIKE '%{$like}%')";
    if ($excluir_cofres) {
        // COLLATE: mybb_op_objetos.objeto_id es utf8_unicode_ci (explícito en el
        // esquema) y mybb_op_cofres.cofre_id no tiene collation propio (usa el
        // default del servidor, utf8_general_ci) — chocan sin forzar uno común
        // (ver el fatal real que esto causó en producción).
        $sql .= " AND objeto_id NOT IN (SELECT DISTINCT cofre_id COLLATE utf8_general_ci FROM `mybb_op_cofres`)";
    }
    $sql .= " ORDER BY nombre LIMIT 20";

    $resultados = array();
    $query = $db->query($sql);
    while ($r = $db->fetch_array($query)) {
        $resultados[] = array('id' => $r['objeto_id'], 'nombre' => $r['nombre']);
    }
    echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Arma el custom_data (array, no JSON todavía) a partir de los campos del
 * formulario. Devuelve null si ninguna clave tiene valor real (no se deja
 * guardar una fila Custom vacía — 100_Requirements §4.4).
 */
function cofres_armar_custom_data($mybb)
{
    $datos = array();
    $nikas    = (float) $mybb->get_input('custom_nikas');
    $berries  = (int) $mybb->get_input('custom_berries');
    $exp      = (float) $mybb->get_input('custom_experiencia');
    $oficio   = (int) $mybb->get_input('custom_puntos_oficio');
    $bonus_id = trim($mybb->get_input('custom_objeto_bonus', MyBB::INPUT_STRING));
    $bonus_ct = (int) $mybb->get_input('custom_objeto_bonus_cantidad');

    if ($nikas > 0)   { $datos['nikas'] = $nikas; }
    if ($berries > 0) { $datos['berries'] = $berries; }
    if ($exp > 0)     { $datos['experiencia'] = $exp; }
    if ($oficio > 0)  { $datos['puntos_oficio'] = $oficio; }
    if ($bonus_id !== '') {
        $datos['objeto_bonus'] = $bonus_id;
        $datos['objeto_bonus_cantidad'] = $bonus_ct > 0 ? $bonus_ct : 1;
    }

    return count($datos) > 0 ? $datos : null;
}

/** Resumen legible de un custom_data, para la columna objeto_id (solo lectura humana, nunca releído por la tirada). */
function cofres_resumen_custom($datos)
{
    $partes = array();
    if (!empty($datos['nikas']))         { $partes[] = $datos['nikas'] . 'N'; }
    if (!empty($datos['berries']))       { $partes[] = $datos['berries'] . 'B'; }
    if (!empty($datos['experiencia']))   { $partes[] = $datos['experiencia'] . 'E'; }
    if (!empty($datos['puntos_oficio'])) { $partes[] = $datos['puntos_oficio'] . 'PO'; }
    if (!empty($datos['objeto_bonus']))  { $partes[] = '+' . $datos['objeto_bonus']; }
    return $partes ? implode('-', $partes) : 'CUSTOM';
}

/**
 * Arma el HTML de las filas de recompensa de un cofre — reusado tanto por la
 * carga normal de la vista editor (GET) como por la respuesta parcial que
 * htmx pide tras agregar/editar/eliminar una fila (ver Tarea htmx).
 * Devuelve ['html' => string, 'peso_total' => int].
 */
function cofres_construir_filas_html($db, $cofre_id, $post_key)
{
    $safe_cofre = $db->escape_string($cofre_id);
    $filas_query = $db->query("SELECT * FROM `mybb_op_cofres` WHERE cofre_id='{$safe_cofre}' ORDER BY id");
    $filas = array();
    $peso_total = 0;
    while ($f = $db->fetch_array($filas_query)) {
        $peso_total += (int) $f['peso'];
        $filas[] = $f;
    }

    // El resumen de peso total va DENTRO del fragmento que htmx reemplaza
    // (no afuera, como una variable aparte) — así siempre está sincronizado
    // con las filas actuales, tanto en la carga normal como tras un swap.
    $filas_html = count($filas) > 0
        ? '<p class="cf-resumen-peso"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Peso total <strong>' . $peso_total . '</strong></p>'
        : '';
    foreach ($filas as $f) {
        $probabilidad = $peso_total > 0 ? round(((int) $f['peso'] / $peso_total) * 100, 1) : 0;
        $nombre_esc = htmlspecialchars($f['nombre'], ENT_QUOTES, 'UTF-8');
        $tipo_esc = htmlspecialchars($f['tipo'], ENT_QUOTES, 'UTF-8');
        $datos_js = htmlspecialchars(json_encode(array(
            'id'          => (int) $f['id'],
            'tipo'        => $f['tipo'],
            'nombre'      => $f['nombre'],
            'objeto_id'   => $f['objeto_id'],
            'peso'        => (int) $f['peso'],
            'cantidad'    => (int) $f['cantidad'],
            'custom_data' => $f['custom_data'] ? json_decode($f['custom_data'], true) : null,
        ), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

        $filas_html .= '
        <div class="cf-fila" data-peso="' . (int) $f['peso'] . '" data-fila=\'' . $datos_js . '\'>
            <span class="cf-fila-tipo cf-fila-tipo--' . $tipo_esc . '">' . $tipo_esc . '</span>
            <span class="cf-fila-nombre">' . $nombre_esc . '</span>
            <span class="cf-fila-peso">Peso ' . (int) $f['peso'] . '</span>
            <span class="cf-fila-prob">' . $probabilidad . '%</span>
            <span class="cf-fila-acciones">
                <button type="button" class="opg-chip cf-editar-fila">Editar</button>
                <button type="button" class="opg-chip chip-peligro cf-eliminar-fila"
                    hx-post="cofres_gestion.php"
                    hx-target="#cf-filas" hx-swap="innerHTML"
                    hx-vals=\'{"accion":"eliminar_fila","my_post_key":"' . $post_key . '","cofre_id":"' . htmlspecialchars($cofre_id, ENT_QUOTES, 'UTF-8') . '","fila_id":' . (int) $f['id'] . '}\'
                    hx-confirm="¿Eliminar esta recompensa?">Eliminar</button>
            </span>
        </div>';
    }

    if ($filas_html === '') {
        $filas_html = '<p class="opg-vacio" id="cf-sin-filas">Este cofre todavía no tiene ninguna recompensa configurada.</p>';
    }

    return array('html' => $filas_html, 'peso_total' => $peso_total);
}

/** htmx manda esta cabecera real en cada petición que dispara — no un campo del POST. */
function cofres_es_htmx()
{
    return isset($_SERVER['HTTP_HX_REQUEST']);
}

/**
 * Respuesta parcial para una petición htmx: el fragmento de filas (que
 * reemplaza #cf-filas) más un aviso fuera de banda (hx-swap-oob) que
 * reemplaza #cf-aviso sin tocar el resto de la página. Termina la petición.
 */
function cofres_responder_htmx($db, $cofre_id, $post_key, $tipo_msg, $texto_msg)
{
    $resultado = cofres_construir_filas_html($db, $cofre_id, $post_key);
    $clase = $tipo_msg === 'ok' ? 'ok' : 'err';
    $aviso_html = $texto_msg !== ''
        ? '<p class="aviso ' . $clase . '">' . htmlspecialchars($texto_msg, ENT_QUOTES, 'UTF-8') . '</p>'
        : '';
    // Solo en éxito: dispara un evento propio que el cliente escucha para
    // limpiar el formulario — en error, el admin no pierde lo que escribió,
    // puede corregirlo sin volver a tipear todo.
    if ($tipo_msg === 'ok') {
        header('HX-Trigger: cofres-guardado');
    }
    echo '<span id="cf-aviso" hx-swap-oob="true">' . $aviso_html . '</span>' . $resultado['html'];
    exit;
}

// ── POST: acciones que escriben ──────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);

    if ($accion === 'crear_cofre') {
        $objeto_id = trim($mybb->get_input('objeto_id', MyBB::INPUT_STRING));
        $existe = (bool) $db->fetch_field($db->query("SELECT objeto_id FROM `mybb_op_objetos` WHERE objeto_id='" . $db->escape_string($objeto_id) . "' LIMIT 1"), 'objeto_id');
        $ya_es_cofre = (bool) $db->fetch_field($db->query("SELECT cofre_id FROM `mybb_op_cofres` WHERE cofre_id='" . $db->escape_string($objeto_id) . "' LIMIT 1"), 'cofre_id');

        if ($objeto_id === '' || !$existe) {
            cofres_mensaje('err', 'Ese ítem no existe en el catálogo.');
        } elseif ($ya_es_cofre) {
            // Ya tiene loot table: no se duplica, se va directo al editor existente.
            cofres_mensaje('ok', 'Ese cofre ya existía — aquí está su loot table.');
        } else {
            // Primera fila de arranque, mínima (Jackpot, peso 1) para que el
            // cofre_id exista desde ya en mybb_op_cofres y aparezca en el listado.
            $safe_id = $db->escape_string($objeto_id);
            $db->query("INSERT INTO `mybb_op_cofres` (`cofre_id`, `objeto_id`, `nombre`, `tipo`, `peso`, `cantidad`) VALUES ('{$safe_id}', '__JACKPOT__', 'Todo lo demás del cofre', 'Jackpot', '1', '0')");
            log_audit($uid, $username, '[Cofres]', "Creó la loot table de {$safe_id}.");
            cofres_mensaje('ok', 'Cofre creado. Agrega o edita sus recompensas.');
        }

        header('Location: ' . cofres_url($objeto_id));
        exit;
    }

    if ($accion === 'agregar_fila' || $accion === 'editar_fila') {
        $cofre_id = trim($mybb->get_input('cofre_id', MyBB::INPUT_STRING));
        $fila_id  = (int) $mybb->get_input('fila_id');
        $tipo     = $mybb->get_input('tipo', MyBB::INPUT_STRING);
        $peso     = (int) $mybb->get_input('peso');

        $error = '';
        if ($cofre_id === '') { $error = 'Cofre no válido.'; }
        elseif (!in_array($tipo, $GLOBALS['COFRES_TIPOS'], true)) { $error = 'Tipo de recompensa no válido.'; }
        elseif ($peso < 1) { $error = 'El peso debe ser un entero de al menos 1.'; }

        $objeto_id_fila = '';
        $nombre_fila = trim($mybb->get_input('nombre', MyBB::INPUT_STRING));
        $cantidad = (int) $mybb->get_input('cantidad');
        $custom_json_escaped = 'NULL';

        if ($error === '' && $tipo === 'Objeto') {
            $objeto_id_fila = trim($mybb->get_input('objeto_id', MyBB::INPUT_STRING));
            $objeto_row = $db->fetch_array($db->query("SELECT nombre FROM `mybb_op_objetos` WHERE objeto_id='" . $db->escape_string($objeto_id_fila) . "' LIMIT 1"));
            if (!$objeto_row) {
                $error = 'El ítem de la recompensa no existe en el catálogo.';
            } else {
                if ($nombre_fila === '') { $nombre_fila = $objeto_row['nombre']; }
                if ($cantidad < 1) { $cantidad = 1; }
            }
        } elseif ($error === '' && $tipo === 'Custom') {
            $datos = cofres_armar_custom_data($mybb);
            if ($datos === null) {
                $error = 'Una recompensa Custom necesita al menos un valor mayor a 0.';
            } else {
                $objeto_id_fila = cofres_resumen_custom($datos);
                if ($nombre_fila === '') { $nombre_fila = 'Recompensa: ' . $objeto_id_fila; }
                $custom_json_escaped = "'" . $db->escape_string(json_encode($datos, JSON_UNESCAPED_UNICODE)) . "'";
            }
        } elseif ($error === '' && $tipo === 'Jackpot') {
            $objeto_id_fila = '__JACKPOT__';
            if ($nombre_fila === '') { $nombre_fila = 'Todo lo demás del cofre'; }
            $cantidad = 0;
        }

        if ($error === '') {
            $safe_cofre  = $db->escape_string($cofre_id);
            $safe_obj    = $db->escape_string($objeto_id_fila);
            $safe_nombre = $db->escape_string($nombre_fila);

            if ($accion === 'agregar_fila') {
                $db->query("INSERT INTO `mybb_op_cofres` (`cofre_id`, `objeto_id`, `nombre`, `tipo`, `peso`, `cantidad`, `custom_data`) VALUES ('{$safe_cofre}', '{$safe_obj}', '{$safe_nombre}', '{$tipo}', '{$peso}', '{$cantidad}', {$custom_json_escaped})");
                log_audit($uid, $username, '[Cofres]', "Agregó una recompensa ({$tipo}) a {$safe_cofre}.");
            } else {
                $db->query("UPDATE `mybb_op_cofres` SET `objeto_id`='{$safe_obj}', `nombre`='{$safe_nombre}', `tipo`='{$tipo}', `peso`='{$peso}', `cantidad`='{$cantidad}', `custom_data`={$custom_json_escaped} WHERE `id`={$fila_id} AND `cofre_id`='{$safe_cofre}'");
                log_audit($uid, $username, '[Cofres]', "Editó una recompensa (id {$fila_id}) de {$safe_cofre}.");
            }
            if (cofres_es_htmx()) { cofres_responder_htmx($db, $cofre_id, $post_key, 'ok', 'Recompensa guardada.'); }
            cofres_mensaje('ok', 'Recompensa guardada.');
        } else {
            if (cofres_es_htmx()) { cofres_responder_htmx($db, $cofre_id, $post_key, 'err', $error); }
            cofres_mensaje('err', $error);
        }

        header('Location: ' . cofres_url($cofre_id));
        exit;
    }

    if ($accion === 'eliminar_fila') {
        $cofre_id = trim($mybb->get_input('cofre_id', MyBB::INPUT_STRING));
        $fila_id  = (int) $mybb->get_input('fila_id');
        $safe_cofre = $db->escape_string($cofre_id);
        $db->query("DELETE FROM `mybb_op_cofres` WHERE id={$fila_id} AND cofre_id='{$safe_cofre}'");
        log_audit($uid, $username, '[Cofres]', "Eliminó una recompensa (id {$fila_id}) de {$safe_cofre}.");
        if (cofres_es_htmx()) { cofres_responder_htmx($db, $cofre_id, $post_key, 'ok', 'Recompensa eliminada.'); }
        cofres_mensaje('ok', 'Recompensa eliminada.');
        header('Location: ' . cofres_url($cofre_id));
        exit;
    }

    // Borrar un cofre completo (toda su loot table) no es una acción
    // disponible a propósito — ver docs/100_Requirements_Cofres.md §5.5.
}

// ── GET: armar la vista ───────────────────────────────────────────────────────
// ($post_key ya se generó al principio del archivo)

$cofre_id = trim($mybb->get_input('cofre_id', MyBB::INPUT_STRING));

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[COFRES_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[COFRES_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(COFRES_MSG_COOKIE);
}

if ($cofre_id === '') {
    // ── Vista: listado ───────────────────────────────────────────────────────
    $query = $db->query("
        SELECT o.cofre_id,
               obj.nombre AS obj_nombre,
               obj.imagen AS obj_imagen,
               COUNT(*) AS num_filas,
               SUM(o.peso) AS peso_total
        FROM `mybb_op_cofres` o
        LEFT JOIN `mybb_op_objetos` obj ON obj.objeto_id COLLATE utf8_general_ci = o.cofre_id
        GROUP BY o.cofre_id
        ORDER BY o.cofre_id
    ");

    $tarjetas_html = '';
    while ($fila = $db->fetch_array($query)) {
        $nombre = $fila['obj_nombre'] !== null ? $fila['obj_nombre'] : '(ítem no encontrado en el catálogo)';
        $nombre_esc = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $imagen_esc = htmlspecialchars((string) $fila['obj_imagen'], ENT_QUOTES, 'UTF-8');

        $cofre_id_esc_card = htmlspecialchars($fila['cofre_id'], ENT_QUOTES, 'UTF-8');

        $tarjetas_html .= '
            <div class="opg-card" style="--opg-card-acento: var(--opg-morado);">
                <span class="opg-card__cabecera">
                    <span class="opg-card__icono"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span>
                    <span class="opg-card__titulo">' . $nombre_esc . '</span>
                </span>
                <span class="cf-card-id">' . $cofre_id_esc_card . '</span>
                <span class="cf-card-metricas">
                    <span class="cf-card-metrica"><strong>' . (int) $fila['num_filas'] . '</strong><span>recompensas</span></span>
                    <span class="cf-card-metrica"><strong>' . (int) $fila['peso_total'] . '</strong><span>peso total</span></span>
                </span>
                <span class="cf-card-acciones">
                    <a class="opg-chip" href="cofres_gestion.php?cofre_id=' . rawurlencode($fila['cofre_id']) . '"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar loot</a>
                </span>
            </div>';
    }

    if ($tarjetas_html === '') {
        $tarjetas_html = '<p class="opg-vacio">Todavía no hay ningún cofre con loot table configurada.</p>';
    }

    eval("\$page = \"".$templates->get("staff_cofres_gestion_listado")."\";");
    output_page($page);
    exit;
}

// ── Vista: editor de un cofre ────────────────────────────────────────────────

$safe_cofre = $db->escape_string($cofre_id);
$cofre_info = $db->fetch_array($db->query("SELECT objeto_id, nombre, imagen FROM `mybb_op_objetos` WHERE objeto_id='{$safe_cofre}' LIMIT 1"));

if (!$cofre_info) {
    $mensaje_redireccion = "Ese ítem no existe en el catálogo.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    exit;
}

$resultado_filas = cofres_construir_filas_html($db, $cofre_id, $post_key);
$filas_html = $resultado_filas['html'];

$cofre_nombre_esc = htmlspecialchars($cofre_info['nombre'], ENT_QUOTES, 'UTF-8');
$cofre_id_esc = htmlspecialchars($cofre_id, ENT_QUOTES, 'UTF-8');

eval("\$page = \"".$templates->get("staff_cofres_gestion_editor")."\";");
output_page($page);
