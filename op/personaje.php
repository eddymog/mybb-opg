<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/personaje_errors.log');
error_reporting(E_ALL);

/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// uid=0 no es un usuario válido — redirigir sin el parámetro para que global.php
// resuelva al UID real del visitante (o el JS redirect post-global.php lo lleva a ficha_crear)
if (isset($_GET['uid']) && intval($_GET['uid']) === 0) {
    header('Location: /op/personaje.php', true, 302);
    exit;
}

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'personaje.php');

require_once "./../global.php";
require_once "./functions/op_functions.php";
require_once MYBB_ROOT."inc/plugins/lib/spoiler.php";

// ─── Parámetros ──────────────────────────────────────────────────────────────
$query_uid = $mybb->get_input('uid');
if (!$query_uid) { $query_uid = $mybb->user['uid']; }
$user_uid  = $mybb->user['uid'];

// Invitado sin UID válido — redirigir al formulario de creación de ficha
if (intval($query_uid) === 0) {
    echo '<script>window.location.href="/op/ficha_crear.php";</script>';
    exit;
}
$is_owner      = $user_uid == $query_uid;
$username      = $mybb->user['username'];
$accion        = $mybb->get_input('accion');
$objeto_vender = $db->escape_string($mybb->get_input('objeto'));

// ─── Verificar que la ficha existe y es accesible ────────────────────────────
$ficha_existe = false;
$ficha_staff  = false;
$aprobada     = false;

$query_check = $db->query("SELECT aprobada_por, faccion FROM mybb_op_fichas WHERE fid='$query_uid'");
while ($f = $db->fetch_array($query_check)) {
    $aprobada     = $f['aprobada_por'] != 'sin_aprobar';
    $ficha_existe = true;
    $ficha_staff  = $f['faccion'] == 'Staff' && !$g_is_staff;
}

if (!$ficha_existe && $user_uid == $query_uid) {
    // El usuario no tiene ficha y está viendo su propio perfil → creación
    echo '<script>window.location.href="/op/ficha_crear.php";</script>';
    exit;
}

if (!$ficha_existe || $ficha_staff) {
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    return;
}

// ─── Permisos de mutación ─────────────────────────────────────────────────────
$can_mutate = $is_owner || is_mod($user_uid) || is_staff($user_uid);

// ─── Upsert para ficha secreta ────────────────────────────────────────────────
function updateOrInsertSecret($db, $query_uid, $field, $value) {
    $check = $db->query("SELECT fid FROM mybb_op_fichas_secret WHERE fid='$query_uid'");
    if ($db->num_rows($check) == 0) {
        $defaults = [
            'nombre' => '', 'apodo' => '', 'avatar1' => '', 'avatar2' => '',
            'apariencia' => '', 'personalidad' => '', 'historia' => '',
            'extra' => '', 'faccion' => 'Civil', 'rango' => 'civil', 'es_visible' => '0',
        ];
        $defaults[$field] = $value;
        $fields = implode('`, `', array_keys($defaults));
        $vals   = "'" . implode("', '", array_values($defaults)) . "'";
        $db->query("INSERT INTO mybb_op_fichas_secret (`fid`, `$fields`) VALUES ('$query_uid', $vals)");
    } else {
        $db->query("UPDATE mybb_op_fichas_secret SET `$field`='$value' WHERE fid='$query_uid'");
    }
}

if ($can_mutate) {
    // ─── Inputs ───────────────────────────────────────────────────────────────
    $cambiar_avatar1        = $db->escape_string($mybb->get_input('cambiar_avatar1'));
    $cambiar_avatar2        = $db->escape_string($mybb->get_input('cambiar_avatar2'));
    $cambiar_avatar3        = $db->escape_string($mybb->get_input('cambiar_avatar3'));
    $cambiar_avatar4        = $db->escape_string($mybb->get_input('cambiar_avatar4'));
    $cambiar_avatar5        = $db->escape_string($mybb->get_input('cambiar_avatar5'));
    $cambiar_apariencia     = $db->escape_string($mybb->get_input('cambiar_apariencia'));
    $cambiar_personalidad   = $db->escape_string($mybb->get_input('cambiar_personalidad'));
    $cambiar_historia       = $db->escape_string($mybb->get_input('cambiar_historia'));
    $cambiar_extras         = $db->escape_string($mybb->get_input('cambiar_extras'));
    $cambiar_apodo          = $db->escape_string($mybb->get_input('cambiar_apodo'));
    $cambiar_equipamiento   = $db->escape_string($mybb->get_input('cambiar_equipamiento'));
    $cambiar_cronologia     = $db->escape_string($mybb->get_input('cambiar_cronologia'));
    $cambiar_avatar1S1      = $db->escape_string($mybb->get_input('cambiar_avatarS1'));
    $cambiar_avatar2S1      = $db->escape_string($mybb->get_input('cambiar_avatarS2'));
    $cambiar_aparienciaS1   = $db->escape_string($mybb->get_input('cambiar_aparienciaS1'));
    $cambiar_personalidadS1 = $db->escape_string($mybb->get_input('cambiar_personalidadS1'));
    $cambiar_historiaS1     = $db->escape_string($mybb->get_input('cambiar_historiaS1'));
    $cambiar_extrasS1       = $db->escape_string($mybb->get_input('cambiar_extrasS1'));
    $cambiar_apodoS1        = $db->escape_string($mybb->get_input('cambiar_apodoS1'));
    $cambiar_nombreS1       = $db->escape_string($mybb->get_input('cambiar_nombreS1'));
    $cambiar_faccionS1      = $db->escape_string($mybb->get_input('cambiar_faccionS1'));
    $cambiar_visibleS1      = $db->escape_string($mybb->get_input('cambiar_visibleS1'));

    // ─── Actualizaciones de ficha principal ───────────────────────────────────
    if ($cambiar_avatar1      != '') $db->query("UPDATE mybb_op_fichas SET avatar3='$cambiar_avatar1' WHERE fid='$query_uid'");
    if ($cambiar_avatar2      != '') $db->query("UPDATE mybb_op_fichas SET avatar1='$cambiar_avatar2' WHERE fid='$query_uid'");
    if ($cambiar_avatar3      != '') $db->query("UPDATE mybb_op_fichas SET avatar2='$cambiar_avatar3' WHERE fid='$query_uid'");
    if ($cambiar_avatar4      != '') $db->query("UPDATE mybb_users    SET avatar='$cambiar_avatar4'   WHERE uid='$query_uid'");
    if ($cambiar_avatar5      != '') $db->query("UPDATE mybb_op_fichas SET avatar4='$cambiar_avatar5' WHERE fid='$query_uid'");
    if ($cambiar_apariencia   != '') $db->query("UPDATE mybb_op_fichas SET apariencia='$cambiar_apariencia' WHERE fid='$query_uid'");
    if ($cambiar_personalidad != '') $db->query("UPDATE mybb_op_fichas SET personalidad='$cambiar_personalidad' WHERE fid='$query_uid'");
    if ($cambiar_historia     != '') $db->query("UPDATE mybb_op_fichas SET historia='$cambiar_historia' WHERE fid='$query_uid'");
    if ($cambiar_extras       != '') $db->query("UPDATE mybb_op_fichas SET extra='$cambiar_extras' WHERE fid='$query_uid'");
    if ($cambiar_apodo        != '') $db->query("UPDATE mybb_op_fichas SET apodo='$cambiar_apodo' WHERE fid='$query_uid'");
    if ($cambiar_equipamiento != '') $db->query("UPDATE mybb_op_fichas SET equipamiento='$cambiar_equipamiento' WHERE fid='$query_uid'");
    if ($cambiar_cronologia   != '') $db->query("UPDATE mybb_op_fichas SET cronologia='$cambiar_cronologia' WHERE fid='$query_uid'");

    // ─── Actualizaciones de ficha secreta ─────────────────────────────────────
    if ($cambiar_avatar1S1      != '') updateOrInsertSecret($db, $query_uid, 'avatar1',      $cambiar_avatar1S1);
    if ($cambiar_avatar2S1      != '') updateOrInsertSecret($db, $query_uid, 'avatar2',      $cambiar_avatar2S1);
    if ($cambiar_aparienciaS1   != '') updateOrInsertSecret($db, $query_uid, 'apariencia',   $cambiar_aparienciaS1);
    if ($cambiar_personalidadS1 != '') updateOrInsertSecret($db, $query_uid, 'personalidad', $cambiar_personalidadS1);
    if ($cambiar_historiaS1     != '') updateOrInsertSecret($db, $query_uid, 'historia',     $cambiar_historiaS1);
    if ($cambiar_extrasS1       != '') updateOrInsertSecret($db, $query_uid, 'extra',        $cambiar_extrasS1);
    if ($cambiar_apodoS1        != '') updateOrInsertSecret($db, $query_uid, 'apodo',        $cambiar_apodoS1);
    if ($cambiar_nombreS1       != '') updateOrInsertSecret($db, $query_uid, 'nombre',       $cambiar_nombreS1);
    if ($cambiar_faccionS1      != '') updateOrInsertSecret($db, $query_uid, 'faccion',      $cambiar_faccionS1);
    if ($cambiar_visibleS1      != '') updateOrInsertSecret($db, $query_uid, 'es_visible',   $cambiar_visibleS1);

    // ─── Banda sonora (AJAX) ──────────────────────────────────────────────────
    if (isset($_POST['cambiar_banda_sonora'])) {
        $bandaSonora = $db->escape_string($_POST['cambiar_banda_sonora']);
        if (empty($bandaSonora) || preg_match('/(youtube\.com|youtu\.be)/', $bandaSonora)) {
            $db->query("UPDATE mybb_op_fichas SET banda_sonora='{$bandaSonora}' WHERE fid='{$query_uid}'");
            echo "success";
        } else {
            echo "error";
        }
        exit;
    }

    // ─── Respuesta AJAX genérica para campos de mutación ─────────────────────
    $ajax_fields = [
        'cambiar_avatar1', 'cambiar_avatar2', 'cambiar_avatar3', 'cambiar_avatar4', 'cambiar_avatar5',
        'cambiar_apariencia', 'cambiar_personalidad', 'cambiar_historia', 'cambiar_extras', 'cambiar_apodo',
        'cambiar_equipamiento', 'cambiar_cronologia',
        'cambiar_avatarS1', 'cambiar_avatarS2', 'cambiar_aparienciaS1', 'cambiar_personalidadS1',
        'cambiar_historiaS1', 'cambiar_extrasS1', 'cambiar_apodoS1', 'cambiar_nombreS1',
        'cambiar_faccionS1', 'cambiar_visibleS1',
    ];
    foreach ($ajax_fields as $field) {
        if (!empty($_POST[$field])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Datos actualizados correctamente']);
            exit;
        }
    }

    // ─── Edición de objeto personalizado ──────────────────────────────────────
    $edit_objeto_id = isset($_POST['edit_objeto_id']) ? $_POST['edit_objeto_id'] : '';
    $edit_nombre    = $db->escape_string($mybb->get_input('edit_nombre'));
    $edit_imagen    = $db->escape_string($mybb->get_input('edit_imagen'));

    if ($edit_nombre != '' && $edit_imagen != '' && $edit_objeto_id != '') {
        $obj_custom        = null;
        $inventario_custom = null;

        $objeto_custom_query = $db->query("SELECT * FROM mybb_op_objetos WHERE objeto_id='$edit_objeto_id'");
        while ($q = $db->fetch_array($objeto_custom_query)) { $obj_custom = $q; }

        $inventario_custom_query = $db->query("SELECT * FROM mybb_op_inventario WHERE objeto_id='$edit_objeto_id' AND uid='$query_uid'");
        while ($q = $db->fetch_array($inventario_custom_query)) { $inventario_custom = $q; }

        $count_id = 0;
        $objeto_count = $db->query("SELECT count(*) as count FROM mybb_op_objetos WHERE objeto_id LIKE '%$edit_objeto_id-$query_uid%'");
        while ($q = $db->fetch_array($objeto_count)) { $count_id = intval($q['count']) + 1; }

        $already_custom = strpos($edit_objeto_id, '-') !== false;
        $new_nombre     = $already_custom ? $edit_objeto_id : "$edit_objeto_id-$query_uid-$count_id";

        $categoria      = $obj_custom['categoria'];
        $subcategoria   = $obj_custom['subcategoria'];
        $nombre_obj     = $obj_custom['nombre'];
        $tier           = $obj_custom['tier'];
        $imagen_id      = $obj_custom['imagen_id'];
        $imagen_avatar  = $obj_custom['imagen_avatar'];
        $berries_obj    = $obj_custom['berries'];
        $cantidadMaxima = $obj_custom['cantidadMaxima'];
        $dano           = $obj_custom['dano'];
        $efecto         = $obj_custom['efecto'];
        $exclusivo      = $obj_custom['exclusivo'];
        $espacios       = $obj_custom['espacios'];
        $imagen_obj     = $obj_custom['imagen'];
        $desbloquear    = $obj_custom['desbloquear'];
        $oficio_obj     = $obj_custom['oficio'];
        $nivel_obj      = $obj_custom['nivel'];
        $requisitos     = $obj_custom['requisitos'];
        $escalado       = $obj_custom['escalado'];
        $descripcion    = $obj_custom['descripcion'];

        if ($already_custom) {
            $db->query("UPDATE mybb_op_inventario SET imagen='$edit_imagen', apodo='$edit_nombre', editado='1' WHERE objeto_id='$edit_objeto_id' AND uid='$query_uid'");
        } else {
            $inventarioCantidad = intval($inventario_custom['cantidad']);
            if ($inventarioCantidad > 0) {
                $db->query("INSERT INTO mybb_op_objetos(objeto_id,categoria,subcategoria,nombre,tier,imagen_id,imagen_avatar,berries,cantidadMaxima,dano,efecto,exclusivo,espacios,imagen,desbloquear,oficio,nivel,requisitos,escalado,editable,custom,descripcion) VALUES ('$new_nombre','$categoria','$subcategoria','$nombre_obj','$tier','$imagen_id','$imagen_avatar','$berries_obj','$cantidadMaxima','$dano','$efecto','$exclusivo','$espacios','$imagen_obj','$desbloquear','$oficio_obj','$nivel_obj','$requisitos','$escalado','1','1','$descripcion')");
                $db->query("INSERT INTO mybb_op_inventario(objeto_id,uid,cantidad,imagen,apodo,especial,editado) VALUES ('$new_nombre','$query_uid','1','$edit_imagen','$edit_nombre','1','1')");
                if ($inventarioCantidad > 1) {
                    $nueva_cantidad = $inventarioCantidad - 1;
                    $db->query("UPDATE mybb_op_inventario SET cantidad='$nueva_cantidad' WHERE objeto_id='$edit_objeto_id' AND uid='$query_uid'");
                } else {
                    $db->query("DELETE FROM mybb_op_inventario WHERE objeto_id='$edit_objeto_id' AND uid='$query_uid'");
                }
            }
        }
        return;
    }
}

// ─── Mejoras (AJAX, solo owner, antes de cualquier output) ───────────────────
$_mejoras_acciones = ['kenbun','buso','hao','control_akuma','limite_nivel',
    'estilo','estilo1_desbloquear','estilo2_desbloquear','estilo3_desbloquear','estilo4_desbloquear',
    'belica','belica_espe','oficio','oficio_espe'];

