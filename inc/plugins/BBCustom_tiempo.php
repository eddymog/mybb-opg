<?php
/**
 * BBCustom Tiempo
 * Procesa BBCode [tiempo=<horas>]
 *
 * Muestra una tarjeta centrada con un contador en vivo (dias, horas, minutos y segundos)
 * hasta que pasen <horas> desde que se publico el post. Ejemplo: [tiempo=72].
 * Al vencer muestra "La ronda ha terminado". Es solo informativo: no depende de
 * los posts que vengan despues. No guarda nada en la base de datos: la hora de
 * referencia es el dateline del post, asi que editar el post no reinicia el contador.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// Despues del hide (10), para que tambien se resuelva el [tiempo] que quede dentro de su contenido.
$plugins->add_hook("postbit", "BBCustom_tiempo_run", 20);
$plugins->add_hook("postbit_prev", "BBCustom_tiempo_run", 20);

const BBCUSTOM_TIEMPO_MAX_HORAS = 8760;   // un ano

function BBCustom_tiempo_info()
{
    return array(
        "name"          => "Tiempo BBCode",
        "description"   => "BBCode [tiempo=horas]: contador en vivo desde que se publica el post (ejemplo: [tiempo=72])",
        "website"       => "",
        "author"        => "Cascabelles",
        "authorsite"    => "",
        "version"       => "1.0",
        "codename"      => "BBCustom_tiempo",
        "compatibility" => "*"
    );
}

function BBCustom_tiempo_activate() {}
function BBCustom_tiempo_deactivate() {}

/** Segundos restantes -> array(dias, horas, minutos, segundos, expirado). */
function BBCustom_tiempo_partes($restante)
{
    $restante = (int) $restante;
    if ($restante <= 0) {
        return array('d' => 0, 'h' => 0, 'm' => 0, 's' => 0, 'expirado' => true);
    }
    return array(
        'd' => intdiv($restante, 86400),
        'h' => intdiv($restante % 86400, 3600),
        'm' => intdiv($restante % 3600, 60),
        's' => $restante % 60,
        'expirado' => false,
    );
}

/** "1 día" / "2 días": etiqueta de una cifra (el JS replica esto). */
function BBCustom_tiempo_etiqueta($clave, $n)
{
    $nombres = array('d' => array('Día', 'Días'), 'h' => array('Hora', 'Horas'), 'm' => array('Minuto', 'Minutos'), 's' => array('Segundo', 'Segundos'));
    return $nombres[$clave][$n === 1 ? 0 : 1];
}

/**
 * HTML del contador (sin saltos de linea, para no romper el formato del post).
 * $fin = timestamp en que vence; $ahora = hora del servidor al renderizar.
 */
function BBCustom_tiempo_html($fin, $ahora)
{
    $p = BBCustom_tiempo_partes($fin - $ahora);
    $html = '<div class="opg-tiempo-caja"><div class="opg-tiempo' . ($p['expirado'] ? ' opg-tiempo-expirado' : '') . '" role="timer"'
        . ' data-fin="' . (int) $fin . '" data-ahora="' . (int) $ahora . '">'
        . '<div class="opg-tiempo-titulo">' . ($p['expirado'] ? 'La ronda ha terminado' : 'Tiempo de la ronda') . '</div>'
        . '<div class="opg-tiempo-cifras">';
    foreach (array('d', 'h', 'm', 's') as $k) {
        $html .= '<div class="opg-tiempo-cifra opg-tiempo-cifra-' . $k . '"><span class="opg-tiempo-num" data-u="' . $k . '">'
            . str_pad((string) $p[$k], 2, '0', STR_PAD_LEFT) . '</span><span class="opg-tiempo-etq" data-e="' . $k . '">'
            . BBCustom_tiempo_etiqueta($k, $p[$k]) . '</span></div>';
    }
    return $html . '</div><div class="opg-tiempo-pie">para postear en esta ronda</div></div></div>';
}

function BBCustom_tiempo_run(&$post)
{
    if (!isset($post['message']) || strpos($post['message'], '[tiempo=') === false) {
        return;
    }
    $ahora = time();
    // Vista previa: el post aun no tiene fecha, cuenta desde ahora.
    $inicio = !empty($post['dateline']) ? (int) $post['dateline'] : $ahora;

    $post['message'] = preg_replace_callback('#\[tiempo=(\d{1,4})\]#i', function ($m) use ($inicio, $ahora) {
        $horas = (int) $m[1];
        if ($horas < 1 || $horas > BBCUSTOM_TIEMPO_MAX_HORAS) {
            return $m[0];   // valor no valido: se deja el texto tal cual
        }
        return BBCustom_tiempo_html($inicio + $horas * 3600, $ahora);
    }, $post['message']);

    // Estilos y script una sola vez por pagina, pegados al primer contador.
    static $assets_emitidos = false;
    if (!$assets_emitidos && strpos($post['message'], 'class="opg-tiempo-caja') !== false) {
        $assets_emitidos = true;
        $post['message'] .= BBCustom_tiempo_assets();
    }
}

