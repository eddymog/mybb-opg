<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);
/**
 * MyBB 1.8 — One Piece Gaiden
 * Staff: Gestión de Peticiones de Reset de Build
 *
 * SQL para crear la tabla (ejecutar una vez en la base de datos):
 *
 * CREATE TABLE `mybb_op_fichas_reset` (
 *   `id`              int(10) unsigned NOT NULL AUTO_INCREMENT,
 *   `peticion_id`     int(10) unsigned NOT NULL,
 *   `uid`             int(10) unsigned NOT NULL,
 *   `backup_json`     longtext NOT NULL,
 *   `propuesta_json`  longtext NOT NULL,
 *   `estado`          enum('pendiente','aplicado','rechazado') NOT NULL DEFAULT 'pendiente',
 *   `staff_uid`       int(10) unsigned DEFAULT NULL,
 *   `staff_nombre`    varchar(255) DEFAULT NULL,
 *   `created_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   `applied_at`      datetime DEFAULT NULL,
 *   PRIMARY KEY (`id`),
 *   KEY `uid` (`uid`),
 *   KEY `peticion_id` (`peticion_id`),
 *   KEY `estado` (`estado`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'reset_peticiones.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid      = $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_staff($uid)) {
    header('Location: /');
    exit;
}

$accion   = $mybb->get_input('accion');
$reset_id = (int)$mybb->get_input('id');

// ─── Shared validation helpers ────────────────────────────────────────────────
$belica_valid = ['Escudero','Artista Marcial','Combatiente','Artista','Asesino','Guerrero','Espadachín','Tecnicista','Artillero','Arquero','Tirador','Pícaro'];
$oficio_valid = ['Artesano','Médico','Navegante','Inventor','Carpintero','Cocinero','Mercader','Investigador','Aventurero','Recolector'];
$camino_valid = ['','Voz','Haki','Akuma'];

$belica_map = [
    'Escudero'       => ['sub'=>['Vanguardia'=>0,'Bastión'=>0],'nivel'=>1],
    'Artista Marcial'=> ['sub'=>['Monje'=>0,'Acróbata'=>0],'nivel'=>1],
    'Combatiente'    => ['sub'=>['Berserker'=>0,'Campeón'=>0],'nivel'=>1],
    'Artista'        => ['sub'=>['Bardo'=>0,'Trovador'=>0],'nivel'=>1],
    'Asesino'        => ['sub'=>['Sombra'=>0,'Verdugo'=>0],'nivel'=>1],
    'Guerrero'       => ['sub'=>['Castigador'=>0,'Warhammer'=>0],'nivel'=>1],
    'Espadachín'     => ['sub'=>['Samurái'=>0,'Mosquetero'=>0],'nivel'=>1],
    'Tecnicista'     => ['sub'=>['Diletante'=>0,'WeaponMaster'=>0],'nivel'=>1],
    'Artillero'      => ['sub'=>['Destructor'=>0,'Juggernaut'=>0],'nivel'=>1],
    'Arquero'        => ['sub'=>['Ballestero'=>0,'Cazador'=>0],'nivel'=>1],
    'Tirador'        => ['sub'=>['Duelista'=>0,'Francotirador'=>0],'nivel'=>1],
    'Pícaro'         => ['sub'=>['Gambito'=>0,'Trickster'=>0],'nivel'=>1],
];
$oficio_map = [
    'Artesano'      => ['sub'=>['Herrero'=>0,'Modista'=>0],'nivel'=>1],
    'Médico'        => ['sub'=>['Farmacólogo'=>0,'Doctor'=>0],'nivel'=>1],
    'Navegante'     => ['sub'=>['Cartógrafo'=>0,'Timonel'=>0],'nivel'=>1],
    'Inventor'      => ['sub'=>['Biólogo'=>0,'Ingeniero'=>0],'nivel'=>1],
    'Carpintero'    => ['sub'=>['Astillero'=>0,'Constructor'=>0],'nivel'=>1],
    'Cocinero'      => ['sub'=>['Chef'=>0,'Aprovisionador'=>0],'nivel'=>1],
    'Mercader'      => ['sub'=>['Comerciante'=>0,'Contrabandista'=>0],'nivel'=>1],
    'Investigador'  => ['sub'=>['Periodista'=>0,'Arqueólogo'=>0],'nivel'=>1],
    'Aventurero'    => ['sub'=>['Cazador'=>0,'Domador'=>0],'nivel'=>1],
    'Recolector'    => ['sub'=>['Agreste'=>0,'Mayorista'=>0],'nivel'=>1],
];

$caminos_map_r = [
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

// Collect propuesta from POST p_* fields
function reset_collect_propuesta($backup, $belica_valid, $oficio_valid, $camino_valid) {
    global $caminos_map_r;
    $p = [];
    $p['resetear_atributos'] = !empty($_POST['p_resetear_atributos']);
    for ($i = 1; $i <= 12; $i++) {
        $v = trim($_POST["p_belica$i"] ?? '');
        $p["belica$i"] = in_array($v, $belica_valid) ? $v : '';
    }
    // Caminos por slot
    for ($i = 1; $i <= 12; $i++) {
        $disc  = $p["belica$i"];
        $avail = $caminos_map_r[$disc] ?? [];
        $e1    = trim($_POST["p_belica{$i}_espe1"] ?? '');
        $e2    = trim($_POST["p_belica{$i}_espe2"] ?? '');
        $e1n   = (int)($_POST["p_belica{$i}_espe1_nivel"] ?? 1);
        $e2n   = (int)($_POST["p_belica{$i}_espe2_nivel"] ?? 1);
        if (!in_array($e1, $avail)) $e1 = '';
        if (!in_array($e2, $avail)) $e2 = '';
        if ($e1 === '' || $e1 === $e2) $e2 = '';
        $e1n = $e1 !== '' ? min(2, max(1, $e1n)) : 1;
        $e2n = $e2 !== '' ? min(2, max(1, $e2n)) : 1;
        if ($e1n === 2 && $e2n === 2) $e2n = 1;
        $p["belica{$i}_espe1"]       = $e1;
        $p["belica{$i}_espe1_nivel"] = $e1n;
        $p["belica{$i}_espe2"]       = $e2;
        $p["belica{$i}_espe2_nivel"] = $e2n;
    }
    for ($i = 1; $i <= 4; $i++) {
        $p["estilo$i"] = mb_substr(trim($_POST["p_estilo$i"] ?? ''), 0, 100, 'UTF-8');
    }
    $v = trim($_POST['p_oficio1'] ?? '');
    $p['oficio1'] = in_array($v, $oficio_valid) ? $v : ($backup['oficio1'] ?? '');
    $v = trim($_POST['p_oficio2'] ?? '');
    $p['oficio2'] = in_array($v, $oficio_valid) ? $v : '';
    // Oficio nivel and sub-specializations
    $r_oficio_subs_map = [
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
    for ($oi = 1; $oi <= 2; $oi++) {
        $of_n = $p["oficio$oi"] ?? '';
        if (empty($of_n) || !isset($r_oficio_subs_map[$of_n])) {
            $p["oficio{$oi}_nivel"]      = 1;
            $p["oficio{$oi}_sub1_nivel"] = 0;
            $p["oficio{$oi}_sub2_nivel"] = 0;
            continue;
        }
        $p["oficio{$oi}_nivel"]      = min(2, max(1, (int)($_POST["p_oficio{$oi}_nivel"] ?? 1)));
        $p["oficio{$oi}_sub1_nivel"] = min(3, max(0, (int)($_POST["p_oficio{$oi}_sub1_nivel"] ?? 0)));
        $p["oficio{$oi}_sub2_nivel"] = min(3, max(0, (int)($_POST["p_oficio{$oi}_sub2_nivel"] ?? 0)));
    }
    $p['kenbun']        = max(1, min(7, (int)($_POST['p_kenbun'] ?? 1)));
    $p['buso']          = max(1, min(7, (int)($_POST['p_buso']   ?? 1)));
    $p['hao']           = max(1, min(7, (int)($_POST['p_hao']    ?? 1)));
    $p['dominio_akuma'] = max(0, min(6, (int)($_POST['p_dominio_akuma'] ?? 0)));
    $p['akuma']         = mb_substr(trim($_POST['p_akuma'] ?? ''), 0, 100, 'UTF-8');
    $p['akuma_subnombre'] = mb_substr(trim($_POST['p_akuma_subnombre'] ?? ''), 0, 100, 'UTF-8');
    $v = trim($_POST['p_camino'] ?? '');
    $p['camino']        = in_array($v, $camino_valid) ? $v : ($backup['camino'] ?? '');
    $p['nika_ajuste']           = (int)($_POST['p_nika_ajuste'] ?? 0);
    $p['puntos_oficio_ajuste']  = (int)($_POST['p_puntos_oficio_ajuste'] ?? 0);
    $p['notas'] = mb_substr(trim($_POST['p_notas'] ?? ''), 0, 2000, 'UTF-8');
    return $p;
}

$alert   = '';
$success = '';

// ─── Rechazar reset ───────────────────────────────────────────────────────────
if ($accion === 'rechazar' && $reset_id > 0) {
    $rq = $db->query("SELECT * FROM mybb_op_fichas_reset WHERE id='$reset_id' AND estado='pendiente' LIMIT 1");
    $reset = $db->fetch_array($rq);
    if ($reset) {
        $target_uid = (int)$reset['uid'];
        $pet_id     = (int)$reset['peticion_id'];
        $staff_esc  = $db->escape_string($username);

        $db->query("UPDATE mybb_op_fichas_reset SET estado='rechazado', staff_uid='$uid', staff_nombre='$staff_esc', applied_at=NOW() WHERE id='$reset_id'");
        $db->query("UPDATE mybb_op_peticiones SET resuelto=1, mod_uid='$uid', mod_nombre='$staff_esc' WHERE id='$pet_id'");

        // Return ticket to user
        $tq = $db->query("SELECT cantidad FROM mybb_op_inventario WHERE uid='$target_uid' AND objeto_id='TNP001' LIMIT 1");
        $tr = $db->fetch_array($tq);
        if ($tr) {
            $nc = (int)$tr['cantidad'] + 1;
            $db->query("UPDATE mybb_op_inventario SET cantidad='$nc' WHERE uid='$target_uid' AND objeto_id='TNP001'");
        } else {
            $db->query("INSERT INTO mybb_op_inventario(objeto_id,uid,cantidad,imagen,apodo,especial,editado,bautizado) VALUES ('TNP001','$target_uid','1','','','0','0','0')");
        }

        log_audit($uid, $username, $target_uid, '[Reset Build]', "Reset #$reset_id rechazado. Ticket devuelto.");
        header("Location: /op/staff/reset_peticiones.php?success=rechazado&id=$reset_id");
        exit;
    }
}

// ─── Guardar propuesta (sin aplicar) ─────────────────────────────────────────
if ($accion === 'guardar' && $reset_id > 0) {
    $rq = $db->query("SELECT backup_json FROM mybb_op_fichas_reset WHERE id='$reset_id' AND estado='pendiente' LIMIT 1");
    $rr = $db->fetch_array($rq);
    if ($rr) {
        $backup   = json_decode($rr['backup_json'], true) ?: [];
        $propuesta = reset_collect_propuesta($backup, $belica_valid, $oficio_valid, $camino_valid);
        $prop_esc  = $db->escape_string(json_encode($propuesta, JSON_UNESCAPED_UNICODE));
        $db->query("UPDATE mybb_op_fichas_reset SET propuesta_json='$prop_esc' WHERE id='$reset_id'");
    }
    header("Location: /op/staff/reset_peticiones.php?id=$reset_id&saved=1");
    exit;
}

// ─── Aplicar reset ────────────────────────────────────────────────────────────
if ($accion === 'aplicar' && $reset_id > 0) {
    $rq = $db->query("SELECT * FROM mybb_op_fichas_reset WHERE id='$reset_id' AND estado='pendiente' LIMIT 1");
    $reset = $db->fetch_array($rq);
    if (!$reset) {
        $alert = 'Reset no encontrado o ya procesado.';
    } else {
        $target_uid = (int)$reset['uid'];
        $backup     = json_decode($reset['backup_json'], true) ?: [];

        // Update propuesta from POST before applying
        $propuesta  = reset_collect_propuesta($backup, $belica_valid, $oficio_valid, $camino_valid);
        $prop_esc   = $db->escape_string(json_encode($propuesta, JSON_UNESCAPED_UNICODE));
        $db->query("UPDATE mybb_op_fichas_reset SET propuesta_json='$prop_esc' WHERE id='$reset_id'");

        // Load current ficha
        $fq = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$target_uid' LIMIT 1");
        $ficha = $db->fetch_array($fq);
        if (!$ficha) {
            $alert = 'Ficha no encontrada.';
        } else {
            $set_clauses = [];
            $log_parts   = [];

            // 1. Atributos reset
            if ($propuesta['resetear_atributos']) {
                $stats = ['fuerza','resistencia','reflejos','punteria','voluntad','agilidad','destreza'];
                $sum = 0;
                foreach ($stats as $s) { $sum += (int)$ficha[$s]; }
                $new_pts = (int)$ficha['puntos_estadistica'] + $sum;
                foreach ($stats as $s) { $set_clauses[] = "$s='0'"; }
                $set_clauses[] = "puntos_estadistica='$new_pts'";
                $log_parts[] = "Atributos→0, pts devueltos: $sum (total disponible: $new_pts)";
            }

            // 2. Belicas — rebuild JSON for discipline changes AND camino updates
            $old_disc = [];
            $new_disc = [];
            for ($i = 1; $i <= 12; $i++) {
                $ov = $backup["belica$i"] ?? '';
                $nv = $propuesta["belica$i"] ?? '';
                if ($ov !== '') $old_disc[$ov] = true;
                if ($nv !== '') $new_disc[$nv] = true;
                if ($nv !== $ov) {
                    $set_clauses[] = "belica$i='".$db->escape_string($nv)."'";
                    $log_parts[] = "belica$i:".($ov ?: '∅')."→".($nv ?: '∅');
                }
            }
            $removed_disc = array_diff_key($old_disc, $new_disc);
            $added_disc   = array_diff_key($new_disc, $old_disc);
            $cur_belicas  = json_decode($ficha['belicas'] ?? '{}', true) ?: [];
            foreach (array_keys($removed_disc) as $rd) { unset($cur_belicas[$rd]); }
            foreach (array_keys($added_disc) as $ad) {
                if (isset($belica_map[$ad])) { $cur_belicas[$ad] = $belica_map[$ad]; }
            }
            // Apply camino data to every discipline in the proposed build
            $belicas_changed = !empty($removed_disc) || !empty($added_disc);
            for ($i = 1; $i <= 12; $i++) {
                $disc = $propuesta["belica$i"] ?? '';
                if (!$disc || !isset($cur_belicas[$disc])) continue;
                $avail = $caminos_map_r[$disc] ?? [];
                $e1    = $propuesta["belica{$i}_espe1"] ?? '';
                $e2    = $propuesta["belica{$i}_espe2"] ?? '';
                $e1n   = (int)($propuesta["belica{$i}_espe1_nivel"] ?? 1);
                $e2n   = (int)($propuesta["belica{$i}_espe2_nivel"] ?? 1);
                $old_e1 = $cur_belicas[$disc]['espe1'] ?? '';
                $old_e2 = $cur_belicas[$disc]['espe2'] ?? '';
                $old_e1n = ($old_e1 && isset($cur_belicas[$disc]['sub'][$old_e1])) ? $cur_belicas[$disc]['sub'][$old_e1] : 1;
                $old_e2n = ($old_e2 && isset($cur_belicas[$disc]['sub'][$old_e2])) ? $cur_belicas[$disc]['sub'][$old_e2] : 1;
                $cur_belicas[$disc]['espe1'] = $e1;
                $cur_belicas[$disc]['espe2'] = $e2;
                foreach ($avail as $c) {
                    if ($c === $e1) $cur_belicas[$disc]['sub'][$c] = $e1n;
                    elseif ($c === $e2) $cur_belicas[$disc]['sub'][$c] = $e2n;
                    else $cur_belicas[$disc]['sub'][$c] = 0;
                }
                if ($e1 !== $old_e1 || $e2 !== $old_e2 || $e1n !== $old_e1n || $e2n !== $old_e2n) {
                    $belicas_changed = true;
                    $log_parts[] = "belica{$i}_caminos:{$old_e1}({$old_e1n})/{$old_e2}({$old_e2n})→{$e1}({$e1n})/{$e2}({$e2n})";
                }
            }
            if ($belicas_changed) {
                $set_clauses[] = "belicas='".$db->escape_string(json_encode($cur_belicas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))."'";
            }

            // 3. Estilos
            for ($i = 1; $i <= 4; $i++) {
                $ov = $backup["estilo$i"] ?? ''; $nv = $propuesta["estilo$i"] ?? '';
                if ($nv !== $ov) {
                    $set_clauses[] = "estilo$i='".$db->escape_string($nv)."'";
                    $log_parts[] = "estilo$i:".($ov ?: '∅')."→".($nv ?: '∅');
                }
            }

            // 4. Oficios
            $old_of = []; $new_of = [];
            foreach (['oficio1','oficio2'] as $ofk) {
                $ov = $backup[$ofk] ?? ''; $nv = $propuesta[$ofk] ?? '';
                if ($ov !== '') $old_of[$ov] = true;
                if ($nv !== '') $new_of[$nv] = true;
                if ($nv !== $ov) {
                    $set_clauses[] = "$ofk='".$db->escape_string($nv)."'";
                    $log_parts[] = "$ofk:".($ov ?: '∅')."→".($nv ?: '∅');
                }
            }
            $removed_of  = array_diff_key($old_of, $new_of);
            $added_of    = array_diff_key($new_of, $old_of);
            $cur_oficios = json_decode($ficha['oficios'] ?? '{}', true) ?: [];
            foreach (array_keys($removed_of) as $ro) { unset($cur_oficios[$ro]); }
            foreach (array_keys($added_of) as $ao) {
                if (isset($oficio_map[$ao])) { $cur_oficios[$ao] = $oficio_map[$ao]; }
            }
            // Apply nivel and sub levels from propuesta to each active oficio
            $oficios_changed = !empty($removed_of) || !empty($added_of);
            $r_of_subs = [
                'Artesano'=>['Herrero','Modista'],'Médico'=>['Farmacólogo','Doctor'],
                'Navegante'=>['Cartógrafo','Timonel'],'Inventor'=>['Biólogo','Ingeniero'],
                'Carpintero'=>['Astillero','Constructor'],'Cocinero'=>['Chef','Aprovisionador'],
                'Mercader'=>['Comerciante','Contrabandista'],'Investigador'=>['Periodista','Arqueólogo'],
                'Aventurero'=>['Cazador','Domador'],'Recolector'=>['Agreste','Mayorista'],
            ];
            for ($oi = 1; $oi <= 2; $oi++) {
                $of_n = $propuesta["oficio$oi"] ?? '';
                if (empty($of_n) || !isset($cur_oficios[$of_n])) continue;
                $sk        = $r_of_subs[$of_n] ?? [];
                $new_nivel = (int)($propuesta["oficio{$oi}_nivel"] ?? 1);
                $sl1       = (int)($propuesta["oficio{$oi}_sub1_nivel"] ?? 0);
                $sl2       = (int)($propuesta["oficio{$oi}_sub2_nivel"] ?? 0);
                $old_nivel = (int)($cur_oficios[$of_n]['nivel'] ?? 1);
                $old_sl1   = isset($sk[0]) ? (int)($cur_oficios[$of_n]['sub'][$sk[0]] ?? 0) : 0;
                $old_sl2   = isset($sk[1]) ? (int)($cur_oficios[$of_n]['sub'][$sk[1]] ?? 0) : 0;
                $cur_oficios[$of_n]['nivel'] = $new_nivel;
                if (isset($sk[0])) $cur_oficios[$of_n]['sub'][$sk[0]] = $sl1;
                if (isset($sk[1])) $cur_oficios[$of_n]['sub'][$sk[1]] = $sl2;
                // Rebuild espe1/espe2 from sub levels
                unset($cur_oficios[$of_n]['espe1'], $cur_oficios[$of_n]['espe2']);
                $espe_slot = 1;
                foreach (array_filter($sk) as $sn) {
                    if ((int)($cur_oficios[$of_n]['sub'][$sn] ?? 0) >= 1) {
                        $cur_oficios[$of_n]["espe$espe_slot"] = $sn;
                        $espe_slot++;
                    }
                }
                if ($new_nivel !== $old_nivel || $sl1 !== $old_sl1 || $sl2 !== $old_sl2) {
                    $oficios_changed = true;
                    $log_parts[] = "of{$oi}({$of_n}):n{$old_nivel}→n{$new_nivel},s1:{$old_sl1}→{$sl1},s2:{$old_sl2}→{$sl2}";
                }
            }
            if ($oficios_changed) {
                $set_clauses[] = "oficios='".$db->escape_string(json_encode($cur_oficios, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))."'";
            }

            // 5. Haki + dominio
            foreach (['kenbun','buso','hao','dominio_akuma'] as $hk) {
                $ov = (int)($backup[$hk] ?? 0); $nv = (int)($propuesta[$hk] ?? 0);
                if ($nv !== $ov) {
                    $set_clauses[] = "$hk='$nv'";
                    $log_parts[] = "$hk:$ov→$nv";
                }
            }

            // 6. Akuma
            $ov_a = $backup['akuma'] ?? ''; $nv_a = $propuesta['akuma'] ?? '';
            $ov_as = $backup['akuma_subnombre'] ?? ''; $nv_as = $propuesta['akuma_subnombre'] ?? '';
            if ($nv_a !== $ov_a || $nv_as !== $ov_as) {
                $set_clauses[] = "akuma='".$db->escape_string($nv_a)."'";
                $set_clauses[] = "akuma_subnombre='".$db->escape_string($nv_as)."'";
                $log_parts[] = "akuma:".($ov_a ?: '∅')."→".($nv_a ?: '∅');
            }

            // 7. Camino
            $ov_c = $backup['camino'] ?? ''; $nv_c = $propuesta['camino'] ?? '';
            if ($nv_c !== $ov_c) {
                $set_clauses[] = "camino='".$db->escape_string($nv_c)."'";
                $log_parts[] = "camino:".($ov_c ?: '∅')."→".($nv_c ?: '∅');
            }

            // 8. Nika — delta automático por cambios de build + ajuste manual de staff
            $slot_costs_b = ['belica2'=>10,'belica3'=>20,'belica4'=>35,'belica5'=>50,'belica6'=>65,
                             'belica7'=>80,'belica8'=>95,'belica9'=>110,'belica10'=>125,'belica11'=>140,'belica12'=>155];
            $slot_costs_e = ['estilo2'=>25,'estilo3'=>50,'estilo4'=>75];
            $r_costs_e1   = [1=>0,2=>20,3=>30,4=>45,5=>60,6=>75,7=>90,8=>105,9=>120,10=>135,11=>150,12=>165];
            $r_costs_e2   = [1=>15,2=>30,3=>40,4=>60,5=>75,6=>90,7=>105,8=>120,9=>135,10=>150,11=>165,12=>180];
            $r_costs_spec = [1=>45,2=>60,3=>75,4=>100,5=>125,6=>150,7=>175,8=>200,9=>225,10=>250,11=>275,12=>300];
            $r_costs_maest = [1=>90,2=>120,3=>150,4=>200,5=>250,6=>300,7=>350,8=>400,9=>450,10=>500,11=>550,12=>600];
            $bak_belicas_json = json_decode($backup['belicas'] ?? '{}', true) ?: [];
            $old_build_cost = 0;
            for ($i = 2; $i <= 12; $i++) {
                if (!empty($backup["belica$i"])) $old_build_cost += $slot_costs_b["belica$i"];
            }
            for ($i = 2; $i <= 4; $i++) {
                if (($backup["estilo$i"] ?? 'bloqueado') !== 'bloqueado') $old_build_cost += $slot_costs_e["estilo$i"];
            }
            for ($i = 1; $i <= 12; $i++) {
                $disc = $backup["belica$i"] ?? '';
                if (!$disc) continue;
                $dd = $bak_belicas_json[$disc] ?? null; if (!$dd) continue;
                $e1 = $dd['espe1'] ?? '';
                if ($e1 !== '') {
                    $old_build_cost += $r_costs_e1[$i];
                    $e1_sub_old = (int)($dd['sub'][$e1] ?? 0);
                    if ($e1_sub_old >= 2) $old_build_cost += $r_costs_spec[$i];
                    if ($e1_sub_old >= 3) $old_build_cost += $r_costs_maest[$i];
                }
                $e2_bk = $dd['espe2'] ?? '';
                if ($e2_bk !== '') {
                    $old_build_cost += $r_costs_e2[$i];
                    $e2_sub_old = (int)($dd['sub'][$e2_bk] ?? 0);
                    if ($e2_sub_old >= 2) $old_build_cost += $r_costs_spec[$i];
                    if ($e2_sub_old >= 3) $old_build_cost += $r_costs_maest[$i];
                }
            }
            $new_build_cost = 0;
            for ($i = 2; $i <= 12; $i++) {
                if (!empty($propuesta["belica$i"])) $new_build_cost += $slot_costs_b["belica$i"];
            }
            for ($i = 2; $i <= 4; $i++) {
                if (($propuesta["estilo$i"] ?? 'bloqueado') !== 'bloqueado') $new_build_cost += $slot_costs_e["estilo$i"];
            }
            for ($i = 1; $i <= 12; $i++) {
                $e1 = $propuesta["belica{$i}_espe1"] ?? '';
                if ($e1 !== '') {
                    $new_build_cost += $r_costs_e1[$i];
                    $e1n_new = (int)($propuesta["belica{$i}_espe1_nivel"] ?? 1);
                    if ($e1n_new >= 2) $new_build_cost += $r_costs_spec[$i];
                }
                $e2 = $propuesta["belica{$i}_espe2"] ?? '';
                if ($e2 !== '') {
                    $new_build_cost += $r_costs_e2[$i];
                    $e2n_new = (int)($propuesta["belica{$i}_espe2_nivel"] ?? 1);
                    if ($e2n_new >= 2) $new_build_cost += $r_costs_spec[$i];
                }
            }
            // Include oficio espe nikas in build cost delta
            $bak_oficios_r = json_decode($backup['oficios'] ?? '{}', true) ?: [];
            foreach ($bak_oficios_r as $of_d) {
                foreach (($of_d['sub'] ?? []) as $sl_v) {
                    $sl = (int)$sl_v;
                    if ($sl >= 1) $old_build_cost += 10;
                    if ($sl >= 2) $old_build_cost += 25;
                    if ($sl >= 3) $old_build_cost += 50;
                }
            }
            foreach ($cur_oficios as $of_d) {
                foreach (($of_d['sub'] ?? []) as $sl_v) {
                    $sl = (int)$sl_v;
                    if ($sl >= 1) $new_build_cost += 10;
                    if ($sl >= 2) $new_build_cost += 25;
                    if ($sl >= 3) $new_build_cost += 50;
                }
            }
            $auto_nika_delta  = $old_build_cost - $new_build_cost; // positivo=reembolso, negativo=cobro
            $nika_adj         = (int)($propuesta['nika_ajuste'] ?? 0);
            $total_nika_delta = $auto_nika_delta + $nika_adj;
            if ($total_nika_delta !== 0) {
                $new_nika = max(0, (int)$ficha['nika'] + $total_nika_delta);
                $set_clauses[] = "nika='$new_nika'";
                $sign_auto = $auto_nika_delta >= 0 ? '+' : '';
                $sign_man  = $nika_adj >= 0 ? '+' : '';
                $log_parts[] = "nika:auto={$sign_auto}{$auto_nika_delta},manual={$sign_man}{$nika_adj}→{$new_nika}";
            }

            // 8b. Puntos de oficio — auto delta from oficio changes + manual staff adjustment
            $old_of_pto = 0;
            $new_of_pto = 0;
            foreach ($bak_oficios_r as $of_d) {
                if (($of_d['nivel'] ?? 1) >= 2) $old_of_pto += 1000;
                foreach (($of_d['sub'] ?? []) as $sl_v) {
                    $sl = (int)$sl_v;
                    if ($sl >= 1) $old_of_pto += 2000;
                    if ($sl >= 2) $old_of_pto += 3500;
                    if ($sl >= 3) $old_of_pto += 5000;
                }
            }
            foreach ($cur_oficios as $of_d) {
                if (($of_d['nivel'] ?? 1) >= 2) $new_of_pto += 1000;
                foreach (($of_d['sub'] ?? []) as $sl_v) {
                    $sl = (int)$sl_v;
                    if ($sl >= 1) $new_of_pto += 2000;
                    if ($sl >= 2) $new_of_pto += 3500;
                    if ($sl >= 3) $new_of_pto += 5000;
                }
            }
            $auto_po_delta  = $old_of_pto - $new_of_pto;
            $po_adj         = (int)($propuesta['puntos_oficio_ajuste'] ?? 0);
            $total_po_delta = $auto_po_delta + $po_adj;
            if ($total_po_delta !== 0) {
                $new_po    = max(0, (int)$ficha['puntos_oficio'] + $total_po_delta);
                $set_clauses[] = "puntos_oficio='$new_po'";
                $sign_auto = $auto_po_delta >= 0 ? '+' : '';
                $sign_man  = $po_adj >= 0 ? '+' : '';
                $log_parts[] = "puntos_oficio:auto={$sign_auto}{$auto_po_delta},manual={$sign_man}{$po_adj}→{$new_po}";
            }

            // Apply ficha changes
            if (!empty($set_clauses)) {
                $set_sql = implode(', ', $set_clauses);
                $db->query("UPDATE mybb_op_fichas SET $set_sql WHERE fid='$target_uid'");
            }

            // Apply technique swap
            $apply_tecs_perder = $propuesta['tecs_perder'] ?? [];
            $apply_tecs_ganar  = $propuesta['tecs_ganar']  ?? [];
            foreach ((array)$apply_tecs_perder as $tec_del) {
                $tec_del_e = $db->escape_string($tec_del);
                $db->query("DELETE FROM mybb_op_tec_aprendidas WHERE uid='$target_uid' AND tid='$tec_del_e'");
            }
            foreach ((array)$apply_tecs_ganar as $tec_add) {
                $tec_add_e = $db->escape_string($tec_add);
                $db->query("INSERT IGNORE INTO mybb_op_tec_aprendidas (tid, uid) VALUES ('$tec_add_e', '$target_uid')");
            }
            if (!empty($apply_tecs_perder)) $log_parts[] = 'tecs_perdidas:'.count($apply_tecs_perder);
            if (!empty($apply_tecs_ganar))  $log_parts[] = 'tecs_ganadas:'.count($apply_tecs_ganar);

            // Mark peticion resolved and reset applied
            $pet_id    = (int)$reset['peticion_id'];
            $staff_esc = $db->escape_string($username);
            $db->query("UPDATE mybb_op_peticiones SET resuelto=1, mod_uid='$uid', mod_nombre='$staff_esc' WHERE id='$pet_id'");
            $db->query("UPDATE mybb_op_fichas_reset SET estado='aplicado', staff_uid='$uid', staff_nombre='$staff_esc', applied_at=NOW() WHERE id='$reset_id'");

            $log_str = implode('; ', $log_parts) ?: 'Sin cambios de campo';
            log_audit($uid, $username, $target_uid, '[Reset Build]', "Reset #$reset_id aplicado: $log_str");

            header("Location: /op/staff/reset_peticiones.php?success=aplicado&id=$reset_id");
            exit;
        }
    }
}

// ─── Helper: render option select ────────────────────────────────────────────
function rst_sel_opts(array $opts, $current, $empty_label = '— vacío —') {
    $html = '';
    foreach ($opts as $v) {
        $sel = ((string)$v === (string)$current) ? ' selected' : '';
        $label = ($v === '') ? $empty_label : $v;
        $html .= '<option value="'.htmlspecialchars($v, ENT_QUOTES).'"'.$sel.'>'.htmlspecialchars($label).'</option>';
    }
    return $html;
}
function rst_camino_label($v) {
    $map = [''=>'Sin camino','Voz'=>'Latido del Mundo','Haki'=>'Sendero de la Voluntad','Akuma'=>'Herencia del Árbol Prohibido'];
    return $map[$v] ?? $v;
}

// ─── Detail view ─────────────────────────────────────────────────────────────
if ($reset_id > 0) {
    $rq = $db->query("SELECT r.*, p.nombre AS p_nombre, p.enviado AS p_enviado FROM mybb_op_fichas_reset r LEFT JOIN mybb_op_peticiones p ON p.id=r.peticion_id WHERE r.id='$reset_id' LIMIT 1");
    $reset = $db->fetch_array($rq);

    if (!$reset) {
        $reset_content = '<p style="color:#f87171">Reset no encontrado.</p><p><a href="/op/staff/reset_peticiones.php">← Volver al listado</a></p>';
    } else {
        $backup    = json_decode($reset['backup_json'], true) ?: [];
        $propuesta = json_decode($reset['propuesta_json'], true) ?: [];
        $estado    = $reset['estado'];
        $p_nombre  = htmlspecialchars($reset['p_nombre'] ?? '');
        $p_fecha   = htmlspecialchars($reset['p_enviado'] ?? $reset['created_at'] ?? '');
        $saved_msg = !empty($_GET['saved']) ? '<div class="rst-alert rst-ok">Propuesta guardada.</div>' : '';

        // Load current ficha for live resource display
        $target_uid_detail = (int)$reset['uid'];
        $fq_detail = $db->query("SELECT nika, puntos_oficio FROM mybb_op_fichas WHERE fid='$target_uid_detail' LIMIT 1");
        $ficha_detail = $db->fetch_array($fq_detail);
        $cur_nika = (int)($ficha_detail['nika'] ?? 0);
        $cur_po   = (int)($ficha_detail['puntos_oficio'] ?? 0);

        // Diff helper: mark field if propuesta differs from backup
        $diff = function($field) use ($backup, $propuesta) {
            $ov = (string)($backup[$field] ?? '');
            $nv = (string)($propuesta[$field] ?? '');
            return ($nv !== $ov) ? ' rst-changed' : '';
        };

        $is_pending  = ($estado === 'pendiente');
        $form_start  = $is_pending ? '<form method="POST" action="/op/staff/reset_peticiones.php?id='.$reset_id.'">' : '<div class="rst-readonly-note">Esta petición ya fue procesada ('.$estado.'). Vista de solo lectura.</div>';
        $form_end    = $is_pending ? '</form>' : '</div>';

        // Belica selects / text display (with caminos)
        $belica_opts_arr = array_merge([''], $belica_valid);
        $belica_rows = '';
        for ($i = 1; $i <= 12; $i++) {
            $cls     = $diff("belica$i");
            $cur     = $propuesta["belica$i"] ?? '';
            $bak     = $backup["belica$i"] ?? '';
            $bak_lbl = $bak ?: '—';
            $e1      = $propuesta["belica{$i}_espe1"] ?? '';
            $e2      = $propuesta["belica{$i}_espe2"] ?? '';
            $e1n     = (int)($propuesta["belica{$i}_espe1_nivel"] ?? 1);
            $e2n     = (int)($propuesta["belica{$i}_espe2_nivel"] ?? 1);
            $avail_r = $caminos_map_r[$cur] ?? [];
            $bak_e1n = (int)($backup["belica{$i}_espe1_nivel"] ?? 1);
            $bak_e2  = $backup["belica{$i}_espe2"] ?? '';
            $bak_e2n = (int)($backup["belica{$i}_espe2_nivel"] ?? 1);
            $e1_cls  = ($e1 !== ($backup["belica{$i}_espe1"] ?? '') || $e1n !== $bak_e1n) ? ' rst-changed' : '';
            $e2_cls  = ($e2 !== $bak_e2 || $e2n !== $bak_e2n) ? ' rst-changed' : '';
            if ($is_pending) {
                $camino_html_r = '';
                if (!empty($avail_r)) {
                    $e1_opts_r = '<option value="">Sin camino</option>';
                    $e2_opts_r = '<option value="">Sin camino</option>';
                    foreach ($avail_r as $c) {
                        $hc = htmlspecialchars($c, ENT_QUOTES);
                        $e1_opts_r .= '<option value="'.$hc.'"'.($e1===$c?' selected':'').'>'.$hc.'</option>';
                        $e2_opts_r .= '<option value="'.$hc.'"'.($e2===$c?' selected':'').'>'.$hc.'</option>';
                    }
                    $e1n_opts_r = '<option value="1"'.($e1n===1?' selected':'').'>Camino</option>'
                        .'<option value="2"'.($e1n===2?' selected':'').'>Especialización</option>';
                    $e2n_opts_r = '<option value="1"'.($e2n===1?' selected':'').'>Camino</option>'
                        .'<option value="2"'.($e2n===2?' selected':'').'>Especialización</option>';
                    $camino_html_r = '<div class="rst-camino-row">'
                        .'<span class="rst-slabel'.$e1_cls.'" style="font-size:11px">Camino 1</span>'
                        .'<select name="p_belica'.$i.'_espe1" class="rst-sel" style="max-width:140px">'.$e1_opts_r.'</select>'
                        .'<select name="p_belica'.$i.'_espe1_nivel" class="rst-sel" style="max-width:130px">'.$e1n_opts_r.'</select>'
                        .'&nbsp;<span class="rst-slabel'.$e2_cls.'" style="font-size:11px">Camino 2</span>'
                        .'<select name="p_belica'.$i.'_espe2" class="rst-sel" style="max-width:140px">'.$e2_opts_r.'</select>'
                        .'<select name="p_belica'.$i.'_espe2_nivel" class="rst-sel" style="max-width:130px">'.$e2n_opts_r.'</select>'
                        .'</div>';
                }
                $belica_rows .= '<div class="rst-bslot'.$cls.'"><div class="rst-slabel">Slot '.$i.' <span class="rst-bak">(backup: '.htmlspecialchars($bak_lbl).')</span></div>'
                    .'<select name="p_belica'.$i.'" class="rst-sel rst-disc-sel" data-slot="'.$i.'">'
                    .rst_sel_opts($belica_opts_arr, $cur).'</select>'
                    .$camino_html_r
                    .'</div>';
            } else {
                $nivel_labels = [0=>'', 1=>'', 2=>' (Espe)', 3=>' (Maest)'];
                $cam_str = $e1 ? $e1.($nivel_labels[$e1n] ?? '').($e2 ? ' / '.$e2.($nivel_labels[$e2n] ?? '') : '') : '—';
                $belica_rows .= '<div class="rst-slot'.$cls.'"><div class="rst-slabel">Slot '.$i.'</div>'
                    .'<div>'.htmlspecialchars($cur ?: '—').' <span class="rst-bak">(era: '.htmlspecialchars($bak_lbl).')</span></div>'
                    .($cam_str !== '—' ? '<div style="font-size:11px;color:#9ca3af">Caminos: '.htmlspecialchars($cam_str).'</div>' : '')
                    .'</div>';
            }
        }

        // Estilo inputs
        $estilo_rows = '';
        $estilo_labels = ['Estilo 1','Estilo 2 (25✦)','Estilo 3 (50✦)','Estilo 4 (75✦)'];
        for ($i = 1; $i <= 4; $i++) {
            $cls = $diff("estilo$i");
            $cur = htmlspecialchars($propuesta["estilo$i"] ?? '', ENT_QUOTES);
            $bak = htmlspecialchars($backup["estilo$i"] ?? '', ENT_QUOTES);
            if ($is_pending) {
                $estilo_rows .= '<div class="rst-slot'.$cls.'"><div class="rst-slabel">'.$estilo_labels[$i-1].' <span class="rst-bak">(backup: '.$bak.')</span></div>'
                    .'<input type="text" name="p_estilo'.$i.'" class="rst-inp" value="'.$cur.'" maxlength="100"></div>';
            } else {
                $estilo_rows .= '<div class="rst-slot'.$cls.'"><div class="rst-slabel">'.$estilo_labels[$i-1].'</div><div>'.$cur.' <span class="rst-bak">(era: '.$bak.')</span></div></div>';
            }
        }

        // Oficio selects with nivel + sub levels
        $oficio_opts_arr = array_merge([''], $oficio_valid);
        $rst_of_subs = [
            'Artesano'=>['Herrero','Modista'],'Médico'=>['Farmacólogo','Doctor'],
            'Navegante'=>['Cartógrafo','Timonel'],'Inventor'=>['Biólogo','Ingeniero'],
            'Carpintero'=>['Astillero','Constructor'],'Cocinero'=>['Chef','Aprovisionador'],
            'Mercader'=>['Comerciante','Contrabandista'],'Investigador'=>['Periodista','Arqueólogo'],
            'Aventurero'=>['Cazador','Domador'],'Recolector'=>['Agreste','Mayorista'],
        ];
        $rst_sub_lvl_labels = ['0 — Base','1 — Espe (−10✦ −2k pto)','2 — Maestría (−25✦ −3.5k pto)','3 — Gran M. (−50✦ −5k pto)'];
        $oficio_rows = '';
        $oi = 0;
        foreach (['oficio1'=>'Oficio 1', 'oficio2'=>'Oficio 2'] as $ofk => $ofl) {
            $oi++;
            $cls  = $diff($ofk);
            $cur  = $propuesta[$ofk] ?? '';
            $bak  = $backup[$ofk] ?? '';
            $subs = $rst_of_subs[$cur] ?? [];
            $cur_niv  = (int)($propuesta["oficio{$oi}_nivel"] ?? 1);
            $cur_sl1  = (int)($propuesta["oficio{$oi}_sub1_nivel"] ?? 0);
            $cur_sl2  = (int)($propuesta["oficio{$oi}_sub2_nivel"] ?? 0);
            $bak_niv_lbl  = $bak ? ' / N'.((int)($backup["oficio{$oi}_nivel"] ?? 1)) : '';
            if ($is_pending) {
                $niv_opts = '<option value="1"'.($cur_niv===1?' selected':'').'>Nivel 1</option>'
                           .'<option value="2"'.($cur_niv===2?' selected':'').'>Nivel 2 (−1000 pto)</option>';
                $s1_opts = $s2_opts = '';
                foreach ($rst_sub_lvl_labels as $lv => $lbl) {
                    $s1_opts .= '<option value="'.$lv.'"'.($lv===$cur_sl1?' selected':'').'>'.htmlspecialchars($lbl).'</option>';
                    $s2_opts .= '<option value="'.$lv.'"'.($lv===$cur_sl2?' selected':'').'>'.htmlspecialchars($lbl).'</option>';
                }
                $det_style = $cur ? '' : ' style="display:none"';
                $sub1_name = htmlspecialchars($subs[0] ?? 'Sub 1');
                $sub2_name = htmlspecialchars($subs[1] ?? 'Sub 2');
                $oficio_rows .=
                    '<div class="rst-slot'.$cls.'"><div class="rst-slabel">'.htmlspecialchars($ofl).' <span class="rst-bak">(backup: '.htmlspecialchars($bak ?: '—').')</span></div>'
                    .'<select name="p_'.$ofk.'" class="rst-sel rst-of-sel" data-oi="'.$oi.'">'.rst_sel_opts($oficio_opts_arr, $cur).'</select>'
                    .'<div id="rst-of-detail-'.$oi.'" class="rst-of-detail"'.$det_style.' style="margin-top:4px">'
                    .'<div style="display:flex;gap:4px;align-items:center;margin-top:4px">'
                    .'<span style="font-size:11px;color:#9ca3af;min-width:36px">Nivel</span>'
                    .'<select name="p_oficio'.$oi.'_nivel" class="rst-sel" style="max-width:180px">'.$niv_opts.'</select>'
                    .'</div>'
                    .'<div style="display:flex;gap:4px;align-items:center;margin-top:4px">'
                    .'<span class="rst-of-sub1name-'.$oi.'" style="font-size:11px;color:#9ca3af;min-width:80px;display:inline-block">'.$sub1_name.'</span>'
                    .'<select name="p_oficio'.$oi.'_sub1_nivel" class="rst-sel" style="max-width:220px">'.$s1_opts.'</select>'
                    .'</div>'
                    .'<div style="display:flex;gap:4px;align-items:center;margin-top:4px">'
                    .'<span class="rst-of-sub2name-'.$oi.'" style="font-size:11px;color:#9ca3af;min-width:80px;display:inline-block">'.$sub2_name.'</span>'
                    .'<select name="p_oficio'.$oi.'_sub2_nivel" class="rst-sel" style="max-width:220px">'.$s2_opts.'</select>'
                    .'</div>'
                    .'</div>'
                    .'</div>';
            } else {
                $sub1_name = $subs[0] ?? ''; $sub2_name = $subs[1] ?? '';
                $niv_lbl = 'Nivel '.$cur_niv;
                $sub_lbl = '';
                if ($sub1_name) $sub_lbl .= $sub1_name.': '.$cur_sl1;
                if ($sub2_name) $sub_lbl .= ($sub_lbl ? ' / ' : '').$sub2_name.': '.$cur_sl2;
                $oficio_rows .=
                    '<div class="rst-slot'.$cls.'"><div class="rst-slabel">'.htmlspecialchars($ofl).'</div>'
                    .'<div>'.htmlspecialchars($cur ?: '—').' <span class="rst-bak">(era: '.htmlspecialchars($bak ?: '—').')</span></div>'
                    .($cur ? '<div style="font-size:11px;color:#9ca3af">'.htmlspecialchars($niv_lbl.($sub_lbl ? ' · '.$sub_lbl : '')).'</div>' : '')
                    .'</div>';
            }
        }
        // Staff JS for oficio detail toggle
        $oficio_js = $is_pending ? '
<script>
var RST_OFICIO_MAP = {
    "Artesano":["Herrero","Modista"],"Médico":["Farmacólogo","Doctor"],
    "Navegante":["Cartógrafo","Timonel"],"Inventor":["Biólogo","Ingeniero"],
    "Carpintero":["Astillero","Constructor"],"Cocinero":["Chef","Aprovisionador"],
    "Mercader":["Comerciante","Contrabandista"],"Investigador":["Periodista","Arqueólogo"],
    "Aventurero":["Cazador","Domador"],"Recolector":["Agreste","Mayorista"]
};
function rstUpdateOficioSlot(oi, resetLevels) {
    var sel = document.querySelector("[name=p_oficio"+oi+"]");
    var det = document.getElementById("rst-of-detail-"+oi);
    if (!sel || !det) return;
    var of = sel.value;
    var s1El = document.querySelector("[name=p_oficio"+oi+"_sub1_nivel]");
    var s2El = document.querySelector("[name=p_oficio"+oi+"_sub2_nivel]");
    if (!of || !RST_OFICIO_MAP[of]) {
        det.style.display = "none";
        if (s1El) s1El.value = "0";
        if (s2El) s2El.value = "0";
        return;
    }
    det.style.display = "";
    if (resetLevels) {
        if (s1El) s1El.value = "0";
        if (s2El) s2El.value = "0";
    }
    var subs = RST_OFICIO_MAP[of];
    var sub1NameEl = det.querySelector(".rst-of-sub1name-"+oi);
    var sub2NameEl = det.querySelector(".rst-of-sub2name-"+oi);
    if (sub1NameEl) sub1NameEl.textContent = subs[0] || "Sub 1";
    if (sub2NameEl) sub2NameEl.textContent = subs[1] || "Sub 2";
}
document.querySelectorAll(".rst-of-sel").forEach(function(el) {
    el.addEventListener("change", function() { rstUpdateOficioSlot(parseInt(el.dataset.oi), true); });
});
for (var _oi = 1; _oi <= 2; _oi++) { rstUpdateOficioSlot(_oi); }
</script>' : '';

        // Haki fields
        $haki_rows = '';
        $haki_fields = ['kenbun'=>'Kenbun (1-7)','buso'=>'Buso (1-7)','hao'=>'Hao (1-7)','dominio_akuma'=>'Dominio Akuma (0-6)'];
        $haki_min = ['kenbun'=>1,'buso'=>1,'hao'=>1,'dominio_akuma'=>0];
        $haki_max = ['kenbun'=>7,'buso'=>7,'hao'=>7,'dominio_akuma'=>6];
        foreach ($haki_fields as $hk => $hl) {
            $cls = $diff($hk);
            $cur = (int)($propuesta[$hk] ?? $haki_min[$hk]);
            $bak = (int)($backup[$hk] ?? $haki_min[$hk]);
            if ($is_pending) {
                $haki_rows .= '<div class="rst-slot'.$cls.'"><div class="rst-slabel">'.htmlspecialchars($hl).' <span class="rst-bak">(backup: '.$bak.')</span></div>'
                    .'<input type="number" name="p_'.$hk.'" class="rst-inp" value="'.$cur.'" min="'.$haki_min[$hk].'" max="'.$haki_max[$hk].'"></div>';
            } else {
                $haki_rows .= '<div class="rst-slot'.$cls.'"><div class="rst-slabel">'.htmlspecialchars($hl).'</div><div>'.$cur.' <span class="rst-bak">(era: '.$bak.')</span></div></div>';
            }
        }

        // Akuma fields
        $cls_ak = $diff('akuma');
        $cur_ak = htmlspecialchars($propuesta['akuma'] ?? '', ENT_QUOTES);
        $bak_ak = htmlspecialchars($backup['akuma'] ?? '', ENT_QUOTES);
        $cur_aks = htmlspecialchars($propuesta['akuma_subnombre'] ?? '', ENT_QUOTES);
        $bak_aks = htmlspecialchars($backup['akuma_subnombre'] ?? '', ENT_QUOTES);

        // Camino select
        $cls_cam = $diff('camino');
        $cur_cam = $propuesta['camino'] ?? '';
        $bak_cam = $backup['camino'] ?? '';
        $cam_opts_arr = $camino_valid;
        $camino_html = '';
        foreach ($cam_opts_arr as $cv) {
            $sel = ($cv === $cur_cam) ? ' selected' : '';
            $camino_html .= '<option value="'.htmlspecialchars($cv, ENT_QUOTES).'"'.$sel.'>'.htmlspecialchars(rst_camino_label($cv)).'</option>';
        }

        // Notas
        $notas_bak = htmlspecialchars($propuesta['notas'] ?? '');
        $nika_adj_v = (int)($propuesta['nika_ajuste'] ?? 0);
        $po_adj_v   = (int)($propuesta['puntos_oficio_ajuste'] ?? 0);
        $attr_reset_v = !empty($propuesta['resetear_atributos']) ? 'checked' : '';

        // Technique swap section
        $tec_hours_r    = [1=>1,2=>4,3=>8,4=>12,5=>24,6=>36,7=>48,8=>60,9=>72,10=>100];
        $tecs_perder_ids = $propuesta['tecs_perder'] ?? [];
        $tecs_ganar_ids  = $propuesta['tecs_ganar']  ?? [];
        $tec_section_html = '';
        if (!empty($tecs_perder_ids) || !empty($tecs_ganar_ids)) {
            $all_t_ids = array_merge((array)$tecs_perder_ids, (array)$tecs_ganar_ids);
            $esc_all_t = implode("','", array_map([$db, 'escape_string'], $all_t_ids));
            $tec_info = [];
            $q_ti = $db->query("SELECT tid, nombre, tier+0 AS tier FROM mybb_op_tecnicas WHERE tid IN ('$esc_all_t')");
            while ($r = $db->fetch_array($q_ti)) { $tec_info[$r['tid']] = ['nombre' => $r['nombre'], 'tier' => (int)$r['tier']]; }
            $lose_h = 0; $gain_h = 0;
            foreach ((array)$tecs_perder_ids as $tid) { $t = $tec_info[$tid] ?? null; if ($t) $lose_h += $tec_hours_r[$t['tier']] ?? 0; }
            foreach ((array)$tecs_ganar_ids  as $tid) { $t = $tec_info[$tid] ?? null; if ($t) $gain_h += $tec_hours_r[$t['tier']] ?? 0; }
            $tec_lose_html = '';
            foreach ((array)$tecs_perder_ids as $tid) {
                $t = $tec_info[$tid] ?? null;
                $tec_lose_html .= '<li>'.htmlspecialchars($t ? $t['nombre'] : $tid).' <span style="color:#6b7280;font-size:11px">'.($t ? 'T'.$t['tier'].' ('.$tec_hours_r[$t['tier']].'h)' : '?').'</span></li>';
            }
            $tec_gain_html = '';
            foreach ((array)$tecs_ganar_ids  as $tid) {
                $t = $tec_info[$tid] ?? null;
                $tec_gain_html .= '<li>'.htmlspecialchars($t ? $t['nombre'] : $tid).' <span style="color:#6b7280;font-size:11px">'.($t ? 'T'.$t['tier'].' ('.$tec_hours_r[$t['tier']].'h)' : '?').'</span></li>';
            }
            $ok_color = $gain_h <= $lose_h ? '#86efac' : '#fca5a5';
            $tec_section_html = '
<div class="rst-section">
    <div class="rst-stitle">INTERCAMBIO DE TÉCNICAS</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div><div style="font-size:11px;color:#f87171;margin-bottom:6px;font-family:moonGetHeavy,Arial">PERDER ('.$lose_h.'h)</div>'
            .($tec_lose_html ? '<ul style="margin:0;padding-left:16px;font-size:12px;color:#d1d5db">'.$tec_lose_html.'</ul>' : '<span style="font-size:12px;color:#6b7280">Ninguna</span>').'</div>
        <div><div style="font-size:11px;color:'.$ok_color.';margin-bottom:6px;font-family:moonGetHeavy,Arial">GANAR ('.$gain_h.'h / '.$lose_h.'h)</div>'
            .($tec_gain_html ? '<ul style="margin:0;padding-left:16px;font-size:12px;color:#d1d5db">'.$tec_gain_html.'</ul>' : '<span style="font-size:12px;color:#6b7280">Ninguna</span>').'</div>
    </div>
</div>';
        }

        $alert_html = $alert ? '<div class="rst-alert rst-warn">'.htmlspecialchars($alert).'</div>' : '';
        $reset_content = '
<p><a href="/op/staff/reset_peticiones.php" class="rst-back">← Listado de resets</a></p>
'.$alert_html.$saved_msg.'
<h2 class="rst-h2">Reset #'.$reset_id.' — '.$p_nombre.' — '.$p_fecha.'</h2>
<div class="rst-estado rst-estado-'.$estado.'">Estado: '.strtoupper($estado).'</div>

'.$form_start.'
<div class="rst-sections">

<div class="rst-section">
    <div class="rst-stitle">ATRIBUTOS</div>
    '.($is_pending
        ? '<label class="rst-check"><input type="checkbox" name="p_resetear_atributos" value="1" '.$attr_reset_v.'> Resetear atributos (devolver puntos)</label>'
        : '<div>Reset atributos: '.(!empty($propuesta['resetear_atributos']) ? 'Sí' : 'No').'</div>'
    ).'
</div>

<div class="rst-section">
    <div class="rst-stitle">DISCIPLINAS</div>
    <div class="rst-g3">'.$belica_rows.'</div>
</div>

<div class="rst-section">
    <div class="rst-stitle">ESTILOS</div>
    <div class="rst-g2">'.$estilo_rows.'</div>
</div>

<div class="rst-section">
    <div class="rst-stitle">OFICIOS</div>
    <div class="rst-g2">'.$oficio_rows.'</div>
</div>

<div class="rst-section">
    <div class="rst-stitle">HAKI Y DOMINIO AKUMA</div>
    <div class="rst-g4">'.$haki_rows.'</div>
</div>

<div class="rst-section">
    <div class="rst-stitle">AKUMA NO MI</div>
    <div class="rst-g2">
        <div class="rst-slot'.$cls_ak.'">
            <div class="rst-slabel">Nombre <span class="rst-bak">(backup: '.$bak_ak.')</span></div>
            '.($is_pending ? '<input type="text" name="p_akuma" class="rst-inp" value="'.$cur_ak.'" maxlength="100">' : '<div>'.$cur_ak.'</div>').'
        </div>
        <div class="rst-slot">
            <div class="rst-slabel">Subnombre <span class="rst-bak">(backup: '.$bak_aks.')</span></div>
            '.($is_pending ? '<input type="text" name="p_akuma_subnombre" class="rst-inp" value="'.$cur_aks.'" maxlength="100">' : '<div>'.$cur_aks.'</div>').'
        </div>
    </div>
</div>

<div class="rst-section">
    <div class="rst-stitle">CAMINO</div>
    <div class="rst-slot'.$cls_cam.'">
        <div class="rst-slabel">Camino <span class="rst-bak">(backup: '.htmlspecialchars(rst_camino_label($bak_cam)).')</span></div>
        '.($is_pending ? '<select name="p_camino" class="rst-sel" style="max-width:280px">'.$camino_html.'</select>' : '<div>'.htmlspecialchars(rst_camino_label($cur_cam)).'</div>').'
    </div>
</div>

'.$tec_section_html.'

<div class="rst-section">
    <div class="rst-stitle">AJUSTES DE MONEDA (solo staff)</div>
    <div class="rst-g2">
        <div class="rst-slot"><div class="rst-slabel">Nikas ajuste (positivo = dar, negativo = quitar)</div>
        '.($is_pending ? '<input type="number" name="p_nika_ajuste" class="rst-inp" value="'.$nika_adj_v.'"><div class="rst-hint">Nikas actuales: '.$cur_nika.'</div>' : '<div>'.$nika_adj_v.'</div>').'
        </div>
        <div class="rst-slot"><div class="rst-slabel">Puntos Oficio ajuste</div>
        '.($is_pending ? '<input type="number" name="p_puntos_oficio_ajuste" class="rst-inp" value="'.$po_adj_v.'"><div class="rst-hint">Puntos oficio actuales: '.$cur_po.'</div>' : '<div>'.$po_adj_v.'</div>').'
        </div>
    </div>
</div>

<div class="rst-section">
    <div class="rst-stitle">NOTAS DEL USUARIO</div>
    '.($is_pending
        ? '<textarea name="p_notas" class="rst-textarea" rows="4" maxlength="2000">'.htmlspecialchars($propuesta['notas'] ?? '').'</textarea>'
        : '<div class="rst-notas">'.nl2br(htmlspecialchars($propuesta['notas'] ?? '')).'</div>'
    ).'
</div>

'.($is_pending ? '
<div class="rst-actions">
    <button type="submit" name="accion" value="guardar" class="rst-btn rst-btn-save">GUARDAR PROPUESTA</button>
    <button type="submit" name="accion" value="aplicar" class="rst-btn rst-btn-apply" onclick="return confirm(\'¿Aplicar este reset a la ficha? Esta acción no se puede deshacer.\')">APLICAR RESET</button>
    <a href="/op/staff/reset_peticiones.php?id='.$reset_id.'&accion=rechazar" class="rst-btn rst-btn-reject" onclick="return confirm(\'¿Rechazar? El ticket se devolverá al usuario.\')">RECHAZAR Y DEVOLVER TICKET</a>
</div>
' : '').'

</div>
'.$form_end.'
'.$oficio_js;
    }

    eval("\$page = \"".$templates->get("staff_reset_peticiones")."\";");
    output_page($page);
    exit;
}

// ─── List view ────────────────────────────────────────────────────────────────
$filter = $mybb->get_input('estado') ?: 'pendiente';
$filter_esc = $db->escape_string($filter);

$rq = $db->query("
    SELECT r.*, p.nombre AS p_nombre, p.enviado AS p_enviado
    FROM mybb_op_fichas_reset r
    LEFT JOIN mybb_op_peticiones p ON p.id = r.peticion_id
    WHERE r.estado = '$filter_esc'
    ORDER BY r.id DESC
    LIMIT 200
");

$success_msg = '';
if (!empty($_GET['success'])) {
    $s = htmlspecialchars($_GET['success']);
    $sid = (int)($_GET['id'] ?? 0);
    if ($s === 'aplicado')   $success_msg = '<div class="rst-alert rst-ok">Reset #'.$sid.' aplicado correctamente.</div>';
    if ($s === 'rechazado')  $success_msg = '<div class="rst-alert rst-warn">Reset #'.$sid.' rechazado. Ticket devuelto al usuario.</div>';
}

$filter_tabs = '';
foreach (['pendiente','aplicado','rechazado'] as $fe) {
    $active = ($fe === $filter) ? ' class="rst-tab-active"' : '';
    $filter_tabs .= '<a href="/op/staff/reset_peticiones.php?estado='.$fe.'"'.$active.'>'.strtoupper($fe).'</a>';
}

$rows = '';
while ($r = $db->fetch_array($rq)) {
    $rid   = (int)$r['id'];
    $bak   = json_decode($r['backup_json'], true) ?: [];
    $prop  = json_decode($r['propuesta_json'], true) ?: [];
    $nombre = htmlspecialchars($r['p_nombre'] ?? '');
    $fecha  = htmlspecialchars($r['p_enviado'] ?? $r['created_at'] ?? '');
    $estado = htmlspecialchars($r['estado']);

    // Count fields changed
    $n_changes = 0;
    for ($i = 1; $i <= 12; $i++) { if (($prop["belica$i"] ?? '') !== ($bak["belica$i"] ?? '')) $n_changes++; }
    for ($i = 1; $i <= 4;  $i++) { if (($prop["estilo$i"] ?? '') !== ($bak["estilo$i"] ?? '')) $n_changes++; }
    foreach (['oficio1','oficio2','kenbun','buso','hao','dominio_akuma','akuma','camino'] as $f) {
        if ((string)($prop[$f] ?? '') !== (string)($bak[$f] ?? '')) $n_changes++;
    }
    if (!empty($prop['resetear_atributos'])) $n_changes++;

    $rows .= '<tr>'
        .'<td>'.$rid.'</td>'
        .'<td><a href="/op/staff/reset_peticiones.php?id='.$rid.'">'.$nombre.'</a></td>'
        .'<td>'.$fecha.'</td>'
        .'<td>'.$n_changes.' campo(s)</td>'
        .'<td><span class="rst-estado rst-estado-'.$estado.'">'.$estado.'</span></td>'
        .'<td><a href="/op/staff/reset_peticiones.php?id='.$rid.'" class="rst-btn rst-btn-small">Ver</a></td>'
        .'</tr>';
}
if (!$rows) $rows = '<tr><td colspan="6" style="text-align:center;color:#6b7280">No hay registros con estado "'.$filter_esc.'".</td></tr>';

$alert_html_list = $alert ? '<div class="rst-alert rst-warn">'.htmlspecialchars($alert).'</div>' : '';
$reset_content = '
<h2 class="rst-h2">Peticiones de Reset de Build</h2>
'.$alert_html_list.$success_msg.'
<div class="rst-tabs">'.$filter_tabs.'</div>
<table class="rst-table">
    <thead><tr><th>#</th><th>Usuario</th><th>Fecha</th><th>Cambios</th><th>Estado</th><th></th></tr></thead>
    <tbody>'.$rows.'</tbody>
</table>';

eval("\$page = \"".$templates->get("staff_reset_peticiones")."\";");
output_page($page);