if ($is_owner && in_array($accion, $_mejoras_acciones)) {
    $mj = null;
    $mj_q = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$query_uid'");
    while ($r = $db->fetch_array($mj_q)) { $mj = $r; }

    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    if (!$mj) { echo json_encode(['success'=>false,'message'=>'Ficha no encontrada.']); exit; }

    $mj_haki  = ($mj['camino'] == 'Haki');
    $mj_akuma = ($mj['camino'] == 'Akuma');
    $mj_nikas = intval($mj['nika']);
    $mj_nivel = intval($mj['nivel']);
    $mj_lim   = intval($mj['limite_nivel']);
    $mj_pto   = intval($mj['puntos_oficio']);

    $mj_belica_map = [
        'Escudero'=>'{"sub":{"Vanguardia":0,"Bastión":0},"nivel":1}',
        'Artista Marcial'=>'{"sub":{"Monje":0,"Acróbata":0},"nivel":1}',
        'Combatiente'=>'{"sub":{"Berserker":0,"Campeón":0},"nivel":1}',
        'Artista'=>'{"sub":{"Bardo":0,"Trovador":0},"nivel":1}',
        'Asesino'=>'{"sub":{"Sombra":0,"Verdugo":0},"nivel":1}',
        'Guerrero'=>'{"sub":{"Castigador":0,"Warhammer":0},"nivel":1}',
        'Espadachín'=>'{"sub":{"Samurái":0,"Mosquetero":0},"nivel":1}',
        'Tecnicista'=>'{"sub":{"Diletante":0,"WeaponMaster":0},"nivel":1}',
        'Artillero'=>'{"sub":{"Destructor":0,"Juggernaut":0},"nivel":1}',
        'Arquero'=>'{"sub":{"Ballestero":0,"Cazador":0},"nivel":1}',
        'Tirador'=>'{"sub":{"Duelista":0,"Francotirador":0},"nivel":1}',
        'Pícaro'=>'{"sub":{"Gambito":0,"Trickster":0},"nivel":1}',
    ];
    $mj_oficio_map = [
        'Artesano'=>'{"sub":{"Herrero":0,"Modista":0},"nivel":1}',
        'Médico'=>'{"sub":{"Farmacólogo":0,"Doctor":0},"nivel":1}',
        'Navegante'=>'{"sub":{"Cartógrafo":0,"Timonel":0},"nivel":1}',
        'Inventor'=>'{"sub":{"Biólogo":0,"Ingeniero":0},"nivel":1}',
        'Carpintero'=>'{"sub":{"Astillero":0,"Constructor":0},"nivel":1}',
        'Cocinero'=>'{"sub":{"Chef":0,"Aprovisionador":0},"nivel":1}',
        'Mercader'=>'{"sub":{"Comerciante":0,"Contrabandista":0},"nivel":1}',
        'Investigador'=>'{"sub":{"Periodista":0,"Arqueólogo":0},"nivel":1}',
        'Aventurero'=>'{"sub":{"Cazador":0,"Domador":0},"nivel":1}',
        'Recolector'=>'{"sub":{"Agreste":0,"Mayorista":0},"nivel":1}',
    ];

    // ── Haki ─────────────────────────────────────────────────────────────────
    if ($accion == 'kenbun' || $accion == 'buso' || $accion == 'hao') {
        $col  = $accion;
        $cur  = intval($mj[$col]);
        $new  = $cur + 1;
        $cost = 10000;
        if ($cur == 1 && ($mj_nivel >= 15 || ($mj_haki && $mj_nivel >= 10))) { $cost = 10; if ($mj_haki) $cost = 0; }
        if ($cur == 2 && ($mj_nivel >= 20 || ($mj_haki && $mj_nivel >= 15))) $cost = 15;
        if ($cur == 3 && ($mj_nivel >= 25 || ($mj_haki && $mj_nivel >= 20))) $cost = 25;
        if ($cur == 4 && ($mj_nivel >= 30 || ($mj_haki && $mj_nivel >= 25))) $cost = 40;
        if ($cur == 5 && ($mj_nivel >= 35 || ($mj_haki && $mj_nivel >= 30))) $cost = 60;
        if ($cur == 6 && ($mj_nivel >= 40 || ($mj_haki && $mj_nivel >= 35))) $cost = 150;
        if ($mj_nikas >= $cost && $new >= 2 && $new <= 7) {
            $new_nikas = $mj_nikas - $cost;
            $db->query("UPDATE mybb_op_fichas SET `$col`='$new' WHERE fid='$query_uid'");
            log_audit($user_uid, $username, $query_uid, "[Mejoras][Haki]", "$col: $new: $mj_nikas->$new_nikas (Gasto: $cost).");
            log_audit_currency($user_uid, $username, $query_uid, "[Mejoras][".ucfirst($col)." Mejora]", 'nikas', $new_nikas);
            echo json_encode(['success'=>true,'message'=>"¡Has aumentado tu nivel de Haki!",'data'=>[$col=>$new,'nikas'=>$new_nikas]]);
        } else {
            echo json_encode(['success'=>false,'message'=>'No cumples los requisitos para subir este Haki.']);
        }
        exit;
    }

    // ── Control Akuma ─────────────────────────────────────────────────────────
    if ($accion == 'control_akuma') {
        $dom  = intval($mj['dominio_akuma']);
        $cost = 10000;
        if ($mj_akuma) {
            if ($dom==0) $cost=0;  if ($dom==1) $cost=5;  if ($dom==2) $cost=15;
            if ($dom==3) $cost=20; if ($dom==4) $cost=30; if ($dom==5) $cost=80;
        } else {
            if ($dom==0) $cost=5;  if ($dom==1) $cost=10; if ($dom==2) $cost=20;
            if ($dom==3) $cost=25; if ($dom==4) $cost=40;
        }
        if ($mj_nikas >= $cost && $dom >= 0 && $dom <= 5) {
            $new_nikas = $mj_nikas - $cost;
            $new_dom   = $dom + 1;
            $db->query("UPDATE mybb_op_fichas SET dominio_akuma='$new_dom' WHERE fid='$query_uid'");
            log_audit($user_uid, $username, $query_uid, '[Mejoras][Haki]', "Dominio: $new_dom: $mj_nikas->$new_nikas (Gasto: $cost).");
            log_audit_currency($user_uid, $username, $query_uid, '[Mejoras][Dominio Akuma Mejora]', 'nikas', $new_nikas);
            echo json_encode(['success'=>true,'message'=>'¡Has aumentado tu Dominio de Akuma!','data'=>['control_akuma'=>$new_dom,'nikas'=>$new_nikas]]);
        } else {
            echo json_encode(['success'=>false,'message'=>'No cumples los requisitos para aumentar el Dominio de Akuma.']);
        }
        exit;
    }

    // ── Límite de nivel ───────────────────────────────────────────────────────
    if ($accion == 'limite_nivel') {
        if ($mj_lim >= 40) {
            echo json_encode(['success'=>false,'message'=>'El límite de nivel máximo es 40 por ahora.']);
            exit;
        }
        $cost = 50;
        if ($mj_lim >= 20) $cost = 5;  if ($mj_lim >= 25) $cost = 10;
        if ($mj_lim >= 30) $cost = 15; if ($mj_lim >= 35) $cost = 20;
        if ($mj_lim >= 40) $cost = 25;
        if ($mj_nikas >= $cost) {
            $new_nikas = $mj_nikas - $cost;
            $new_lim   = $mj_lim + 1;
            $db->query("UPDATE mybb_op_fichas SET limite_nivel='$new_lim' WHERE fid='$query_uid'");
            log_audit_currency($user_uid, $username, $query_uid, '[Mejoras][Límite Nivel]', 'nikas', $new_nikas);
            log_audit($user_uid, $username, $query_uid, '[Mejoras][Nivel]', "Limite Nivel: $new_lim: $mj_nikas->$new_nikas (Gasto: $cost).");
            echo json_encode(['success'=>true,'message'=>"¡Has aumentado tu límite de nivel a $new_lim!",'data'=>['limite_nivel'=>$new_lim,'nikas'=>$new_nikas]]);
        } else {
            echo json_encode(['success'=>false,'message'=>'No tienes suficientes nikas.']);
        }
        exit;
    }

    // ── Estilos ───────────────────────────────────────────────────────────────
    if ($accion == 'estilo') {
        $estilo  = $db->escape_string($mybb->get_input('estilo'));
        $slot    = $db->escape_string($mybb->get_input('slot'));
        $allowed = ['estilo1','estilo2','estilo3','estilo4'];
        if (in_array($slot, $allowed) && $mj[$slot] == 'no_bloqueado') {
            $db->query("UPDATE mybb_op_fichas SET `$slot`='$estilo' WHERE fid='$query_uid'");
            echo json_encode(['success'=>true,'message'=>"Estilo $estilo asignado.",'data'=>['slot'=>$slot,'estilo'=>$estilo]]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Slot no disponible.']);
        }
        exit;
    }

    if ($accion == 'estilo1_desbloquear') {
        if ($mj_nivel >= 8 && $mj['estilo1'] == 'bloqueado') {
            $db->query("UPDATE mybb_op_fichas SET estilo1='no_bloqueado' WHERE fid='$query_uid'");
            echo json_encode(['success'=>true,'message'=>'¡Desbloqueaste el primer slot de estilo!','data'=>['slot'=>'estilo1']]);
        } else { echo json_encode(['success'=>false,'message'=>'No cumples los requisitos (nivel 8 mínimo).']); }
        exit;
    }

    if ($accion == 'estilo2_desbloquear') {
        $new_nikas = $mj_nikas - 25;
        if ($new_nikas >= 0 && $mj_nivel >= 16 && $mj['estilo2'] == 'bloqueado') {
            $db->query("UPDATE mybb_op_fichas SET estilo2='no_bloqueado' WHERE fid='$query_uid'");
            log_audit_currency($user_uid, $username, $query_uid, '[Mejoras][Estilo 2 Mejora]', 'nikas', $new_nikas);
            echo json_encode(['success'=>true,'message'=>'¡Desbloqueaste el segundo slot de estilo!','data'=>['slot'=>'estilo2','nikas'=>$new_nikas]]);
        } else { echo json_encode(['success'=>false,'message'=>'No cumples los requisitos (nivel 16, 25 nikas).']); }
        exit;
    }

    if ($accion == 'estilo3_desbloquear') {
        $new_nikas = $mj_nikas - 50;
        if ($new_nikas >= 0 && $mj_nivel >= 25 && $mj['estilo3'] == 'bloqueado') {
            $db->query("UPDATE mybb_op_fichas SET estilo3='no_bloqueado' WHERE fid='$query_uid'");
            log_audit_currency($user_uid, $username, $query_uid, '[Mejoras][Estilo 3 Mejora]', 'nikas', $new_nikas);
            echo json_encode(['success'=>true,'message'=>'¡Desbloqueaste el tercer slot de estilo!','data'=>['slot'=>'estilo3','nikas'=>$new_nikas]]);
        } else { echo json_encode(['success'=>false,'message'=>'No cumples los requisitos (nivel 25, 50 nikas).']); }
        exit;
    }

    if ($accion == 'estilo4_desbloquear') {
        $new_nikas = $mj_nikas - 75;
        if ($new_nikas >= 0 && $mj_nivel >= 35 && $mj['estilo4'] == 'bloqueado') {
            $db->query("UPDATE mybb_op_fichas SET estilo4='no_bloqueado' WHERE fid='$query_uid'");
            log_audit_currency($user_uid, $username, $query_uid, '[Mejoras][Estilo 4 Mejora]', 'nikas', $new_nikas);
            echo json_encode(['success'=>true,'message'=>'¡Desbloqueaste el cuarto slot de estilo!','data'=>['slot'=>'estilo4','nikas'=>$new_nikas]]);
        } else { echo json_encode(['success'=>false,'message'=>'No cumples los requisitos (nivel 35, 75 nikas).']); }
        exit;
    }

    // ── Belica ────────────────────────────────────────────────────────────────
    if ($accion == 'belica') {
        $belicaNumber = $db->escape_string($mybb->get_input('belicaNumber'));
        $belica       = $db->escape_string($mybb->get_input('belica'));
        $costs = ['belica2'=>10,'belica3'=>20,'belica4'=>35,'belica5'=>50,'belica6'=>65,
                  'belica7'=>80,'belica8'=>95,'belica9'=>110,'belica10'=>125,'belica11'=>140,'belica12'=>155];
        $cost      = isset($costs[$belicaNumber]) ? $costs[$belicaNumber] : 10000;
        $new_nikas = $mj_nikas - $cost;
        if ($new_nikas >= 0) {
            $bl_obj = json_decode($mj['belicas']);
            $bl_obj->{$belica} = isset($mj_belica_map[$belica]) ? json_decode($mj_belica_map[$belica]) : null;
            $bl_json = json_encode($bl_obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $db->query("UPDATE mybb_op_fichas SET belicas='".$db->escape_string($bl_json)."', `$belicaNumber`='$belica' WHERE fid='$query_uid'");
            log_audit($user_uid, $username, $query_uid, '[Mejoras][Disciplina]', "Nueva: $belica; $mj_nikas->$new_nikas (Gasto: $cost).");
            log_audit_currency($user_uid, $username, $query_uid, '[Mejoras][Belica Mejora]', 'nikas', $new_nikas);
            echo json_encode(['success'=>true,'message'=>"¡Has aprendido $belica!",'data'=>['belica'=>$belica,'belicaNumber'=>$belicaNumber,'belicasJson'=>$bl_json,'nikas'=>$new_nikas]]);
        } else {
            echo json_encode(['success'=>false,'message'=>'No tienes suficientes nikas.']);
        }
        exit;
    }

    // ── Belica espe ───────────────────────────────────────────────────────────
    if ($accion == 'belica_espe') {
        $espe         = $db->escape_string($mybb->get_input('espe'));
        $espeNumber   = $db->escape_string($mybb->get_input('espeNumber'));
        $belicaNumber = $db->escape_string($mybb->get_input('belicaNumber'));
        $belica       = $mj[$belicaNumber];
        $bl_obj       = json_decode($mj['belicas']);
        $costs_e1 = ['belica1'=>0,'belica2'=>20,'belica3'=>30,'belica4'=>45,'belica5'=>60,'belica6'=>75,
            'belica7'=>90,'belica8'=>105,'belica9'=>120,'belica10'=>135,'belica11'=>150,'belica12'=>165];
        $costs_e2 = ['belica1'=>15,'belica2'=>30,'belica3'=>40,'belica4'=>60,'belica5'=>75,'belica6'=>90,
            'belica7'=>105,'belica8'=>120,'belica9'=>135,'belica10'=>150,'belica11'=>165,'belica12'=>180];
        $costs_up = ['belica1'=>45,'belica2'=>60,'belica3'=>75,'belica4'=>100,'belica5'=>125,'belica6'=>150,
            'belica7'=>175,'belica8'=>200,'belica9'=>225,'belica10'=>250,'belica11'=>275,'belica12'=>300];
        $espe_nivel = $bl_obj->{$belica}->{'sub'}->{$espe} ?? -1;
        if ($espe_nivel === 0) {
            $cost = ($espeNumber == 'espe1') ? ($costs_e1[$belicaNumber] ?? 10000) : ($costs_e2[$belicaNumber] ?? 10000);
            if ($mj_nikas >= $cost) {
                $new_nikas = $mj_nikas - $cost;
                $bl_obj->{$belica}->{'sub'}->{$espe} = 1;
                $bl_obj->{$belica}->{$espeNumber} = $espe;
                $bl_json = json_encode($bl_obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $db->query("UPDATE mybb_op_fichas SET belicas='".$db->escape_string($bl_json)."', `$belicaNumber`='$belica' WHERE fid='$query_uid'");
                log_audit($user_uid, $username, $query_uid, '[Mejoras][Camino]', "Elegido: $espe en $belica; $mj_nikas->$new_nikas (Gasto: $cost).");
                log_audit_currency($user_uid, $username, $query_uid, '[Mejoras][Belica Espe Mejora]', 'nikas', $new_nikas);
                echo json_encode(['success'=>true,'message'=>"¡Has elegido el camino $espe!",'data'=>['espe'=>$espe,'espeNumber'=>$espeNumber,'belica'=>$belica,'belicaNumber'=>$belicaNumber,'belicasJson'=>$bl_json,'nikas'=>$new_nikas]]);
            } else { echo json_encode(['success'=>false,'message'=>'No tienes suficientes nikas.']); }
        } elseif ($espe_nivel === 1) {
            $cost = $costs_up[$belicaNumber] ?? 10000;
            if ($mj_nikas >= $cost) {
                $new_nikas = $mj_nikas - $cost;
                $bl_obj->{$belica}->{'sub'}->{$espe} = 2;
                $bl_json = json_encode($bl_obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $db->query("UPDATE mybb_op_fichas SET belicas='".$db->escape_string($bl_json)."', `$belicaNumber`='$belica' WHERE fid='$query_uid'");
                log_audit($user_uid, $username, $query_uid, '[Mejoras][Camino]', "Subido: $espe en $belica; $mj_nikas->$new_nikas (Gasto: $cost).");
                log_audit_currency($user_uid, $username, $query_uid, '[Mejoras][Belica Espe Mejora]', 'nikas', $new_nikas);
                echo json_encode(['success'=>true,'message'=>"¡Has subido de nivel el camino $espe!",'data'=>['espe'=>$espe,'espeNumber'=>$espeNumber,'belica'=>$belica,'belicaNumber'=>$belicaNumber,'belicasJson'=>$bl_json,'nikas'=>$new_nikas]]);
            } else { echo json_encode(['success'=>false,'message'=>'No tienes suficientes nikas.']); }
        } else { echo json_encode(['success'=>false,'message'=>'Estado del camino no válido.']); }
        exit;
    }

    // ── Oficio ────────────────────────────────────────────────────────────────
    if ($accion == 'oficio') {
        $oficio       = $db->escape_string($mybb->get_input('oficio'));
        $oficioNumber = $db->escape_string($mybb->get_input('oficioNumber'));
        $of_obj       = json_decode($mj['oficios']);
        $new_pto      = $mj_pto;
        if (isset($of_obj->{$oficio})) {
            $new_pto = $mj_pto - 1000;
            $of_obj->{$oficio}->{'nivel'} = 2;
            $msg = "¡Has subido $oficio a nivel 2!";
        } else {
            $of_obj->{$oficio} = isset($mj_oficio_map[$oficio]) ? json_decode($mj_oficio_map[$oficio]) : null;
            $msg = "¡Has aprendido el oficio $oficio!";
        }
        $of_json = json_encode($of_obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $db->query("UPDATE mybb_op_fichas SET oficios='".$db->escape_string($of_json)."', `$oficioNumber`='$oficio', puntos_oficio='$new_pto' WHERE fid='$query_uid'");
        log_audit($user_uid, $username, $query_uid, '[Mejoras][Oficio]', "$msg; $oficioNumber=$oficio, puntos_oficio=$new_pto");
        log_audit_currency($user_uid, $username, $query_uid, '[Mejoras][Oficio Mejora]', 'puntos_oficio', $new_pto);
        echo json_encode(['success'=>true,'message'=>$msg,'data'=>['oficio'=>$oficio,'oficioNumber'=>$oficioNumber,'oficiosJson'=>$of_json,'puntos_oficio'=>$new_pto]]);
        exit;
    }

    // ── Oficio espe ───────────────────────────────────────────────────────────
    if ($accion == 'oficio_espe') {
        $espe         = $db->escape_string($mybb->get_input('espe'));
        $espeNumber   = $db->escape_string($mybb->get_input('espeNumber'));
        $oficioNumber = $db->escape_string($mybb->get_input('oficioNumber'));
        $oficio       = $mj[$oficioNumber];
        $of_obj       = json_decode($mj['oficios']);
        $new_nikas    = $mj_nikas;
        $new_pto      = $mj_pto;
        $raw_nivel    = $of_obj->{$oficio}->{'sub'}->{$espe} ?? null;
        $espe_nivel   = ($raw_nivel !== null) ? intval($raw_nivel) : -1;

        if ($espe_nivel === 0) {
            $mj_espe_slot = isset($of_obj->{$oficio}->{'espe1'}) ? 'espe2' : 'espe1';
            $new_nikas = $mj_nikas - 10; $new_pto = $mj_pto - 2000;
            $of_obj->{$oficio}->{'sub'}->{$espe} = 1;
            $of_obj->{$oficio}->{$mj_espe_slot} = $espe;
            $msg = "¡Has elegido la especialización $espe!";
        } elseif ($espe_nivel === 1) {
            $new_nikas = $mj_nikas - 25; $new_pto = $mj_pto - 3500;
            $of_obj->{$oficio}->{'sub'}->{$espe} = 2;
            $msg = "¡Has subido la especialización $espe a nivel 2!";
        } elseif ($espe_nivel === 2) {
            $new_nikas = $mj_nikas - 50; $new_pto = $mj_pto - 5000;
            $of_obj->{$oficio}->{'sub'}->{$espe} = 3;
            $msg = "¡Has subido la especialización $espe a nivel 3!";
        } else {
            echo json_encode(['success'=>false,'message'=>'Estado de especialización no válido.']); exit;
        }
        if ($new_nikas < 0 || $new_pto < 0) { echo json_encode(['success'=>false,'message'=>'No tienes suficientes recursos.']); exit; }
        $of_json = json_encode($of_obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $db->query("UPDATE mybb_op_fichas SET oficios='".$db->escape_string($of_json)."', `$oficioNumber`='$oficio' WHERE fid='$query_uid'");
        log_audit($user_uid, $username, $query_uid, '[Mejoras][Oficio Espe]', "$msg; $mj_nikas->$new_nikas; $mj_pto->$new_pto");
        log_audit_currency($user_uid, $username, $query_uid, '[Mejoras][Oficio Espe Mejora]', 'puntos_oficio', $new_pto);
        log_audit_currency($user_uid, $username, $query_uid, '[Mejoras][Oficio Espe Nikas]', 'nikas', $new_nikas);
        echo json_encode(['success'=>true,'message'=>$msg,'data'=>['espe'=>$espe,'espeNumber'=>$espeNumber,'oficioNumber'=>$oficioNumber,'oficiosJson'=>$of_json,'nikas'=>$new_nikas,'puntos_oficio'=>$new_pto]]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Acción no reconocida.']);
    exit;
}

// ─── Ticket de Reset (AJAX, solo owner) ──────────────────────────────────────
if ($is_owner && in_array($user_uid, [850, 258, 157]) && $accion === 'ticket_reset') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');

    $tq = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$user_uid' AND objeto_id='TNP001' LIMIT 1");
    $ticket_row = $db->fetch_array($tq);
    if (!$ticket_row) {
        echo json_encode(['ok' => false, 'error' => 'No tienes el Ticket de Reset de Build en tu inventario.']);
        exit;
    }

    $pq = $db->query("SELECT id FROM mybb_op_peticiones WHERE uid='$user_uid' AND categoria='reset' AND resuelto=0 LIMIT 1");
    if ($db->num_rows($pq) > 0) {
        echo json_encode(['ok' => false, 'error' => 'Ya tienes una petición de reset pendiente de resolución.']);
        exit;
    }

    $fq = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$user_uid' LIMIT 1");
    $fd = $db->fetch_array($fq);
    if (!$fd) {
        echo json_encode(['ok' => false, 'error' => 'No se encontró tu ficha.']);
        exit;
    }

    // Snapshot of current ficha state
    $backup_fields = ['fuerza','resistencia','reflejos','punteria','voluntad','agilidad','destreza',
        'fuerza_pasiva','resistencia_pasiva','reflejos_pasiva','punteria_pasiva','voluntad_pasiva','agilidad_pasiva','destreza_pasiva',
        'puntos_estadistica',
        'belica1','belica2','belica3','belica4','belica5','belica6',
        'belica7','belica8','belica9','belica10','belica11','belica12','belicas',
        'estilo1','estilo2','estilo3','estilo4',
        'oficio1','oficio2','oficios','puntos_oficio',
        'kenbun','buso','hao','dominio_akuma',
        'akuma','akuma_subnombre','camino','nika'];
    $backup = [];
    foreach ($backup_fields as $f) { $backup[$f] = $fd[$f] ?? ''; }

    // Collect and validate propuesta from POST r_* fields
    $belica_valid = ['Escudero','Artista Marcial','Combatiente','Artista','Asesino','Guerrero','Espadachín','Tecnicista','Artillero','Arquero','Tirador','Pícaro'];
    $oficio_valid = ['Artesano','Médico','Navegante','Inventor','Carpintero','Cocinero','Mercader','Investigador','Aventurero','Recolector'];
    $camino_valid = ['','Voz','Haki','Akuma'];

    $propuesta = [];
    $propuesta['resetear_atributos'] = !empty($_POST['r_resetear_atributos']);
    $belica_raw = [];
    for ($i = 1; $i <= 12; $i++) {
        $v = trim($_POST["r_belica$i"] ?? '');
        $belica_raw[$i] = in_array($v, $belica_valid) ? $v : '';
    }
    $belica_filled = count(array_filter($belica_raw));
    if ($belica_filled > 6) {
        echo json_encode(['ok' => false, 'error' => 'No puedes seleccionar más de 6 disciplinas en el reset.']);
        exit;
    }
    foreach ($belica_raw as $i => $v) { $propuesta["belica$i"] = $v; }
    $estilo_valid = ['bloqueado','no_bloqueado','Gunkata','Hasshoken','Santoryu','Kuroashi','Gyojin Karate','Gyojin Bukijutsu','Gyojin Jujutsu','Rokushiki','Okama Kempo','Sora Yokujin','Ninjutsu','Ryusoken','Jiyuumura Kempo','Pop Green','Clima Tact','Funekiri','Hakai Shin','Railgun Style','Shuron Hakke','Raqisat Alsahra','Breeskjold','Impacto Explosivo','Royal Guard','Shikaku Teikoku','Duelliste de Givre','Filo Della Vita','Havets Symfoni','Bakudai Karin','Yama Kurai','Shiseiju','Sea Corsair','Wano Nitoryu','Mano de Tahur','Kokudan','Kodai no Bushido','Ittoryu Sekai','Global Performer','Cavalry Warrior','Ashigara Dokoi','Kanpo Kenpo'];
    for ($i = 1; $i <= 4; $i++) {
        $v = trim($_POST["r_estilo$i"] ?? '');
        $propuesta["estilo$i"] = in_array($v, $estilo_valid) ? $v : ($fd["estilo$i"] ?? 'bloqueado');
    }
    // Forzar bloqueado en slots cuyo nivel requerido no se alcanza
    $estilo_level_reqs = [1=>8, 2=>16, 3=>25, 4=>35];
    for ($i = 1; $i <= 4; $i++) {
        if (($backup["estilo$i"] ?? 'bloqueado') === 'bloqueado' && (int)$fd['nivel'] < $estilo_level_reqs[$i]) {
            $propuesta["estilo$i"] = 'bloqueado';
        }
    }
    $v = trim($_POST['r_oficio1'] ?? '');
    $propuesta['oficio1'] = in_array($v, $oficio_valid) ? $v : $fd['oficio1'];
    $v = trim($_POST['r_oficio2'] ?? '');
    $propuesta['oficio2'] = in_array($v, $oficio_valid) ? $v : '';

    // Oficio nivel and sub-specializations
    $oficio_subs_map_h = [
        'Artesano'    => ['Herrero','Modista'],
        'Médico'      => ['Farmacólogo','Doctor'],
        'Navegante'   => ['Cartógrafo','Timonel'],
        'Inventor'    => ['Biólogo','Ingeniero'],
        'Carpintero'  => ['Astillero','Constructor'],
        'Cocinero'    => ['Chef','Aprovisionador'],
        'Mercader'    => ['Comerciante','Contrabandista'],
        'Investigador'=> ['Periodista','Arqueólogo'],
        'Aventurero'  => ['Cazador','Domador'],
        'Recolector'  => ['Agreste','Mayorista'],
    ];
    $cur_of_backup = json_decode($backup['oficios'] ?? '{}', true) ?: [];
    $cur_of_nikas  = 0;
    $cur_of_pto    = 0;
    foreach ($cur_of_backup as $of_d) {
        if (($of_d['nivel'] ?? 1) >= 2) $cur_of_pto += 1000;
        foreach (($of_d['sub'] ?? []) as $sl_v) {
            $sl = (int)$sl_v;
            if ($sl >= 1) { $cur_of_pto += 2000; $cur_of_nikas += 10; }
            if ($sl >= 2) { $cur_of_pto += 3500; $cur_of_nikas += 25; }
            if ($sl >= 3) { $cur_of_pto += 5000; $cur_of_nikas += 50; }
        }
    }
    for ($oi = 1; $oi <= 2; $oi++) {
        $of_n = $propuesta["oficio$oi"] ?? '';
        if (empty($of_n) || !isset($oficio_subs_map_h[$of_n])) {
            $propuesta["oficio{$oi}_nivel"]      = 1;
            $propuesta["oficio{$oi}_sub1_nivel"] = 0;
            $propuesta["oficio{$oi}_sub2_nivel"] = 0;
            continue;
        }
        $propuesta["oficio{$oi}_nivel"]      = min(2, max(1, (int)($_POST["r_oficio{$oi}_nivel"] ?? 1)));
        $propuesta["oficio{$oi}_sub1_nivel"] = min(3, max(0, (int)($_POST["r_oficio{$oi}_sub1_nivel"] ?? 0)));
        $propuesta["oficio{$oi}_sub2_nivel"] = min(3, max(0, (int)($_POST["r_oficio{$oi}_sub2_nivel"] ?? 0)));
    }
    $new_of_nikas = 0;
    $new_of_pto   = 0;
    for ($oi = 1; $oi <= 2; $oi++) {
        if (empty($propuesta["oficio$oi"])) continue;
        if (($propuesta["oficio{$oi}_nivel"] ?? 1) >= 2) $new_of_pto += 1000;
        foreach ([1, 2] as $si) {
            $sl = (int)($propuesta["oficio{$oi}_sub{$si}_nivel"] ?? 0);
            if ($sl >= 1) { $new_of_pto += 2000; $new_of_nikas += 10; }
            if ($sl >= 2) { $new_of_pto += 3500; $new_of_nikas += 25; }
            if ($sl >= 3) { $new_of_pto += 5000; $new_of_nikas += 50; }
        }
    }
    $pto_actual = (int)$fd['puntos_oficio'];
    $pto_budget = $pto_actual + $cur_of_pto;
    if ($new_of_pto > $pto_budget) {
        echo json_encode(['ok' => false, 'error' => "El build de oficios requiere {$new_of_pto} pts de oficio, pero dispones de {$pto_budget} ({$pto_actual} actuales + {$cur_of_pto} de reembolso)."]);
        exit;
    }

    $is_haki_camino_h = ($fd['camino'] === 'Haki');
    $kenbun_reqs_h = $is_haki_camino_h ? [2=>5,3=>15,4=>20,5=>25,6=>30,7=>35] : [2=>10,3=>20,4=>25,5=>30,6=>35,7=>40];
    $bh_reqs_h     = $is_haki_camino_h ? [2=>10,3=>15,4=>20,5=>25,6=>30,7=>35] : [2=>15,3=>20,4=>25,5=>30,6=>35,7=>40];
    $fd_nivel_h    = (int)$fd['nivel'];
    $get_max_h = function($reqs, $nivel) { $m = 1; foreach ($reqs as $l => $r) { if ($nivel >= $r) $m = $l; } return $m; };
    $max_kenbun_h = $get_max_h($kenbun_reqs_h, $fd_nivel_h);
    $max_bh_h     = $get_max_h($bh_reqs_h, $fd_nivel_h);
    $cur_kenbun_h = (int)$fd['kenbun']; $cur_buso_h = (int)$fd['buso']; $cur_hao_h = (int)$fd['hao'];
    $propuesta['kenbun'] = $cur_kenbun_h <= 0 ? 0 : max(1, min($max_kenbun_h, (int)($_POST['r_kenbun'] ?? $cur_kenbun_h)));
    $propuesta['buso']   = $cur_buso_h   <= 0 ? 0 : max(1, min($max_bh_h,     (int)($_POST['r_buso']   ?? $cur_buso_h)));
    $propuesta['hao']    = $cur_hao_h    <= 0 ? $cur_hao_h : max(1, min($max_bh_h, (int)($_POST['r_hao'] ?? $cur_hao_h)));
    $akuma_origen_h  = $fd['akuma_origen'] ?? '';
    $current_akuma_h = $fd['akuma'];
    if ($akuma_origen_h === 'aventura') {
        $new_akuma_h = mb_substr(trim($_POST['r_akuma'] ?? ''), 0, 100, 'UTF-8');
        $propuesta['akuma']           = $new_akuma_h;
        $propuesta['akuma_subnombre'] = mb_substr(trim($_POST['r_akuma_subnombre'] ?? ''), 0, 100, 'UTF-8');
        if ($new_akuma_h === '') {
            $propuesta['dominio_akuma'] = 0;
        } elseif ($new_akuma_h !== $current_akuma_h) {
            $propuesta['dominio_akuma'] = (int)$fd['dominio_akuma'];
        } else {
            $propuesta['dominio_akuma'] = max(0, min(6, (int)($_POST['r_dominio_akuma'] ?? $fd['dominio_akuma'])));
        }
    } else {
        $propuesta['akuma']           = $current_akuma_h;
        $propuesta['akuma_subnombre'] = $fd['akuma_subnombre'];
        $propuesta['dominio_akuma']   = max(0, min(6, (int)($_POST['r_dominio_akuma'] ?? $fd['dominio_akuma'])));
    }
    $v = trim($_POST['r_camino'] ?? '');
    $propuesta['camino']        = in_array($v, $camino_valid) ? $v : $fd['camino'];
    $propuesta['nika_ajuste']            = 0; // staff-only field
    $propuesta['puntos_oficio_ajuste']   = 0; // staff-only field
    $propuesta['notas']         = mb_substr(trim($_POST['r_notas'] ?? ''), 0, 2000, 'UTF-8');

    // ── Caminos de disciplinas ────────────────────────────────────────────────────
    $caminos_map_h = [
        'Escudero'       => ['Vanguardia',   'Bastión'],
        'Artista Marcial'=> ['Acróbata',     'Monje'],
        'Combatiente'    => ['Berserker',    'Campeón'],
        'Artista'        => ['Bardo',        'Trovador'],
        'Asesino'        => ['Sombra',       'Verdugo'],
        'Guerrero'       => ['Castigador',   'Warhammer'],
        'Espadachín'     => ['Samurái',      'Mosquetero'],
        'Tecnicista'     => ['Diletante',    'WeaponMaster'],
        'Artillero'      => ['Destructor',   'Juggernaut'],
        'Arquero'        => ['Ballestero',   'Cazador'],
        'Tirador'        => ['Duelista',     'Francotirador'],
        'Pícaro'         => ['Gambito',      'Trickster'],
    ];
    $fd_nivel_caminos_h = (int)$fd['nivel'];
    for ($i = 1; $i <= 12; $i++) {
        $disc  = $propuesta["belica$i"] ?? '';
        $avail = $caminos_map_h[$disc] ?? [];
        $e1    = trim($_POST["r_belica{$i}_espe1"] ?? '');
        $e2    = trim($_POST["r_belica{$i}_espe2"] ?? '');
        $e1n   = (int)($_POST["r_belica{$i}_espe1_nivel"] ?? 1);
        $e2n   = (int)($_POST["r_belica{$i}_espe2_nivel"] ?? 1);
        if (!in_array($e1, $avail)) $e1 = '';
        if (!in_array($e2, $avail)) $e2 = '';
        if ($e1 === '' || $e1 === $e2) $e2 = '';
        if (!$disc) { $e1 = ''; $e2 = ''; }
        if ($e1 !== '' && $fd_nivel_caminos_h < 8)  $e1 = '';
        if ($e2 !== '' && $fd_nivel_caminos_h < 8)  $e2 = '';
        if ($e1 === '') { $e1n = 1; } elseif ($e1n >= 2 && $fd_nivel_caminos_h >= 20) { $e1n = 2; } else { $e1n = 1; }
        if ($e2 === '') { $e2n = 1; } elseif ($e2n >= 2 && $e1n < 2 && $fd_nivel_caminos_h >= 20) { $e2n = 2; } else { $e2n = 1; }
        $propuesta["belica{$i}_espe1"]       = $e1;
        $propuesta["belica{$i}_espe1_nivel"] = $e1n;
        $propuesta["belica{$i}_espe2"]       = $e2;
        $propuesta["belica{$i}_espe2_nivel"] = $e2n;
    }

    // ── Validación: coste del build propuesto vs. presupuesto ────────────────────
    $slot_costs_b = ['belica2'=>10,'belica3'=>20,'belica4'=>35,'belica5'=>50,'belica6'=>65,
                     'belica7'=>80,'belica8'=>95,'belica9'=>110,'belica10'=>125,'belica11'=>140,'belica12'=>155];
    $slot_costs_e = ['estilo2'=>25,'estilo3'=>50,'estilo4'=>75];
    $costes_espe1_h = [1=>0,2=>20,3=>30,4=>45,5=>60,6=>75,7=>90,8=>105,9=>120,10=>135,11=>150,12=>165];
    $costes_espe2_h = [1=>15,2=>30,3=>40,4=>60,5=>75,6=>90,7=>105,8=>120,9=>135,10=>150,11=>165,12=>180];
    $costes_spec_h  = [1=>45,2=>60,3=>75,4=>100,5=>125,6=>150,7=>175,8=>200,9=>225,10=>250,11=>275,12=>300];
    $costes_maest_h = [1=>90,2=>120,3=>150,4=>200,5=>250,6=>300,7=>350,8=>400,9=>450,10=>500,11=>550,12=>600];
    $cur_belicas_json_h = json_decode($backup['belicas'] ?? '{}', true) ?: [];
    $current_build_cost = 0;
    for ($i = 2; $i <= 12; $i++) {
        if (!empty($backup["belica$i"])) $current_build_cost += $slot_costs_b["belica$i"];
    }
    for ($i = 2; $i <= 4; $i++) {
        if (($backup["estilo$i"] ?? 'bloqueado') !== 'bloqueado') $current_build_cost += $slot_costs_e["estilo$i"];
    }
    for ($i = 1; $i <= 12; $i++) {
        $disc = $backup["belica$i"] ?? '';
        if (!$disc) continue;
        $dd = $cur_belicas_json_h[$disc] ?? null; if (!$dd) continue;
        $e1 = $dd['espe1'] ?? '';
        if ($e1 !== '') {
            $current_build_cost += $costes_espe1_h[$i];
            $e1_sub_lvl = (int)($dd['sub'][$e1] ?? 0);
            if ($e1_sub_lvl >= 2) $current_build_cost += $costes_spec_h[$i];
            if ($e1_sub_lvl >= 3) $current_build_cost += $costes_maest_h[$i];
        }
        $e2_bk = $dd['espe2'] ?? '';
        if ($e2_bk !== '') {
            $current_build_cost += $costes_espe2_h[$i];
            $e2_sub_lvl = (int)($dd['sub'][$e2_bk] ?? 0);
            if ($e2_sub_lvl >= 2) $current_build_cost += $costes_spec_h[$i];
            if ($e2_sub_lvl >= 3) $current_build_cost += $costes_maest_h[$i];
        }
    }
    $proposed_build_cost = 0;
    for ($i = 2; $i <= 12; $i++) {
        if (!empty($propuesta["belica$i"])) $proposed_build_cost += $slot_costs_b["belica$i"];
    }
    for ($i = 2; $i <= 4; $i++) {
        if (($propuesta["estilo$i"] ?? 'bloqueado') !== 'bloqueado') $proposed_build_cost += $slot_costs_e["estilo$i"];
    }
    for ($i = 1; $i <= 12; $i++) {
        $e1 = $propuesta["belica{$i}_espe1"] ?? '';
        if ($e1 !== '') {
            $proposed_build_cost += $costes_espe1_h[$i];
            $e1n_lvl = (int)($propuesta["belica{$i}_espe1_nivel"] ?? 1);
            if ($e1n_lvl >= 2) $proposed_build_cost += $costes_spec_h[$i];
        }
        $e2 = $propuesta["belica{$i}_espe2"] ?? '';
        if ($e2 !== '') {
            $proposed_build_cost += $costes_espe2_h[$i];
            $e2n_lvl = (int)($propuesta["belica{$i}_espe2_nivel"] ?? 1);
            if ($e2n_lvl >= 2) $proposed_build_cost += $costes_spec_h[$i];
        }
    }
    $nika_actual          = (int)$fd['nika'];
    $nika_budget          = $nika_actual + $current_build_cost + $cur_of_nikas;
    $total_proposed_nikas = $proposed_build_cost + $new_of_nikas;
    if ($total_proposed_nikas > $nika_budget) {
        echo json_encode(['ok' => false, 'error' => "El build propuesto cuesta {$total_proposed_nikas} nikas pero solo dispones de {$nika_budget} ({$nika_actual} actuales + {$current_build_cost} reembolso disciplinas + {$cur_of_nikas} reembolso oficios)."]);
        exit;
    }

    // Build auto-summary from detected changes
    $cambios = [];
    if ($propuesta['resetear_atributos']) $cambios[] = 'Reset atributos';
    for ($i = 1; $i <= 12; $i++) {
        $ov = $backup["belica$i"] ?? ''; $nv = $propuesta["belica$i"];
        if ($nv !== $ov) $cambios[] = "B{$i}:".($ov ?: '∅')."→".($nv ?: '∅');
    }
    for ($i = 1; $i <= 4; $i++) {
        $ov = $backup["estilo$i"] ?? ''; $nv = $propuesta["estilo$i"];
        if ($nv !== $ov) $cambios[] = "E{$i}:".($ov ?: '∅')."→".($nv ?: '∅');
    }
    if ($propuesta['oficio1'] !== ($backup['oficio1'] ?? '')) $cambios[] = "O1:".($backup['oficio1']??'∅')."→".$propuesta['oficio1'];
    if ($propuesta['oficio2'] !== ($backup['oficio2'] ?? '')) $cambios[] = "O2:".($backup['oficio2']??'∅')."→".$propuesta['oficio2'];
    for ($oi = 1; $oi <= 2; $oi++) {
        $of_n = $propuesta["oficio$oi"] ?? '';
        if (!$of_n) continue;
        $bk_of_d = $cur_of_backup[$of_n] ?? null;
        $bk_nv   = $bk_of_d ? (int)($bk_of_d['nivel'] ?? 1) : 1;
        $new_nv  = (int)($propuesta["oficio{$oi}_nivel"] ?? 1);
        if ($new_nv !== $bk_nv) $cambios[] = "O{$oi}_nivel:{$bk_nv}→{$new_nv}";
        $subs_h = $oficio_subs_map_h[$of_n] ?? [];
        foreach ([1, 2] as $si) {
            $sn  = $subs_h[$si-1] ?? '';
            if (!$sn) continue;
            $old_sl = $bk_of_d ? (int)($bk_of_d['sub'][$sn] ?? 0) : 0;
            $new_sl = (int)($propuesta["oficio{$oi}_sub{$si}_nivel"] ?? 0);
            if ($new_sl !== $old_sl) $cambios[] = "O{$oi}_{$sn}:{$old_sl}→{$new_sl}";
        }
    }
    foreach (['kenbun','buso','hao','dominio_akuma'] as $hk) {
        if ((int)$propuesta[$hk] !== (int)($backup[$hk]??0)) $cambios[] = "$hk:".($backup[$hk]??0)."→".$propuesta[$hk];
    }
    if ($propuesta['akuma'] !== ($backup['akuma']??'')) $cambios[] = "Akuma:".($backup['akuma']??'∅')."→".$propuesta['akuma'];
    if ($propuesta['camino'] !== ($backup['camino']??'')) $cambios[] = "Camino:".($backup['camino']??'∅')."→".$propuesta['camino'];

    // Technique swap — validate and store
    $tec_hours_h = [1=>1,2=>4,3=>8,4=>12,5=>24,6=>36,7=>48,8=>60,9=>72,10=>100];
    $raw_perder = trim($_POST['r_tecs_perder'] ?? '');
    $raw_ganar  = trim($_POST['r_tecs_ganar']  ?? '');
    $tids_perder = $raw_perder ? array_filter(array_unique(array_map('trim', explode(',', $raw_perder))), function($t){ return preg_match('/^[A-Z][A-Z0-9]{2,}$/', $t); }) : [];
    $tids_ganar  = $raw_ganar  ? array_filter(array_unique(array_map('trim', explode(',', $raw_ganar))),  function($t){ return preg_match('/^[A-Z][A-Z0-9]{2,}$/', $t); }) : [];
    $tids_perder = array_values($tids_perder);
    $tids_ganar  = array_values($tids_ganar);
    $validated_perder = [];
    $validated_ganar  = [];
    if (!empty($tids_perder)) {
        $esc_tp = implode("','", array_map([$db, 'escape_string'], $tids_perder));
        $qvp = $db->query("SELECT t.tid, t.tier+0 AS tier FROM mybb_op_tecnicas t INNER JOIN mybb_op_tec_aprendidas ta ON t.tid=ta.tid WHERE ta.uid='$user_uid' AND t.tid IN ('$esc_tp')");
        while ($r = $db->fetch_array($qvp)) { $validated_perder[] = ['tid' => $r['tid'], 'tier' => (int)$r['tier']]; }
    }
    if (!empty($tids_ganar)) {
        $esc_tg = implode("','", array_map([$db, 'escape_string'], $tids_ganar));
        $qvg = $db->query("SELECT tid, tier+0 AS tier FROM mybb_op_tecnicas WHERE tid IN ('$esc_tg') AND tid NOT IN (SELECT tid FROM mybb_op_tec_aprendidas WHERE uid='$user_uid')");
        while ($r = $db->fetch_array($qvg)) { $validated_ganar[] = ['tid' => $r['tid'], 'tier' => (int)$r['tier']]; }
    }
    $budget_h = array_sum(array_map(function($t) use ($tec_hours_h){ return $tec_hours_h[$t['tier']] ?? 0; }, $validated_perder));
    $cost_h   = array_sum(array_map(function($t) use ($tec_hours_h){ return $tec_hours_h[$t['tier']] ?? 0; }, $validated_ganar));
    if ($cost_h > $budget_h) {
        echo json_encode(['ok' => false, 'error' => "Las técnicas a ganar ({$cost_h}h) superan el tiempo liberado ({$budget_h}h)."]);
        exit;
    }
    $propuesta['tecs_perder'] = array_column($validated_perder, 'tid');
    $propuesta['tecs_ganar']  = array_column($validated_ganar,  'tid');
    if (!empty($propuesta['tecs_perder'])) $cambios[] = 'tecs_perder:'.count($propuesta['tecs_perder']);
    if (!empty($propuesta['tecs_ganar']))  $cambios[] = 'tecs_ganar:'.count($propuesta['tecs_ganar']);

    $resumen_txt = empty($cambios) ? 'Petición de reset (sin cambios estructurales)' : implode('; ', array_slice($cambios, 0, 5));
    if (count($cambios) > 5) $resumen_txt .= ' (+' . (count($cambios) - 5) . ' más)';

    $resumen_esc = $db->escape_string(mb_substr($resumen_txt, 0, 200, 'UTF-8'));
    $backup_esc  = $db->escape_string(json_encode($backup, JSON_UNESCAPED_UNICODE));
    $prop_esc    = $db->escape_string(json_encode($propuesta, JSON_UNESCAPED_UNICODE));

    $db->query("INSERT INTO mybb_op_peticiones (uid, nombre, categoria, resumen, descripcion, url) VALUES ('$user_uid', '$username', 'reset', '$resumen_esc', '$prop_esc', '')");
    $pet_id = (int)$db->insert_id();
    $db->query("INSERT INTO mybb_op_fichas_reset (peticion_id, uid, backup_json, propuesta_json) VALUES ('$pet_id', '$user_uid', '$backup_esc', '$prop_esc')");

    $cant = (int)$ticket_row['cantidad'];
    if ($cant > 1) {
        $nueva = $cant - 1;
        $db->query("UPDATE mybb_op_inventario SET cantidad='$nueva' WHERE uid='$user_uid' AND objeto_id='TNP001'");
    } else {
        $db->query("DELETE FROM mybb_op_inventario WHERE uid='$user_uid' AND objeto_id='TNP001'");
    }

    log_audit($user_uid, $username, '[Ticket Reset]', "Petición de reset #$pet_id enviada. $resumen_esc");
    echo json_encode(['ok' => true]);
    exit;
}

// ─── Staff: modificar inventario (AJAX) ──────────────────────────────────────
if ($accion == 'staff_inventario_mod' && (is_staff($user_uid) || is_mod($user_uid))) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');

    $razon   = $db->escape_string(trim($mybb->get_input('razon')));
    $cambios = json_decode($mybb->get_input('cambios'), true);

    if (!$razon || !is_array($cambios) || empty($cambios)) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
        exit;
    }

    $log_items = [];

    foreach ($cambios as $cambio) {
        $tipo      = $cambio['tipo'];
        $objeto_id = $db->escape_string(trim($cambio['objeto_id']));
        $cantidad  = max(1, intval($cambio['cantidad']));

        if (!$objeto_id || !in_array($tipo, ['add', 'remove'])) continue;

        $obj_q = $db->query("SELECT nombre FROM mybb_op_objetos WHERE objeto_id='$objeto_id' LIMIT 1");
        $obj   = $db->fetch_array($obj_q);
        if (!$obj) {
            echo json_encode(['success' => false, 'message' => "Objeto no encontrado: $objeto_id"]);
            exit;
        }
        $obj_nombre = $db->escape_string($obj['nombre']);

        $inv_q   = $db->query("SELECT cantidad FROM mybb_op_inventario WHERE uid='$query_uid' AND objeto_id='$objeto_id' LIMIT 1");
        $inv_row = $db->fetch_array($inv_q);

        if ($tipo == 'add') {
            if ($inv_row) {
                $nueva_cantidad = intval($inv_row['cantidad']) + $cantidad;
                $db->query("UPDATE mybb_op_inventario SET cantidad='$nueva_cantidad' WHERE uid='$query_uid' AND objeto_id='$objeto_id'");
            } else {
                $db->query("INSERT INTO mybb_op_inventario(objeto_id,uid,cantidad,imagen,apodo,especial,editado,bautizado) VALUES ('$objeto_id','$query_uid','$cantidad','','','0','0','0')");
            }
            $log_items[] = "+{$cantidad}x {$obj_nombre} ({$objeto_id})";
        }

        if ($tipo == 'remove') {
            if ($inv_row) {
                $qty_actual = intval($inv_row['cantidad']);
                if ($cantidad >= $qty_actual) {
                    $db->query("DELETE FROM mybb_op_inventario WHERE uid='$query_uid' AND objeto_id='$objeto_id'");
                } else {
                    $nueva_cantidad = $qty_actual - $cantidad;
                    $db->query("UPDATE mybb_op_inventario SET cantidad='$nueva_cantidad' WHERE uid='$query_uid' AND objeto_id='$objeto_id'");
                }
                $log_items[] = "-{$cantidad}x {$obj_nombre} ({$objeto_id})";
            }
        }
    }

    if (!empty($log_items)) {
        $log_str = implode(', ', $log_items);
        log_audit($user_uid, $username, '[Staff][Inventario] uid:'.$query_uid, "Razón: $razon. Cambios: $log_str");
    }

    echo json_encode(['success' => true, 'message' => 'Inventario actualizado.']);
    exit;
}

$fileVersion = rand();

// ─── Virtudes especiales (afectan cálculos de stats) ─────────────────────────
$has_full_haki   = false;
$has_full_akuma  = false;
$has_sin_oficio  = false;
$has_estudioso   = false;
$has_polivalente = false;
$has_erudito     = false;

$q_ve = $db->query("
    SELECT virtud_id FROM mybb_op_virtudes_usuarios
    WHERE uid='$query_uid' AND virtud_id IN ('D024','V035','V036','V028')
");
while ($q = $db->fetch_array($q_ve)) {
    switch ($q['virtud_id']) {
        case 'D024': $has_sin_oficio  = true; break;
        case 'V035': $has_estudioso   = true; break;
        case 'V036': $has_polivalente = true; break;
        case 'V028': $has_erudito     = true; break;
    }
}

// ─── Datos de usuario y avatar ───────────────────────────────────────────────
$usuario = null;
$avatar  = '/images/default_avatar.png';

$query_usuario = $db->query("SELECT * FROM mybb_users WHERE uid='$query_uid'");
while ($u = $db->fetch_array($query_usuario)) {
    $avatar = $u['avatar'] ?: '/images/default_avatar.png';
    if (substr($avatar, 0, 18) == './uploads/avatars/') {
        $avatar = substr($avatar, 1);
        $db->query("UPDATE mybb_users SET avatar='$avatar' WHERE uid='$query_uid'");
    }
    $usuario = $u;
    echo '<script>window.OPG = window.OPG || {}; window.OPG.user = window.OPG.user || {}; window.OPG.user.avatar = ' . json_encode($avatar) . ';</script>';
}

// ─── Límite de experiencia semanal ───────────────────────────────────────────
$experiencia_limite = null;
$q_exp_lim = $db->query("
    SELECT * FROM mybb_op_experiencia_limite WHERE uid='$query_uid' ORDER BY id DESC LIMIT 1
");
while ($q = $db->fetch_array($q_exp_lim)) { $experiencia_limite = $q; }

// ─── Datos de ficha, akuma y cálculo de stats ────────────────────────────────
$ficha        = null;
$akuma        = null;
$akuma_imagen = '';

$query_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$query_uid'");
while ($q = $db->fetch_array($query_ficha)) {
    $ficha = $q;

    if ($ficha['akuma'] != '') {
        $akuma_nombre    = $ficha['akuma'];
        $akuma_subnombre = $ficha['akuma_subnombre'];
        $q_akuma = $db->query("
            SELECT * FROM mybb_op_akumas WHERE nombre='$akuma_nombre' AND subnombre='$akuma_subnombre'
        ");
        while ($qa = $db->fetch_array($q_akuma)) { $akuma = $qa; $akuma_imagen = $qa['imagen']; }
    }

    if ($ficha['camino'] == 'Haki')  { $has_full_haki  = true; }
    if ($ficha['camino'] == 'Akuma') { $has_full_akuma = true; }

    $oficio1      = $ficha['oficio1'];
    $_oficios_dec  = json_decode($ficha['oficios']);
    $nivelOficio1 = $_oficios_dec->{$oficio1}->{'nivel'} ?? 0;
    $sum_stats    = intval($ficha['fuerza'])   + intval($ficha['resistencia']) +
                   intval($ficha['reflejos']) + intval($ficha['punteria'])    +
                   intval($ficha['voluntad']) + intval($ficha['agilidad'])    +
                   intval($ficha['destreza']);

    $vitalidad_extra = 0;
    $energia_extra   = 0;
    $haki_extra      = 0;

    $vitalidad_extra =
        (intval($ficha['fuerza_pasiva'])     * 6)  +
        (intval($ficha['resistencia_pasiva'])* 15) +
        (intval($ficha['destreza_pasiva'])   * 4)  +
        (intval($ficha['agilidad_pasiva'])   * 3)  +
        (intval($ficha['voluntad_pasiva'])   * 1)  +
        (intval($ficha['punteria_pasiva'])   * 2)  +
        (intval($ficha['reflejos_pasiva'])   * 1);

    if (intval($ficha['fuerza_pasiva']))      $energia_extra += intval($ficha['fuerza_pasiva'])      * 2;
    if (intval($ficha['resistencia_pasiva'])) $energia_extra += intval($ficha['resistencia_pasiva']) * 4;
    if (intval($ficha['punteria_pasiva']))    $energia_extra += intval($ficha['punteria_pasiva'])    * 5;
    if (intval($ficha['destreza_pasiva']))    $energia_extra += intval($ficha['destreza_pasiva'])    * 4;
    if (intval($ficha['agilidad_pasiva']))    $energia_extra += intval($ficha['agilidad_pasiva'])    * 5;
    if (intval($ficha['reflejos_pasiva']))    $energia_extra += intval($ficha['reflejos_pasiva'])    * 1;
    if (intval($ficha['voluntad_pasiva'])) {
        $energia_extra += intval($ficha['voluntad_pasiva']) * 1;
        $haki_extra    += intval($ficha['voluntad_pasiva']) * 10;
    }

    $vitalidad_completa   = intval($ficha['vitalidad'])   + $vitalidad_extra + intval($ficha['vitalidad_pasiva']);
    $energia_completa     = intval($ficha['energia'])     + $energia_extra   + intval($ficha['energia_pasiva']);
    $haki_completo        = intval($ficha['haki'])        + $haki_extra      + intval($ficha['haki_pasiva']);
    $fuerza_completa      = intval($ficha['fuerza'])      + intval($ficha['fuerza_pasiva']);
    $resistencia_completa = intval($ficha['resistencia']) + intval($ficha['resistencia_pasiva']);
    $destreza_completa    = intval($ficha['destreza'])    + intval($ficha['destreza_pasiva']);
    $punteria_completa    = intval($ficha['punteria'])    + intval($ficha['punteria_pasiva']);
    $agilidad_completa    = intval($ficha['agilidad'])    + intval($ficha['agilidad_pasiva']);
    $reflejos_completa    = intval($ficha['reflejos'])    + intval($ficha['reflejos_pasiva']);
    $voluntad_completa    = intval($ficha['voluntad'])    + intval($ficha['voluntad_pasiva']);
}

$faccion    = $ficha['faccion'];
$apariencia = nl2br($ficha['apariencia']);
$personalidad = nl2br($ficha['personalidad']);
$historia   = nl2br($ficha['historia']);
$extra      = nl2br($ficha['extra']);
$ano_edad         = 725 - $ficha['edad'];
$frase            = nl2br($ficha['frase']);
$rasgos_positivos = nl2br($ficha['rasgos_positivos']);
$rasgos_negativos = nl2br($ficha['rasgos_negativos']);

// ─── Colores de facción ───────────────────────────────────────────────────────
$faccion_colors = [
    'Pirata'         => ['#ff0000','#ff0000','#ff0000','linear-gradient(42deg, #950000 20%, #ff0000 50%, #950000 80%)','#f63030','#fd0202'],
    'Marina'         => ['#00bafc','#0039ed','#00bafc','linear-gradient(42deg, #002282 20%, #00b8fa 50%, #002282 80%)','#0055bb','#0038c7'],
    'CipherPol'      => ['#08002c','#6534aa','#08002c','linear-gradient(42deg, #1b1424 20%, #9577ba 50%, #1b1424 80%)','#ac30d9','#861fac'],
    'Cazadores'      => ['#00c200','#00ab00','#00c200','linear-gradient(42deg, #0f2313 20%, #46af70 50%, #0f2313 80%)','#00d506','#007400'],
    'Revolucionario' => ['#be9d6f','#7d6452','#be9d6f','linear-gradient(42deg, #4e3e2c 20%, #e9c696 50%, #4e3e2c 80%)','#9d8771','#937e67'],
    'Civil'          => ['#ff0283','#c6005c','#ff0283','linear-gradient(42deg, #950044 20%, #f40277 50%, #950044 80%)','#e0428d','#c30041'],
];
[$faccionColor, $romboColor, $borderTagColor, $rangoColor, $borderColor, $borderPillColor]
    = $faccion_colors[$faccion] ?? $faccion_colors['Civil'];

// ─── Virtudes y defectos ─────────────────────────────────────────────────────
$virtudes       = [];
$virtudes_array = [];
$defectos       = [];
$defectos_array = [];

$query_virtudes = $db->query("
    SELECT * FROM mybb_op_virtudes
    INNER JOIN mybb_op_virtudes_usuarios
    ON mybb_op_virtudes.virtud_id = mybb_op_virtudes_usuarios.virtud_id
    WHERE mybb_op_virtudes_usuarios.uid='$query_uid' AND puntos > 0
    ORDER BY nombre
");
while ($q = $db->fetch_array($query_virtudes)) {
    $vid = $q['virtud_id'];
    if (!$virtudes[$vid]) $virtudes[$vid] = [];
    array_push($virtudes[$vid], $q);
    array_push($virtudes_array, $vid);
}

$query_defectos = $db->query("
    SELECT * FROM mybb_op_virtudes
    INNER JOIN mybb_op_virtudes_usuarios
    ON mybb_op_virtudes.virtud_id = mybb_op_virtudes_usuarios.virtud_id
    WHERE mybb_op_virtudes_usuarios.uid='$query_uid' AND puntos < 0
    ORDER BY nombre
");
while ($q = $db->fetch_array($query_defectos)) {
    $vid = $q['virtud_id'];
    if (!$defectos[$vid]) $defectos[$vid] = [];
    array_push($defectos[$vid], $q);
    array_push($defectos_array, $vid);
}

$virtudes_array_json = json_encode($virtudes_array);
$virtudes_json       = json_encode($virtudes);
$defectos_array_json = json_encode($defectos_array);
$defectos_json       = json_encode($defectos);
$should_see_private  = $is_owner || is_staff($user_uid) || is_peti_mod($user_uid);

// ─── NPCs y mascotas ─────────────────────────────────────────────────────────
$npcs       = [];
$npcs_array = [];
$mascotas       = [];
$mascotas_array = [];

$query_pets = $db->query(" SELECT * FROM `mybb_op_npcs` WHERE npc_id LIKE '%$query_uid-PET%'; ");
$query_npcs = $db->query(" SELECT * FROM `mybb_op_npcs` WHERE npc_id LIKE '%$query_uid-NPC%'; ");

while ($q = $db->fetch_array($query_pets)) {
    $npc_id = $q['npc_id'];
    $key = "$npc_id";
    if (!$mascotas[$key]) { $mascotas[$key] = []; }
    array_push($mascotas[$key], $q);
    array_push($mascotas_array, $npc_id);
}
while ($q = $db->fetch_array($query_npcs)) {
    $npc_id = $q['npc_id'];
    $key = "$npc_id";
    if (!$npcs[$key]) { $npcs[$key] = []; }
    array_push($npcs[$key], $q);
    array_push($npcs_array, $npc_id);
}

$npcs_array_json     = json_encode($npcs_array);
$npcs_json           = json_encode($npcs);
$mascotas_array_json = json_encode($mascotas_array);
$mascotas_json       = json_encode($mascotas);

// ─── Experiencia y nivel ─────────────────────────────────────────────────────
$experiencia     = intval(floor($usuario['newpoints']));
$nivel           = $ficha['nivel'];
$nivelPorcentaje = 100;
$expMax          = 50;
$expRem          = 0;

// Modificadores de reputación por virtudes/defectos
if ($virtudes['V017']) {
    $reputacion         = intval($ficha['reputacion'])          * 1.10;
    $reputacionPositiva = intval($ficha['reputacion_positiva']) * 1.10;
    $reputacionNegativa = intval($ficha['reputacion_negativa']) * 1.10;
} else if ($defectos['D013']) {
    $reputacion         = intval($ficha['reputacion'])          * 0.9;
    $reputacionPositiva = intval($ficha['reputacion_positiva']) * 0.9;
    $reputacionNegativa = intval($ficha['reputacion_negativa']) * 0.9;
} else {
    $reputacion         = intval($ficha['reputacion']);
    $reputacionPositiva = intval($ficha['reputacion_positiva']);
    $reputacionNegativa = intval($ficha['reputacion_negativa']);
}

// Modificadores de stats por virtudes
if      ($virtudes['V037']) $vitalidad_completa += intval($nivel) * 10;
else if ($virtudes['V038']) $vitalidad_completa += intval($nivel) * 15;
else if ($virtudes['V039']) $vitalidad_completa += intval($nivel) * 20;
if      ($virtudes['V040']) $energia_completa   += intval($nivel) * 10;
else if ($virtudes['V041']) $energia_completa   += intval($nivel) * 15;
if      ($virtudes['V058']) $haki_completo      += intval($nivel) * 5;
else if ($virtudes['V059']) $haki_completo      += intval($nivel) * 10;

$reputacionDiff = $reputacionPositiva - $reputacionNegativa;
$reputacionPerc = 50;
if ($reputacion != 0) {
    $reputacionPerc = round(($reputacionPositiva / $reputacion) * 100);
}

if      ($reputacionPerc <= 20)                         $reputacionImagen = 'ReputacionNegativa';
else if ($reputacionPerc > 20 && $reputacionPerc <= 40) $reputacionImagen = 'ReputacionNeutralMala';
else if ($reputacionPerc > 40 && $reputacionPerc < 60)  $reputacionImagen = 'ReputacionNeutral';
else if ($reputacionPerc >= 60 && $reputacionPerc < 80) $reputacionImagen = 'ReputacionNeutralBuena';
else if ($reputacionPerc >= 80)                         $reputacionImagen = 'ReputacionPositiva';
else                                                    $reputacionImagen = 'ReputacionNeutral';

// ─── Fama ────────────────────────────────────────────────────────────────────
$fama_tabla = [
    [5000, 20, 80, 'Héroe',      'Leyenda',    'Calamidad'],
    [3001, 20, 80, 'Paladín',    'Ícono',      'Pesadilla'],
    [1501, 20, 80, 'Santo',      'Famoso',     'Infame'],
    [601,  20, 80, 'Justiciero', 'Popular',    'Criminal'],
    [301,  20, 80, 'Noble',      'Aspirante',  'Delincuente'],
    [101,  20, 80, 'Honorable',  'Rumor',      'Forajido'],
    [51,   -1, 101,'Novato',     'Novato',     'Novato'],
    [26,   -1, 101,'Iniciado',   'Iniciado',   'Iniciado'],
];
$fama = 'Desconocido';
foreach ($fama_tabla as [$rep_min, $perc_malo, $perc_bueno, $nombre_b, $nombre_n, $nombre_m]) {
    if ($reputacion >= $rep_min) {
        if      ($reputacionPerc >= $perc_bueno) $fama = $nombre_b;
        else if ($reputacionPerc <= $perc_malo)  $fama = $nombre_m;
        else                                     $fama = $nombre_n;
        break;
    }
}

// ─── Rango (ascensos automáticos) ────────────────────────────────────────────
$rango = $ficha['rango'];

if ($faccion == 'Marina') {
    if      ($rango == 'SargentoM' && $reputacion >= 51  && intval($nivel) >= 11) $rango = 'Suboficial';
    else if ($rango == 'SoldadoM'  && $reputacion >= 51  && intval($nivel) >= 8)  $rango = 'SargentoM';
    else if ($rango == 'ReclutaM'  && $reputacion >= 26  && intval($nivel) >= 4)  $rango = 'SoldadoM';
}
if ($faccion == 'CipherPol') {
    if      ($rango == 'CP6' && $reputacion >= 101 && intval($nivel) >= 19) $rango = 'CP7';
    else if ($rango == 'CP5' && $reputacion >= 101 && intval($nivel) >= 16) $rango = 'CP6';
    else if ($rango == 'CP4' && $reputacion >= 51  && intval($nivel) >= 13) $rango = 'CP5';
    else if ($rango == 'CP3' && $reputacion >= 51  && intval($nivel) >= 10) $rango = 'CP4';
    else if ($rango == 'CP2' && $reputacion >= 26  && intval($nivel) >= 7)  $rango = 'CP3';
    else if ($rango == 'CP1' && $reputacion >= 26  && intval($nivel) >= 4)  $rango = 'CP2';
}
if ($faccion == 'Revolucionario') {
    if      ($rango == 'SargentoR' && $reputacion >= 51 && intval($nivel) >= 11) $rango = 'AgenteR';
    else if ($rango == 'SoldadoR'  && $reputacion >= 51 && intval($nivel) >= 8)  $rango = 'SargentoR';
    else if ($rango == 'ReclutaR'  && $reputacion >= 26 && intval($nivel) >= 4)  $rango = 'SoldadoR';
}

if ($rango != $ficha['rango']) $db->query("UPDATE mybb_op_fichas SET rango='$rango' WHERE fid='$query_uid'");

$movido_inframundo_base = (int)($ficha['movidoInframundo'] ?? 0);
if (in_array('V019', $virtudes_array)) {
    $movido_inframundo_base += 50000000;
}
$movido_inframundo_fmt = $movido_inframundo_base > 0 ? number_format($movido_inframundo_base, 0, ',', '.') : '';

// Mapa rango → nombre del archivo de imagen (para casos con valor legacy o vacío)
$rango_imagen_map = ['' => 'Ciudadano', 'civil' => 'Ciudadano', 'Civil' => 'Ciudadano'];
$rango_imagen = $rango_imagen_map[$ficha['rango']] ?? ($ficha['rango'] ?: 'Ciudadano');
if ($fama  != $ficha['fama'])  $db->query("UPDATE mybb_op_fichas SET fama='$fama'   WHERE fid='$query_uid'");

// ─── Tabla de experiencia y % de nivel ───────────────────────────────────────
$exp_tabla = [
     1 => [0,50],        2 => [50,125],      3 => [125,225],    4 => [225,350],    5 => [350,500],
     6 => [500,675],     7 => [675,875],     8 => [875,1100],   9 => [1100,1350], 10 => [1350,1625],
    11 => [1625,1925],  12 => [1925,2250],  13 => [2250,2600], 14 => [2600,2975], 15 => [2975,3375],
    16 => [3375,3800],  17 => [3800,4250],  18 => [4250,4725], 19 => [4725,5225], 20 => [5225,5750],
    21 => [5750,6300],  22 => [6300,6870],  23 => [6870,7460], 24 => [7460,8070], 25 => [8070,8700],
    26 => [8700,9350],  27 => [9350,10020], 28 => [10020,10700],29 => [10700,11400],30 => [11400,12360],
    31 => [12360,13340],32 => [13340,14350],33 => [14350,15390],34 => [15390,16450],35 => [16450,17530],
    36 => [17530,18650],37 => [18650,19790],38 => [19790,20950],39 => [20950,22140],40 => [22140,23700],
    41 => [23700,25310],42 => [25310,26970],43 => [26970,28680],44 => [28680,30440],45 => [30440,32540],
    46 => [32540,34700],47 => [34700,36920],48 => [36920,39270],49 => [39270,41820],50 => [41820,41821],
];
$nv_int = intval($nivel);
[$expMin, $expMax] = $exp_tabla[$nv_int] ?? [0, 50];
$expRem          = $expMax - $experiencia;
$nivelPorcentaje = floor(($experiencia - $expMin) / ($expMax - $expMin) * 100);
if ($nivelPorcentaje == 0) $nivelPorcentaje = 1;

$limite_nivel       = min(intval($ficha['limite_nivel']), 40); // cap temporal nivel 40
$raza               = $ficha['raza'];
$puntos_estadistica = intval($ficha['puntos_estadistica']);

// ─── Level-up ────────────────────────────────────────────────────────────────
$puntos_bonus_nivel = [10 => 20, 20 => 20, 30 => 20, 40 => 20, 50 => 20];
foreach ($exp_tabla as $nv => $rango_vals) {
    $exp_min_nv      = $rango_vals[0];
    $nivel_anterior  = $nv - 1;
    $requiere_limite = $nv > 20;
    if ($experiencia >= $exp_min_nv && $nivel == (string)$nivel_anterior &&
        (!$requiere_limite || $limite_nivel > $nivel_anterior)) {
        $nivel = $nv;
        $puntos_estadistica += $puntos_bonus_nivel[$nv] ?? 10;
        $db->query("UPDATE mybb_op_fichas SET nivel='$nivel', puntos_estadistica='$puntos_estadistica' WHERE fid='$query_uid'");
        if ($raza == 'Skypian') {
            $db->query("UPDATE mybb_op_fichas SET energia_pasiva=energia_pasiva + 5 WHERE fid='$query_uid'");
        }
        break;
    }
}

$pestana = $mybb->get_input('pestana');

// ─── Técnicas aprendidas ──────────────────────────────────────────────────────
$query_tec_aprendidas = $db->query("
    SELECT * FROM `mybb_op_tecnicas`
    INNER JOIN `mybb_op_tec_aprendidas`
    ON `mybb_op_tecnicas`.`tid`=`mybb_op_tec_aprendidas`.`tid`
    WHERE `mybb_op_tec_aprendidas`.`uid`='$query_uid'
    ORDER BY `mybb_op_tecnicas`.`tid`,`mybb_op_tecnicas`.`rama`
");

$tec_aprendidas           = [];
$tec_aprendidas['todo']   = [];
$tecnicas_por_estilo_rama = [];
$tecnicas_por_rama        = [];

while ($tec_aprendida = $db->fetch_array($query_tec_aprendidas)) {
    $tec_aprendida['descripcion'] = nl2br($tec_aprendida['descripcion']);
    $estilo = $tec_aprendida['estilo'];
    $rama   = $tec_aprendida['rama'];
    if (!isset($tec_aprendidas[$rama])) { $tec_aprendidas[$rama] = []; }
    array_push($tec_aprendidas['todo'], $tec_aprendida);
    array_push($tec_aprendidas[$rama], $tec_aprendida);
    if (!isset($tecnicas_por_estilo_rama[$estilo]))        { $tecnicas_por_estilo_rama[$estilo] = []; }
    if (!isset($tecnicas_por_estilo_rama[$estilo][$rama])) { $tecnicas_por_estilo_rama[$estilo][$rama] = []; }
    array_push($tecnicas_por_estilo_rama[$estilo][$rama], $tec_aprendida);
    if (!isset($tecnicas_por_rama[$rama])) { $tecnicas_por_rama[$rama] = []; }
    array_push($tecnicas_por_rama[$rama], $tec_aprendida);
}
$tec_aprendidas_json = json_encode($tec_aprendidas);

// Generar HTML de técnicas usando la librería de spoilers (igual que op/ficha.php)
$is_staff_tec = in_array($mybb->user['usergroup'], [3, 4, 6]);
$tecnicas_html  = [];
$todo_content   = '';
$unicas_content = '';

foreach ($tecnicas_por_estilo_rama as $estilo => $ramas) {
    $estilo_content = '';
    foreach ($ramas as $rama => $tecnicas_array) {
        $tecnicas_content = '';
        foreach ($tecnicas_array as $tecnica) {
            $tecnicas_content .= create_technique_card($tecnica, $is_staff_tec, false, $query_uid);
        }
        $spoiler_title = ($rama === 'Única') ? "Única - $estilo" : $rama;
        $container_style = get_technique_container_style();
        $container_style['open'] = true;
        $rama_spoiler = create_custom_spoiler($spoiler_title, $tecnicas_content, $container_style);
        $estilo_content .= $rama_spoiler;
        if ($rama === 'Única') { $unicas_content .= $rama_spoiler; }
    }
    $tecnicas_html[$estilo] = $estilo_content;
    $todo_content .= $estilo_content;
}

// Generar HTML por rama para ramas que no coinciden con ningún estilo.
// Si la rama tiene el mismo nombre que un estilo, el loop de estilos ya la cubre con todas sus sub-ramas.
foreach ($tecnicas_por_rama as $rama => $tecnicas_array) {
    if ($rama === 'Única') continue;
    if (isset($tecnicas_html[$rama])) continue; // el loop de estilos ya la generó correctamente
    $tecnicas_content = '';
    foreach ($tecnicas_array as $tecnica) {
        $tecnicas_content .= create_technique_card($tecnica, $is_staff_tec, false, $query_uid);
    }
    $container_style = get_technique_container_style();
    $container_style['open'] = true;
    $tecnicas_html[$rama] = create_custom_spoiler($rama, $tecnicas_content, $container_style);
}

$tecnicas_html['Única'] = $unicas_content;
$tecnicas_html['todo']  = $todo_content;
$tecnicas_html_json     = json_encode($tecnicas_html);

// ─── Razas ────────────────────────────────────────────────────────────────────
$razas = [];
$query_razas = $db->query("SELECT * FROM mybb_op_razas");
while ($q = $db->fetch_array($query_razas)) {
    $q['caracteristicas'] = nl2br($q['caracteristicas']);
    $razas[] = $q;
}
$razas_json = json_encode($razas);

// ─── Precio de venta ────────────────────────────────────────────────────────────
$oficios_obj = json_decode($ficha['oficios']);
$precioVentaPct = 2.00000001;
if (isset($oficios_obj->{'Mercader'})) {
    $precioVentaPct = 1.66666667;
    if (isset($oficios_obj->{'Mercader'}->{'sub'}->{'Comerciante'})) {
        $caminoNivel = $oficios_obj->{'Mercader'}->{'sub'}->{'Comerciante'};
        if ($caminoNivel == 1) { $precioVentaPct = 1.42857143; }
        if ($caminoNivel == 2) { $precioVentaPct = 1.25; }
        if ($caminoNivel == 3) { $precioVentaPct = 1.1111111117; }
    }
}

// ─── Vender objeto ────────────────────────────────────────────────────────────
if ($accion == 'vender' && $objeto_vender != '' && $is_owner) {
    $has_objeto    = false;
    $objeto_actual = null;
    $cantidad      = 0;
    $objeto_actual_query = $db->query("SELECT * FROM mybb_op_objetos WHERE objeto_id='$objeto_vender'");
    while ($q = $db->fetch_array($objeto_actual_query)) { $objeto_actual = $q; }
    $inventario_actual = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$query_uid' AND objeto_id='$objeto_vender'");
    while ($q = $db->fetch_array($inventario_actual)) { $has_objeto = true; $cantidad = $q['cantidad']; }
    if ($has_objeto) {
        $cantidad_vender  = max(1, (int)$mybb->get_input('cantidad_vender'));
        $cantidad_vender  = min($cantidad_vender, intval($cantidad));
        if (intval($cantidad) > $cantidad_vender) {
            $nueva_cantidad = intval($cantidad) - $cantidad_vender;
            $db->query("UPDATE mybb_op_inventario SET cantidad='$nueva_cantidad' WHERE objeto_id='$objeto_vender' AND uid='$query_uid'");
        } else {
            $db->query("DELETE FROM mybb_op_inventario WHERE objeto_id='$objeto_vender' AND uid='$query_uid'");
        }
        $berries_actuales = intval($ficha['berries']);
        $precioVenta      = (intval(intval($objeto_actual['berries']) / $precioVentaPct) + 1) * $cantidad_vender;
        $nuevos_berries   = $berries_actuales + $precioVenta;
        $objeto_nombre    = $objeto_actual['nombre'];
        $db->query("UPDATE mybb_op_fichas SET berries='$nuevos_berries' WHERE fid='$query_uid'");
        log_audit($user_uid, $username, $query_uid, '[Venta]', "Vendido: {$cantidad_vender}x $objeto_nombre ($objeto_vender): $berries_actuales->$nuevos_berries (Ganancia: $precioVenta).");
        log_audit_currency($user_uid, $username, $query_uid, '[Venta][Berries]', 'berries', $nuevos_berries);
        $log = "¡Has vendido {$cantidad_vender}x $objeto_nombre por $precioVenta berries!\nTienes ahora $nuevos_berries berries\n($berries_actuales berries + $precioVenta berries)";
        echo "<script>alert(`$log`);window.location.href = 'https://onepiecegaiden.com/op/personaje.php?uid=$query_uid';</script>";
    }
}

// ─── Inventario ───────────────────────────────────────────────────────────────
$query_inventario = $db->query("
    SELECT * FROM `mybb_op_objetos`
    INNER JOIN `mybb_op_inventario`
    ON `mybb_op_objetos`.`objeto_id`=`mybb_op_inventario`.`objeto_id`
    WHERE `mybb_op_inventario`.`uid`='$query_uid'
    ORDER BY `mybb_op_objetos`.categoria, `mybb_op_objetos`.subcategoria, `mybb_op_objetos`.tier, `mybb_op_objetos`.nombre
");

$objetos       = [];
$objetos_array = [];

while ($q = $db->fetch_array($query_inventario)) {
    $objeto_id = $q['objeto_id'];
    $key = "$objeto_id";
    if (!$objetos[$key]) { $objetos[$key] = []; }
    array_push($objetos[$key], $q);
    array_push($objetos_array, $objeto_id);
}

$objetos_array_json = json_encode($objetos_array);
$objetos_json       = json_encode($objetos);

// ─── Ticket de Reset ─────────────────────────────────────────────────────────
$ticket_reset_disponible = false;
$ticket_reset_pendiente  = false;
if ($is_owner) {
    $ticket_reset_disponible = isset($objetos['TNP001']);
    $pq2 = $db->query("SELECT id FROM mybb_op_peticiones WHERE uid='$user_uid' AND categoria='reset' AND resuelto=0 LIMIT 1");
    if ($db->num_rows($pq2) > 0) $ticket_reset_pendiente = true;
}

// ─── Secret 1 ─────────────────────────────────────────────────────────────────
$ficha_secret1      = null;
$ficha_secret1_json = 'null';

$query_ficha_secret1 = $db->query("
    SELECT * FROM mybb_op_fichas_secret WHERE fid='$query_uid' AND secret_number='1'
");
while ($q = $db->fetch_array($query_ficha_secret1)) {
    $q['historia1']    = nl2br($q['historia']);
    $q['apariencia1']  = nl2br($q['apariencia']);
    $q['personalidad1']= nl2br($q['personalidad']);
    $q['extra1']       = nl2br($q['extra']);
    $ficha_secret1      = $q;
    $ficha_secret1_json = json_encode($q);
}

// ─── Historial ────────────────────────────────────────────────────────────────
$historial_kuro_array          = [];
$historial_nikas_array         = [];
$historial_berries_array       = [];
$historial_experiencia_array   = [];
$historial_puntos_oficio_array = [];

$query_historial_all = $db->query("
    SELECT * FROM `mybb_op_audit_general`
    WHERE user_uid='$query_uid'
    ORDER BY id DESC
");

while ($q = $db->fetch_array($query_historial_all)) {
    $cat = $q['categoria'];

    if (strpos($cat,'[Post]') !== false ||
        strpos($cat,'[Modificación de kuros]') !== false ||
        strpos($cat,'[Tienda Kuros][Kuros]') !== false ||
        strpos($cat,'[Kuros]') !== false) {
        $historial_kuro_array[] = $q;
    }

    if (strpos($cat,'[Post]') !== false ||
        strpos($cat,'[Modificación de experiencia]') !== false ||
        strpos($cat,'[Recompensa][Experiencia]') !== false ||
        strpos($cat,'[Entrenamiento][Experiencia]') !== false ||
        strpos($cat,'[Cofre][Experiencia]') !== false ||
        strpos($cat,'[Tienda Kuros][Experiencia]') !== false) {
        $historial_experiencia_array[] = $q;
    }

    if (strpos($cat,'[Modificación de nikas]') !== false ||
        strpos($cat,'[Recompensa][Nikas]') !== false ||
        strpos($cat,'[Cofre][Nikas]') !== false ||
        strpos($cat,'[Tienda Kuros][Nikas]') !== false ||
        strpos($cat,'[Mejoras]') !== false ||
        strpos($cat,'[Creación][Nikas]') !== false) {
        $historial_nikas_array[] = $q;
    }

    if (strpos($cat,'[Modificación de berries]') !== false ||
        strpos($cat,'[Recompensa][Berries]') !== false ||
        strpos($cat,'[Tienda][Berries]') !== false ||
        strpos($cat,'[Cofre][Berries]') !== false ||
        strpos($cat,'[Tienda Kuros][Berries]') !== false ||
        strpos($cat,'[Venta][Berries]') !== false ||
        strpos($cat,'[Intercambio][Berries]') !== false ||
        strpos($cat,'[Salarios]') !== false ||
        strpos($cat,'[Crafteo][Berries]') !== false) {
        $historial_berries_array[] = $q;
    }

    if (strpos($cat,'[Modificación de puntos de oficio]') !== false ||
        strpos($cat,'[Entrenamiento][Puntos oficio]') !== false ||
        strpos($cat,'[Tienda Kuros][Puntos oficio]') !== false ||
        strpos($cat,'[Creación][Puntos oficio]') !== false) {
        $historial_puntos_oficio_array[] = $q;
    }
}

$historial_kuro_json          = json_encode($historial_kuro_array);
$historial_experiencia_json   = json_encode($historial_experiencia_array);
$historial_nikas_json         = json_encode($historial_nikas_array);
$historial_berries_json       = json_encode($historial_berries_array);
$historial_puntos_oficio_json = json_encode($historial_puntos_oficio_array);

// ─── Modal Ticket Reset ───────────────────────────────────────────────────────
if (!function_exists('treset_belica_opts')) {
    function treset_belica_opts($current) {
        $opts = ['','Escudero','Artista Marcial','Combatiente','Artista','Asesino','Guerrero','Espadachín','Tecnicista','Artillero','Arquero','Tirador','Pícaro'];
        $html = '';
        foreach ($opts as $b) {
            $sel = ($b === $current) ? ' selected' : '';
            $html .= '<option value="'.htmlspecialchars($b, ENT_QUOTES).'"'.$sel.'>'.htmlspecialchars($b ?: '— vacío —').'</option>';
        }
        return $html;
    }
    function treset_oficio_opts($current) {
        $opts = ['','Artesano','Médico','Navegante','Inventor','Carpintero','Cocinero','Mercader','Investigador','Aventurero','Recolector'];
        $html = '';
        foreach ($opts as $o) {
            $sel = ($o === $current) ? ' selected' : '';
            $html .= '<option value="'.htmlspecialchars($o, ENT_QUOTES).'"'.$sel.'>'.htmlspecialchars($o ?: '— vacío —').'</option>';
        }
        return $html;
    }
    function treset_of_sub_lvl_opts($cur_lvl) {
        $labels = [
            0 => '0 — Base',
            1 => '1 — Espe (−10✦ −2000 pto)',
            2 => '2 — Maestría (−25✦ −3500 pto)',
            3 => '3 — Gran Maestría (−50✦ −5000 pto)',
        ];
        $html = '';
        foreach ($labels as $v => $lbl) {
            $sel = ($v === (int)$cur_lvl) ? ' selected' : '';
            $html .= '<option value="'.$v.'"'.$sel.'>'.htmlspecialchars($lbl).'</option>';
        }
        return $html;
    }
    function treset_of_slot_html($oi, $of_name, $of_data, $of_subs_map, $oficio_valid_list) {
        $subs    = isset($of_subs_map[$of_name]) ? $of_subs_map[$of_name] : [];
        $nivel   = $of_data ? (int)($of_data['nivel'] ?? 1) : 1;
        $sub1    = $subs[0] ?? '';
        $sub2    = $subs[1] ?? '';
        $sl1     = ($of_data && $sub1) ? (int)($of_data['sub'][$sub1] ?? 0) : 0;
        $sl2     = ($of_data && $sub2) ? (int)($of_data['sub'][$sub2] ?? 0) : 0;
        $opts    = '<option value="">— vacío —</option>';
        foreach ($oficio_valid_list as $ov) {
            $sel   = ($ov === $of_name) ? ' selected' : '';
            $opts .= '<option value="'.htmlspecialchars($ov, ENT_QUOTES).'"'.$sel.'>'.htmlspecialchars($ov).'</option>';
        }
        $niv_opts = '<option value="1"'.($nivel===1?' selected':'').'>Nivel 1</option>'
                   .'<option value="2"'.($nivel===2?' selected':'').'>Nivel 2 (−1000 pto)</option>';
        $det_style = $of_name ? '' : ' style="display:none"';
        $h_sub1 = htmlspecialchars($sub1 ?: 'Sub 1');
        $h_sub2 = htmlspecialchars($sub2 ?: 'Sub 2');
        return
            '<div class="treset-slot treset-of-slot">'
            .'<label class="treset-slabel">Oficio '.$oi.'</label>'
            .'<select name="r_oficio'.$oi.'" class="treset-select treset-of-sel" data-oi="'.$oi.'">'.$opts.'</select>'
            .'<div id="treset-of-detail-'.$oi.'" class="treset-of-detail"'.$det_style.'>'
            .'<div style="display:flex;gap:6px;align-items:center;margin-top:4px">'
            .'<span style="font-size:11px;color:#9ca3af;min-width:36px">Nivel</span>'
            .'<select name="r_oficio'.$oi.'_nivel" class="treset-select" style="max-width:220px">'.$niv_opts.'</select>'
            .'</div>'
            .'<div style="margin-top:4px">'
            .'<div style="display:flex;gap:6px;align-items:center;margin-top:4px">'
            .'<span class="treset-of-sub1name-'.$oi.'" style="font-size:11px;color:#9ca3af;min-width:90px;display:inline-block">'.$h_sub1.'</span>'
            .'<select name="r_oficio'.$oi.'_sub1_nivel" class="treset-select" style="max-width:260px">'.treset_of_sub_lvl_opts($sl1).'</select>'
            .'</div>'
            .'<div style="display:flex;gap:6px;align-items:center;margin-top:4px">'
            .'<span class="treset-of-sub2name-'.$oi.'" style="font-size:11px;color:#9ca3af;min-width:90px;display:inline-block">'.$h_sub2.'</span>'
            .'<select name="r_oficio'.$oi.'_sub2_nivel" class="treset-select" style="max-width:260px">'.treset_of_sub_lvl_opts($sl2).'</select>'
            .'</div>'
            .'</div>'
            .'</div>'
            .'</div>';
    }
}

$op_ficha_reset_panel = '';
if ($is_owner && in_array($user_uid, [850, 258, 157]) && ($ticket_reset_disponible || $ticket_reset_pendiente)) {
    if ($ticket_reset_pendiente) {
        $modal_body = '<div class="treset-status treset-pending">Ya tienes una petición de reset pendiente de revisión. En cuanto sea resuelta, podrás enviar otra si dispones del ticket.</div>';
    } else {
        // Estilos disponibles — misma lista que belicas.js
        $estilos_db = ['bloqueado','no_bloqueado','Gunkata','Hasshoken','Santoryu','Kuroashi','Gyojin Karate','Gyojin Bukijutsu','Gyojin Jujutsu','Rokushiki','Okama Kempo','Sora Yokujin','Ninjutsu','Ryusoken','Jiyuumura Kempo','Pop Green','Clima Tact','Funekiri','Hakai Shin','Railgun Style','Shuron Hakke','Raqisat Alsahra','Breeskjold','Impacto Explosivo','Royal Guard','Shikaku Teikoku','Duelliste de Givre','Filo Della Vita','Havets Symfoni','Bakudai Karin','Yama Kurai','Shiseiju','Sea Corsair','Wano Nitoryu','Mano de Tahur','Kokudan','Kodai no Bushido','Ittoryu Sekai','Global Performer','Cavalry Warrior','Ashigara Dokoi','Kanpo Kenpo'];

        // Presupuesto de nikas = actuales + reembolso del build actual
        $ui_costs_b    = ['belica2'=>10,'belica3'=>20,'belica4'=>35,'belica5'=>50,'belica6'=>65,
                          'belica7'=>80,'belica8'=>95,'belica9'=>110,'belica10'=>125,'belica11'=>140,'belica12'=>155];
        $ui_costs_e    = ['estilo2'=>25,'estilo3'=>50,'estilo4'=>75];
        $ui_costs_e1   = [1=>0,2=>20,3=>30,4=>45,5=>60,6=>75,7=>90,8=>105,9=>120,10=>135,11=>150,12=>165];
        $ui_costs_e2   = [1=>15,2=>30,3=>40,4=>60,5=>75,6=>90,7=>105,8=>120,9=>135,10=>150,11=>165,12=>180];
        $ui_costs_spec = [1=>45,2=>60,3=>75,4=>100,5=>125,6=>150,7=>175,8=>200,9=>225,10=>250,11=>275,12=>300];
        $ficha_belicas_data = json_decode($ficha['belicas'] ?? '{}', true) ?: [];
        $ui_build_cost = 0;
        for ($i = 2; $i <= 12; $i++) {
            if (!empty($ficha["belica$i"])) $ui_build_cost += $ui_costs_b["belica$i"];
        }
        for ($i = 2; $i <= 4; $i++) {
            if (($ficha["estilo$i"] ?? 'bloqueado') !== 'bloqueado') $ui_build_cost += $ui_costs_e["estilo$i"];
        }
        for ($i = 1; $i <= 12; $i++) {
            $disc = $ficha["belica$i"] ?? '';
            if (!$disc) continue;
            $dd = $ficha_belicas_data[$disc] ?? null; if (!$dd) continue;
            $e1 = $dd['espe1'] ?? '';
            if ($e1 !== '') {
                $ui_build_cost += $ui_costs_e1[$i];
                if (($dd['sub'][$e1] ?? 0) >= 2) $ui_build_cost += $ui_costs_spec[$i];
            }
            $e2 = $dd['espe2'] ?? '';
            if ($e2 !== '') {
                $ui_build_cost += $ui_costs_e2[$i];
                if (($dd['sub'][$e2] ?? 0) >= 2) $ui_build_cost += $ui_costs_spec[$i];
            }
        }
        $oficio_valid_ui = ['Artesano','Médico','Navegante','Inventor','Carpintero','Cocinero','Mercader','Investigador','Aventurero','Recolector'];
        $of_subs_map_ui = [
            'Artesano'    => ['Herrero','Modista'],
            'Médico'      => ['Farmacólogo','Doctor'],
            'Navegante'   => ['Cartógrafo','Timonel'],
            'Inventor'    => ['Biólogo','Ingeniero'],
            'Carpintero'  => ['Astillero','Constructor'],
            'Cocinero'    => ['Chef','Aprovisionador'],
            'Mercader'    => ['Comerciante','Contrabandista'],
            'Investigador'=> ['Periodista','Arqueólogo'],
            'Aventurero'  => ['Cazador','Domador'],
            'Recolector'  => ['Agreste','Mayorista'],
        ];
        $cur_oficios_ui      = json_decode($ficha['oficios'] ?? '{}', true) ?: [];
        $ui_oficio_nika_cost = 0;
        $ui_oficio_pto_cost  = 0;
        foreach ($cur_oficios_ui as $of_d) {
            if (($of_d['nivel'] ?? 1) >= 2) $ui_oficio_pto_cost += 1000;
            foreach (($of_d['sub'] ?? []) as $sl_v) {
                $sl = (int)$sl_v;
                if ($sl >= 1) { $ui_oficio_pto_cost += 2000; $ui_oficio_nika_cost += 10; }
                if ($sl >= 2) { $ui_oficio_pto_cost += 3500; $ui_oficio_nika_cost += 25; }
                if ($sl >= 3) { $ui_oficio_pto_cost += 5000; $ui_oficio_nika_cost += 50; }
            }
        }
        $nika_presupuesto = (int)$ficha['nika'] + $ui_build_cost + $ui_oficio_nika_cost;
        $pto_presupuesto  = (int)$ficha['puntos_oficio'] + $ui_oficio_pto_cost;

        if (!function_exists('treset_estilo_opts')) {
            function treset_estilo_opts($current, $list) {
                $labels = ['bloqueado' => '— Bloqueado —', 'no_bloqueado' => '(sin asignar)'];
                $html = '';
                foreach ($list as $o) {
                    $label = $labels[$o] ?? htmlspecialchars($o, ENT_QUOTES);
                    $sel   = ($o === $current) ? ' selected' : '';
                    $html .= '<option value="'.htmlspecialchars($o, ENT_QUOTES).'"'.$sel.'>'.$label.'</option>';
                }
                return $html;
            }
        }

        // Build estilo slots with level-gate check
        $ficha_nivel = (int)$ficha['nivel'];
        $estilo_defs = [
            1 => ['label' => 'Estilo 1',           'nivel_req' => 8],
            2 => ['label' => 'Estilo 2 (25 nikas)', 'nivel_req' => 16],
            3 => ['label' => 'Estilo 3 (50 nikas)', 'nivel_req' => 25],
            4 => ['label' => 'Estilo 4 (75 nikas)', 'nivel_req' => 35],
        ];
        $estilo_slots_html = '';
        foreach ($estilo_defs as $num => $def) {
            $cur = $ficha["estilo$num"] ?? 'bloqueado';
            $locked_by_level = ($cur === 'bloqueado' && $ficha_nivel < $def['nivel_req']);
            if ($locked_by_level) {
                $estilo_slots_html .= '<div class="treset-slot">'
                    .'<label class="treset-slabel">'.$def['label'].'</label>'
                    .'<select name="r_estilo'.$num.'" class="treset-select" disabled>'
                    .'<option value="bloqueado">— Bloqueado —</option>'
                    .'</select>'
                    .'<span class="treset-hint" style="margin-top:3px;">Requiere nivel '.$def['nivel_req'].'</span>'
                    .'</div>';
            } else {
                $estilo_slots_html .= '<div class="treset-slot">'
                    .'<label class="treset-slabel">'.$def['label'].'</label>'
                    .'<select name="r_estilo'.$num.'" class="treset-select">'.treset_estilo_opts($cur, $estilos_db).'</select>'
                    .'</div>';
            }
        }

        // Build belica grid (12 slots) with camino sub-selects
        $caminos_map_ui = [
            'Escudero'       => ['Vanguardia',   'Bastión'],
            'Artista Marcial'=> ['Acróbata',     'Monje'],
            'Combatiente'    => ['Berserker',     'Campeón'],
            'Artista'        => ['Bardo',         'Trovador'],
            'Asesino'        => ['Sombra',        'Verdugo'],
            'Guerrero'       => ['Castigador',    'Warhammer'],
            'Espadachín'     => ['Samurái',       'Mosquetero'],
            'Tecnicista'     => ['Diletante',     'WeaponMaster'],
            'Artillero'      => ['Destructor',    'Juggernaut'],
            'Arquero'        => ['Ballestero',    'Cazador'],
            'Tirador'        => ['Duelista',      'Francotirador'],
            'Pícaro'         => ['Gambito',       'Trickster'],
        ];
        $ficha_nivel_ui = (int)$ficha['nivel'];
        $belica_grid = '';
        for ($i = 1; $i <= 12; $i++) {
            $cur  = $ficha["belica$i"] ?? '';
            $dd   = $cur ? ($ficha_belicas_data[$cur] ?? null) : null;
            $e1   = $dd ? ($dd['espe1'] ?? '') : '';
            $e2   = $dd ? ($dd['espe2'] ?? '') : '';
            $e1_sub = ($e1 && isset($dd['sub'][$e1])) ? (int)$dd['sub'][$e1] : 0;
            $e1n = $e1_sub >= 3 ? 3 : ($e1_sub >= 2 ? 2 : 1);
            $e2_sub = ($e2 && isset($dd['sub'][$e2])) ? (int)$dd['sub'][$e2] : 0;
            $e2n = $e2_sub >= 3 ? 3 : ($e2_sub >= 2 ? 2 : 1);
            if ($e1n === 2 && $e2n === 2) $e2n = 1;
            $avail_ui = $cur ? ($caminos_map_ui[$cur] ?? []) : [];
            $has_caminos = !empty($avail_ui) && $ficha_nivel_ui >= 8;

            $camino_html = '';
            if ($has_caminos) {
                $e1_opts = '<option value="">Sin camino</option>';
                $e2_opts = '<option value="">Sin camino</option>';
                foreach ($avail_ui as $c) {
                    $hc = htmlspecialchars($c, ENT_QUOTES);
                    $e1_opts .= '<option value="'.$hc.'"'.($e1===$c?' selected':'').'>'.$hc.'</option>';
                    $e2_opts .= '<option value="'.$hc.'"'.($e2===$c?' selected':'').'>'.$hc.'</option>';
                }
                $nivel_opts = '<option value="1"'.($e1n===1?' selected':'').'>Camino</option>'
                    .($ficha_nivel_ui >= 20 ? '<option value="2"'.($e1n===2?' selected':'').'>Especialización</option>' : '');
                $nivel_opts_2 = '<option value="1"'.($e2n===1?' selected':'').'>Camino</option>'
                    .($ficha_nivel_ui >= 20 ? '<option value="2"'.($e2n===2?' selected':'').'>Especialización</option>' : '');
                $camino_html = '<div class="treset-camino-row" id="treset-c-'.$i.'" data-slot="'.$i.'" data-avail="'.htmlspecialchars(json_encode($avail_ui), ENT_QUOTES).'">'
                    .'<div class="treset-camino-group">'
                    .'<label class="treset-slabel" style="font-size:11px">Camino 1</label>'
                    .'<select name="r_belica'.$i.'_espe1" class="treset-select treset-espe1" data-slot="'.$i.'">'.$e1_opts.'</select>'
                    .'<select name="r_belica'.$i.'_espe1_nivel" class="treset-select treset-espe1n" data-slot="'.$i.'" style="margin-top:3px">'.$nivel_opts.'</select>'
                    .'</div>'
                    .'<div class="treset-camino-group">'
                    .'<label class="treset-slabel" style="font-size:11px">Camino 2</label>'
                    .'<select name="r_belica'.$i.'_espe2" class="treset-select treset-espe2" data-slot="'.$i.'">'.$e2_opts.'</select>'
                    .'<select name="r_belica'.$i.'_espe2_nivel" class="treset-select treset-espe2n" data-slot="'.$i.'" style="margin-top:3px">'.$nivel_opts_2.'</select>'
                    .'</div>'
                    .'</div>';
            } else {
                $camino_html = '<div class="treset-camino-row" id="treset-c-'.$i.'" data-slot="'.$i.'" data-avail="[]" style="display:none">'
                    .'<div class="treset-camino-group"><label class="treset-slabel" style="font-size:11px">Camino 1</label>'
                    .'<select name="r_belica'.$i.'_espe1" class="treset-select treset-espe1" data-slot="'.$i.'"><option value="">Sin camino</option></select>'
                    .'<select name="r_belica'.$i.'_espe1_nivel" class="treset-select treset-espe1n" data-slot="'.$i.'" style="margin-top:3px"><option value="1">Camino</option>'
                    .($ficha_nivel_ui >= 20 ? '<option value="2">Especialización</option>' : '').'</select>'
                    .'</div>'
                    .'<div class="treset-camino-group"><label class="treset-slabel" style="font-size:11px">Camino 2</label>'
                    .'<select name="r_belica'.$i.'_espe2" class="treset-select treset-espe2" data-slot="'.$i.'"><option value="">Sin camino</option></select>'
                    .'<select name="r_belica'.$i.'_espe2_nivel" class="treset-select treset-espe2n" data-slot="'.$i.'" style="margin-top:3px"><option value="1">Camino</option>'
                    .($ficha_nivel_ui >= 20 ? '<option value="2">Especialización</option>' : '').'</select>'
                    .'</div></div>';
            }

            $belica_grid .= '<div class="treset-bslot">'
                .'<div class="treset-slot"><label class="treset-slabel">Slot '.$i.'</label>'
                .'<select name="r_belica'.$i.'" class="treset-select treset-disc-sel" data-slot="'.$i.'">'.treset_belica_opts($cur).'</select></div>'
                .$camino_html
                .'</div>';
        }

        // Camino options
        $camino_map = [''=>'Sin camino elegido','Voz'=>'Latido del Mundo','Haki'=>'Sendero de la Voluntad','Akuma'=>'Herencia del Árbol Prohibido'];
        $cur_camino = $ficha['camino'] ?? '';
        $camino_opts_html = '';
        foreach ($camino_map as $cv => $cl) {
            $sel = ($cv === $cur_camino) ? ' selected' : '';
            $camino_opts_html .= '<option value="'.htmlspecialchars($cv, ENT_QUOTES).'"'.$sel.'>'.htmlspecialchars($cl).'</option>';
        }

        // Current values pre-filled from ficha
        $c_kenbun = (int)($ficha['kenbun'] ?? 1);
        $c_buso   = (int)($ficha['buso'] ?? 1);
        $c_hao    = (int)($ficha['hao'] ?? 1);
        $c_dom    = (int)($ficha['dominio_akuma'] ?? 0);
        $c_akuma  = htmlspecialchars($ficha['akuma'] ?? '', ENT_QUOTES);
        $c_asub   = htmlspecialchars($ficha['akuma_subnombre'] ?? '', ENT_QUOTES);
        $akuma_origen_ui = $ficha['akuma_origen'] ?? '';

        // Haki max level — per-haki (kenbun despierta antes que buso/hao)
        $is_haki_camino  = ($ficha['camino'] === 'Haki');
        $kenbun_reqs_ui  = $is_haki_camino ? [2=>5,3=>15,4=>20,5=>25,6=>30,7=>35] : [2=>10,3=>20,4=>25,5=>30,6=>35,7=>40];
        $bh_reqs_ui      = $is_haki_camino ? [2=>10,3=>15,4=>20,5=>25,6=>30,7=>35] : [2=>15,3=>20,4=>25,5=>30,6=>35,7=>40];
        $haki_reqs_ui    = ['kenbun' => $kenbun_reqs_ui, 'buso' => $bh_reqs_ui, 'hao' => $bh_reqs_ui];
        $haki_slots_html = '';
        foreach (['kenbun' => $c_kenbun, 'buso' => $c_buso, 'hao' => $c_hao] as $hfield => $hcur) {
            $reqs_ui = $haki_reqs_ui[$hfield];
            $hmax = 1; foreach ($reqs_ui as $lvl => $req) { if ($ficha_nivel >= $req) $hmax = $lvl; }
            if ($hfield === 'hao' && $hcur <= 0) {
                $reason = ($hcur === -1) ? 'Sin obtener' : 'No despertado';
                $haki_slots_html .= '<div class="treset-slot">'
                    .'<label class="treset-slabel">Hao</label>'
                    .'<input type="number" name="r_hao" class="treset-input" value="0" min="0" max="0" disabled>'
                    .'<span class="treset-hint" style="margin-top:3px;">'.$reason.'</span>'
                    .'</div>';
            } elseif ($hcur <= 0) {
                $haki_slots_html .= '<div class="treset-slot">'
                    .'<label class="treset-slabel">'.ucfirst($hfield).'</label>'
                    .'<input type="number" name="r_'.$hfield.'" class="treset-input" value="0" min="0" max="0" disabled>'
                    .'<span class="treset-hint" style="margin-top:3px;">No despertado (requiere nivel '.$reqs_ui[2].')</span>'
                    .'</div>';
            } else {
                $hval = min($hcur, $hmax);
                $haki_slots_html .= '<div class="treset-slot">'
                    .'<label class="treset-slabel">'.ucfirst($hfield).' (1–'.$hmax.')</label>'
                    .'<input type="number" name="r_'.$hfield.'" class="treset-input" value="'.$hval.'" min="1" max="'.$hmax.'">'
                    .'</div>';
            }
        }

        // Technique swap data
        $treset_nivel_tier = 1;
        $fn_lvl = (int)$ficha['nivel'];
        if ($fn_lvl >= 40)      $treset_nivel_tier = 10;
        elseif ($fn_lvl >= 35)  $treset_nivel_tier = 9;
        elseif ($fn_lvl >= 30)  $treset_nivel_tier = 8;
        elseif ($fn_lvl >= 25)  $treset_nivel_tier = 7;
        elseif ($fn_lvl >= 20)  $treset_nivel_tier = 6;
        elseif ($fn_lvl >= 16)  $treset_nivel_tier = 5;
        elseif ($fn_lvl >= 12)  $treset_nivel_tier = 4;
        elseif ($fn_lvl >= 8)   $treset_nivel_tier = 3;
        elseif ($fn_lvl >= 4)   $treset_nivel_tier = 2;

        $treset_learned = [];
        $q_tl = $db->query("SELECT t.tid, t.nombre, t.tier+0 AS tier, t.rama FROM mybb_op_tecnicas t INNER JOIN mybb_op_tec_aprendidas ta ON t.tid = ta.tid WHERE ta.uid = '$user_uid' AND t.tid NOT REGEXP '^[0-9]' AND t.estilo != 'Racial' ORDER BY t.rama, t.tier+0, t.tid");
        while ($r = $db->fetch_array($q_tl)) {
            $treset_learned[] = ['tid' => $r['tid'], 'nombre' => $r['nombre'], 'tier' => (int)$r['tier'], 'rama' => $r['rama']];
        }
        $treset_learned_tids = array_column($treset_learned, 'tid');
        $treset_avail = [];
        if (!empty($treset_learned_tids)) {
            $esc_l = implode("','", array_map([$db, 'escape_string'], $treset_learned_tids));
            $q_ta  = $db->query("SELECT tid, nombre, tier+0 AS tier, rama FROM mybb_op_tecnicas WHERE tid NOT REGEXP '^[0-9]' AND estilo != 'Racial' AND tid NOT IN ('$esc_l') ORDER BY rama, tier+0, tid");
        } else {
            $q_ta  = $db->query("SELECT tid, nombre, tier+0 AS tier, rama FROM mybb_op_tecnicas WHERE tid NOT REGEXP '^[0-9]' AND estilo != 'Racial' ORDER BY rama, tier+0, tid");
        }
        while ($r = $db->fetch_array($q_ta)) {
            $treset_avail[] = ['tid' => $r['tid'], 'nombre' => $r['nombre'], 'tier' => (int)$r['tier'], 'rama' => $r['rama']];
        }
        $treset_learned_json = json_encode($treset_learned, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $treset_avail_json   = json_encode($treset_avail,   JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        // Initial build state for differential computation
        $treset_init_belica = $treset_init_espe1 = $treset_init_espe1n = $treset_init_espe2 = $treset_init_espe2n = [];
        for ($i = 1; $i <= 12; $i++) {
            $d  = $ficha["belica$i"] ?? '';
            $dd = $d ? ($ficha_belicas_data[$d] ?? null) : null;
            $e1 = $dd ? ($dd['espe1'] ?? '') : '';
            $e2 = $dd ? ($dd['espe2'] ?? '') : '';
            $treset_init_belica[]  = $d;
            $treset_init_espe1[]   = $e1;
            $treset_init_espe1n[]  = ($e1 && isset($dd['sub'][$e1]) && (int)$dd['sub'][$e1] >= 2) ? 2 : ($e1 ? 1 : 0);
            $treset_init_espe2[]   = $e2;
            $treset_init_espe2n[]  = ($e2 && isset($dd['sub'][$e2]) && (int)$dd['sub'][$e2] >= 2) ? 2 : ($e2 ? 1 : 0);
        }
        $treset_init_estilo = [];
        for ($i = 1; $i <= 4; $i++) { $treset_init_estilo[] = $ficha["estilo$i"] ?? 'bloqueado'; }
        $treset_init_json = json_encode([
            'belica' => $treset_init_belica, 'espe1' => $treset_init_espe1, 'espe1n' => $treset_init_espe1n,
            'espe2'  => $treset_init_espe2,  'espe2n' => $treset_init_espe2n, 'estilo' => $treset_init_estilo,
        ], JSON_UNESCAPED_UNICODE);

        $modal_body = '
<form id="treset-form" autocomplete="off">
<div class="treset-tabs">
<button type="button" class="treset-tab-btn active" data-tab="build" onclick="tresetSwitchTab(this)">Reconstrucción de Build</button>
<button type="button" class="treset-tab-btn" data-tab="tecs" onclick="tresetSwitchTab(this)">Intercambio de Técnicas</button>
</div>
<div id="treset-build-tab">
<div class="treset-budget-bar">
    Nikas: <strong>'.(int)$ficha['nika'].'✦</strong>&ensp;·&ensp;Pts Oficio: <strong>'.(int)$ficha['puntos_oficio'].' pto</strong>
    &ensp;·&ensp; Δ nikas: <strong id="treset-cost-val" style="color:#9ca3af">sin cambios</strong>
    &ensp;·&ensp; Δ pto: <strong id="treset-pto-val" style="color:#9ca3af">sin cambios</strong>
</div>
<div class="treset-section">
    <div class="treset-stitle">ATRIBUTOS</div>
    <label class="treset-check">
        <input type="checkbox" name="r_resetear_atributos" value="1">
        Resetear todos los puntos de atributos (se devuelven a puntos disponibles)
    </label>
</div>
<div class="treset-section">
    <div class="treset-stitle">DISCIPLINAS</div>
    <div class="treset-g3">'.$belica_grid.'</div>
</div>
<div class="treset-section">
    <div class="treset-stitle">ESTILOS</div>
    <div class="treset-g2">'.$estilo_slots_html.'</div>
</div>
<div class="treset-section">
    <div class="treset-stitle">OFICIOS</div>
    <div class="treset-g2">
        '.treset_of_slot_html(1, $ficha['oficio1'] ?? '', isset($cur_oficios_ui[$ficha['oficio1']??'']) ? $cur_oficios_ui[$ficha['oficio1']] : null, $of_subs_map_ui, $oficio_valid_ui).'
        '.treset_of_slot_html(2, $ficha['oficio2'] ?? '', isset($cur_oficios_ui[$ficha['oficio2']??'']) ? $cur_oficios_ui[$ficha['oficio2']] : null, $of_subs_map_ui, $oficio_valid_ui).'
    </div>
</div>
<div class="treset-section">
    <div class="treset-stitle">HAKI Y DOMINIO AKUMA</div>
    <div class="treset-g4">'.$haki_slots_html.'<div class="treset-slot"><label class="treset-slabel">Dominio Akuma (0-6)</label><input type="number" id="r_dominio_akuma_el" name="r_dominio_akuma" class="treset-input" value="'.$c_dom.'" min="0" max="6" data-orig="'.$c_dom.'"></div></div>
</div>
<div class="treset-section">
    <div class="treset-stitle">AKUMA NO MI</div>
    '.($akuma_origen_ui === 'aventura'
        ? '<div class="treset-g2">
            <div class="treset-slot"><label class="treset-slabel">Nombre</label><input type="text" id="r_akuma_el" name="r_akuma" class="treset-input" value="'.$c_akuma.'" maxlength="100" data-orig="'.$c_akuma.'"></div>
            <div class="treset-slot"><label class="treset-slabel">Subnombre</label><input type="text" name="r_akuma_subnombre" class="treset-input" value="'.$c_asub.'" maxlength="100"></div>
          </div>'
        : '<div class="treset-hint" style="color:#f87171;margin-bottom:6px">Akuma no obtenida por aventura — no modificable mediante reset.</div>
           <div class="treset-g2">
             <div class="treset-slot"><label class="treset-slabel">Nombre</label><input type="text" class="treset-input" value="'.$c_akuma.'" disabled></div>
             <div class="treset-slot"><label class="treset-slabel">Subnombre</label><input type="text" class="treset-input" value="'.$c_asub.'" disabled></div>
           </div>').'
</div>
<div class="treset-section">
    <div class="treset-stitle">CAMINO</div>
    <select name="r_camino" class="treset-select" style="max-width:320px">'.$camino_opts_html.'</select>
</div>
<div class="treset-section">
    <div class="treset-stitle">NOTAS ADICIONALES</div>
    <div class="treset-hint">Virtudes/defectos, motivo del reset u otros detalles relevantes.</div>
    <textarea name="r_notas" class="treset-textarea" rows="4" maxlength="2000" placeholder="Explica aquí el contexto del reset..."></textarea>
</div>
</div>
<div id="treset-tecs-tab" style="display:none">
<div class="treset-tecs-cols">
<div class="treset-tecs-col">
<div class="treset-stitle">TÉCNICAS A PERDER</div>
<div class="treset-hint">Técnicas aprendidas de las disciplinas/estilos que estás eliminando del build.</div>
<div class="treset-tecs-budget-bar">Tiempo liberado: <strong id="treset-tecs-budget" style="color:#86efac">0h</strong></div>
<div id="treset-tecs-lose-list" class="treset-tecs-list"></div>
</div>
<div class="treset-tecs-col">
<div class="treset-stitle">TÉCNICAS A GANAR</div>
<div class="treset-hint">Técnicas de las disciplinas/estilos que estás añadiendo (respetando límites de tier por nivel).</div>
<div class="treset-tecs-budget-bar">Tiempo a invertir: <strong id="treset-tecs-cost" style="color:#9ca3af">0h</strong> / <strong id="treset-tecs-budget2" style="color:#86efac">0h</strong></div>
<div id="treset-tecs-gain-list" class="treset-tecs-list"></div>
</div>
</div>
</div>
<div id="treset-error" class="treset-error" style="display:none"></div>
<button type="button" onclick="submitTicketReset()" class="treset-btn">CONFIRMAR Y ENVIAR PETICIÓN</button>
</form>
<script>
var TRESET_BUDGET              = '.$nika_presupuesto.';
var TRESET_PTO_BUDGET          = '.$pto_presupuesto.';
var TRESET_CURRENT_COST        = '.$ui_build_cost.';
var TRESET_OFICIO_CURRENT_NIKAS = '.$ui_oficio_nika_cost.';
var TRESET_OFICIO_CURRENT_PTO  = '.$ui_oficio_pto_cost.';
var TRESET_NIVEL = '.$ficha_nivel_ui.';
var TRESET_COSTS_B    = {2:10,3:20,4:35,5:50,6:65,7:80,8:95,9:110,10:125,11:140,12:155};
var TRESET_COSTS_E    = {2:25,3:50,4:75};
var TRESET_COSTS_E1   = {1:0,2:20,3:30,4:45,5:60,6:75,7:90,8:105,9:120,10:135,11:150,12:165};
var TRESET_COSTS_E2   = {1:15,2:30,3:40,4:60,5:75,6:90,7:105,8:120,9:135,10:150,11:165,12:180};
var TRESET_COSTS_SPEC = {1:45,2:60,3:75,4:100,5:125,6:150,7:175,8:200,9:225,10:250,11:275,12:300};
var TRESET_COSTS_MAEST = {1:90,2:120,3:150,4:200,5:250,6:300,7:350,8:400,9:450,10:500,11:550,12:600};
var TRESET_NIVEL_TIER = '.$treset_nivel_tier.';
var TRESET_TEC_HOURS = {1:1,2:4,3:8,4:12,5:24,6:36,7:48,8:60,9:72,10:100};
var TRESET_TECS_APRENDIDAS = '.$treset_learned_json.';
var TRESET_TECS_AVAIL      = '.$treset_avail_json.';
var TRESET_INITIAL_BUILD   = '.$treset_init_json.';
var TRESET_OFICIO_MAP = {
    "Artesano":["Herrero","Modista"],"Médico":["Farmacólogo","Doctor"],
    "Navegante":["Cartógrafo","Timonel"],"Inventor":["Biólogo","Ingeniero"],
    "Carpintero":["Astillero","Constructor"],"Cocinero":["Chef","Aprovisionador"],
    "Mercader":["Comerciante","Contrabandista"],"Investigador":["Periodista","Arqueólogo"],
    "Aventurero":["Cazador","Domador"],"Recolector":["Agreste","Mayorista"]
};
var TRESET_CAMINOS = {
    "Escudero":["Vanguardia","Bastión"],
    "Artista Marcial":["Acróbata","Monje"],
    "Combatiente":["Berserker","Campeón"],
    "Artista":["Bardo","Trovador"],
    "Asesino":["Sombra","Verdugo"],
    "Guerrero":["Castigador","Warhammer"],
    "Espadachín":["Samurái","Mosquetero"],
    "Tecnicista":["Diletante","WeaponMaster"],
    "Artillero":["Destructor","Juggernaut"],
    "Arquero":["Ballestero","Cazador"],
    "Tirador":["Duelista","Francotirador"],
    "Pícaro":["Gambito","Trickster"]
};
function tresetUpdateCaminoSlot(slot) {
    var discSel = document.querySelector("[name=r_belica"+slot+"]");
    var cDiv    = document.getElementById("treset-c-"+slot);
    if (!discSel || !cDiv) return;
    var disc    = discSel.value;
    var caminos = TRESET_CAMINOS[disc] || [];
    var e1Sel   = cDiv.querySelector(".treset-espe1");
    var e2Sel   = cDiv.querySelector(".treset-espe2");
    var e1nSel  = cDiv.querySelector(".treset-espe1n");
    var e2nSel  = cDiv.querySelector(".treset-espe2n");
    if (!caminos.length || TRESET_NIVEL < 8) {
        cDiv.style.display = "none";
        if (e1Sel) e1Sel.value = "";
        if (e2Sel) e2Sel.value = "";
        if (e1nSel) e1nSel.value = "1";
        if (e2nSel) e2nSel.value = "1";
        return;
    }
    cDiv.style.display = "";
    var curE1 = e1Sel ? e1Sel.value : "";
    var curE2 = e2Sel ? e2Sel.value : "";
    if (e1Sel) {
        e1Sel.innerHTML = \'<option value="">Sin camino</option>\';
        caminos.forEach(function(c){ e1Sel.innerHTML += \'<option value="\'+c+\'"\'+( curE1===c?\' selected\':\'\')+\'>\'+c+\'</option>\'; });
    }
    if (e2Sel) {
        e2Sel.innerHTML = \'<option value="">Sin camino</option>\';
        caminos.forEach(function(c){ e2Sel.innerHTML += \'<option value="\'+c+\'"\'+( curE2===c?\' selected\':\'\')+\'>\'+c+\'</option>\'; });
    }
}
function tresetUpdateOficioSlot(oi, resetLevels) {
    var sel = document.querySelector("[name=r_oficio"+oi+"]");
    var det = document.getElementById("treset-of-detail-"+oi);
    if (!sel || !det) return;
    var of = sel.value;
    var s1 = document.querySelector("[name=r_oficio"+oi+"_sub1_nivel]");
    var s2 = document.querySelector("[name=r_oficio"+oi+"_sub2_nivel]");
    if (!of || !TRESET_OFICIO_MAP[of]) {
        det.style.display = "none";
        if (s1) s1.value = "0";
        if (s2) s2.value = "0";
        return;
    }
    det.style.display = "";
    if (resetLevels) {
        if (s1) s1.value = "0";
        if (s2) s2.value = "0";
    }
    var subs = TRESET_OFICIO_MAP[of];
    var sub1El = det.querySelector(".treset-of-sub1name-"+oi);
    var sub2El = det.querySelector(".treset-of-sub2name-"+oi);
    if (sub1El) sub1El.textContent = subs[0] || "Sub 1";
    if (sub2El) sub2El.textContent = subs[1] || "Sub 2";
}
function tresetOficioCost() {
    var nika = 0, pto = 0;
    for (var oi = 1; oi <= 2; oi++) {
        var ofSel = document.querySelector("[name=r_oficio"+oi+"]");
        if (!ofSel || !ofSel.value) continue;
        var niv = document.querySelector("[name=r_oficio"+oi+"_nivel]");
        var sl1 = document.querySelector("[name=r_oficio"+oi+"_sub1_nivel]");
        var sl2 = document.querySelector("[name=r_oficio"+oi+"_sub2_nivel]");
        var nv = niv ? parseInt(niv.value, 10) : 1;
        if (nv >= 2) pto += 1000;
        [sl1, sl2].forEach(function(s) {
            var lv = s ? parseInt(s.value, 10) : 0;
            if (lv >= 1) { pto += 2000; nika += 10; }
            if (lv >= 2) { pto += 3500; nika += 25; }
            if (lv >= 3) { pto += 5000; nika += 50; }
        });
    }
    return {nika: nika, pto: pto};
}
function tresetRecalc() {
    var proposed = 0;
    for (var i = 2; i <= 12; i++) {
        var s = document.querySelector("[name=r_belica"+i+"]");
        if (s && s.value !== "") proposed += (TRESET_COSTS_B[i] || 0);
    }
    for (var i = 1; i <= 12; i++) {
        var e1 = document.querySelector("[name=r_belica"+i+"_espe1]");
        var en = document.querySelector("[name=r_belica"+i+"_espe1_nivel]");
        var e2 = document.querySelector("[name=r_belica"+i+"_espe2]");
        var en2 = document.querySelector("[name=r_belica"+i+"_espe2_nivel]");
        if (e1 && e1.value !== "") {
            proposed += (TRESET_COSTS_E1[i] || 0);
            var e1n = en ? parseInt(en.value, 10) : 1;
            if (e1n >= 2) proposed += (TRESET_COSTS_SPEC[i] || 0);
        }
        if (e2 && e2.value !== "") {
            proposed += (TRESET_COSTS_E2[i] || 0);
            var e2n = en2 ? parseInt(en2.value, 10) : 1;
            if (e2n >= 2) proposed += (TRESET_COSTS_SPEC[i] || 0);
        }
    }
    for (var i = 2; i <= 4; i++) {
        var s = document.querySelector("[name=r_estilo"+i+"]");
        if (s && s.value !== "bloqueado") proposed += (TRESET_COSTS_E[i] || 0);
    }
    var ofCost       = tresetOficioCost();
    var totalNikas   = proposed + ofCost.nika;
    var totalCurrent = TRESET_CURRENT_COST + TRESET_OFICIO_CURRENT_NIKAS;
    var nikaNet      = totalNikas - totalCurrent;
    var costEl = document.getElementById("treset-cost-val");
    if (costEl) {
        if (nikaNet === 0) { costEl.textContent = "sin cambios"; costEl.style.color = "#9ca3af"; }
        else if (nikaNet > 0) { costEl.textContent = "−" + nikaNet + " nikas"; costEl.style.color = totalNikas <= TRESET_BUDGET ? "#fbbf24" : "#fca5a5"; }
        else { costEl.textContent = "+" + (-nikaNet) + " nikas reembolso"; costEl.style.color = "#86efac"; }
    }
    var ptoNet  = ofCost.pto - TRESET_OFICIO_CURRENT_PTO;
    var ptoEl   = document.getElementById("treset-pto-val");
    if (ptoEl) {
        if (ptoNet === 0) { ptoEl.textContent = "sin cambios"; ptoEl.style.color = "#9ca3af"; }
        else if (ptoNet > 0) { ptoEl.textContent = "−" + ptoNet + " pto"; ptoEl.style.color = ofCost.pto <= TRESET_PTO_BUDGET ? "#fbbf24" : "#fca5a5"; }
        else { ptoEl.textContent = "+" + (-ptoNet) + " pto reembolso"; ptoEl.style.color = "#86efac"; }
    }
    return {nikas: totalNikas, pto: ofCost.pto};
}
function tresetSwitchTab(btn) {
    var tab = btn.dataset.tab;
    document.querySelectorAll(".treset-tab-btn").forEach(function(b){ b.classList.remove("active"); });
    btn.classList.add("active");
    document.getElementById("treset-build-tab").style.display = tab === "build" ? "" : "none";
    var tecTab = document.getElementById("treset-tecs-tab");
    if (tecTab) { tecTab.style.display = tab === "tecs" ? "" : "none"; if (tab === "tecs") tresetRefreshTecTab(); }
}
function tresetBuildSources(build) {
    var src = {};
    for (var i = 0; i < 12; i++) {
        var disc = build.belica[i];
        if (!disc) continue;
        src[disc] = {tierCap: 2, exact: true};
        var e1 = build.espe1[i], e1n = build.espe1n[i];
        if (e1) { var cap1 = e1n >= 2 ? TRESET_NIVEL_TIER : Math.min(TRESET_NIVEL_TIER, 5); if (!src[e1] || src[e1].tierCap < cap1) src[e1] = {tierCap: cap1, exact: false}; }
        var e2 = build.espe2[i], e2n = build.espe2n[i];
        if (e2) { var cap2 = e2n >= 2 ? TRESET_NIVEL_TIER : Math.min(TRESET_NIVEL_TIER, 5); if (!src[e2] || src[e2].tierCap < cap2) src[e2] = {tierCap: cap2, exact: false}; }
    }
    for (var i = 0; i < 4; i++) {
        var est = build.estilo[i];
        if (est && est !== "bloqueado" && est !== "no_bloqueado") src[est] = {tierCap: TRESET_NIVEL_TIER, exact: false};
    }
    return src;
}
function tresetGetProposedBuild() {
    var b = {belica:[], espe1:[], espe1n:[], espe2:[], espe2n:[], estilo:[]};
    for (var i = 1; i <= 12; i++) {
        var disc = document.querySelector("[name=r_belica"+i+"]");
        b.belica.push(disc ? disc.value : "");
        var e1s = document.querySelector("[name=r_belica"+i+"_espe1]"); b.espe1.push(e1s ? e1s.value : "");
        var e1n = document.querySelector("[name=r_belica"+i+"_espe1_nivel]"); b.espe1n.push(e1n ? (parseInt(e1n.value)||1) : 1);
        var e2s = document.querySelector("[name=r_belica"+i+"_espe2]"); b.espe2.push(e2s ? e2s.value : "");
        var e2n = document.querySelector("[name=r_belica"+i+"_espe2_nivel]"); b.espe2n.push(e2n ? (parseInt(e2n.value)||1) : 1);
    }
    for (var i = 1; i <= 4; i++) { var es = document.querySelector("[name=r_estilo"+i+"]"); b.estilo.push(es ? es.value : "bloqueado"); }
    return b;
}
function tecMatchesSource(tec, name, srcObj) {
    return srcObj.exact ? tec.rama === name : tec.rama.indexOf(name) !== -1;
}
var _trt_lose = {}, _trt_gain = {};
function tresetRefreshTecTab() {
    var proposed = tresetGetProposedBuild();
    var propSrc = tresetBuildSources(proposed);
    var initSrc = tresetBuildSources(TRESET_INITIAL_BUILD);
    var removedSrc = {}, addedSrc = {};
    for (var n in initSrc) { if (!propSrc[n]) removedSrc[n] = initSrc[n]; }
    for (var n in propSrc) { if (!initSrc[n]) addedSrc[n] = propSrc[n]; }
    var loseList = document.getElementById("treset-tecs-lose-list");
    var gainList = document.getElementById("treset-tecs-gain-list");
    if (!loseList || !gainList) return;
    var loseTecs = TRESET_TECS_APRENDIDAS.filter(function(t){ for(var n in removedSrc){ if(tecMatchesSource(t,n,removedSrc[n])) return true; } return false; });
    var gainTecs = TRESET_TECS_AVAIL.filter(function(t){ for(var n in addedSrc){ if(tecMatchesSource(t,n,addedSrc[n])&&t.tier<=addedSrc[n].tierCap) return true; } return false; });
    var buildCol = function(list, col) {
        if (!list.length) return \'<div class="treset-hint" style="padding:8px 0">No hay técnicas en las disciplinas/estilos \' + (col==="lose"?"eliminados":"añadidos") + \'.</div>\';
        return list.map(function(t){ var chk=((col==="lose"?_trt_lose:_trt_gain)[t.tid])?" checked":""; return \'<label class="treset-tec-item"><input type="checkbox" data-tid="\'+t.tid+\'" data-tier="\'+t.tier+\'" data-col="\'+col+\'"\'+chk+\'> <span class="treset-tec-name">\'+t.nombre+\'</span><span class="treset-tec-tier">T\'+t.tier+\' (\'+TRESET_TEC_HOURS[t.tier]+\'h)</span></label>\'; }).join("");
    };
    loseList.innerHTML = buildCol(loseTecs, "lose");
    gainList.innerHTML = buildCol(gainTecs, "gain");
    document.querySelectorAll("#treset-tecs-lose-list input, #treset-tecs-gain-list input").forEach(function(el){
        el.addEventListener("change", function(){
            var tid=this.dataset.tid, col=this.dataset.col;
            if(col==="lose"){ if(this.checked) _trt_lose[tid]=true; else delete _trt_lose[tid]; }
            else { if(this.checked) _trt_gain[tid]=true; else delete _trt_gain[tid]; }
            tresetUpdateTecBudget();
        });
    });
    tresetUpdateTecBudget();
}
function tresetUpdateTecBudget() {
    var bh=0, ch=0;
    for(var tid in _trt_lose){ var t=TRESET_TECS_APRENDIDAS.find(function(x){return x.tid===tid;}); if(t) bh+=TRESET_TEC_HOURS[t.tier]||0; }
    for(var tid in _trt_gain){ var t=TRESET_TECS_AVAIL.find(function(x){return x.tid===tid;}); if(t) ch+=TRESET_TEC_HOURS[t.tier]||0; }
    var bEl=document.getElementById("treset-tecs-budget"), cEl=document.getElementById("treset-tecs-cost"), b2El=document.getElementById("treset-tecs-budget2");
    if(bEl) bEl.textContent=bh+"h";
    if(b2El) b2El.textContent=bh+"h";
    if(cEl){ cEl.textContent=ch+"h"; cEl.style.color=ch<=bh?"#86efac":"#fca5a5"; }
}
function tresetEnforceBelicaLimit() {
    var sels = document.querySelectorAll(".treset-disc-sel");
    var filled = 0;
    sels.forEach(function(s){ if (s.value !== "") filled++; });
    sels.forEach(function(s){
        if (s.value === "" && filled >= 6) {
            s.disabled = true;
            s.title = "Límite de 6 disciplinas alcanzado";
        } else {
            s.disabled = false;
            s.title = "";
        }
    });
}
document.getElementById("treset-form").addEventListener("change", function(ev) {
    var disc  = ev.target.closest(".treset-disc-sel");
    if (disc) { tresetUpdateCaminoSlot(parseInt(disc.dataset.slot)); tresetEnforceBelicaLimit(); }
    var ofSel = ev.target.closest(".treset-of-sel");
    if (ofSel) { tresetUpdateOficioSlot(parseInt(ofSel.dataset.oi), true); }
    var nSel = ev.target;
    if ((nSel.classList.contains("treset-espe1n") || nSel.classList.contains("treset-espe2n")) && nSel.value === "2") {
        var slotN = parseInt(nSel.dataset.slot);
        var isE1n = nSel.classList.contains("treset-espe1n");
        var pairN = document.querySelector("[name=r_belica"+slotN+"_espe"+(isE1n?"2":"1")+"_nivel]");
        if (pairN && pairN.value === "2") pairN.value = "1";
    }
    tresetRecalc();
});
for (var _si = 1; _si <= 12; _si++) { tresetUpdateCaminoSlot(_si); }
for (var _oi = 1; _oi <= 2; _oi++) { tresetUpdateOficioSlot(_oi); }
tresetEnforceBelicaLimit();
tresetRecalc();
(function(){
    var akumaEl = document.getElementById("r_akuma_el");
    var domEl   = document.getElementById("r_dominio_akuma_el");
    if (!akumaEl || !domEl) return;
    var origAkuma = akumaEl.dataset.orig;
    var origDom   = parseInt(domEl.dataset.orig, 10);
    function syncDom() {
        var cur = akumaEl.value.trim();
        if (cur === "") { domEl.value = 0; domEl.disabled = true; }
        else if (cur !== origAkuma) { domEl.value = origDom; domEl.disabled = true; }
        else { domEl.disabled = false; }
    }
    akumaEl.addEventListener("input", syncDom);
    syncDom();
})();
function submitTicketReset() {
    var totals = tresetRecalc();
    var errDiv = document.getElementById("treset-error");
    if (totals.nikas > TRESET_BUDGET) {
        var net = totals.nikas - (TRESET_CURRENT_COST + TRESET_OFICIO_CURRENT_NIKAS);
        errDiv.textContent = "No tienes suficientes nikas: este build cuesta " + net + " nikas adicionales pero solo dispones de " + TRESET_BUDGET + ".";
        errDiv.style.display = "block";
        return;
    }
    if (totals.pto > TRESET_PTO_BUDGET) {
        var ptaNet = totals.pto - TRESET_OFICIO_CURRENT_PTO;
        errDiv.textContent = "No tienes suficientes puntos de oficio: los oficios propuestos cuestan " + ptaNet + " pts adicionales pero solo dispones de " + TRESET_PTO_BUDGET + ".";
        errDiv.style.display = "block";
        return;
    }
    var loseTids = Object.keys(_trt_lose);
    var gainTids = Object.keys(_trt_gain);
    var bh2=0, ch2=0;
    loseTids.forEach(function(tid){ var t=TRESET_TECS_APRENDIDAS.find(function(x){return x.tid===tid;}); if(t) bh2+=TRESET_TEC_HOURS[t.tier]||0; });
    gainTids.forEach(function(tid){ var t=TRESET_TECS_AVAIL.find(function(x){return x.tid===tid;}); if(t) ch2+=TRESET_TEC_HOURS[t.tier]||0; });
    if (ch2 > bh2) {
        errDiv.textContent = "Las técnicas a ganar (" + ch2 + "h) superan el tiempo liberado por las técnicas a perder (" + bh2 + "h).";
        errDiv.style.display = "block";
        return;
    }
    errDiv.style.display = "none";
    var btn = document.querySelector(".treset-btn");
    btn.disabled = true; btn.textContent = "Enviando...";
    var fd = new FormData(document.getElementById("treset-form"));
    fd.append("r_tecs_perder", loseTids.join(","));
    fd.append("r_tecs_ganar", gainTids.join(","));
    fd.append("accion","ticket_reset");
    fetch("/op/personaje.php?uid="+query_uid,{method:"POST",body:fd})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.ok){
                document.getElementById("treset-form").outerHTML="<div class=\'treset-status treset-ok\'>Petición enviada. El staff la revisará y aplicará los cambios próximamente.</div>";
            } else {
                errDiv.textContent=d.error||"Error desconocido."; errDiv.style.display="block";
                btn.disabled=false; btn.textContent="CONFIRMAR Y ENVIAR PETICIÓN";
            }
        })
        .catch(function(){ errDiv.textContent="Error de conexión."; errDiv.style.display="block"; btn.disabled=false; btn.textContent="CONFIRMAR Y ENVIAR PETICIÓN"; });
}
</script>';
    }

    $op_ficha_reset_panel = '
<style>
#tresetModal{position:fixed;z-index:99999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.82);display:flex;align-items:flex-start;justify-content:center;padding:24px 16px;box-sizing:border-box;overflow-y:auto;}
.treset-mbox{background:#111827;border:2px solid #ea580c;border-radius:12px;padding:24px;max-width:900px;width:100%;position:relative;}
.treset-mheader{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;}
.treset-mtitle{font-family:moonGetHeavy,Arial;color:#fb923c;font-size:20px;letter-spacing:.5px;margin:0;}
.treset-mclose{color:#6b7280;font-size:32px;line-height:1;cursor:pointer;padding:0 4px;user-select:none;}
.treset-mclose:hover{color:#f9fafb;}
.treset-subtitle{color:#9ca3af;font-size:13px;margin-bottom:18px;}
.treset-section{border-top:1px solid #1f2937;padding-top:14px;margin-bottom:16px;}
.treset-section:first-child{border-top:none;padding-top:0;}
.treset-stitle{font-family:moonGetHeavy,Arial;color:#f97316;font-size:11px;letter-spacing:2px;margin-bottom:10px;}
.treset-g3{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
.treset-bslot{display:flex;flex-direction:column;gap:4px;}
.treset-camino-row{background:#0f172a;border:1px solid #1f2937;border-radius:5px;padding:8px;display:flex;gap:8px;flex-wrap:wrap;}
.treset-camino-group{display:flex;flex-direction:column;flex:1;min-width:110px;}
.treset-g4{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;}
.treset-g2{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;}
.treset-slot{display:flex;flex-direction:column;}
.treset-slabel{font-size:11px;color:#6b7280;margin-bottom:3px;}
.treset-select,.treset-input{padding:7px 10px;background:#0f172a;border:1px solid #374151;border-radius:5px;color:#f9fafb;font-size:13px;width:100%;box-sizing:border-box;}
.treset-textarea{width:100%;padding:9px 12px;background:#0f172a;border:1px solid #374151;border-radius:6px;color:#f9fafb;font-size:13px;font-family:monospace;line-height:1.6;box-sizing:border-box;resize:vertical;}
.treset-check{font-size:13px;color:#d1d5db;display:flex;align-items:center;gap:8px;cursor:pointer;}
.treset-hint{font-size:12px;color:#6b7280;margin-bottom:8px;}
.treset-status{padding:14px;border-radius:8px;font-size:14px;color:#d1d5db;line-height:1.7;}
.treset-pending{background:#1e1b4b;border:1px solid #4f46e5;}
.treset-ok{background:#052e16;border:1px solid #166534;color:#86efac;}
.treset-btn{background:#ea580c;color:#fff;padding:11px 28px;border:none;border-radius:7px;font-size:14px;cursor:pointer;font-family:moonGetHeavy,Arial;margin-top:6px;}
.treset-btn:disabled{opacity:.5;cursor:not-allowed;}
.treset-error{background:#450a0a;border:1px solid #991b1b;color:#fca5a5;padding:10px;border-radius:6px;font-size:13px;margin-top:8px;}
.treset-budget-bar{background:#0f172a;border:1px solid #374151;border-radius:6px;padding:8px 14px;font-size:13px;color:#9ca3af;margin-bottom:14px;}
.treset-tabs{display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid #1f2937;padding-bottom:0;}
.treset-tab-btn{background:none;border:1px solid #374151;border-bottom:none;border-radius:6px 6px 0 0;padding:8px 18px;color:#9ca3af;font-size:12px;cursor:pointer;font-family:moonGetHeavy,Arial;letter-spacing:.5px;position:relative;bottom:-2px;}
.treset-tab-btn.active{background:#111827;border-color:#ea580c;border-bottom:2px solid #111827;color:#fb923c;}
.treset-tab-btn:hover:not(.active){color:#f9fafb;border-color:#6b7280;}
.treset-tecs-cols{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.treset-tecs-col{background:#0f172a;border:1px solid #1f2937;border-radius:8px;padding:12px;}
.treset-tecs-budget-bar{font-size:12px;color:#9ca3af;margin-bottom:8px;padding:5px 0;}
.treset-tecs-list{max-height:380px;overflow-y:auto;}
.treset-tec-item{display:flex;align-items:center;gap:6px;padding:5px 4px;font-size:12px;color:#d1d5db;cursor:pointer;border-bottom:1px solid #1f2937;line-height:1.4;}
.treset-tec-item:last-child{border-bottom:none;}
.treset-tec-item:hover{background:rgba(255,255,255,.04);}
.treset-tec-name{flex:1;}
.treset-tec-tier{color:#6b7280;font-size:11px;white-space:nowrap;margin-left:4px;}
@media(max-width:700px){.treset-g3,.treset-g4{grid-template-columns:repeat(2,1fr);}.treset-g2{grid-template-columns:1fr;}.treset-tecs-cols{grid-template-columns:1fr;}}
</style>
<div id="tresetModal" style="display:none">
    <div class="treset-mbox">
        <div class="treset-mheader">
            <span class="treset-mtitle">TICKET DE RESET DE BUILD</span>
            <span class="treset-mclose" onclick="closeResetModal()">&times;</span>
        </div>
        <div class="treset-subtitle">Permite solicitar cambios en disciplinas, estilos, oficios, haki, akuma y camino. El ticket se consumirá únicamente al confirmar la petición.</div>
        ' . $modal_body . '
    </div>
</div>
<script>
function openResetModal(){
    document.getElementById("tresetModal").style.display="flex";
}
function closeResetModal(){
    document.getElementById("tresetModal").style.display="none";
}
document.getElementById("tresetModal").addEventListener("click",function(e){
    if(e.target===this) closeResetModal();
});
</script>';
}

// ─── Render templates ─────────────────────────────────────────────────────────
eval("\$op_ficha_css      = \"".$templates->get("op_ficha_css3")."\";");
eval("\$op_ficha_portada  = \"".$templates->get("op_ficha_portada")."\";");
eval("\$op_ficha_biografia= \"".$templates->get("op_ficha_biografia")."\";");
eval("\$op_ficha_belico   = \"".$templates->get("op_ficha_belico")."\";");
eval("\$op_ficha_tecnicas = \"".$templates->get("op_ficha_tecnicas")."\";");
eval("\$op_ficha_tecnicas2= \"".$templates->get("op_ficha_tecnicas2")."\";");
eval("\$op_ficha_inventario= \"".$templates->get("op_ficha_inventario")."\";");
eval("\$op_ficha_secreto  = \"".$templates->get("op_ficha_secreto")."\";");

eval("\$page = \"".$templates->get("op_personaje")."\";");
output_page($page);