/** CSS y JS del contador en vivo. Usa la hora del servidor para no depender del reloj del visitante. */
function BBCustom_tiempo_assets()
{
    return <<<'HTML'
<style>
.opg-tiempo-caja { display: block !important; clear: both; width: 100%; margin: 14px 0; text-align: center !important; }
.opg-tiempo { display: block; box-sizing: border-box; width: 100%; max-width: 440px; margin: 0 auto !important; float: none !important; overflow: hidden; text-align: center; background: var(--opg-crema-clara, #fff3e0); border: 3px solid #000; border-radius: var(--opg-radio-lg, 18px); box-shadow: var(--opg-sombra-offset, 4px 4px 0 #000); }
.opg-tiempo-titulo { padding: 7px 12px 8px; background: var(--opg-naranja, #ff8900); border-bottom: 3px solid #000; color: #fff; font-family: moonGetHeavy, Arial, sans-serif; font-size: 17px; letter-spacing: 2px; text-transform: uppercase; text-shadow: -1px 0 #000, 0 1px #000, 1px 0 #000, 0 -1px #000, 2px 2px 0 #000; }
.opg-tiempo-cifras { display: flex; justify-content: center; gap: 8px; padding: 14px 10px 8px; }
.opg-tiempo-cifra { display: flex; flex-direction: column; align-items: center; flex: 1 1 0; min-width: 0; max-width: 92px; padding: 8px 4px 6px; background: var(--opg-morado-cta, #460c6e); border: 2px solid #000; border-radius: var(--opg-radio-md, 10px); box-shadow: 3px 3px 0 #000; }
.opg-tiempo-cifra-s { background: var(--opg-naranja-accion, #d26500); }
.opg-tiempo-num { color: #fff; font-family: moonGetHeavy, Arial, sans-serif; font-size: 32px; line-height: 1.1; font-variant-numeric: tabular-nums; text-shadow: -1px 0 #000, 0 1px #000, 1px 0 #000, 0 -1px #000, 2px 2px 0 #000; }
.opg-tiempo-etq { margin-top: 2px; color: var(--opg-crema, #ffedd2); font-family: moonGetHeavy, Arial, sans-serif; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; text-shadow: 1px 1px 1px #000; }
.opg-tiempo-pie { padding: 4px 12px 12px; color: var(--opg-marron, #3b1300); font-family: InterRegular, sans-serif; font-size: 12px; }
.opg-tiempo-expirado .opg-tiempo-titulo { background: var(--opg-rojo-error, #dc3545); }
.opg-tiempo-expirado .opg-tiempo-cifra, .opg-tiempo-expirado .opg-tiempo-cifra-s { background: var(--opg-gris-bloqueado, #71706f); }
.opg-tiempo-expirado .opg-tiempo-pie { display: none; }
@media (max-width: 480px) { .opg-tiempo-num { font-size: 24px; } .opg-tiempo-titulo { font-size: 14px; } .opg-tiempo-cifras { gap: 5px; } }
</style>
<script>
(function () {
    if (window.opgTiempoIniciado) { return; }
    window.opgTiempoIniciado = true;
    var NOMBRES = { d: ['Día', 'Días'], h: ['Hora', 'Horas'], m: ['Minuto', 'Minutos'], s: ['Segundo', 'Segundos'] };
    var cargado = Date.now();
    function dos(n) { return (n < 10 ? '0' : '') + n; }
    function actualizar() {
        var nodos = document.querySelectorAll('.opg-tiempo');
        for (var i = 0; i < nodos.length; i++) {
            var n = nodos[i];
            var fin = parseInt(n.getAttribute('data-fin'), 10), ahora = parseInt(n.getAttribute('data-ahora'), 10);
            if (isNaN(fin) || isNaN(ahora)) { continue; }
            var r = Math.max(0, fin - (ahora + Math.floor((Date.now() - cargado) / 1000)));
            var v = { d: Math.floor(r / 86400), h: Math.floor(r % 86400 / 3600), m: Math.floor(r % 3600 / 60), s: r % 60 };
            for (var k in v) {
                var num = n.querySelector('[data-u="' + k + '"]'), etq = n.querySelector('[data-e="' + k + '"]');
                if (num && num.textContent !== dos(v[k])) { num.textContent = dos(v[k]); }
                if (etq) { var t = NOMBRES[k][v[k] === 1 ? 0 : 1]; if (etq.textContent !== t) { etq.textContent = t; } }
            }
            var titulo = n.querySelector('.opg-tiempo-titulo');
            if (titulo) { titulo.textContent = r <= 0 ? 'La ronda ha terminado' : 'Tiempo de la ronda'; }
            n.classList.toggle('opg-tiempo-expirado', r <= 0);
        }
    }
    setInterval(actualizar, 1000);
    actualizar();
})();
</script>
HTML;
}
