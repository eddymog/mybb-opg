<?php
/**
 * BBCustom Recursos
 * Procesa BBCode [recursos="vida"/"energia"/"haki"]
 * 
 * Muestra los recursos actuales vs máximos:
 * VidaActual/VidaMáxima | EnergíaActual/EnergíaMáxima | HakiActual/HakiMáximo
 * 
 * Los valores máximos se obtienen automáticamente de la ficha del usuario
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// Cargar librería de spoilers
require_once MYBB_ROOT . "inc/plugins/lib/spoiler.php";

// Hook para procesar DESPUÉS de MyBB
$plugins->add_hook("postbit", "BBCustom_recursos_run");

function BBCustom_recursos_info()
{
    return array(
        "name"          => "Recursos BBCode",
        "description"   => "BBCode [recursos] para mostrar Vida/Energía/Haki actuales vs máximos unificado",
        "website"       => "",
        "author"        => "Cascabelles",
        "authorsite"    => "",
        "version"       => "1.0",
        "codename"      => "BBCustom_recursos",
        "compatibility" => "*"
    );
}

function BBCustom_recursos_activate() {}
function BBCustom_recursos_deactivate() {}

function BBCustom_recursos_run(&$post)
{
    global $mybb, $db;
    
    // Protección contra procesamiento múltiple
    static $processed_pids = array();
    // Valores del último [recursos=] visto por uid en esta carga de página
    static $prev_recursos = array();
    // UIDs cuyo [recursos=] anterior ya fue buscado en DB (para no repetir la query)
    static $prev_initialized = array();

    $current_pid = isset($post['pid']) ? $post['pid'] : md5($post['message']);

    if (in_array($current_pid, $processed_pids)) {
        return;
    }

    $message = &$post['message'];
    $user_uid = (int)$post['uid'];
    $pid = $post['pid'];

    // Procesar BBCode [recursos="vida"/"energia"/"haki"]
    while (preg_match('#\[recursos=["\']?([0-9]+)/([0-9]+)/([0-9]+)["\']?\]#si', $message, $matches))
    {
        $vida_actual = intval($matches[1]);
        $energia_actual = intval($matches[2]);
        $haki_actual = intval($matches[3]);

        // Obtener los valores máximos de la ficha del usuario
        $ficha_data = BBCustom_recursos_get_ficha($user_uid, $db);

        if (!$ficha_data) {
            $html = '<div style="background: #f44336; color: white; padding: 10px; border-radius: 5px; text-align: center;">
                ⚠️ No se encontró ficha para mostrar recursos
            </div>';
            $message = preg_replace(
                '#\[recursos=["\']?' . preg_quote($matches[1], '#') . '/' . preg_quote($matches[2], '#') . '/' . preg_quote($matches[3], '#') . '["\']?\]#si',
                $html, $message, 1
            );
            continue;
        }

        // Calcular valores máximos (igual que en BBCustom_ficha.php)
        $stats = BBCustom_recursos_calc_stats($ficha_data, $user_uid, $db);

        $vida_max    = $stats['vitalidad'];
        $energia_max = $stats['energia'];
        $haki_max    = $stats['haki'];

        // Primera vez que vemos este uid en este request: buscar en páginas anteriores del hilo
        if (!isset($prev_initialized[$user_uid])) {
            $prev_initialized[$user_uid] = true;
            $tid_thread = (int)$post['tid'];
            if ($tid_thread > 0 && (int)$pid > 0 && !isset($prev_recursos[$user_uid])) {
                $q_prev = $db->query("SELECT message FROM mybb_posts
                                      WHERE tid='{$tid_thread}' AND uid='{$user_uid}' AND pid < '{$pid}'
                                        AND message LIKE '%[recursos=%'
                                      ORDER BY dateline DESC LIMIT 1");
                if ($row_prev = $db->fetch_array($q_prev)) {
                    if (preg_match('#\[recursos=["\']?([0-9]+)/([0-9]+)/([0-9]+)["\']?#si', $row_prev['message'], $mp)) {
                        $prev_recursos[$user_uid] = [
                            'vida' => (int)$mp[1],
                            'ene'  => (int)$mp[2],
                            'haki' => (int)$mp[3],
                        ];
                    }
                }
            }
        }

        // Valores de partida de la animación: el [recursos=] anterior de este uid, o el máximo si es el primero
        $prev = isset($prev_recursos[$user_uid]) ? $prev_recursos[$user_uid] : null;
        $from_vida = $prev ? $prev['vida'] : $vida_max;
        $from_ene  = $prev ? $prev['ene']  : $energia_max;
        $from_haki = $prev ? $prev['haki'] : $haki_max;

        // Actualizar el registro del anterior para el siguiente post de este uid
        $prev_recursos[$user_uid] = ['vida' => $vida_actual, 'ene' => $energia_actual, 'haki' => $haki_actual];

        // Calcular porcentajes para las barras
        $vida_percent    = ($vida_max    > 0) ? round(($vida_actual    / $vida_max)    * 100) : 0;
        $energia_percent = ($energia_max > 0) ? round(($energia_actual / $energia_max) * 100) : 0;
        $haki_percent    = ($haki_max    > 0) ? round(($haki_actual    / $haki_max)    * 100) : 0;

        // Generar HTML
        $recursos_html = BBCustom_recursos_generate_html(
            $vida_actual, $vida_max, $vida_percent,
            $energia_actual, $energia_max, $energia_percent,
            $haki_actual, $haki_max, $haki_percent,
            $pid,
            $from_vida, $from_ene, $from_haki
        );

        $message = preg_replace(
            '#\[recursos=["\']?' . preg_quote($matches[1], '#') . '/' . preg_quote($matches[2], '#') . '/' . preg_quote($matches[3], '#') . '["\']?\]#si',
            $recursos_html, $message, 1
        );
    }

    $processed_pids[] = $current_pid;
}

/**
 * Obtiene los datos de la ficha del usuario
 */
