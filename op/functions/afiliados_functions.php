<?php
/**
 * Sistema de Afiliados — One Piece Gaiden
 * Ver docs/afiliados_implementacion_opg.md
 *
 * Archivo propio (NO op_functions.php) a propósito: op/functions/op_functions.php
 * está vacío en el repo y su estado difiere del servidor; para no arriesgar un
 * overwrite en deploy, las funciones de afiliados viven acá y se requieren
 * explícitamente donde hacen falta (index.php, op/staff/gestionar_afiliados.php,
 * op/peticion_afiliados.php).
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

// Topes duros por nivel (§0.1). Si hay más activos que el tope, los sobrantes
// (por orden) no se muestran.
if (!defined('OP_AFILIADOS_SLOTS_GRANDE'))  { define('OP_AFILIADOS_SLOTS_GRANDE', 4); }
if (!defined('OP_AFILIADOS_SLOTS_PEQUENO')) { define('OP_AFILIADOS_SLOTS_PEQUENO', 20); }

/**
 * Render de una tarjeta pública. Los 3 niveles muestran SOLO imagen; el nombre
 * queda en el title del link. El $tier solo cambia la clase CSS (tamaño/proporción).
 */
if (!function_exists('op_afil_card_html')) {
    function op_afil_card_html($a, $tier) {
        $nombre = htmlspecialchars($a['nombre'], ENT_QUOTES);
        $url    = htmlspecialchars($a['url'], ENT_QUOTES);
        $img    = htmlspecialchars($a['imagen'], ENT_QUOTES);

        return '<a class="afil-card afil-card--' . $tier . '" href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow" title="' . $nombre . '">'
            . '<img class="afil-card__img" src="' . $img . '" alt="' . $nombre . '" loading="lazy" onerror="this.style.opacity=0.2">'
            . '</a>';
    }
}

/**
 * Cuadro vacío: placeholder "+" que linkea al formulario público (sin nivel).
 */
if (!function_exists('op_afil_slot_vacio_html')) {
    function op_afil_slot_vacio_html($tier, $bburl) {
        $url = htmlspecialchars($bburl . '/op/peticion_afiliados.php', ENT_QUOTES);
        return '<a class="afil-card afil-card--vacio afil-card--' . $tier . '" href="' . $url . '" title="Solicitar afiliación">'
            . '<span class="afil-slot-plus">+</span>'
            . '</a>';
    }
}

/**
 * CSS de la sección, embebido una sola vez (self-contained: no depende de
 * variables del theme ni de importar CSS aparte). Colores translúcidos para
 * que se adapte tanto a themes claros como oscuros.
 */
