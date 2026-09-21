<?php
/**
 * Staff - Atributos de ficha (v2, con secciones plegables)
 *
 * Copia funcional exacta de op/staff/ficha_atributos.php — misma lógica de
 * guardado, mismos ~90 campos de mybb_op_fichas, mismo template de datos
 * (`$ficha_esc`, `$fa_*_opciones`). Lo único que cambia es el template
 * (`staff_ficha_atributos2`): cada sección pasa de `<div class="fa-seccion">`
 * con una `.barra-op` naranja completa a un `<details>` plegable con la
 * franja morada `.opg-guia-barra` ya usada en el foro para acordeones (ver
 * opg-components.css) — con 12 secciones, repetir la `.barra-op` (pensada
 * como chrome de "título de página") en cada una era demasiado peso visual
 * y obligaba a bajar por 90+ campos en un solo scroll largo. "Información
 * básica" y "Configuración del personaje" arrancan abiertas (las más
 * tocadas), el resto arranca cerrado. Los campos dentro de un `<details>`
 * cerrado se envían igual con el formulario — el plegado es solo visual.
 *
 * Todos los bugs de la versión original ya están resueltos acá, igual que
 * en ficha_atributos.php (ver ese archivo si se quiere el detalle de cada
 * uno):
 *
 * 1. Inyección SQL: prácticamente ningún campo pasaba por escape_string —
 *    ~85 UPDATE independientes, cada uno con su propio valor sin escapar.
 *    Ahora se arma un único UPDATE con todos los campos que cambiaron,
 *    todos escapados.
 * 2. CSRF: el <form> no llevaba my_post_key.
 * 3. XSS: cada valor se mostraba en el template sin htmlspecialchars.
 * 4. `control_akuma` / `control_akuma_pasiva` tienen columna real en
 *    mybb_op_fichas, pero sus <input> estaban comentados en el template —
 *    el HTML nunca los mandaba por POST, así que en cada guardado el PHP
 *    los encontraba "vacíos" y los pisaba a '' en la base, borrando el
 *    valor real sin que nadie lo pidiera. Se restauran como campos
*    editables normales.
 * 5. `espacios` sí tiene columna real y su <input> sí es editable, pero el
 *    PHP nunca leía `$_POST['espacios']` — cualquier cambio ahí se perdía
 *    en silencio. Ahora se guarda.
 * 6. `npcs` y `wantedcustom` no son columnas de mybb_op_fichas (no existen
 *    en ninguna tabla del dump) — sus <input> nunca guardaban nada de
 *    verdad. Se sacan del formulario en vez de simular que funcionan.
 * 7. `log_audit_currency()` reconoce 'nikas'/'kuros' (plural), pero acá se
 *    la llamaba con 'nika'/'kuro' (singular) — nunca hacía nada para esas
 *    dos monedas. Se corrige el nombre para que sí registre el cambio en
 *    mybb_op_audit_general, igual que ya hacen berries/puntos_oficio.
 *    La llamada para 'movidoInframundo' se saca: esa función no tiene rama
 *    para ese nombre, así que nunca hizo nada más que una consulta de más.
 * 8. `echo $f_var['hao_chance'];` suelto en medio del procesamiento del
 *    POST — quedaba un número impreso en la página antes del formulario.
 *    Era un resto de debug, se saca.
 * 9. El bloque de Select2 (`#referido_select`) del final del template no
 *    corresponde a ningún elemento de esta página — es código copiado de
 *    otra pantalla que nunca hace nada acá. Se saca junto con el <link>/
 *    <script> de la CDN de Select2 que solo estaban para eso.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'ficha_atributos2.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_mod($uid) && !is_staff($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('FICHA_ATRIBUTOS_MSG_COOKIE', 'ficha_atributos2_msg');

// Campos de mybb_op_fichas que se procesan de forma genérica: leer,
// comparar contra el valor actual, y si cambió, escapar + loguear + sumar
// al UPDATE. No incluye los que necesitan un paso extra (ver más abajo):
// berries/nika/kuro/puntos_oficio/movidoInframundo (log_audit_currency),
// faccion (sincroniza mybb_users.usergroup), oficios/belicas (limpieza de
// JSON), akuma_origen (valor restringido a una lista fija).
$CAMPOS_SIMPLES = array(
    'nombre', 'edad', 'altura', 'peso', 'fisico_de_pj', 'origen_de_pj',
    'cronologia', 'fx', 'akuma', 'akuma_subnombre', 'nivelnarrador',
    'rango', 'rango_inframundo', 'fama', 'sexo', 'temporada', 'dia',
    'camino', 'ranuras', 'secret1', 'raza', 'implantes',
    'equipamiento_espacio', 'equipamiento', 'oficio1', 'oficio2',
    'belica1', 'belica2', 'belica3', 'belica4', 'belica5', 'belica6',
    'belica7', 'belica8', 'belica9', 'belica10', 'belica11', 'belica12',
    'estilo1', 'estilo2', 'estilo3', 'estilo4', 'elementos',
    'wanted', 'muerto', 'hao_chance', 'kenbun', 'buso', 'hao',
    'dominio_akuma', 'reputacion', 'reputacion_positiva', 'reputacion_negativa',
    'reputacion2', 'reputacion_positiva2', 'reputacion_negativa2',
    'wanted_repu', 'wantedGuardado', 'puntos_estadistica', 'nivel',
    'fuerza', 'resistencia', 'destreza', 'voluntad', 'punteria', 'agilidad',
    'reflejos', 'control_akuma',
    'vitalidad_pasiva', 'energia_pasiva', 'haki_pasiva',
    'fuerza_pasiva', 'resistencia_pasiva', 'destreza_pasiva',
    'voluntad_pasiva', 'punteria_pasiva', 'agilidad_pasiva',
    'reflejos_pasiva', 'control_akuma_pasiva', 'espacios',
);

$CAMPOS_ENTEROS_ESTRICTOS = array(
    'equipamiento_espacio' => true,
    'control_akuma' => true,
    'control_akuma_pasiva' => true,
);

// ── POST: guardar ─────────────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $ficha_id = trim($mybb->get_input('ficha_id', MyBB::INPUT_STRING));
    $razon    = trim($mybb->get_input('razon', MyBB::INPUT_STRING));

    $error = '';
    if ($ficha_id === '') { $error = 'Falta el UID del personaje.'; }
    elseif ($razon === '') { $error = 'La razón es obligatoria.'; }

    $f_var = null;
    $u_var = null;
    if ($error === '') {
        $f_var = $db->fetch_array($db->query("SELECT * FROM `mybb_op_fichas` WHERE fid='" . $db->escape_string($ficha_id) . "'"));
        $u_var = $db->fetch_array($db->query("SELECT * FROM `mybb_users` WHERE uid='" . $db->escape_string($ficha_id) . "'"));
        if (!$f_var) { $error = 'Ese UID no tiene ficha.'; }
    }

    if ($error === '') {
        $log = "Cambios de atributos para ficha de UID: {$ficha_id} ({$f_var['nombre']}):\n";
        $set_parts = array();
        $cambiados = array();

        foreach ($CAMPOS_SIMPLES as $campo) {
            if (!array_key_exists($campo, $mybb->input)) {
                continue;
            }
            $valor = trim($mybb->get_input($campo, MyBB::INPUT_STRING));
            if (isset($CAMPOS_ENTEROS_ESTRICTOS[$campo]) && !preg_match('/^-?\d+$/', $valor)) {
                continue;
            }
            $actual = (string) $f_var[$campo];
            if ($valor !== $actual) {
                $log .= "-- De {$actual} a {$valor} {$campo}.\n";
                $set_parts[] = "`{$campo}`='" . $db->escape_string($valor) . "'";
                $cambiados[$campo] = $valor;
            }
        }

        // akuma_origen: valor restringido a una lista fija, igual que antes.
        // Mismo guard que el bucle de arriba: si no llega en el POST, no se toca
        // (sin esto, un campo ausente se leía como '' y eso resetea el valor).
        if (array_key_exists('akuma_origen', $mybb->input)) {
            $akuma_origen = $mybb->get_input('akuma_origen', MyBB::INPUT_STRING);
            $akuma_origen = in_array($akuma_origen, array('', 'aventura'), true) ? $akuma_origen : '';
            if ($akuma_origen !== (string) ($f_var['akuma_origen'] ?? '')) {
                $log .= "-- De " . ($f_var['akuma_origen'] ?? '') . " a {$akuma_origen} akuma_origen.\n";
                $set_parts[] = "`akuma_origen`='" . $db->escape_string($akuma_origen) . "'";
            }
        }

        // oficios / belicas: limpiar especializaciones vacías del JSON antes
        // de guardar, igual que la versión anterior. Mismo guard: son columnas
        // JSON NOT NULL, y '' no es JSON válido — sin esto, un campo ausente
        // del POST tira un UPDATE inválido y $db->query() corta la página
        // entera con un error, sin guardar ni siquiera los demás cambios.
        foreach (array('oficios', 'belicas') as $campo_json) {
            if (!array_key_exists($campo_json, $mybb->input)) {
                continue;
            }
            $valor = $mybb->get_input($campo_json, MyBB::INPUT_STRING);
            if ($valor !== (string) $f_var[$campo_json]) {
                $decoded = json_decode($valor, true);
                if ($decoded !== null && is_array($decoded)) {
                    foreach ($decoded as &$item) {
                        if (isset($item['espe1']) && $item['espe1'] === '') { unset($item['espe1']); }
                        if (isset($item['espe2']) && $item['espe2'] === '') { unset($item['espe2']); }
                        if ($campo_json === 'oficios' && array_key_exists('sub', $item) && is_array($item['sub'])) {
                            $item['sub'] = (object) $item['sub'];
                        }
                    }
                    unset($item);
                    $valor = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                }
                $log .= "-- De " . $f_var[$campo_json] . " a {$valor} {$campo_json}.\n";
                $set_parts[] = "`{$campo_json}`='" . $db->escape_string($valor) . "'";
            }
        }

        // berries / nika / kuro / puntos_oficio: además del UPDATE genérico
        // de arriba, log_audit_currency() deja un registro aparte en
        // mybb_op_audit_general. 'nikas'/'kuros' en plural es lo que esa
        // función reconoce — iba en singular y nunca hacía nada.
        $monedas = array(
            'berries' => 'berries',
            'nika' => 'nikas',
            'kuro' => 'kuros',
            'puntos_oficio' => 'puntos_oficio',
        );
        foreach ($monedas as $campo => $moneda) {
            $valor = trim($mybb->get_input($campo, MyBB::INPUT_STRING));
            $actual = (string) $f_var[$campo];
            if ($valor !== $actual) {
                $log .= "-- De {$actual} a {$valor} {$campo}.\n";
                $set_parts[] = "`{$campo}`='" . $db->escape_string($valor) . "'";
                log_audit_currency($uid, $username, $ficha_id, "[Modificación de {$campo}]", $moneda, $valor);
            }
        }

        // movidoInframundo: solo UPDATE genérico — log_audit_currency() no
        // tiene ninguna rama para este nombre, llamarla no hacía nada.
        $movido = max(0, (int) $mybb->get_input('movidoInframundo', MyBB::INPUT_INT));
        $movido_actual = (int) ($f_var['movidoInframundo'] ?? 0);
        if ($movido !== $movido_actual) {
            $log .= "-- De {$movido_actual} a {$movido} movidoInframundo.\n";
            $set_parts[] = "`movidoInframundo`='" . (int) $movido . "'";
        }

        // faccion: además de mybb_op_fichas, sincroniza el grupo de MyBB.
        $faccion = trim($mybb->get_input('faccion', MyBB::INPUT_STRING));
        if ($faccion !== (string) $f_var['faccion']) {
            $log .= "-- De " . $f_var['faccion'] . " a {$faccion} faccion.\n";
            $set_parts[] = "`faccion`='" . $db->escape_string($faccion) . "'";

            // El grupo de MyBB asociado a cada facción se gestiona desde
            // Admin CP → Configuración del foro → OPG Facciones (columna
            // "usergroup" de mybb_op_facciones). Si la tabla no existiera,
            // cae al mapeo fijo de siempre para no dejar de funcionar.
            $faccion_usergroup = 0;
            if ($db->table_exists('op_facciones')) {
                $faccion_usergroup = (int) $db->fetch_field($db->simple_select('op_facciones', 'usergroup', "nombre='" . $db->escape_string($faccion) . "'"), 'usergroup');
            } else {
                $legacy_usergroups = array(
                    'Pirata' => 8, 'Marina' => 9, 'CipherPol' => 11,
                    'Revolucionario' => 12, 'Cazadores' => 10, 'Civil' => 13,
                );
                if (isset($legacy_usergroups[$faccion])) {
                    $faccion_usergroup = $legacy_usergroups[$faccion];
                }
            }
            if ($faccion_usergroup > 0) {
                $db->query("UPDATE `mybb_users` SET usergroup='" . (int) $faccion_usergroup . "' WHERE `uid`='" . $db->escape_string($ficha_id) . "'");
            }
        }

        if ($set_parts) {
            $db->query("UPDATE `mybb_op_fichas` SET " . implode(', ', $set_parts) . " WHERE `fid`='" . $db->escape_string($ficha_id) . "'");
        }

        // puntos_experiencia: vive en mybb_users.newpoints, no en la ficha.
        $puntos_experiencia = trim($mybb->get_input('puntos_experiencia', MyBB::INPUT_STRING));
        $experiencia_actual = (string) ($u_var['newpoints'] ?? '');
        if ($u_var && $puntos_experiencia !== $experiencia_actual) {
            $log .= "-- De {$experiencia_actual} a {$puntos_experiencia} experiencia.\n";
            $db->query("UPDATE `mybb_users` SET newpoints='" . $db->escape_string($puntos_experiencia) . "' WHERE `uid`='" . $db->escape_string($ficha_id) . "'");
            log_audit_currency($uid, $username, $ficha_id, '[Modificación de experiencia]', 'experiencia', $puntos_experiencia);
        }

        $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
            . "('{$uid}', '" . $db->escape_string($username) . "', '" . $db->escape_string($razon) . "', '" . $db->escape_string($log) . "')");
    }

    my_setcookie(FICHA_ATRIBUTOS_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : 'Ficha actualizada.',
    ))), 15, true);
    header('Location: ' . ($ficha_id !== '' ? 'ficha_atributos2.php?fid=' . rawurlencode($ficha_id) : 'ficha_atributos2.php'));
    exit;
}

// ── GET: formulario ────────────────────────────────────────────────────────────

$fid = trim($mybb->get_input('fid', MyBB::INPUT_STRING));
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[FICHA_ATRIBUTOS_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[FICHA_ATRIBUTOS_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(FICHA_ATRIBUTOS_MSG_COOKIE);
}

$ficha = null;
$puntos_experiencia = '';
$rango_narrador = '';
if ($fid !== '') {
    $ficha = $db->fetch_array($db->query("SELECT * FROM `mybb_op_fichas` WHERE fid='" . $db->escape_string($fid) . "'"));
    if ($ficha) {
        $rango_narrador = $ficha['nivelnarrador'];
        $u_row = $db->fetch_array($db->query("SELECT newpoints FROM `mybb_users` WHERE uid='" . $db->escape_string($fid) . "'"));
        $puntos_experiencia = $u_row ? $u_row['newpoints'] : '';
    }
}
$ficha_existe = $ficha ? 1 : 0;
$fid_esc = htmlspecialchars($fid, ENT_QUOTES, 'UTF-8');

// Escapado para mostrar: un array nuevo, htmlspecialchars sobre cada valor
// de $ficha (o '' si todavía no se buscó ningún personaje).
function fa_e($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$campos_mostrar = array(
    'nombre', 'berries', 'nika', 'kuro', 'puntos_oficio', 'edad', 'altura', 'peso',
    'fisico_de_pj', 'origen_de_pj', 'cronologia', 'fx', 'como_nos_conociste',
    'akuma', 'akuma_subnombre', 'akuma_origen', 'dominio_akuma',
    'sexo', 'faccion', 'temporada', 'dia', 'camino', 'ranuras', 'secret1', 'raza',
    'implantes', 'equipamiento', 'equipamiento_espacio',
    'oficio1', 'oficio2', 'oficios', 'belicas', 'elementos',
    'belica1', 'belica2', 'belica3', 'belica4', 'belica5', 'belica6',
    'belica7', 'belica8', 'belica9', 'belica10', 'belica11', 'belica12',
    'estilo1', 'estilo2', 'estilo3', 'estilo4',
    'rango', 'rango_inframundo', 'movidoInframundo', 'fama',
    'reputacion', 'reputacion_positiva', 'reputacion_negativa',
    'reputacion2', 'reputacion_positiva2', 'reputacion_negativa2', 'wanted_repu',
    'kenbun', 'buso', 'hao', 'hao_chance', 'wanted', 'muerto',
    'puntos_estadistica', 'nivel',
    'fuerza', 'resistencia', 'destreza', 'voluntad', 'punteria', 'agilidad', 'reflejos', 'control_akuma',
    'vitalidad_pasiva', 'energia_pasiva', 'haki_pasiva',
    'fuerza_pasiva', 'resistencia_pasiva', 'destreza_pasiva', 'voluntad_pasiva',
    'punteria_pasiva', 'agilidad_pasiva', 'reflejos_pasiva', 'control_akuma_pasiva',
    'espacios', 'wantedGuardado', 'nivelnarrador',
);
$ficha_esc = array();
foreach ($campos_mostrar as $c) {
    $ficha_esc[$c] = fa_e($ficha ? $ficha[$c] : '');
}
$puntos_experiencia_esc = fa_e($puntos_experiencia);

// Desplegables de facción y rango: se generan desde mybb_op_facciones /
// mybb_op_facciones_rangos (Admin CP → Configuración del foro → OPG
// Facciones); si esas tablas no existieran, quedan vacíos y el <select> de
// facción/rango se ve sin opciones — no había otra fuente de datos antes
// tampoco (dependían de las mismas tablas).
$faccion_select_options = '';
$rango_select_options = '';
if ($db->table_exists('op_facciones')) {
    $query_facciones = $db->simple_select('op_facciones', '*', '', array('order_by' => 'orden'));
    while ($f_row = $db->fetch_array($query_facciones)) {
        $selected = ($ficha && $ficha['faccion'] === $f_row['nombre']) ? ' selected' : '';
        $faccion_select_options .= '<option value="' . fa_e($f_row['nombre']) . '"' . $selected . '>' . fa_e($f_row['nombre']) . '</option>';
    }
    if ($ficha && !empty($ficha['faccion']) && $db->table_exists('op_facciones_rangos')) {
        $query_rangos = $db->simple_select('op_facciones_rangos', '*', "faccion='" . $db->escape_string($ficha['faccion']) . "'", array('order_by' => 'orden'));
        while ($r_row = $db->fetch_array($query_rangos)) {
            $selected = ($ficha['rango'] === $r_row['valor']) ? ' selected' : '';
            $rango_select_options .= '<option value="' . fa_e($r_row['valor']) . '"' . $selected . '>' . fa_e($r_row['nombre_visible']) . '</option>';
        }
    }
}

// ── Catálogos de <select> (mismos valores que op/staff/npcs_modificar.php) ──

$ESTILO_OPCIONES = array(
    'bloqueado' => 'Bloqueado', 'no_bloqueado' => 'No Bloqueado',
    'Gunkata' => 'Gunkata', 'Hasshoken' => 'Hasshoken', 'Santoryu' => 'Santoryu', 'Kuroashi' => 'Kuroashi',
    'Gyojin Karate' => 'Gyojin Karate', 'Gyojin Jujutsu' => 'Gyojin Jujutsu', 'Okama Kempo' => 'Okama Kempo',
    'Sora Yokujin' => 'Sora Yokujin', 'Rokushiki' => 'Rokushiki', 'Ninjutsu' => 'Ninjutsu', 'Ryusoken' => 'Ryusoken',
    'Jiyuumura Kempo' => 'Jiyuumura Kempo', 'Clima Tact' => 'Clima Tact', 'Pop Green' => 'Pop Green',
    'Hakai Shin' => 'Hakai Shin', 'Funekiri' => 'Funekiri', 'Railgun Style' => 'Railgun Style',
    'Gyojin Bukijutsu' => 'Gyojin Bukijutsu', 'Shuron Hakke' => 'Shuron Hakke', 'Raqisat Alsahra' => 'Raqisat Alsahra',
    'Breeskjold' => 'Breeskjold', 'Impacto Explosivo' => 'Impacto Explosivo', 'Royal Guard' => 'Royal Guard',
    'Shikaku Teikoku' => 'Shikaku Teikoku', 'Duelliste de Givre' => 'Duelliste de Givre', 'Filo Della Vita' => 'Filo Della Vita',
    'Havets Symfoni' => 'Havets Symfoni', 'Bakudai Karin' => 'Bakudai Karin', 'Yama Kurai' => 'Yama Kurai',
    'Shiseiju' => 'Shiseiju', 'Sea Corsair' => 'Sea Corsair', 'Wano Nitoryu' => 'Wano Nitoryu',
    'Mano de Tahur' => 'Mano de Tahur', 'Kokudan' => 'Kokudan', 'Kodai no Bushido' => 'Kodai no Bushido',
    'Ittoryu Sekai' => 'Ittoryu Sekai', 'Global Performer' => 'Global Performer', 'Ashigara Dokoi' => 'Ashigara Dokoi',
    'Cavalry Warrior' => 'Cavalry Warrior', 'Kanpo Kenpo' => 'Kanpo Kenpo', 'Estilo Único' => 'Estilo Único',
    'Akuma5' => 'Akuma T5', 'Akuma6' => 'Akuma T6',
);
$BELICA_OPCIONES = array(
    '' => 'Sin Disciplina',
    'Escudero' => 'Escudero', 'Artista Marcial' => 'Artista Marcial', 'Combatiente' => 'Combatiente',
    'Artista' => 'Artista', 'Asesino' => 'Asesino', 'Guerrero' => 'Guerrero', 'Espadachín' => 'Espadachín',
    'Tecnicista' => 'Tecnicista', 'Artillero' => 'Artillero', 'Arquero' => 'Arquero', 'Tirador' => 'Tirador',
    'Pícaro' => 'Pícaro',
);
$OFICIO_OPCIONES = array(
    '' => 'Sin Oficio',
    'Navegante' => 'Navegante', 'Cocinero' => 'Cocinero', 'Médico' => 'Médico', 'Recolector' => 'Recolector',
    'Artesano' => 'Artesano', 'Inventor' => 'Inventor', 'Investigador' => 'Investigador', 'Carpintero' => 'Carpintero',
    'Mercader' => 'Mercader', 'Aventurero' => 'Aventurero',
);
$RAZA_OPCIONES = array(
    'Humano' => 'Humano', 'Gyojin' => 'Gyojin', 'Ningyo' => 'Ningyo', 'Gigante' => 'Gigante',
    'Tontatta' => 'Tontatta', 'Mink' => 'Mink', 'Skypian' => 'Skypian', 'Oni' => 'Oni',
    'Lunarian' => 'Lunarian', 'Oceanian' => 'Oceanian', 'Hafugyo' => 'Hafugyo', 'Buccaneers' => 'Buccaneers',
    'Kobito' => 'Kobito', 'Jujin' => 'Jujin', 'Wotan' => 'Wotan', 'Woko' => 'Woko', 'Daimink' => 'Daimink',
    'Komink' => 'Komink', 'Solarian' => 'Solarian', 'Dosundada' => 'Dosundada', 'Diablos' => 'Diablos', 'Ravnos' => 'Ravnos',
);
$FAMA_OPCIONES = array(
    'Desconocido' => 'Desconocido', 'Iniciado' => 'Iniciado', 'Novato' => 'Novato', 'Rumor' => 'Rumor',
    'Aspirante' => 'Aspirante', 'Popular' => 'Popular', 'Famoso' => 'Famoso', 'Icono' => 'Ícono', 'Leyenda' => 'Leyenda',
    'Escudero' => 'Escudero', 'Cortes' => 'Cortés', 'Caballero' => 'Caballero', 'Campeon' => 'Campeón',
    'Alimaña' => 'Alimaña', 'Sabandija' => 'Sabandija', 'Ratero' => 'Ratero', 'Truhan' => 'Truhán',
    'Celebre' => 'Célebre', 'Honorable' => 'Honorable', 'Galante' => 'Galante',
    'Incorruptible' => 'Incorruptible', 'Noble' => 'Noble', 'Protector' => 'Protector', 'Virtuoso' => 'Virtuoso',
    'Baluarte' => 'Baluarte', 'Justiciero' => 'Justiciero', 'Solemne' => 'Solemne', 'Santo' => 'Santo',
    'Paladin' => 'Paladín', 'Alma Pura' => 'Alma Pura',
    'Heroe' => 'Héroe', 'Adalid' => 'Adalid', 'Canalla' => 'Canalla', 'Granuja' => 'Granuja',
    'Delincuente' => 'Delincuente', 'Forajido' => 'Forajido', 'Bandido' => 'Bandido', 'Infame' => 'Infame',
    'Tirano' => 'Tirano', 'Terror' => 'Terror',
    'Sanguinario' => 'Sanguinario', 'Pesadilla' => 'Pesadilla', 'Desalmado' => 'Desalmado', 'Leviatan' => 'Leviatán',
    'Calamidad' => 'Calamidad',
);
$RANGO_INFRAMUNDO_OPCIONES = array(
    '' => 'Sin Rango', 'Alimaña' => 'Alimaña', 'Operativo' => 'Operativo', 'Capo' => 'Capo',
    'Broker' => 'Broker', 'Broker Estrella' => 'Broker Estrella', 'Emperador' => 'Emperador',
);
$NIVELNARRADOR_OPCIONES = array(
    'Aprendiz' => 'Narrador Aprendiz', 'Estudioso' => 'Narrador Estudioso',
    'Ilustre' => 'Narrador Ilustre', 'Erudito' => 'Narrador Erudito',
);

function fa_opciones($valores, $actual)
{
    $html = '';
    foreach ($valores as $val => $etiqueta) {
        $sel = ((string) $val === (string) $actual) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
            . htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

$fa_raza_opciones = fa_opciones($RAZA_OPCIONES, $ficha ? $ficha['raza'] : '');
$fa_fama_opciones = fa_opciones($FAMA_OPCIONES, $ficha ? $ficha['fama'] : '');
$fa_rango_inframundo_opciones = fa_opciones($RANGO_INFRAMUNDO_OPCIONES, $ficha ? $ficha['rango_inframundo'] : '');
$fa_nivelnarrador_opciones = fa_opciones($NIVELNARRADOR_OPCIONES, $rango_narrador);
$fa_oficio1_opciones = fa_opciones($OFICIO_OPCIONES, $ficha ? $ficha['oficio1'] : '');
$fa_oficio2_opciones = fa_opciones($OFICIO_OPCIONES, $ficha ? $ficha['oficio2'] : '');
for ($i = 1; $i <= 12; $i++) {
    $var = 'fa_belica' . $i . '_opciones';
    $$var = fa_opciones($BELICA_OPCIONES, $ficha ? $ficha['belica' . $i] : '');
}
for ($i = 1; $i <= 4; $i++) {
    $var = 'fa_estilo' . $i . '_opciones';
    $$var = fa_opciones($ESTILO_OPCIONES, $ficha ? $ficha['estilo' . $i] : '');
}

eval("\$page = \"".$templates->get("staff_ficha_atributos2")."\";");
output_page($page);
