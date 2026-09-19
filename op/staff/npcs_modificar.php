<?php
/**
 * Staff - Crear / modificar / eliminar NPCs
 *
 * Reescrito por varios problemas reales en el original:
 * 1. GRAVE: el control de permisos (is_mod/is_staff/is_narra) corría DESPUÉS
 *    de todos los INSERT/UPDATE/DELETE — cualquiera, sin sesión, podía crear,
 *    modificar o borrar un NPC con solo mandar un POST/GET a esta URL.
 * 2. Casi ningún campo pasaba por escape_string antes de ir a la consulta
 *    (npc_id, nuevo_npc_id, nombre, rango, sangre, akuma, wanted... solo
 *    trim() o addslashes()) — inyección SQL directa.
 * 3. Ningún <form> llevaba my_post_key (CSRF).
 * 4. Los campos se mostraban sin htmlspecialchars — XSS almacenado (un
 *    nombre con `</textarea><script>` rompía el formulario para cualquier
 *    staff que lo abriera después).
 * 5. En cada carga de página corría un SHOW COLUMNS + ALTER TABLE contra
 *    mybb_op_npcs, mybb_op_mascotas y mybb_op_npcs_usuarios para columnas
 *    que ya existen en producción — se quita, no hace falta.
 * 6. Las ramas de mascotas/NPC-usuario dependían de $is_pet/$is_npc_user,
 *    que no se definían en ningún lado del archivo — código muerto que
 *    nunca se ejecutaba (ni siquiera el botón "Borrar" de la plantilla
 *    llegaba a usarlas). Ese sistema (mybb_op_mascotas /
 *    mybb_op_npcs_usuarios) lo usan op/compas.php y op/crafteo.php, pero
 *    esta página nunca pudo gestionarlo de verdad — se saca de acá por
 *    completo. Si hace falta administrarlo hay que construirlo de cero.
 * 7. El campo "Elementos" del formulario no se guardaba nunca: mybb_op_npcs
 *    no tiene columna `elementos` (sí la tiene mybb_op_fichas, la de
 *    personajes de jugador) — se quita el campo.
 * 8. Renombrar un npc_id a uno que ya pertenece a OTRO NPC pisaba esa fila
 *    en silencio (la tabla no tiene índice único en npc_id) — ahora se
 *    bloquea con un error.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'npcs_modificar.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_mod($uid) && !is_staff($uid) && !is_narra($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('NPCS_MSG_COOKIE', 'npcs_msg');

// ── Typeahead: busca por npc_id o nombre, de solo lectura ────────────────────
if ($mybb->get_input('buscar_npc', MyBB::INPUT_STRING) !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($mybb->get_input('buscar_npc', MyBB::INPUT_STRING));
    $like = $db->escape_string(addcslashes($q, '%_'));
    $resultados = array();
    $query = $db->query("SELECT npc_id, nombre FROM `mybb_op_npcs` WHERE npc_id LIKE '%{$like}%' OR nombre LIKE '%{$like}%' ORDER BY nombre LIMIT 20");
    while ($r = $db->fetch_array($query)) {
        $resultados[] = array('id' => $r['npc_id'], 'nombre' => $r['nombre']);
    }
    echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
    exit;
}

function npcs_url($id = '')
{
    return 'npcs_modificar.php' . ($id !== '' ? '?npc_id=' . rawurlencode($id) : '');
}

// ── Catálogos (mismos valores que en las fichas de jugador) ──────────────────

$RANGO_OPCIONES = array(
    'Ciudadano' => 'Civil',
    'Pirata' => 'Pirata', 'CapitanPirata' => 'Capitán Pirata', 'Corsario' => 'Corsario', 'Bucanero' => 'Bucanero',
    'LoboDeMar' => 'Lobo de Mar', 'Supernova' => 'Supernova', 'PirataAfamado' => 'Pirata Afamado',
    'ViceCapitanFamoso' => 'Vice Capitán Famoso', 'CapitanFamoso' => 'Capitán Famoso', 'Shichibukai' => 'Shichibukai',
    'GranPirata' => 'Gran Pirata', 'GranViceCapitan' => 'Gran Vice Capitán', 'GranCapitan' => 'Gran Capitán',
    'ComandanteP' => 'Comandante de Yonkou', 'PrimerComandante' => 'Primer Comandante de Yonkou', 'Yonkou' => 'Yonkou',
    'LeyendaDelMar' => 'Leyenda del Mar', 'AladelRey' => 'Ala del Rey', 'ReyPirata' => 'Rey de los Piratas',
    'ReclutaM' => 'Recluta', 'SoldadoM' => 'Soldado Raso', 'SargentoM' => 'Sargento', 'Suboficial' => 'Suboficial',
    'Alferez' => 'Alferez', 'Teniente' => 'Teniente', 'ComandanteM' => 'Comandante', 'Capitan' => 'Capitán',
    'Comodoro' => 'Comodoro', 'ContraAlmirante' => 'Contralmirante', 'Vicealmirante' => 'Vice Almirante',
    'Almirante' => 'Almirante', 'AlmiranteFlota' => 'Almirante de Flota', 'Instructor' => 'Instructor',
    'Inspector' => 'Inspector General', 'HeroeDeLaMarina' => 'Heroe de la Marina',
    'CP1' => 'Cipher Pol 1', 'CP2' => 'Cipher Pol 2', 'CP3' => 'Cipher Pol 3', 'CP4' => 'Cipher Pol 4',
    'CP5' => 'Cipher Pol 5', 'CP6' => 'Cipher Pol 6', 'CP7' => 'Cipher Pol 7', 'CP8' => 'Cipher Pol 8',
    'CP9' => 'Cipher Pol 9', 'CPAegis0' => 'Cipher Pol 0', 'CPMasquerade' => 'Cipher Pol Masquerade',
    'CPComisario' => 'Comisario', 'CPComandanteEjecutivo' => 'Comandante Ejecutivo', 'CPCaballeroDivino' => 'Caballero Divino',
    'CPComandanteSupremo' => 'Comandante Supremo',
    'Cazador' => 'Cazador', 'CazadorZeta' => 'Cazador Zeta', 'CazadorEpsilon' => 'Cazador Epsilon',
    'CazadorDelta' => 'Cazador Delta', 'CazadorGamma' => 'Cazador Gamma', 'CazadorBeta' => 'Cazador Beta',
    'CazadorAlpha' => 'Cazador Alpha', 'CazadorOmega' => 'Cazador Omega', 'ReyCazador' => 'Rey de los Cazadores',
    'ReclutaR' => 'Recluta', 'SoldadoR' => 'Soldado Raso', 'SargentoR' => 'Sargento', 'AgenteR' => 'Agente',
    'Oficial' => 'Oficial', 'Mariscal' => 'Mariscal Revolucionario', 'General' => 'General',
    'ComandanteAdjunto' => 'Comandante Adjunto', 'ComandanteR' => 'Comandante Revolucionario',
    'JefePersonal' => 'Jefe de Personal', 'ComandanteSupremo' => 'Comandante Supremo',
);

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

function npc_opciones($valores, $actual)
{
    $html = '';
    foreach ($valores as $val => $etiqueta) {
        $sel = ((string) $val === (string) $actual) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
            . htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

// Campos de texto/número cortos (todo lo que no es textarea largo).
$CAMPOS_TEXTO = array(
    'apodo', 'faccion', 'raza', 'edad', 'altura', 'peso', 'sexo', 'temporada', 'nivel',
    'fuerza', 'resistencia', 'destreza', 'voluntad', 'punteria', 'agilidad', 'reflejos', 'control_akuma',
    'vitalidad', 'energia', 'haki', 'sangre', 'akuma', 'avatar1', 'avatar2',
    'wanted', 'reputacion', 'etiqueta', 'buso', 'kenbun', 'hao',
    'estilo1', 'estilo2', 'estilo3', 'estilo4', 'belica1', 'belica2', 'belica3', 'belica4',
    'belica5', 'belica6', 'belica7', 'belica8', 'oficio1', 'oficio2',
);
// Campos largos (textarea): se leen sin trim para no comerse saltos de línea al final.
$CAMPOS_LARGOS = array(
    'apariencia', 'personalidad', 'historia1', 'historia2', 'historia3', 'estilo_combate',
    'extra', 'notas', 'belicas', 'oficios', 'estilos', 'belicas_disponibles', 'info',
);

// ── POST: guardar / eliminar ─────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);

    if ($accion === 'eliminar') {
        $npc_id = trim($mybb->get_input('npc_id', MyBB::INPUT_STRING));
        $error = '';
        if ($npc_id === '') {
            $error = 'NPC ID inválido.';
        } else {
            $previo = $db->fetch_array($db->query("SELECT nombre FROM `mybb_op_npcs` WHERE npc_id='" . $db->escape_string($npc_id) . "'"));
            if (!$previo) {
                $error = 'Ese NPC no existe.';
            } else {
                $db->query("DELETE FROM `mybb_op_npcs` WHERE `npc_id`='" . $db->escape_string($npc_id) . "'");
                $log_texto = "NPC {$npc_id} ({$previo['nombre']}) eliminado por {$username} ({$uid}).";
                $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
                    . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Apertura', '" . $db->escape_string($log_texto) . "')");
            }
        }

        my_setcookie(NPCS_MSG_COOKIE, base64_encode(json_encode(array(
            'tipo'  => $error !== '' ? 'err' : 'ok',
            'texto' => $error !== '' ? $error : "NPC {$npc_id} eliminado.",
        ))), 15, true);
        header('Location: ' . npcs_url($error !== '' ? $npc_id : ''));
        exit;
    }

    // accion === 'guardar' (crear o modificar)
    $npc_id_old = trim($mybb->get_input('npc_id_old', MyBB::INPUT_STRING));
    $npc_id_new = trim($mybb->get_input('npc_id', MyBB::INPUT_STRING));
    $nombre     = trim($mybb->get_input('nombre', MyBB::INPUT_STRING));
    $rango      = trim($mybb->get_input('rango', MyBB::INPUT_STRING));

    $error = '';
    if ($npc_id_new === '') { $error = 'El NPC ID es obligatorio.'; }
    elseif ($nombre === '') { $error = 'El nombre es obligatorio.'; }

    $existe = false;
    $es_rename = false;
    if ($error === '') {
        $existe = (bool) $db->fetch_field($db->query("SELECT npc_id FROM `mybb_op_npcs` WHERE npc_id='" . $db->escape_string($npc_id_new) . "'"), 'npc_id');
        $es_rename = $npc_id_old !== '' && $npc_id_old !== $npc_id_new
            && $db->fetch_field($db->query("SELECT npc_id FROM `mybb_op_npcs` WHERE npc_id='" . $db->escape_string($npc_id_old) . "'"), 'npc_id');

        if ($existe && $es_rename) {
            $error = "Ya existe un NPC con el ID {$npc_id_new}; no se puede renombrar sobre él.";
        }
    }

    if ($error === '') {
        $e = array();
        foreach ($CAMPOS_TEXTO as $c) {
            $e[$c] = $db->escape_string(trim($mybb->get_input($c, MyBB::INPUT_STRING)));
        }
        foreach ($CAMPOS_LARGOS as $c) {
            $val = $mybb->get_input($c, MyBB::INPUT_STRING);
            if ($c === 'info' && trim($val) === '') { $val = 'null'; }
            $e[$c] = $db->escape_string($val);
        }
        $e['rango'] = $db->escape_string($rango);
        $id_esc = $db->escape_string($npc_id_new);
        $nombre_esc = $db->escape_string($nombre);

        $set_sql = "`npc_id`='{$id_esc}', `nombre`='{$nombre_esc}', `apodo`='{$e['apodo']}', `faccion`='{$e['faccion']}', `raza`='{$e['raza']}', "
            . "`edad`='{$e['edad']}', `altura`='{$e['altura']}', `peso`='{$e['peso']}', `sexo`='{$e['sexo']}', `temporada`='{$e['temporada']}', `nivel`='{$e['nivel']}', "
            . "`fuerza`='{$e['fuerza']}', `resistencia`='{$e['resistencia']}', `destreza`='{$e['destreza']}', `voluntad`='{$e['voluntad']}', `punteria`='{$e['punteria']}', "
            . "`agilidad`='{$e['agilidad']}', `reflejos`='{$e['reflejos']}', `control_akuma`='{$e['control_akuma']}', `vitalidad`='{$e['vitalidad']}', `energia`='{$e['energia']}', `haki`='{$e['haki']}', "
            . "`rango`='{$e['rango']}', `sangre`='{$e['sangre']}', `akuma`='{$e['akuma']}', `avatar1`='{$e['avatar1']}', `avatar2`='{$e['avatar2']}', "
            . "`apariencia`='{$e['apariencia']}', `personalidad`='{$e['personalidad']}', `historia1`='{$e['historia1']}', `historia2`='{$e['historia2']}', `historia3`='{$e['historia3']}', "
            . "`extra`='{$e['extra']}', `notas`='{$e['notas']}', `buso`='{$e['buso']}', `kenbun`='{$e['kenbun']}', `haoshoku`='{$e['hao']}', `estilo_combate`='{$e['estilo_combate']}', "
            . "`estilo1`='{$e['estilo1']}', `estilo2`='{$e['estilo2']}', `estilo3`='{$e['estilo3']}', `estilo4`='{$e['estilo4']}', "
            . "`belicas`='{$e['belicas']}', `oficios`='{$e['oficios']}', `estilos`='{$e['estilos']}', `belicas_disponibles`='{$e['belicas_disponibles']}', "
            . "`belica1`='{$e['belica1']}', `belica2`='{$e['belica2']}', `belica3`='{$e['belica3']}', `belica4`='{$e['belica4']}', `belica5`='{$e['belica5']}', `belica6`='{$e['belica6']}', `belica7`='{$e['belica7']}', `belica8`='{$e['belica8']}', "
            . "`oficio1`='{$e['oficio1']}', `oficio2`='{$e['oficio2']}', `info`='{$e['info']}', `wanted`='{$e['wanted']}', `reputacion`='{$e['reputacion']}', `etiqueta`='{$e['etiqueta']}'";

        if ($existe || $es_rename) {
            $id_where = $es_rename ? $npc_id_old : $npc_id_new;
            $db->query("UPDATE `mybb_op_npcs` SET {$set_sql} WHERE `npc_id`='" . $db->escape_string($id_where) . "'");
        } else {
            $db->query("INSERT INTO `mybb_op_npcs` SET {$set_sql}");
        }

        $log_texto = "NPC {$npc_id_new} (" . (($existe || $es_rename) ? 'modificado' : 'creado') . ") por {$username} ({$uid}).";
        $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
            . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Apertura', '" . $db->escape_string($log_texto) . "')");
    }

    my_setcookie(NPCS_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : 'NPC guardado.',
    ))), 15, true);
    header('Location: ' . npcs_url($error !== '' ? $npc_id_old : $npc_id_new));
    exit;
}

// ── GET: formulario ──────────────────────────────────────────────────────────

$npc_id = trim($mybb->get_input('npc_id', MyBB::INPUT_STRING));
$post_key = generate_post_check();

$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[NPCS_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[NPCS_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        if ($msg['tipo'] === 'ok') { $msg_ok = $msg['texto']; } else { $msg_err = $msg['texto']; }
    }
    my_unsetcookie(NPCS_MSG_COOKIE);
}

$npcs_count = (int) $db->fetch_field($db->query("SELECT COUNT(*) AS c FROM `mybb_op_npcs`"), 'c');

$npc = null;
if ($npc_id !== '') {
    $npc = $db->fetch_array($db->query("SELECT * FROM `mybb_op_npcs` WHERE npc_id='" . $db->escape_string($npc_id) . "'"));
}
$npc_existe = $npc ? 1 : 0;

function npc_e($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$campos_mostrar = array(
    'nombre', 'apodo', 'faccion', 'raza', 'edad', 'altura', 'peso', 'sexo', 'temporada', 'nivel',
    'fuerza', 'resistencia', 'destreza', 'voluntad', 'punteria', 'agilidad', 'reflejos', 'control_akuma',
    'vitalidad', 'energia', 'haki', 'kenbun', 'haoshoku', 'buso', 'extra', 'wanted', 'reputacion',
    'sangre', 'akuma', 'avatar1', 'avatar2', 'apariencia', 'personalidad', 'historia1', 'historia2', 'historia3',
    'estilo_combate', 'estilos', 'belicas', 'oficios', 'belicas_disponibles', 'notas', 'info', 'etiqueta',
);
$npc_esc = array();
foreach ($campos_mostrar as $c) {
    $npc_esc[$c] = npc_e($npc ? $npc[$c] : '');
}
$npc_id_esc = npc_e($npc_id);

// <select> pre-armados en PHP: MyBB no soporta llamar a una función dentro de {$...} en un template.
$npc_rango_opciones   = npc_opciones($RANGO_OPCIONES, $npc ? $npc['rango'] : '');
$npc_estilo1_opciones = npc_opciones($ESTILO_OPCIONES, $npc ? $npc['estilo1'] : '');
$npc_estilo2_opciones = npc_opciones($ESTILO_OPCIONES, $npc ? $npc['estilo2'] : '');
$npc_estilo3_opciones = npc_opciones($ESTILO_OPCIONES, $npc ? $npc['estilo3'] : '');
$npc_estilo4_esc      = npc_e($npc ? $npc['estilo4'] : '');
$npc_belica1_opciones = npc_opciones($BELICA_OPCIONES, $npc ? $npc['belica1'] : '');
$npc_belica2_opciones = npc_opciones($BELICA_OPCIONES, $npc ? $npc['belica2'] : '');
$npc_belica3_opciones = npc_opciones($BELICA_OPCIONES, $npc ? $npc['belica3'] : '');
$npc_belica4_opciones = npc_opciones($BELICA_OPCIONES, $npc ? $npc['belica4'] : '');
$npc_belica5_opciones = npc_opciones($BELICA_OPCIONES, $npc ? $npc['belica5'] : '');
$npc_belica6_opciones = npc_opciones($BELICA_OPCIONES, $npc ? $npc['belica6'] : '');
$npc_belica7_opciones = npc_opciones($BELICA_OPCIONES, $npc ? $npc['belica7'] : '');
$npc_belica8_opciones = npc_opciones($BELICA_OPCIONES, $npc ? $npc['belica8'] : '');
$npc_oficio1_opciones = npc_opciones($OFICIO_OPCIONES, $npc ? $npc['oficio1'] : '');
$npc_oficio2_opciones = npc_opciones($OFICIO_OPCIONES, $npc ? $npc['oficio2'] : '');

eval("\$page = \"".$templates->get("staff_npcs_modificar")."\";");
output_page($page);