if (!function_exists('op_afiliados_css')) {
    function op_afiliados_css() {
        return '<style>
        /* Sección de Afiliados ("Nakamas") — sigue docs/style.md (paleta OPG). */
        .afil-wrap {
            box-sizing: border-box; width: 100%;
            padding: 10px 0px; 
        }
        .afil-wrap * { box-sizing: border-box; }

        .afil-maintitle-wrap { text-align: center; margin-bottom: 16px; }
        .afil-maintitle {
            display: inline-block; padding: 6px 22px;
            font-family: moonGetHeavy, Arial, sans-serif; text-transform: uppercase;
            letter-spacing: 1.5px; color: #fff; background: #8f59f7;
            border: 2px solid #000; border-radius: 8px;
            text-shadow: 1px 1px 1px #000; box-shadow: 0px 0px 8px rgba(0,0,0,.4);
        }

        /* align-items:flex-start = cada columna toma su altura natural (NO se
           estiran a la más alta, que era lo que agrandaba el Almirante). */
        .afil-columns { display: flex; gap: 10px; align-items: flex-start; }
        .afil-columns > .afil-col { display: flex; flex-direction: column; flex: 1 1 0; min-width: 0; }
        .afil-section__title {
            font-family: moonGetHeavy, Arial, sans-serif; text-transform: uppercase;
            letter-spacing: 1px; font-size: 13px; color: #fff; background: #ff8900;
            border: 2px solid #000; border-radius: 8px; padding: 5px 12px;
            text-shadow: 1px 1px 1px #000; box-shadow: 0px 0px 6px rgba(0,0,0,.35);
        }

        .afil-card {
            position: relative; display: block; text-decoration: none;
            border: 1px solid #000; background: #ffe59b;
            border-radius: 8px; overflow: hidden;
            box-shadow: 0px 0px 2px rgba(0,0,0,.4);
            transition: all 0.25s ease;
        }
        .afil-card:hover { transform: scale(1.05); box-shadow: 0px 0px 10px #000; }
        .afil-card__img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* Almirante: un solo banner cuadrado prominente (ratio fijo, no llena
           toda la altura de la columna). */
        .afil-hermano { display: block; }
        .afil-card--hermano { width: 100%; aspect-ratio: 1.3 / 1; }

        .afil-grid { display: grid; gap: 10px; align-content: start; }
        /* grid FIJO de 2 columnas (auto-fit no rendía columnas y salían cajas
           gigantes a ancho completo). */
        .afil-grid--grande { grid-template-columns: repeat(2, 1fr); }
        .afil-card--grande { aspect-ratio: 11.87 / 9; }

        .afil-grid--pequeno { grid-template-columns: repeat(auto-fill, 55px); justify-content: start; align-content: start; }
        .afil-card--pequeno { width: 55px; height: 55px; }

        .afil-card--vacio {
            display: flex; align-items: center; justify-content: center;
            border-style: dashed; 
        }
        .afil-card--vacio:hover { background: #ff8900; transform: scale(1.01); }
        .afil-slot-plus {
            font-family: Arial, sans-serif; font-size: 24px;
            color: #ff8900; text-shadow: 1px 1px 1px #000; line-height: 1;
        }
        .afil-card--vacio:hover .afil-slot-plus { color: #fff; }

        .afil-empty {
            padding: 18px; text-align: center; border: 2px dashed #000;
            border-radius: 8px; background: #fcecd2; font-size: 13px;
            font-family: InterRegular, sans-serif; color: #3b1300;
        }

        @media (max-width: 640px) {
            .afil-columns { flex-direction: column; gap: 22px; }
            .afil-columns > .afil-col { flex: 0 0 auto; }
            .afil-card--hermano { aspect-ratio: 16 / 9; }
        }
        </style>';
    }
}

/**
 * HTML completo de la sección para el índice (hermano + grande + pequeño en 3
 * columnas de igual alto). Aplica tope duro con array_slice.
 */
if (!function_exists('op_afiliados_index_html')) {
    function op_afiliados_index_html() {
        global $db, $mybb;
        $bburl = isset($mybb->settings['bburl']) ? $mybb->settings['bburl'] : '';

        $filas = array('hermano' => array(), 'grande' => array(), 'pequeno' => array());
        $q = $db->query("SELECT * FROM `mybb_op_afiliados` WHERE activo=1 ORDER BY tipo, orden, id");
        while ($r = $db->fetch_array($q)) {
            if (isset($filas[$r['tipo']])) { $filas[$r['tipo']][] = $r; }
        }

        // hermano: 1 solo espacio.
        if (!empty($filas['hermano'])) {
            $hermano_html = op_afil_card_html($filas['hermano'][0], 'hermano');
        } else {
            $hermano_html = op_afil_slot_vacio_html('hermano', $bburl);
        }

        // grande: tope duro.
        $grande_visibles = array_slice($filas['grande'], 0, OP_AFILIADOS_SLOTS_GRANDE);
        $grandes_html = '';
        foreach ($grande_visibles as $a) { $grandes_html .= op_afil_card_html($a, 'grande'); }
        $faltan_grande = max(0, OP_AFILIADOS_SLOTS_GRANDE - count($grande_visibles));
        for ($i = 0; $i < $faltan_grande; $i++) { $grandes_html .= op_afil_slot_vacio_html('grande', $bburl); }

        // pequeno: tope duro.
        $pequeno_visibles = array_slice($filas['pequeno'], 0, OP_AFILIADOS_SLOTS_PEQUENO);
        $pequenos_html = '';
        foreach ($pequeno_visibles as $a) { $pequenos_html .= op_afil_card_html($a, 'pequeno'); }
        $faltan_pequeno = max(0, OP_AFILIADOS_SLOTS_PEQUENO - count($pequeno_visibles));
        for ($i = 0; $i < $faltan_pequeno; $i++) { $pequenos_html .= op_afil_slot_vacio_html('pequeno', $bburl); }

        return op_afiliados_css() . '
        <div class="afil-wrap">
          <div class="afil-columns">
            <section class="afil-section afil-col afil-col--hermano">
              <div class="afil-hermano">' . $hermano_html . '</div>
            </section>
            <section class="afil-section afil-col afil-col--grande">
              <div class="afil-grid afil-grid--grande">' . $grandes_html . '</div>
            </section>
            <section class="afil-section afil-col afil-col--pequeno">
              <div class="afil-grid afil-grid--pequeno">' . $pequenos_html . '</div>
            </section>
          </div>
        </div>
        ';
    }
}

/**
 * Rate-limit por IP para el formulario público sin login: máximo 1 solicitud
 * cada $minutos. Limpia filas viejas (>1 día).
 */
if (!function_exists('op_afiliados_rate_limit_ok')) {
    function op_afiliados_rate_limit_ok($ip, $minutos = 10) {
        global $db;
        $ip_esc = $db->escape_string($ip);
        $minutos = (int) $minutos;

        $db->query("DELETE FROM `mybb_op_afiliados_rate_limit` WHERE tiempo < DATE_SUB(NOW(), INTERVAL 1 DAY)");

        $q = $db->query("SELECT id FROM `mybb_op_afiliados_rate_limit` WHERE ip='$ip_esc' AND tiempo > DATE_SUB(NOW(), INTERVAL $minutos MINUTE) LIMIT 1");
        if ($db->fetch_array($q)) { return false; }

        $db->query("INSERT INTO `mybb_op_afiliados_rate_limit` (`ip`) VALUES ('$ip_esc')");
        return true;
    }
}
