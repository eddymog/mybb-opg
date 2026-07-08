<?php
/**
 * BBCustom Tabla
 * Renderiza tablas con BBCode [tabla][tr][td].
 *
 * Sintaxis:
 *   [tabla]  o  [tabla=600]  o  [tabla=80%]
 *   [tr]     o  [tr=50]                        (alto en px)
 *   [td]     o  [td=200]  o  [td=50%]  o  [td=200,center]  o  [td=,center]
 *   [/td][/tr][/tabla]
 *
 * El parámetro de [td] acepta: ancho (px o %) y/o alineación (left/center/right/justify)
 * separados por coma. Ambos son opcionales.
 * Las celdas no tienen borde por defecto. Para añadir bordes a toda la tabla: [tabla=600,borde]
 * o solo [tabla=borde] (ancho 100%).
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$plugins->add_hook("postbit",      "BBCustom_tabla_run");
$plugins->add_hook("postbit_prev", "BBCustom_tabla_run");

function BBCustom_tabla_info()
{
    return [
        "name"          => "Tabla BBCode",
        "description"   => "BBCode [tabla][tr][td] para tablas con tamaños personalizables",
        "website"       => "",
        "author"        => "OPG",
        "authorsite"    => "",
        "version"       => "1.0",
        "codename"      => "BBCustom_tabla",
        "compatibility" => "*"
    ];
}

function BBCustom_tabla_activate() {}
function BBCustom_tabla_deactivate() {}

function BBCustom_tabla_run(&$post)
{
    $message = &$post['message'];
    if (stripos($message, '[tabla') === false) return;

    $message = preg_replace_callback(
        '#\[tabla(?:=([^\]]*))?\](.*?)\[/tabla\]#si',
        'BBCustom_tabla_render',
        $message
    );
}

function BBCustom_tabla_render($matches)
{
    $tabla_param = isset($matches[1]) ? trim($matches[1]) : '';
    $inner       = $matches[2];

    // Parsear parámetros de [tabla]: [tabla=ancho,borde] | [tabla=borde] | [tabla=ancho]
    $tbl_width   = '100%';
    $with_border = false;

    if ($tabla_param !== '') {
        $parts     = array_map('trim', explode(',', $tabla_param, 2));
        $size_part = $parts[0];
        $opt_part  = strtolower($parts[1] ?? '');

        if ($size_part === 'borde') {
            $with_border = true;
        } else {
            if (preg_match('/^\d+%$/', $size_part)) {
                $tbl_width = $size_part;
            } elseif (preg_match('/^\d+$/', $size_part)) {
                $tbl_width = $size_part . 'px';
            }
            if ($opt_part === 'borde') {
                $with_border = true;
            }
        }
    }

    // Eliminar <br> y saltos de línea alrededor de etiquetas estructurales
    $struct = '(?:\[/?tr[^\]]*\]|\[/?td[^\]]*\])';
    $inner  = preg_replace("#(<br\s*/?>|[\r\n]+)\s*($struct)#si", '$2', $inner);
    $inner  = preg_replace("#($struct)\s*(<br\s*/?>|[\r\n]+)#si", '$1', $inner);

    $table_border_css = $with_border ? 'border:1px solid rgba(255,255,255,0.2);' : '';

    // [td=ancho,alineacion_h,alineacion_v,borde] → <td style="...">
    // Parámetros por coma, en cualquier orden:
    //   - dimensión : número (px) o número% (porcentaje)
    //   - horizontal: left · right · justify
    //   - center     : activa text-align:center Y vertical-align:middle
    //                  (si se combina con left/right/justify, el horizontal cede al explícito)
    //   - up / down  : vertical-align:top / bottom (sobreescriben el eje vertical de center)
    //   - borde      : añade borde solo a esta celda
    $inner = preg_replace_callback(
        '#\[td(?:=([^\]]*))?\](.*?)\[/td\]#si',
        function ($m) use ($table_border_css) {
            $param   = isset($m[1]) ? trim($m[1]) : '';
            $content = $m[2];

            $h_align          = '';
            $v_align          = 'top';
            $h_explicitly_set = false;
            $width            = '';
            $cell_border      = $table_border_css;

            if ($param !== '') {
                foreach (array_map('trim', explode(',', $param)) as $part) {
                    $p = strtolower($part);
                    if ($p === 'borde') {
                        $cell_border = 'border:1px solid rgba(255,255,255,0.2);';
                    } elseif ($p === 'up') {
                        $v_align = 'top';
                    } elseif ($p === 'down') {
                        $v_align = 'bottom';
                    } elseif ($p === 'center') {
                        $v_align = 'middle';
                        if (!$h_explicitly_set) { $h_align = 'center'; }
                    } elseif (in_array($p, ['left', 'right', 'justify'])) {
                        $h_align          = $p;
                        $h_explicitly_set = true;
                    } elseif (preg_match('/^\d+%$/', $part)) {
                        $width = "width:$part;";
                    } elseif (preg_match('/^\d+$/', $part)) {
                        $width = "width:{$part}px;";
                    }
                }
            }

            $style  = 'padding:8px 12px;';
            $style .= "vertical-align:$v_align;";
            if ($width)   { $style .= $width; }
            if ($h_align) { $style .= "text-align:$h_align;"; }

            $content = preg_replace('#(<img\b[^>]*?)(?:\s*/?>)#i', '$1 style="max-width:100%;height:auto;">', $content);
            return "<td style=\"$style$cell_border\">$content</td>";
        },
        $inner
    );

    // [tr=alto] → <tr style="height:Xpx">
    $inner = preg_replace_callback(
        '#\[tr(?:=([^\]]*))?\](.*?)\[/tr\]#si',
        function ($m) {
            $param   = isset($m[1]) ? trim($m[1]) : '';
            $content = $m[2];
            $style   = preg_match('/^\d+$/', $param) ? " style=\"height:{$param}px;\"" : '';
            return "<tr$style>$content</tr>";
        },
        $inner
    );

    $tbl_style = "width:$tbl_width;border-collapse:collapse;";

    return '<div style="display:flow-root;margin:10px 0;max-width:100%;overflow-x:auto;">'
         . "<table style=\"$tbl_style;max-width:100%;\"><tbody>$inner</tbody></table>"
         . '</div>';
}
