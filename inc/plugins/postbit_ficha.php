<?php
/**
 * Postbit Ficha
 * Inyecta datos de personaje (nivel, facción, raza, stats) en el postbit.
 *
 * Se engancha en el hook "postbit" que dispara ANTES de que postbit_classic.html
 * se renderice pero DESPUÉS de postbit_avatar.html y postbit_author_user.html.
 * Por eso sobreescribe $post['useravatar'] y prepend a $post['user_details'].
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$plugins->add_hook("postbit",          "postbit_ficha_run");
$plugins->add_hook("showthread_start", "postbit_ficha_thread_fecha");

function postbit_ficha_info()
{
    return [
        "name"          => "Postbit Ficha",
        "description"   => "Muestra nivel, facción, raza y estadísticas del personaje en el sidebar de cada post.",
        "website"       => "",
        "author"        => "One Piece Gaiden",
        "authorsite"    => "",
        "version"       => "1.0",
        "codename"      => "postbit_ficha",
        "compatibility" => "18*",
    ];
}

function postbit_ficha_activate() {}
function postbit_ficha_deactivate() {}

function postbit_ficha_run(&$post)
{
    global $db, $mybb;

    $post['staff_checkbox']         = '';
    $post['faccion_border_color']   = '#650084';
    $post['ficha_nombre_head']      = '';
    $post['ficha_apodo_controls']   = '';
    $post['share_button']           = '';

    if (empty($post['uid'])) {
        return;
    }

    static $cache = [];
    static $thread_cache = [];
    static $style_injected   = false;
    static $share_js_done    = false;
    $uid = (int)$post['uid'];
    $pid = (int)($post['pid'] ?? 0);
    $tid = (int)($post['tid'] ?? 0);

    // ── Share button (siempre, con o sin ficha) ─────────────────────────────────
    if ($pid > 0) {
        $share_script = '';
        if (!$share_js_done) {
            $share_script = '<script>function pbitShare(b,p){'
                . 'var u=location.protocol+"//"+location.host+"/showthread.php?pid="+p+"#pid"+p,'
                . 'c=function(){var o=b.textContent;b.textContent="¡Copiado!";'
                . 'setTimeout(function(){b.textContent=o;},1500);}; '
                . 'if(navigator.clipboard){navigator.clipboard.writeText(u).then(c);}'
                . 'else{var t=document.createElement("textarea");t.value=u;'
                . 'document.body.appendChild(t);t.select();'
                . 'try{document.execCommand("copy");c();}catch(e){}'
                . 'document.body.removeChild(t);}}'
                . '</script>';
            $share_js_done = true;
        }
        $post['share_button'] = $share_script
            . '<button type="button" onclick="pbitShare(this,' . $pid . ')"'
            . ' style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);'
            . 'color:white;font-size:10px;height:30px;padding:0 10px;border-radius:3px;cursor:pointer;'
            . 'margin:5px 6px;font-family:LemonMilkLight,sans-serif;letter-spacing:1px;'
            . 'vertical-align:middle;"'
            . ' onmouseover="this.style.background=\'rgba(255,255,255,0.28)\'"'
            . ' onmouseout="this.style.background=\'rgba(255,255,255,0.15)\'">'
            . '&#128279; Compartir</button>';
    }

    // ── Viewer staff check ──────────────────────────────────────────────────────
    $current_user_is_staff = (function_exists('is_staff') && is_staff((int)$mybb->user['uid']))
        || in_array((int)$mybb->user['usergroup'], [4, 6, 14, 16])
        || (!empty($mybb->user['additionalgroups']) && (bool)array_intersect([4, 6, 14, 16], array_map('intval', explode(',', $mybb->user['additionalgroups']))));
    if (!empty($post['button_edit'])) {
        $post['button_edit'] = preg_replace('/>((?:&nbsp;|\s)+)Editar/', '>Editar', $post['button_edit']);
    }

    $post['staff_checkbox'] = $current_user_is_staff
        ? '<input type="checkbox" class="inline_mod_checkbox" name="inline_moderation" id="inlinemod_' . $pid . '" value="' . $pid . '" style="margin-left:8px;margin-right:8px;vertical-align:middle;accent-color:#650084;">'
        : '';

    if (!array_key_exists($uid, $cache)) {
        $q = $db->query("
            SELECT nombre, apodo, nivel, faccion, raza,
                   fuerza, resistencia, destreza, punteria,
                   agilidad, reflejos, voluntad,
                   fuerza_pasiva, resistencia_pasiva, destreza_pasiva, punteria_pasiva,
                   agilidad_pasiva, reflejos_pasiva, voluntad_pasiva,
                   altura, peso, sexo, edad, rango, nivelnarrador, rango_inframundo,
                   reputacion, reputacion_positiva, reputacion_negativa, fama,
                   akuma, akuma_subnombre, dominio_akuma, camino
            FROM mybb_op_fichas WHERE fid='{$uid}' LIMIT 1
        ");
        $row = $db->fetch_array($q) ?: null;

        // Segunda query solo si el personaje tiene akuma
        if ($row && !empty($row['akuma'])) {
            $ak_n  = $db->escape_string($row['akuma']);
            $ak_sn = $db->escape_string($row['akuma_subnombre']);
            $q2 = $db->query("SELECT * FROM mybb_op_akumas WHERE nombre='{$ak_n}' AND subnombre='{$ak_sn}' LIMIT 1");
            $akuma_row = $db->fetch_array($q2) ?: [];
            // Compatibilidad: soporta columna única 'dominios'/'pasivas' O columnas separadas dominio1-3/pasiva1-3
            if (!isset($akuma_row['dominios'])) {
                $akuma_row['dominios'] = trim(implode("\n", array_filter([
                    $akuma_row['dominio1'] ?? '', $akuma_row['dominio2'] ?? '', $akuma_row['dominio3'] ?? ''
                ])));
            }
            if (!isset($akuma_row['pasivas'])) {
                $akuma_row['pasivas'] = trim(implode("\n", array_filter([
                    $akuma_row['pasiva1'] ?? '', $akuma_row['pasiva2'] ?? '', $akuma_row['pasiva3'] ?? ''
                ])));
            }
            $row['_akuma'] = $akuma_row;
        }

        $cache[$uid] = $row;
    }

    $ficha = $cache[$uid];
    if (!$ficha) {
        return;
    }

    // ── Thread-locked resources (vitalidad/energia/haki) ───────────────────────
    static $virtudes_cache = [];
    $thread_key = $tid . '_' . $uid;
    if ($tid > 0 && !array_key_exists($thread_key, $thread_cache)) {
        $q_tp = $db->query("SELECT fuerza, resistencia, destreza, punteria, agilidad, reflejos, voluntad,
                                    fuerza_pasiva, resistencia_pasiva, destreza_pasiva,
                                    punteria_pasiva, agilidad_pasiva, reflejos_pasiva, voluntad_pasiva,
                                    vitalidad, energia, haki,
                                    vitalidad_pasiva, energia_pasiva, haki_pasiva,
                                    nivel
                             FROM mybb_op_thread_personaje WHERE tid='{$tid}' AND uid='{$uid}' LIMIT 1");
        $thread_cache[$thread_key] = $db->fetch_array($q_tp) ?: null;
    }
    $thread_res = ($tid > 0) ? ($thread_cache[$thread_key] ?? null) : null;

    // Virtud bonuses (cached per uid) — needed for resources and reputation
    if (!array_key_exists($uid, $virtudes_cache)) {
        $q_v = $db->query("SELECT virtud_id FROM mybb_op_virtudes_usuarios
                            WHERE uid='{$uid}' AND virtud_id IN ('V017','D013','V037','V038','V039','V040','V041','V058','V059')");
        $v_list = [];
        while ($vrow = $db->fetch_array($q_v)) { $v_list[] = $vrow['virtud_id']; }
        $virtudes_cache[$uid] = $v_list;
    }
    $virtudes_uid = $virtudes_cache[$uid];

    $resources_bar_html = '';
    if ($thread_res) {
        $nivel_tp = (int)$thread_res['nivel'];

        // Fórmula idéntica a BBCustom_recursos_calc_stats
        $fp  = (int)$thread_res['fuerza_pasiva'];
        $resp= (int)$thread_res['resistencia_pasiva'];
        $dp  = (int)$thread_res['destreza_pasiva'];
        $pp  = (int)$thread_res['punteria_pasiva'];
        $ap  = (int)$thread_res['agilidad_pasiva'];
        $refp= (int)$thread_res['reflejos_pasiva'];
        $vop = (int)$thread_res['voluntad_pasiva'];

        $vita_max = (int)$thread_res['vitalidad'] + (int)$thread_res['vitalidad_pasiva']
            + (int)floor($fp*6 + $resp*15 + $dp*4 + $ap*3 + $pp*2 + $refp*1 + $vop*1);
        $ene_max  = (int)$thread_res['energia']   + (int)$thread_res['energia_pasiva']
            + (int)floor($fp*2 + $resp*4 + $pp*5 + $dp*4 + $ap*5 + $refp*1 + $vop*1);
        $haki_max = (int)$thread_res['haki']      + (int)$thread_res['haki_pasiva']
            + (int)floor($vop*10);

        foreach ($virtudes_uid as $vid) {
            if ($vid === 'V037') $vita_max += $nivel_tp * 10;
            if ($vid === 'V038') $vita_max += $nivel_tp * 15;
            if ($vid === 'V039') $vita_max += $nivel_tp * 20;
            if ($vid === 'V040') $ene_max  += $nivel_tp * 10;
            if ($vid === 'V041') $ene_max  += $nivel_tp * 15;
            if ($vid === 'V058') $haki_max += $nivel_tp * 5;
            if ($vid === 'V059') $haki_max += $nivel_tp * 10;
        }

        // Barras de progreso — inicializadas al 100% (máximo)
        // BBCustom_recursos.php actualiza .subBarraVida/.subBarraEnergia/.subBarraHaki
        // y .personaje_vida2/.personaje_energia2/.personaje_haki2 post a post
        $bar_green = 'hsl(120,90%,38%)';
        $bar = function($label, $color, $class_text, $class_bar, $max) use ($bar_green) {
            return '<div style="margin-bottom:2px;">'
                . '<div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:1px;">'
                . '<span style="color:' . $color . ';font-family:moonGetHeavy,sans-serif;font-size:9px;letter-spacing:0.5px;">' . $label . '</span>'
                . '<span class="' . $class_text . '" style="color:white;font-family:moonGetHeavy,sans-serif;font-size:9px;">' . $max . '/' . $max . '</span>'
                . '</div>'
                . '<div style="background:#111;border-radius:2px;height:4px;overflow:hidden;">'
                . '<div class="' . $class_bar . '" style="background:' . $bar_green . ';width:100%;height:100%;"></div>'
                . '</div></div>';
        };
        $resources_bar_html = '<div class="postbit-resource-bars" style="position:absolute;bottom:40px;left:20px;right:20px;z-index:25;background:rgba(0,0,0,0.75);padding:3px 6px;box-sizing:border-box;border-radius:4px;">'
            . $bar('VITALIDAD', '#e74c3c', 'personaje_vida2',    'subBarraVida',    $vita_max)
            . $bar('ENERGÍA',   '#7eb8e8', 'personaje_energia2', 'subBarraEnergia', $ene_max)
            . $bar('HAKI',      '#c49fd8', 'personaje_haki2',    'subBarraHaki',    $haki_max)
            . '</div>';
    }

    $nombre     = htmlspecialchars($ficha['nombre']             ?? '', ENT_QUOTES, 'UTF-8');
    $nivel      = (int)($ficha['nivel']                        ?? 0);
    $faccion    = htmlspecialchars($ficha['faccion']            ?? '', ENT_QUOTES, 'UTF-8');
    $faccion_border_colors = [
        'Pirata'         => '#8d0101',
        'Marina'         => '#006d94',
        'CipherPol'      => '#3e528f',
        'Cazadores'      => '#007500',
        'Revolucionario' => '#be9d6f',
        'Civil'          => '#ac0359',
    ];
    $post['faccion_border_color'] = $faccion_border_colors[$ficha['faccion'] ?? ''] ?? '#650084';
    $post['posturl'] = str_replace('__FACCION_COLOR__', $post['faccion_border_color'], $post['posturl'] ?? '');
    $apodo = htmlspecialchars($ficha['apodo'] ?? '', ENT_QUOTES, 'UTF-8');
    if ($apodo) {
        $post['ficha_apodo_controls'] = '<div style="position:absolute;left:0;right:0;top:0;bottom:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:1;">'
            . '<span style="font-family:moonGetHeavy,sans-serif;font-weight:bold;font-size:11px;color:white;letter-spacing:2px;text-transform:uppercase;text-shadow:1px 1px 3px rgba(0,0,0,0.7);">' . $apodo . '</span>'
            . '</div>';
    }
    $post['ficha_nombre_head'] = '<div style="position:absolute;left:16%;right:0;top:0;bottom:0;display:flex;align-items:center;justify-content:center;z-index:1;">'
        . '<a href="member.php?action=profile&uid=' . $uid . '" class="ficha-nombre-link" style="font-family:moonGetHeavy,sans-serif;font-weight:bold;font-size:16px;color:white;text-shadow:1px 1px 5px rgba(0,0,0,0.9);letter-spacing:2px;text-transform:uppercase;text-decoration:none;">' . $nombre . '</a>'
        . '</div>';
    $raza       = htmlspecialchars($ficha['raza']               ?? '', ENT_QUOTES, 'UTF-8');
    $rango           = htmlspecialchars($ficha['rango']           ?? '', ENT_QUOTES, 'UTF-8');
    $rango_narrador  = htmlspecialchars($ficha['nivelnarrador']  ?? '', ENT_QUOTES, 'UTF-8');
    $rango_inframundo= htmlspecialchars($ficha['rango_inframundo']?? '', ENT_QUOTES, 'UTF-8');
    $altura     = htmlspecialchars($ficha['altura']             ?? '', ENT_QUOTES, 'UTF-8');
    $peso       = htmlspecialchars($ficha['peso']               ?? '', ENT_QUOTES, 'UTF-8');
    $sexo       = htmlspecialchars($ficha['sexo']               ?? '', ENT_QUOTES, 'UTF-8');
    $edad       = htmlspecialchars($ficha['edad']               ?? '', ENT_QUOTES, 'UTF-8');
    $fama       = htmlspecialchars($ficha['fama']               ?? '', ENT_QUOTES, 'UTF-8');
    $rep_total  = (int)($ficha['reputacion']                   ?? 0);
    $rep_pos    = (int)($ficha['reputacion_positiva']          ?? 0);
    $rep_neg    = (int)($ficha['reputacion_negativa']          ?? 0);
    if (in_array('V017', $virtudes_uid)) {
        $rep_total = (int)round($rep_total * 1.10);
        $rep_pos   = (int)round($rep_pos   * 1.10);
        $rep_neg   = (int)round($rep_neg   * 1.10);
    } elseif (in_array('D013', $virtudes_uid)) {
        $rep_total = (int)round($rep_total * 0.9);
        $rep_pos   = (int)round($rep_pos   * 0.9);
        $rep_neg   = (int)round($rep_neg   * 0.9);
    }

    // Compute XP percentage within the current level (same table as personaje.php)
    $exp_tabla = [
         1=>[0,50],       2=>[50,125],     3=>[125,225],   4=>[225,350],   5=>[350,500],
         6=>[500,675],    7=>[675,875],    8=>[875,1100],  9=>[1100,1350],10=>[1350,1625],
        11=>[1625,1925], 12=>[1925,2250], 13=>[2250,2600],14=>[2600,2975],15=>[2975,3375],
        16=>[3375,3800], 17=>[3800,4250], 18=>[4250,4725],19=>[4725,5225],20=>[5225,5750],
        21=>[5750,6300], 22=>[6300,6870], 23=>[6870,7460],24=>[7460,8070],25=>[8070,8700],
        26=>[8700,9350], 27=>[9350,10020],28=>[10020,10700],29=>[10700,11400],30=>[11400,12360],
        31=>[12360,13340],32=>[13340,14350],33=>[14350,15390],34=>[15390,16450],35=>[16450,17530],
        36=>[17530,18650],37=>[18650,19790],38=>[19790,20950],39=>[20950,22140],40=>[22140,23700],
        41=>[23700,25310],42=>[25310,26970],43=>[26970,28680],44=>[28680,30440],45=>[30440,32540],
        46=>[32540,34700],47=>[34700,36920],48=>[36920,39270],49=>[39270,41820],50=>[41820,41821],
    ];
    $newpoints = (int)($post['newpoints'] ?? 0);
    [$expMin, $expMax] = $exp_tabla[$nivel] ?? [0, 50];
    $nivelPorcentaje = ($expMax > $expMin)
        ? max(1, (int)floor(($newpoints - $expMin) / ($expMax - $expMin) * 100))
        : 100;
    // Si hay snapshot de hilo, usar sus stats; si no, usar la ficha en vivo
    $src = $thread_res ?? $ficha;
    $fuerza      = (int)($src['fuerza']      ?? 0) + (int)($src['fuerza_pasiva']      ?? 0);
    $resistencia = (int)($src['resistencia'] ?? 0) + (int)($src['resistencia_pasiva'] ?? 0);
    $destreza    = (int)($src['destreza']    ?? 0) + (int)($src['destreza_pasiva']    ?? 0);
    $punteria    = (int)($src['punteria']    ?? 0) + (int)($src['punteria_pasiva']    ?? 0);
    $agilidad    = (int)($src['agilidad']    ?? 0) + (int)($src['agilidad_pasiva']    ?? 0);
    $reflejos    = (int)($src['reflejos']    ?? 0) + (int)($src['reflejos_pasiva']    ?? 0);
    $voluntad    = (int)($src['voluntad']    ?? 0) + (int)($src['voluntad_pasiva']    ?? 0);

    // Usar el avatar de la identidad secreta si está activa
    $raw_avatar = (!empty($post['secret_identity_active']) && !empty($post['secret_identity_avatar']))
        ? $post['secret_identity_avatar']
        : ($post['avatar'] ?? '');

    // Sanitizar: quedarse solo con la parte de la URL antes de cualquier carácter no válido
    // y rechazar schemes no-http (data:, javascript:, etc.)
    $raw_avatar = trim($raw_avatar);
    if (empty($raw_avatar) || preg_match('#^(javascript|data|vbscript):#i', $raw_avatar)) {
        $raw_avatar = '';
    }
    $avatar_fallback = '/images/default_avatar.png';
    $avatar_src      = $raw_avatar !== '' ? htmlspecialchars($raw_avatar, ENT_QUOTES, 'UTF-8') : $avatar_fallback;

    // En posts con personaje secreto, mostrar el nombre secreto
    if (!empty($post['secret_identity_active']) && !empty($post['secret_identity_nombre'])) {
        $nombre = htmlspecialchars($post['secret_identity_nombre'], ENT_QUOTES, 'UTF-8');
    }

    // ── Akuma data (from second query stored in $ficha['_akuma']) ─────────────────
    $akuma_nombre_raw = $ficha['akuma'] ?? '';
    $akuma_has        = !empty($akuma_nombre_raw) && !empty($ficha['_akuma']);
    $akuma_tab_btn    = '';
    $akuma_panel_html = '';

    if ($akuma_has) {
        $ak_row         = $ficha['_akuma'];
        $ak_nombre      = htmlspecialchars($akuma_nombre_raw,             ENT_QUOTES, 'UTF-8');
        $ak_subnombre   = htmlspecialchars($ficha['akuma_subnombre'] ?? '', ENT_QUOTES, 'UTF-8');
        $ak_categoria   = htmlspecialchars($ak_row['categoria']      ?? '', ENT_QUOTES, 'UTF-8');
        $ak_tier        = htmlspecialchars($ak_row['tier']            ?? '', ENT_QUOTES, 'UTF-8');
        $ak_descripcion = htmlspecialchars($ak_row['descripcion']     ?? '', ENT_QUOTES, 'UTF-8');
        $ak_imagen      = htmlspecialchars($ak_row['imagen']          ?? '', ENT_QUOTES, 'UTF-8');
        $ak_dominios    = htmlspecialchars($ak_row['dominios']        ?? '', ENT_QUOTES, 'UTF-8');
        $ak_pasivas     = htmlspecialchars($ak_row['pasivas']         ?? '', ENT_QUOTES, 'UTF-8');
        $ak_control     = (int)($ficha['dominio_akuma'] ?? 0);
        $ak_camino      = htmlspecialchars($ficha['camino']           ?? '', ENT_QUOTES, 'UTF-8');

        $akuma_tab_btn = '
      <div class="pestanas_avatar" id="pestana_akuma' . $pid . '" data-pid="' . $pid . '" data-tipo="akuma"
           style="width:26px;height:26px;background:#55306b;border-radius:4px;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;cursor:pointer;box-shadow:0 2px 5px rgba(0,0,0,0.5);" title="Fruta del Diablo">&#9670;</div>';

        $akuma_panel_html = '
<div class="avatar_akuma postbit-akuma-preview" id="avatar_akuma' . $pid . '"
     data-nombre="' . $ak_nombre . '"
     data-subnombre="' . $ak_subnombre . '"
     data-categoria="' . $ak_categoria . '"
     data-tier="' . $ak_tier . '"
     data-imagen="' . $ak_imagen . '"
     data-descripcion="' . $ak_descripcion . '"
     data-dominios="' . $ak_dominios . '"
     data-pasivas="' . $ak_pasivas . '"
     data-control="' . $ak_control . '"
     data-camino="' . $ak_camino . '"
     style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:15;
            opacity:0;transition:all 0.30s ease-out;background-color:rgba(0,0,0,0.88);
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            box-sizing:border-box;padding:8px;cursor:pointer;">
  <div style="width:100%;background:url(/images/op/uploads/Libro%20Akuma%20_One_Piece_Gaiden_Foro_Rol.webp) no-repeat center/cover;
              border:2px solid black;padding:10px 0;height:280px;border-radius:8px;box-sizing:border-box;">
    <div style="text-align:center;font-size:15px;color:black;font-family:moonGetHeavy,sans-serif;
                margin-bottom:4px;text-shadow:2px 2px 1px white;">' . $ak_nombre . '</div>
    <div style="text-align:center;font-size:11px;color:white;font-family:moonGetHeavy,sans-serif;
                font-style:italic;margin-bottom:4px;text-shadow:1px 1px 1px black;">' . $ak_subnombre . '</div>
    <div style="text-align:center;background:linear-gradient(90deg,rgba(0,116,143,0) 0%,rgb(81,25,106) 20%,rgb(35,5,46) 50%,rgb(81,25,106) 80%,rgba(0,116,143,0) 100%);margin-top:6px;">
      <div style="font-family:moonGetHeavy,sans-serif;color:white;font-size:11px;padding:2px 0;">' . $ak_categoria . ' | Tier ' . $ak_tier . '</div>
    </div>
    <div style="text-align:center;margin-top:20px;">
      <img src="' . $ak_imagen . '" style="width:160px;height:160px;object-fit:contain;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.7));" />
    </div>
  </div>
  <div style="color:white;font-family:moonGetHeavy,sans-serif;font-size:10px;
              margin-top:8px;opacity:0.7;letter-spacing:1px;">Clic para ver detalles</div>
</div>';
    }

    // ── Stats overlay HTML ──────────────────────────────────────────────────────
    $overlay = postbit_ficha_stat($fuerza,      '#c0392b', 'FUE')
             . postbit_ficha_stat($resistencia,  '#2980b9', 'RES')
             . postbit_ficha_stat($destreza,     '#27ae60', 'DES')
             . postbit_ficha_stat($punteria,     '#d4ac0d', 'PUN')
             . postbit_ficha_stat($agilidad,     '#17a589', 'AGI')
             . postbit_ficha_stat($reflejos,     '#e67e22', 'REF')
             . '<div style="grid-column:1/-1;">' . postbit_ficha_stat($voluntad, '#8e44ad', 'VOL') . '</div>';

    $stats_html = '
<div class="avatar_stats" id="avatar_stats' . $pid . '" style="position:absolute;top:0;left:0;width:100%;height:100%;overflow:hidden auto;pointer-events:none;z-index:15;background-color:rgba(0,0,0,0.80);transition:all 0.30s ease-out;display:flex;flex-direction:column;justify-content:center;align-items:center;box-sizing:border-box;padding:12px 8px;">
  <div style="text-align:center;width:100%;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 4px;">' . $overlay . '</div>
  </div>

</div>';

    // ── Info overlay (nombre, facción, rango, características, reputación) ──────────
    $info_html = '
<div class="avatar_info" id="avatar_info' . $pid . '"
     style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:15;
            opacity:0;transition:all 0.30s ease-out;background-color:rgba(0,0,0,0.80);
            display:flex;flex-direction:column;align-items:center;
            padding:14px 10px 10px;box-sizing:border-box;overflow:hidden;gap:8px;">

  <!-- Nombre -->
  <div style="color:white;font-family:LemonMilkLight,sans-serif;font-size:13px;letter-spacing:1px;
              text-shadow:2px 2px 3px #000;text-align:center;line-height:1.4;width:100%;">'
      . $nombre . '</div>

  <!-- Facción + Rango -->
  <div style="display:flex;gap:4px;width:100%;justify-content:center;">
    <div style="flex:1;background:rgba(255,255,255,0.08);border-radius:4px;padding:4px 6px;text-align:center;">
      <div style="font-family:moonGetHeavy,sans-serif;color:#c49fd8;font-size:8px;letter-spacing:1px;">FACCIÓN</div>
      <div style="font-family:LemonMilkLight,sans-serif;color:white;font-size:10px;margin-top:2px;">' . $faccion . '</div>
    </div>
    <div style="flex:1;background:rgba(255,255,255,0.08);border-radius:4px;padding:4px 6px;text-align:center;">
      <div style="font-family:moonGetHeavy,sans-serif;color:#c49fd8;font-size:8px;letter-spacing:1px;">RANGO</div>
      <div style="font-family:LemonMilkLight,sans-serif;color:white;font-size:10px;margin-top:2px;">' . $rango . '</div>
    </div>
  </div>'

      // Rango narrador (solo si tiene valor)
      . ($rango_narrador ? '
  <div style="width:100%;background:rgba(101,52,170,0.25);border:1px solid rgba(196,159,216,0.4);border-radius:4px;padding:4px 8px;text-align:center;">
    <div style="font-family:moonGetHeavy,sans-serif;color:#c49fd8;font-size:8px;letter-spacing:1px;">NARRADOR</div>
    <div style="font-family:LemonMilkLight,sans-serif;color:white;font-size:10px;margin-top:2px;">' . $rango_narrador . '</div>
  </div>' : '')

      // Rango inframundo (solo si tiene valor y no es vacío/Sin rango/Alimaña)
      . (($rango_inframundo && $rango_inframundo !== 'Sin rango' && $rango_inframundo !== 'Alimaña') ? '
  <div style="width:100%;background:linear-gradient(145deg,#0d0005,#200010);border:1px solid rgba(192,64,106,0.5);border-radius:4px;padding:4px 8px;text-align:center;position:relative;">
    <div style="position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(220,80,120,0.5),transparent);"></div>
    <div style="font-family:moonGetHeavy,sans-serif;color:#c0406a;font-size:8px;letter-spacing:2px;text-shadow:0 0 6px rgba(192,64,106,0.7);">— INFRAMUNDO —</div>
    <div style="font-family:moonGetHeavy,sans-serif;color:#f8d0dc;font-size:11px;letter-spacing:1px;text-shadow:0 0 8px rgba(200,50,80,0.6);text-transform:uppercase;">' . $rango_inframundo . '</div>
  </div>' : '') . '

  <!-- Separador -->
  <div style="width:80%;height:1px;background:rgba(196,159,216,0.35);"></div>

  <!-- Características -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;width:100%;">
    <div style="background:rgba(255,255,255,0.06);border-radius:4px;padding:4px 6px;">
      <div style="font-family:moonGetHeavy,sans-serif;color:#c49fd8;font-size:7px;letter-spacing:1px;">RAZA</div>
      <div style="font-family:LemonMilkLight,sans-serif;color:white;font-size:9px;margin-top:1px;">' . $raza . '</div>
    </div>
    <div style="background:rgba(255,255,255,0.06);border-radius:4px;padding:4px 6px;">
      <div style="font-family:moonGetHeavy,sans-serif;color:#c49fd8;font-size:7px;letter-spacing:1px;">GÉNERO</div>
      <div style="font-family:LemonMilkLight,sans-serif;color:white;font-size:9px;margin-top:1px;">' . $sexo . '</div>
    </div>
    <div style="background:rgba(255,255,255,0.06);border-radius:4px;padding:4px 6px;">
      <div style="font-family:moonGetHeavy,sans-serif;color:#c49fd8;font-size:7px;letter-spacing:1px;">EDAD</div>
      <div style="font-family:LemonMilkLight,sans-serif;color:white;font-size:9px;margin-top:1px;">' . $edad . ' años</div>
    </div>
    <div style="background:rgba(255,255,255,0.06);border-radius:4px;padding:4px 6px;">
      <div style="font-family:moonGetHeavy,sans-serif;color:#c49fd8;font-size:7px;letter-spacing:1px;">ALTURA</div>
      <div style="font-family:LemonMilkLight,sans-serif;color:white;font-size:9px;margin-top:1px;">' . $altura . ' cm</div>
    </div>
    <div style="background:rgba(255,255,255,0.06);border-radius:4px;padding:4px 6px;grid-column:1/-1;">
      <div style="font-family:moonGetHeavy,sans-serif;color:#c49fd8;font-size:7px;letter-spacing:1px;">PESO</div>
      <div style="font-family:LemonMilkLight,sans-serif;color:white;font-size:9px;margin-top:1px;">' . $peso . ' kg</div>
    </div>
  </div>

  <!-- Separador -->
  <div style="width:80%;height:1px;background:rgba(196,159,216,0.35);"></div>

  <!-- Reputación (igual que en la ficha) -->
  <div style="width:100%;">
    <!-- Fama + Total -->
    <div style="width:100%;background-color:#ffe8b3;text-align:center;
                border:2px solid black;border-bottom:0;padding:4px 5px;box-sizing:border-box;">
      <div style="font-family:moonGetHeavy,sans-serif;color:black;
                  background:linear-gradient(90deg,rgb(229,209,160) 0%,rgba(146,4,255,1) 50%,rgb(229,209,160) 100%);
                  text-transform:uppercase;padding:0 5px;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <span style="text-shadow:0 0 3px #fff;">' . $fama . '</span>
      </div>
      <div style="font-family:moonGetHeavy,sans-serif;color:black;font-size:22px;line-height:1.1;margin-top:2px;">'
          . $rep_total . '</div>
    </div>
    <!-- Positiva / Negativa -->
    <div style="width:100%;display:flex;border:2px solid black;border-top:1px solid black;box-sizing:border-box;">
      <div style="flex:1;background:#fff;text-align:center;padding:3px 0;border-right:1px solid #444;">
        <div style="font-family:moonGetHeavy,sans-serif;color:black;font-size:8px;letter-spacing:1px;">R. POSITIVA</div>
        <div style="font-family:moonGetHeavy,sans-serif;color:black;font-size:13px;">' . $rep_pos . '</div>
      </div>
      <div style="flex:1;background:#000;text-align:center;padding:3px 0;">
        <div style="font-family:moonGetHeavy,sans-serif;color:white;font-size:8px;letter-spacing:1px;">R. NEGATIVA</div>
        <div style="font-family:moonGetHeavy,sans-serif;color:white;font-size:13px;">' . $rep_neg . '</div>
      </div>
    </div>
  </div>


</div>';

    // ── Level indicator (circular progress, top-left of avatar) ──────────────────
    $level_html = '
<div class="circular-progress"
     data-inner-circle-color="#101010f0"
     data-percentage="' . $nivelPorcentaje . '"
     data-progress-color="#ff7e00"
     data-bg-color="#c49fd8"
     data-nivel="' . $nivel . '"
     style="position:absolute;left:0;top:0;transform:translate(-50%,-50%);z-index:30;width:56px;height:55px;--progress-bar-width:56px;--progress-bar-height:55px;cursor:pointer;border-radius:50%;box-shadow:-1px -2px 6px black;">
  <div class="inner-circle" style="width:calc(56px - 10px);height:calc(55px - 10px);background-color:rgba(16,16,16,0.94);border-radius:50%;z-index:3;"></div>
  <p class="percentage" style="font-size:16px;z-index:4;margin:0;position:relative;color:white;font-family:moonGetHeavy,sans-serif;"></p>
</div>';

    // ── Avatar block (overrides $post['useravatar']) ────────────────────────────
    // Inyectar una vez el override de overflow para que el badge sobresalga
    $style_tag = '';
    if (!$style_injected) {
        $style_tag = '<style>'
            . '.post.classic{overflow:visible!important;}'
            . '.post.classic::after{content:"";display:table;clear:both;}'
            . '.post.classic .post_author{overflow:visible!important;background:transparent!important;float:left!important;width:250px!important;height:450px!important;margin-right:13px!important;margin-left:10px!important;margin-top:10px!important;position:relative!important;}'
            . '.post.classic .post_content{float:none!important;width:auto!important;overflow:visible!important;}'
            . '.post.classic .post_body > div{display:flow-root;}'
            . '.post.classic .post_content blockquote.mycode_quote{overflow:hidden;}'
            . '.post.classic .post_content ul,.post.classic .post_content ol{display:flow-root;}'
            . '.post span.edited_post,.post span.edited_post a{color:rgb(51,32,134)!important;font-style:italic!important;font-size:8px!important;text-shadow:none!important;}'
            . '#posts_container,#posts{overflow:visible!important;}'
            . '#content{overflow:visible!important;}'
            . '#content::after{content:"";display:table;clear:both;}'
            // Akuma modal overlay
            . '#akumaModal{display:none;position:fixed;z-index:10000;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.4);}'
            . '@-webkit-keyframes animatetop{from{top:-300px;opacity:0}to{top:0;opacity:1}}'
            . '@keyframes animatetop{from{top:-300px;opacity:0}to{top:0;opacity:1}}'
            . '.akuma-libro-modal{position:relative;background-image:url(\'/images/op/misc/LibroBase_One_Piece_Gaiden_Foro_Rol.png\');margin:150px auto 0;width:1200px;height:644px;background-repeat:no-repeat;background-size:contain;-webkit-animation-name:animatetop;-webkit-animation-duration:0.4s;animation-name:animatetop;animation-duration:0.4s;}'
            . '.akuma-libro-body{display:flex;flex-direction:row;height:100%;padding:0;}'
            . '.akuma-libro-left{display:flex;flex-direction:column;width:42%;margin-left:123px;position:relative;}'
            . '.akuma-libro-right{display:flex;flex-direction:column;width:42%;position:relative;}'
            . '.akuma-nombre-libro{font-family:moonGetHeavy,sans-serif;color:black;font-size:36px;margin:auto;text-shadow:2px 2px 1px white;margin-top:29px;letter-spacing:1px;text-align:center;}'
            . '.akuma-subnombre-libro{font-family:moonGetHeavy,sans-serif;color:white;font-size:17px;margin:auto;text-shadow:2px 2px 1px black;letter-spacing:1px;text-align:center;}'
            . '.akuma-tipo-tier-libro{margin:auto;margin-top:25px;width:483px;text-align:center;border-radius:25px;padding:8px 0;}'
            . '.akuma-tipo-tier-text{font-family:moonGetHeavy,sans-serif;color:white;font-size:29px;margin:auto;text-shadow:2px 2px 1px black;letter-spacing:2px;}'
            . '.akuma-imagen-libro-container{margin:auto;margin-top:20px;}'
            . '.akuma-imagen-libro{height:411px;max-width:100%;object-fit:contain;filter:drop-shadow(0 0 10px rgba(0,0,0,0.5));}'
            . '.akuma-descripcion-background-libro{margin:auto;margin-top:46px;width:483px;text-align:center;margin-bottom:0;border-radius:25px;padding:8px 0;}'
            . '.akuma-descripcion-titulo-libro{font-family:moonGetHeavy,sans-serif;color:white;font-size:24px;margin:auto;text-shadow:2px 2px 1px black;letter-spacing:2px;}'
            . '.akuma-descripcion-libro{text-align:justify;margin-top:11px;font-family:InterRegular,sans-serif;color:black;font-size:16px;padding:7px 26px;font-weight:600;line-height:1.4;max-height:150px;overflow-y:auto;}'
            . '#modoLectura.activo{background:rgba(255,200,50,0.85)!important;color:black!important;border-color:rgba(0,0,0,0.3)!important;}'
            . 'a.new_reply_button span{text-transform:uppercase;font-weight:900;}'
            . '.post.classic{margin-bottom:1px!important;margin-top:0!important;}'
            . 'a.ficha-nombre-link:hover{color:rgba(255,255,255,0.65)!important;}'
            . '.post.classic .post_date{margin-left:20px!important;}'
            . '.post.classic .post_meta{margin-left:20px!important;}'
            . '.post.classic .post_controls{padding:1px 0!important;min-height:40px!important;box-sizing:border-box!important;}'
            . '.post.classic .post_controls .post_management_buttons a{background-color:#ff6100!important;color:white!important;font-weight:bold!important;margin-top:2px!important;top:2px!important;}'
            . '.post.classic .post_controls .post_management_buttons a span{color:white!important;font-weight:bold!important;}'
            . '</style>';
        $style_injected = true;
    }

    // El wrapper no tiene z-index propio para no crear stacking context,
    // así el indicador de nivel (hermano de author_avatar) puede sobresalir.
    $border_style = '<style>#post_' . $pid . '{border:4px solid ' . $post['faccion_border_color'] . '!important;box-sizing:border-box!important;}'
        . '#post_' . $pid . ' .post_head{background-color:' . $post['faccion_border_color'] . '!important;height:40px!important;position:relative!important;}'
        . '#post_' . $pid . ' .post_controls{background-color:' . $post['faccion_border_color'] . '!important;position:relative!important;}'
        . '</style>';
    $post['useravatar'] = $style_tag . $border_style . '
<div style="position:relative;">

  <div id="avatar_avatar' . $pid . '" class="author_avatar" style="position:relative;margin-bottom:0;height:450px;z-index:10;background:#1a0a2e;">

    <!-- Tab buttons -->
    <div style="position:absolute;bottom:8px;left:8px;z-index:20;display:flex;flex-direction:row;gap:4px;">
      <div class="pestanas_avatar" id="pestana_avatar' . $pid . '" data-pid="' . $pid . '" data-tipo="avatar"
           style="width:26px;height:26px;background:#55306b;border-radius:4px;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;cursor:pointer;box-shadow:0 2px 5px rgba(0,0,0,0.5);" title="Retrato">&#9685;</div>
      <div class="pestanas_avatar" id="pestana_info' . $pid . '" data-pid="' . $pid . '" data-tipo="info"
           style="width:26px;height:26px;background:#55306b;border-radius:4px;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;cursor:pointer;box-shadow:0 2px 5px rgba(0,0,0,0.5);" title="Personaje">&#8505;</div>
      <div class="pestanas_avatar pestana_active" id="pestana_stats' . $pid . '" data-pid="' . $pid . '" data-tipo="stats"
           style="width:26px;height:26px;background:#55306b;border-radius:4px;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;cursor:pointer;box-shadow:0 2px 5px rgba(0,0,0,0.5);" title="Estadísticas">&#9876;</div>
      ' . $akuma_tab_btn . '
    </div>

    <!-- Portrait image -->
    <a href="/op/ficha.php?uid=' . $uid . '">
      <img src="' . $avatar_src . '" alt=""
           onerror="if(this.src!==\'' . $avatar_fallback . '\'){this.src=\'' . $avatar_fallback . '\';}"
           style="width:100%;height:100%;object-fit:cover;display:block;border:4px solid ' . $post['faccion_border_color'] . ';box-sizing:border-box;" />
    </a>

    ' . $stats_html . '

    ' . $info_html . '

    ' . $akuma_panel_html . '

    ' . $resources_bar_html . '

  </div>

  ' . $level_html . '

</div>';

    // ── User details: prepend raza ──────────────────────────────────────────────
    if ($raza) {
        $raza_html = '<div class="outer-postbit"><div class="postbit-text" style="font-family:LemonMilkLight,sans-serif;color:#55306b;font-size:11px;">'
            . $raza . '</div></div>';
        $post['user_details'] = $raza_html . $post['user_details'];
    }
}

function postbit_ficha_stat($value, $color, $label)
{
    return '
<div style="text-align:center;">
  <div class="stat_diamante" style="background-color:' . $color . ';margin:0 auto;"></div>
  <div class="stat_texto">' . $value . '</div>
  <div style="color:rgba(255,255,255,0.9);font-family:moonGetHeavy,sans-serif;font-size:9px;text-shadow:1px 1px 1px #000;position:relative;top:-14px;">' . $label . '</div>
</div>';
}

function postbit_ficha_thread_fecha()
{
    global $db, $thread, $thread_fecha_html;
    $thread_fecha_html = '';
    if (empty($thread['tid'])) return;
    $partes = [];
    if (!empty($thread['year']))     $partes[] = 'Año ' . (int)$thread['year'];
    if (!empty($thread['estacion'])) $partes[] = htmlspecialchars($thread['estacion'], ENT_QUOTES, 'UTF-8');
    if (!empty($thread['day']))      $partes[] = 'Día ' . (int)$thread['day'];
    if ($partes) {
        $thread_fecha_html = ' <span style="font-family:LemonMilkLight,sans-serif;font-size:11px;font-weight:normal;'
            . 'color:rgba(255,255,255,0.75);letter-spacing:1px;vertical-align:middle;">'
            . '(' . implode(' · ', $partes) . ')</span>';
    }
}
