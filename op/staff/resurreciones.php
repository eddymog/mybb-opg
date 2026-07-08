<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'resurreciones.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

$uid      = (int)$mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_staff($uid) && !is_mod($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

// ── Configuración ─────────────────────────────────────────────────────────────

$PORCENTAJES = [
    'torpe'      => 0.20,
    'normal'     => 0.30,
    'respetable' => 0.50,
    'epica'      => 0.60,
    'legendaria' => 0.75,
];

$LABELS_TIPO = [
    'torpe'      => 'Muerte Torpe',
    'normal'     => 'Muerte Normal',
    'respetable' => 'Muerte Respetable',
    'epica'      => 'Muerte Épica',
    'legendaria' => 'Muerte Legendaria',
];

// ── Helpers ───────────────────────────────────────────────────────────────────

function resur_ficha($fid) {
    global $db;
    $fid = (int)$fid;
    $q = $db->simple_select('op_fichas', '*', "fid='$fid'");
    return $db->fetch_array($q) ?: null;
}

function resur_user($fid) {
    global $db;
    $fid = (int)$fid;
    $q = $db->simple_select('users', 'uid, username, newpoints', "uid='$fid'");
    return $db->fetch_array($q) ?: null;
}

function resur_calcular_nikas_gastadas($ficha) {
    $total = 0;
    $has_haki_camino  = ($ficha['camino'] === 'Haki');
    $has_akuma_camino = ($ficha['camino'] === 'Akuma');

    // Hakis: level starts at 1, upgrades cost nikas
    $haki_costs = [0, 10, 15, 25, 40, 60, 150]; // index = level transitioning FROM (1..6)
    if ($has_haki_camino) $haki_costs[1] = 0;    // first upgrade free with Haki camino
    foreach (['kenbun', 'buso', 'hao'] as $haki) {
        $lvl = (int)($ficha[$haki] ?? 1);
        for ($i = 1; $i < $lvl; $i++) {
            $total += $haki_costs[$i];
        }
    }

    // Dominio Akuma: starts at 0
    $dominio = (int)($ficha['dominio_akuma'] ?? 0);
    if ($dominio > 0) {
        $akuma_costs = $has_akuma_camino
            ? [0, 5, 15, 20, 30, 80]
            : [5, 10, 20, 25, 40];
        for ($i = 0; $i < $dominio && $i < count($akuma_costs); $i++) {
            $total += $akuma_costs[$i];
        }
    }

    // Estilos (estilo1 is free)
    if (($ficha['estilo2'] ?? 'bloqueado') !== 'bloqueado') $total += 25;
    if (($ficha['estilo3'] ?? 'bloqueado') !== 'bloqueado') $total += 50;
    if (($ficha['estilo4'] ?? 'bloqueado') !== 'bloqueado') $total += 75;

    // Belicas: unlock cost per slot (slot 1 is free/initial)
    $belica_unlock = [0, 0, 10, 20, 35, 50, 65, 80, 95, 110, 125, 140, 155];
    for ($b = 2; $b <= 12; $b++) {
        if (!empty($ficha['belica' . $b])) {
            $total += $belica_unlock[$b];
        }
    }

    // Belica espe costs
    $espe1_init    = [0, 0, 20, 30, 45, 60, 75, 90, 105, 120, 135, 150, 165];
    $espe2_init    = [0, 15, 30, 40, 60, 75, 90, 105, 120, 135, 150, 165, 180];
    $espe_levelup  = [0, 45, 60, 75, 100, 125, 150, 175, 200, 225, 250, 275, 300];
    $belicas = json_decode($ficha['belicas'] ?? '{}');
    for ($b = 1; $b <= 12; $b++) {
        $bname = $ficha['belica' . $b] ?? '';
        if (empty($bname) || !isset($belicas->{$bname})) continue;
        $bd = $belicas->{$bname};
        foreach (['espe1' => $espe1_init, 'espe2' => $espe2_init] as $eslot => $init_table) {
            if (!isset($bd->{$eslot})) continue;
            $ename = $bd->{$eslot};
            $elvl  = isset($bd->sub->{$ename}) ? (int)$bd->sub->{$ename} : 0;
            if ($elvl <= 0) continue;
            $total += $init_table[$b];
            if ($elvl >= 2) $total += $espe_levelup[$b];
        }
    }

    // Oficio espe nikas
    $oficios = json_decode($ficha['oficios'] ?? '{}');
    if ($oficios) {
        foreach ($oficios as $odata) {
            if (!isset($odata->sub)) continue;
            foreach ($odata->sub as $slvl) {
                $slvl = (int)$slvl;
                if ($slvl >= 1) $total += 10;
                if ($slvl >= 2) $total += 25;
                if ($slvl >= 3) $total += 50;
            }
        }
    }

    // Limite de nivel (default is 20, each upgrade costs nikas)
    $limite = (int)($ficha['limite_nivel'] ?? 20);
    for ($lv = 20; $lv < $limite; $lv++) {
        if      ($lv >= 40) $total += 25;
        elseif  ($lv >= 35) $total += 20;
        elseif  ($lv >= 30) $total += 15;
        elseif  ($lv >= 25) $total += 10;
        else                $total += 5;
    }

    return $total;
}

function resur_calcular_puntos_oficio_gastados($ficha) {
    $total   = 0;
    $oficios = json_decode($ficha['oficios'] ?? '{}');
    if (!$oficios) return 0;
    foreach ($oficios as $odata) {
        if (isset($odata->nivel) && (int)$odata->nivel >= 2) $total += 1000;
        if (!isset($odata->sub)) continue;
        foreach ($odata->sub as $slvl) {
            $slvl = (int)$slvl;
            if ($slvl >= 1) $total += 2000;
            if ($slvl >= 2) $total += 3500;
            if ($slvl >= 3) $total += 5000;
        }
    }
    return $total;
}

function resur_nf($v, $dec = 0) {
    return number_format((float)$v, $dec, ',', '.');
}

// ── Input ─────────────────────────────────────────────────────────────────────

$accion      = $mybb->get_input('accion');
$fid_old     = (int)$mybb->get_input('fid_old');
$fid_new     = (int)$mybb->get_input('fid_new');
$tipo        = $mybb->get_input('tipo');
$link_muerte = trim($mybb->get_input('link_muerte'));

// Valores por defecto para el formulario
if (!$tipo) $tipo = 'respetable';

$resur_alert   = '';
$resur_preview = '';

// ── Acción: confirmar ─────────────────────────────────────────────────────────

if ($accion === 'confirmar' && $fid_old && $fid_new && isset($PORCENTAJES[$tipo])) {
    $pct = $PORCENTAJES[$tipo];
    $fo  = resur_ficha($fid_old);
    $uo  = resur_user($fid_old);
    $fn  = resur_ficha($fid_new);
    $un  = resur_user($fid_new);

    if (!$fo || !$fn || !$uo || !$un) {
        $resur_alert = '<div class="resur-alert resur-alert-err">Fichas no encontradas (UID fallecido: ' . $fid_old . ' / UID heredero: ' . $fid_new . ').</div>';
    } else {
        $ng = resur_calcular_nikas_gastadas($fo);
        $pg = resur_calcular_puntos_oficio_gastados($fo);
        $tn = (int)$fo['nika']          + $ng;
        $tp = (int)$fo['puntos_oficio'] + $pg;

        $hb = (int)floor((int)$fo['berries']    * $pct);
        $hk = (int)floor((int)$fo['kuro']       * $pct);
        $hn = (int)floor($tn                    * $pct);
        $hp = (int)floor($tp                    * $pct);
        $he = round((float)$uo['newpoints']     * $pct, 2);
        $hr = (int)floor((int)$fo['reputacion'] * $pct);

        $nb = (int)$fn['berries']       + $hb;
        $nk = (int)$fn['kuro']          + $hk;
        $nn = (int)$fn['nika']          + $hn;
        $np = (int)$fn['puntos_oficio'] + $hp;
        $ne = round((float)$un['newpoints'] + $he, 2);
        $nr = (int)$fn['reputacion']    + $hr;

        $db->query("UPDATE mybb_op_fichas SET
            berries       = '$nb',
            kuro          = '$nk',
            nika          = '$nn',
            puntos_oficio = '$np',
            reputacion    = '$nr'
            WHERE fid = '$fid_new'");
        $db->query("UPDATE mybb_users SET newpoints = '$ne' WHERE uid = '$fid_new'");
        $db->query("UPDATE mybb_op_fichas SET muerto = '1' WHERE fid = '$fid_old'");

        $tipo_label = $LABELS_TIPO[$tipo];
        $pct_txt    = (int)($pct * 100);
        $link_txt   = $link_muerte ? '<br><strong>Enlace:</strong> ' . htmlspecialchars($link_muerte) : '';

        log_audit($uid, $username, '[Reencarnación]',
            "<strong>Staff:</strong> $username ($uid)<br>" .
            "<strong>Fallecido:</strong> {$fo['nombre']} ($fid_old) — $tipo_label ({$pct_txt}%){$link_txt}<br>" .
            "<strong>Heredero:</strong> {$fn['nombre']} ($fid_new)<br><br>" .
            "<strong>Herencia aplicada:</strong><br>" .
            "• Berries: {$fo['berries']} → $nb (+$hb)<br>" .
            "• Kuros: {$fo['kuro']} → $nk (+$hk)<br>" .
            "• Nikas: {$fo['nika']} → $nn (+$hn de total $tn [$ng gast. en mejoras])<br>" .
            "• P.Oficio: {$fo['puntos_oficio']} → $np (+$hp de total $tp [$pg gast. en mejoras])<br>" .
            "• Experiencia: {$uo['newpoints']} → $ne (+$he)<br>" .
            "• Reputación: {$fo['reputacion']} → $nr (+$hr)<br>"
        );

        $nombre_old = htmlspecialchars($fo['nombre']);
        $nombre_new = htmlspecialchars($fn['nombre']);
        $resur_alert = "<div class=\"resur-alert resur-alert-ok\">{$nombre_new} ha recibido la herencia de {$nombre_old} ($tipo_label, {$pct_txt}%). El personaje fallecido ha sido marcado como muerto.</div>";

        // Limpia form tras confirmar exitosamente
        $fid_old = $fid_new = 0;
        $tipo = 'respetable';
        $link_muerte = '';
    }
}

// ── Acción: calcular (preview) ────────────────────────────────────────────────

if ($accion === 'calcular' && $fid_old && $fid_new && isset($PORCENTAJES[$tipo])) {
    $pct = $PORCENTAJES[$tipo];
    $fo  = resur_ficha($fid_old);
    $uo  = resur_user($fid_old);
    $fn  = resur_ficha($fid_new);
    $un  = resur_user($fid_new);

    if (!$fo || !$fn) {
        $resur_alert = '<div class="resur-alert resur-alert-err">Fichas no encontradas (UID fallecido: ' . $fid_old . ' / UID heredero: ' . $fid_new . '). Verifica que ambos tengan ficha creada.</div>';
    } else {
        $ng = resur_calcular_nikas_gastadas($fo);
        $pg = resur_calcular_puntos_oficio_gastados($fo);
        $tn = (int)$fo['nika']          + $ng;
        $tp = (int)$fo['puntos_oficio'] + $pg;

        $hb = (int)floor((int)$fo['berries']    * $pct);
        $hk = (int)floor((int)$fo['kuro']       * $pct);
        $hn = (int)floor($tn                    * $pct);
        $hp = (int)floor($tp                    * $pct);
        $he = round((float)($uo ? $uo['newpoints'] : 0) * $pct, 2);
        $hr = (int)floor((int)$fo['reputacion'] * $pct);

        $unp = (float)($un ? $un['newpoints'] : 0);
        $uop = (float)($uo ? $uo['newpoints'] : 0);

        $tipo_label = $LABELS_TIPO[$tipo];
        $pct_txt    = (int)($pct * 100);
        $nombre_old = htmlspecialchars($fo['nombre']);
        $nombre_new = htmlspecialchars($fn['nombre']);

        $rows_html = '';
        $rows = [
            ['Berries',
                resur_nf($fo['berries']),        resur_nf($hb),
                resur_nf($fn['berries']),        resur_nf((int)$fn['berries'] + $hb)],
            ['Kuros',
                resur_nf($fo['kuro']),           resur_nf($hk),
                resur_nf($fn['kuro']),           resur_nf((int)$fn['kuro'] + $hk)],
            ['Nikas <small class="resur-note-col">(actual: ' . resur_nf($fo['nika']) . ' + gastadas en mejoras: ' . resur_nf($ng) . ' = total: ' . resur_nf($tn) . ')</small>',
                resur_nf($fo['nika']),           resur_nf($hn),
                resur_nf($fn['nika']),           resur_nf((int)$fn['nika'] + $hn)],
            ['Ptos. Oficio <small class="resur-note-col">(actual: ' . resur_nf($fo['puntos_oficio']) . ' + gastados en mejoras: ' . resur_nf($pg) . ' = total: ' . resur_nf($tp) . ')</small>',
                resur_nf($fo['puntos_oficio']),  resur_nf($hp),
                resur_nf($fn['puntos_oficio']),  resur_nf((int)$fn['puntos_oficio'] + $hp)],
            ['Experiencia',
                resur_nf($uop, 2),               resur_nf($he, 2),
                resur_nf($unp, 2),               resur_nf($unp + $he, 2)],
            ['Reputación (racha)',
                resur_nf($fo['reputacion']),     resur_nf($hr),
                resur_nf($fn['reputacion']),     resur_nf((int)$fn['reputacion'] + $hr)],
        ];

        foreach ($rows as $r) {
            $rows_html .= '<tr>'
                . '<td class="resur-td">'            . $r[0] . '</td>'
                . '<td class="resur-td resur-num">'  . $r[1] . '</td>'
                . '<td class="resur-td resur-num resur-gain">+' . $r[2] . '</td>'
                . '<td class="resur-td resur-num resur-dim">' . $r[3] . '</td>'
                . '<td class="resur-td resur-num resur-bold">' . $r[4] . '</td>'
                . '</tr>';
        }

        $link_hidden = htmlspecialchars($link_muerte);
        $tipo_safe   = htmlspecialchars($tipo);

        $resur_preview = <<<HTML
<div class="resur-preview">
  <h3 class="resur-preview-title">PREVIEW DE HERENCIA</h3>
  <div class="resur-preview-meta">
    <span><strong>Fallecido:</strong> {$nombre_old} (UID {$fid_old}) &rarr; <em>{$tipo_label}</em> ({$pct_txt}%)</span>
    <span><strong>Heredero:</strong> {$nombre_new} (UID {$fid_new})</span>
  </div>
  <table class="resur-table">
    <thead>
      <tr>
        <th class="resur-th">Recurso</th>
        <th class="resur-th resur-num">Fallecido</th>
        <th class="resur-th resur-num">Herencia</th>
        <th class="resur-th resur-num">Heredero (actual)</th>
        <th class="resur-th resur-num">Heredero (tras)</th>
      </tr>
    </thead>
    <tbody>{$rows_html}</tbody>
  </table>
  <p class="resur-note-bottom">Los objetos, Akuma no Mi, Hakis y demás recursos <strong>no</strong> se transfieren. El personaje fallecido quedará marcado como muerto.</p>
  <form method="POST" style="text-align:center;margin-top:18px;">
    <input type="hidden" name="accion"      value="confirmar">
    <input type="hidden" name="fid_old"     value="{$fid_old}">
    <input type="hidden" name="fid_new"     value="{$fid_new}">
    <input type="hidden" name="tipo"        value="{$tipo_safe}">
    <input type="hidden" name="link_muerte" value="{$link_hidden}">
    <button type="submit" class="resur-btn-confirm">CONFIRMAR TRANSFERENCIA</button>
  </form>
</div>
HTML;
    }
}

// ── Variables para el template ────────────────────────────────────────────────

$resur_fid_old     = $fid_old ?: '';
$resur_fid_new     = $fid_new ?: '';
$resur_link_muerte = htmlspecialchars($link_muerte);

$resur_options_tipo = '';
foreach ($PORCENTAJES as $k => $v) {
    $sel = ($tipo === $k) ? ' selected="selected"' : '';
    $resur_options_tipo .= '<option value="' . $k . '"' . $sel . '>'
        . $LABELS_TIPO[$k] . ' (' . (int)($v * 100) . '%)</option>' . "\n";
}

// ── Render ────────────────────────────────────────────────────────────────────

eval("\$page = \"".$templates->get("staff_resurrecciones")."\";");
output_page($page);