function BBCustom_recursos_get_ficha($uid, $db)
{
    $query = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$uid' LIMIT 1");
    return $db->fetch_array($query);
}

/**
 * Calcula las estadísticas completas (igual que en BBCustom_ficha.php)
 */
function BBCustom_recursos_calc_stats($ficha, $uid, $db)
{
    // Calcular stats pasivas
    $vitalidad_extra = (
        (intval($ficha['fuerza_pasiva']) * 6) + 
        (intval($ficha['resistencia_pasiva']) * 15) + 
        (intval($ficha['destreza_pasiva']) * 4) +
        (intval($ficha['agilidad_pasiva']) * 3) + 
        (intval($ficha['voluntad_pasiva']) * 1) + 
        (intval($ficha['punteria_pasiva']) * 2) + 
        (intval($ficha['reflejos_pasiva']) * 1)
    );

    $energia_extra = (
        (intval($ficha['fuerza_pasiva']) * 2) +
        (intval($ficha['resistencia_pasiva']) * 4) +
        (intval($ficha['punteria_pasiva']) * 5) +
        (intval($ficha['destreza_pasiva']) * 4) +
        (intval($ficha['agilidad_pasiva']) * 5) +
        (intval($ficha['reflejos_pasiva']) * 1) +
        (intval($ficha['voluntad_pasiva']) * 1)
    );

    $haki_extra = (intval($ficha['voluntad_pasiva']) * 10);

    $vitalidad = intval($ficha['vitalidad']) + $vitalidad_extra + intval($ficha['vitalidad_pasiva']);
    $energia = intval($ficha['energia']) + $energia_extra + intval($ficha['energia_pasiva']);
    $haki = intval($ficha['haki']) + $haki_extra + intval($ficha['haki_pasiva']);

    // Aplicar bonos de virtudes
    $nivel = intval($ficha['nivel']);
    
    // V037, V038, V039: Vigoroso 1, 2, 3
    $vigoroso_query = $db->query("
        SELECT virtud_id FROM mybb_op_virtudes_usuarios 
        WHERE uid='$uid' AND virtud_id IN ('V037', 'V038', 'V039')
    ");
    while ($v = $db->fetch_array($vigoroso_query)) {
        if ($v['virtud_id'] == 'V037') $vitalidad += $nivel * 10;
        if ($v['virtud_id'] == 'V038') $vitalidad += $nivel * 15;
        if ($v['virtud_id'] == 'V039') $vitalidad += $nivel * 20;
    }

    // V040, V041: Hiperactivo 1, 2
    $hiperactivo_query = $db->query("
        SELECT virtud_id FROM mybb_op_virtudes_usuarios 
        WHERE uid='$uid' AND virtud_id IN ('V040', 'V041')
    ");
    while ($v = $db->fetch_array($hiperactivo_query)) {
        if ($v['virtud_id'] == 'V040') $energia += $nivel * 10;
        if ($v['virtud_id'] == 'V041') $energia += $nivel * 15;
    }

    // V058, V059: Espiritual 1, 2
    $espiritual_query = $db->query("
        SELECT virtud_id FROM mybb_op_virtudes_usuarios 
        WHERE uid='$uid' AND virtud_id IN ('V058', 'V059')
    ");
    while ($v = $db->fetch_array($espiritual_query)) {
        if ($v['virtud_id'] == 'V058') $haki += $nivel * 5;
        if ($v['virtud_id'] == 'V059') $haki += $nivel * 10;
    }

    return array(
        'vitalidad' => $vitalidad,
        'energia' => $energia,
        'haki' => $haki
    );
}

/**
 * Genera el HTML de las barras de recursos
 */
function BBCustom_recursos_generate_html($vida_actual, $vida_max, $vida_percent,
                                         $energia_actual, $energia_max, $energia_percent,
                                         $haki_actual, $haki_max, $haki_percent,
                                         $pid,
                                         $from_vida = null, $from_ene = null, $from_haki = null)
{
    $from_vida  = ($from_vida  !== null) ? (int)$from_vida  : $vida_max;
    $from_ene   = ($from_ene   !== null) ? (int)$from_ene   : $energia_max;
    $from_haki  = ($from_haki  !== null) ? (int)$from_haki  : $haki_max;
    $green = 'hsl(120,90%,38%)';
    $html = '
    <div id="pbr-display-'.$pid.'" style="display:flow-root;background:linear-gradient(135deg,#2c3e50 0%,#34495e 100%);
                padding:15px;border-radius:10px;margin:10px 0;box-shadow:0 4px 6px rgba(0,0,0,0.3);cursor:default;">
        <!-- Vida -->
        <div style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                <span style="color:#e74c3c;font-weight:bold;font-size:14px;">❤️ VIDA</span>
                <span id="pbr-dt-vida-'.$pid.'" style="color:white;font-weight:bold;font-size:14px;">'.$from_vida.' / '.$vida_max.'</span>
            </div>
            <div style="background:#1a1a1a;border-radius:10px;height:20px;overflow:hidden;border:2px solid #2c3e50;">
                <div id="pbr-db-vida-'.$pid.'" style="background:'.$green.';width:'.min(100,round($from_vida/$vida_max*100)).'%;height:100%;box-shadow:0 0 10px rgba(231,76,60,0.5);"></div>
            </div>
        </div>
        <!-- Energía -->
        <div style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                <span style="color:#f39c12;font-weight:bold;font-size:14px;">⚡ ENERGÍA</span>
                <span id="pbr-dt-ene-'.$pid.'" style="color:white;font-weight:bold;font-size:14px;">'.$from_ene.' / '.$energia_max.'</span>
            </div>
            <div style="background:#1a1a1a;border-radius:10px;height:20px;overflow:hidden;border:2px solid #2c3e50;">
                <div id="pbr-db-ene-'.$pid.'" style="background:'.$green.';width:'.min(100,round($from_ene/$energia_max*100)).'%;height:100%;box-shadow:0 0 10px rgba(243,156,18,0.5);"></div>
            </div>
        </div>
        <!-- Haki -->
        <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                <span style="color:#9b59b6;font-weight:bold;font-size:14px;">🔮 HAKI</span>
                <span id="pbr-dt-haki-'.$pid.'" style="color:white;font-weight:bold;font-size:14px;">'.$from_haki.' / '.$haki_max.'</span>
            </div>
            <div style="background:#1a1a1a;border-radius:10px;height:20px;overflow:hidden;border:2px solid #2c3e50;">
                <div id="pbr-db-haki-'.$pid.'" style="background:'.$green.';width:'.min(100,round($from_haki/$haki_max*100)).'%;height:100%;box-shadow:0 0 10px rgba(155,89,182,0.5);"></div>
            </div>
        </div>
    </div>
    
    <script>
        (function() {
            // Inyectar keyframes rainbow una sola vez por página
            if (!document.getElementById("pbr-style")) {
                var s = document.createElement("style");
                s.id = "pbr-style";
                s.textContent = "@keyframes pbr-rainbow{"
                    + "0%{background:hsl(0,100%,50%)}14%{background:hsl(50,100%,50%)}28%{background:hsl(100,100%,45%)}"
                    + "42%{background:hsl(160,100%,45%)}57%{background:hsl(210,100%,55%)}71%{background:hsl(270,100%,55%)}"
                    + "85%{background:hsl(320,100%,55%)}100%{background:hsl(360,100%,50%)}}"
                    + ".pbr-overflow{animation:pbr-rainbow 1.4s linear infinite!important;width:100%!important;}";
                document.head.appendChild(s);
            }

            var post = document.getElementById("post_'.$pid.'");
            if (!post) return;

            var VIDA_ACT='.$vida_actual.',  VIDA_MAX='.$vida_max.',  VIDA_FROM='.$from_vida.';
            var ENE_ACT='.$energia_actual.',   ENE_MAX='.$energia_max.',   ENE_FROM='.$from_ene.';
            var HAKI_ACT='.$haki_actual.',  HAKI_MAX='.$haki_max.',  HAKI_FROM='.$from_haki.';

            function barColor(pct) { return "hsl("+(pct*1.2)+",90%,38%)"; }

            function animateResource(textEl, barEl, fromVal, toVal, maxVal, delay) {
                if (!textEl && !barEl) return;
                var overflow = toVal > maxVal;
                var duration = 3000;
                var startTime = null;
                var fromPct = maxVal > 0 ? Math.min(fromVal / maxVal * 100, 100) : 0;
                var toPct   = overflow ? 100 : (maxVal > 0 ? toVal / maxVal * 100 : 0);

                setTimeout(function() {
                    if (barEl) barEl.classList.remove("pbr-overflow");

                    if (overflow) {
                        // Animar fromPct→100 llenando, luego activar rainbow
                        if (barEl)  { barEl.style.width = fromPct + "%"; barEl.style.background = barColor(fromPct); }
                        if (textEl) textEl.innerText = fromVal + "/" + maxVal;
                        function fillStep(ts) {
                            if (!startTime) startTime = ts;
                            var t = Math.min((ts - startTime) / duration, 1);
                            var ease = 1 - Math.pow(1 - t, 3);
                            var pct = fromPct + (100 - fromPct) * ease;
                            var val = Math.round(fromVal + (toVal - fromVal) * ease);
                            if (barEl) barEl.style.width = pct + "%";
                            if (textEl) textEl.innerText = val + "/" + maxVal;
                            if (t < 1) { requestAnimationFrame(fillStep); }
                            else {
                                if (textEl) textEl.innerText = toVal + "/" + maxVal;
                                if (barEl) barEl.classList.add("pbr-overflow");
                            }
                        }
                        requestAnimationFrame(fillStep);
                    } else {
                        if (barEl)  { barEl.style.width = fromPct + "%"; barEl.style.background = barColor(fromPct); }
                        if (textEl) textEl.innerText = fromVal + "/" + maxVal;
                        function drainStep(ts) {
                            if (!startTime) startTime = ts;
                            var t = Math.min((ts - startTime) / duration, 1);
                            var ease = 1 - Math.pow(1 - t, 3);
                            var pct = fromPct + (toPct - fromPct) * ease;
                            var val = Math.round(fromVal + (toVal - fromVal) * ease);
                            if (barEl)  { barEl.style.width = pct + "%"; barEl.style.background = barColor(pct); }
                            if (textEl) textEl.innerText = val + "/" + maxVal;
                            if (t < 1) requestAnimationFrame(drainStep);
                        }
                        requestAnimationFrame(drainStep);
                    }
                }, delay);
            }

            function playAll() {
                // Avatar sidebar bars
                animateResource(
                    post.querySelector(".personaje_vida2"),    post.querySelector(".subBarraVida"),
                    VIDA_FROM, VIDA_ACT, VIDA_MAX, 0);
                animateResource(
                    post.querySelector(".personaje_energia2"), post.querySelector(".subBarraEnergia"),
                    ENE_FROM,  ENE_ACT,  ENE_MAX,  150);
                animateResource(
                    post.querySelector(".personaje_haki2"),    post.querySelector(".subBarraHaki"),
                    HAKI_FROM, HAKI_ACT, HAKI_MAX, 300);
                // Display principal bars
                animateResource(
                    document.getElementById("pbr-dt-vida-'.$pid.'"),  document.getElementById("pbr-db-vida-'.$pid.'"),
                    VIDA_FROM, VIDA_ACT, VIDA_MAX, 0);
                animateResource(
                    document.getElementById("pbr-dt-ene-'.$pid.'"),   document.getElementById("pbr-db-ene-'.$pid.'"),
                    ENE_FROM,  ENE_ACT,  ENE_MAX,  150);
                animateResource(
                    document.getElementById("pbr-dt-haki-'.$pid.'"),  document.getElementById("pbr-db-haki-'.$pid.'"),
                    HAKI_FROM, HAKI_ACT, HAKI_MAX, 300);
            }

            setTimeout(playAll, 400);

            // Hover en el avatar sidebar
            var container = post.querySelector(".postbit-resource-bars");
            if (container) container.addEventListener("mouseenter", playAll);
            // Hover en el display principal
            var display = document.getElementById("pbr-display-'.$pid.'");
            if (display) display.addEventListener("mouseenter", playAll);
        })();
    </script>';
    
    return $html;
}

/**
 * Devuelve el color según el porcentaje de recursos
 */
function BBCustom_recursos_get_color($percent)
{
    if ($percent >= 70) {
        return 'linear-gradient(90deg, #27ae60 0%, #2ecc71 100%)'; // Verde
    } else if ($percent >= 40) {
        return 'linear-gradient(90deg, #f39c12 0%, #f1c40f 100%)'; // Amarillo
    } else if ($percent >= 20) {
        return 'linear-gradient(90deg, #e67e22 0%, #d35400 100%)'; // Naranja
    } else {
        return 'linear-gradient(90deg, #c0392b 0%, #e74c3c 100%)'; // Rojo
    }
}
