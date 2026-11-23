<?php
/**
 * Catalogo de tipos de notificacion (sistema global de notificaciones, issue #2).
 *
 * Cada tipo se declara una sola vez, como un arreglo:
 *   categoria   aventuras | ficha | recompensas | tecnicas | peticiones | temas
 *   origen      gestion | usuario | sistema
 *   importancia normal | importante
 *   agrupable   true si varios eventos se resumen en una sola fila (con cantidad)
 *   icono       clase de Font Awesome
 *   texto       function ($datos, $cantidad): texto PLANO (sin HTML) construido con los datos guardados
 *   url         function ($datos): ruta interna o '' si no hay enlace
 *
 * Aqui solo hay logica pura (sin base de datos). Los datos guardados llevan solo lo necesario para escribir el texto
 * (nombres, cantidades, motivo), nunca HTML. El texto se construye al mostrar y se escapa UNA vez en opg_notif_texto().
 *
 * Claves de datos que usan los textos (todas opcionales):
 *   peticion_id, tema (titulo del tema de la aventura), isla, tier, actor (quien hizo la accion), narrador, motivo, dias,
 *   tid (tema del foro), nombre (personaje relacionado en la Bitacora).
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/** Texto de los tipos que no estan en el catalogo (requisito 25). */
define('OPG_NOTIF_TEXTO_GENERICO', 'Actualización');

/** Icono de los tipos que no estan en el catalogo. */
define('OPG_NOTIF_ICONO_GENERICO', 'fa-bell');

// ── Ayudas internas para redactar (devuelven texto plano) ───────────────────────────────────────────────────────────────

function opg_notif_dato($datos, $clave, $defecto = '')
{
    if (!is_array($datos) || !isset($datos[$clave]) || !is_scalar($datos[$clave])) {
        return $defecto;
    }
    $valor = trim((string) $datos[$clave]);
    return $valor === '' ? $defecto : $valor;
}

/** «Tema» de la aventura, o "la aventura #id" si no hay titulo, o "una aventura" si tampoco hay id. */
function opg_notif_nombre_aventura($d)
{
    $tema = opg_notif_dato($d, 'tema');
    if ($tema !== '') {
        return '«' . $tema . '»';
    }
    $id = (int) opg_notif_dato($d, 'peticion_id', 0);
    return $id > 0 ? 'la aventura #' . $id : 'una aventura';
}

/** Quien hizo la accion; "El staff" cuando no se guardo el nombre (igual que las novedades actuales del Tablon). */
function opg_notif_actor($d, $defecto = 'El staff')
{
    return opg_notif_dato($d, 'actor', $defecto);
}

/** Complemento "(Isla · Tier N)" si hay datos de lugar. */
function opg_notif_lugar($d)
{
    $partes = array();
    $isla = opg_notif_dato($d, 'isla');
    if ($isla !== '') {
        $partes[] = $isla;
    }
    $tier = (int) opg_notif_dato($d, 'tier', 0);
    if ($tier > 0) {
        $partes[] = 'Tier ' . $tier;
    }
    return $partes ? ' (' . implode(' · ', $partes) . ')' : '';
}

function opg_notif_motivo($d)
{
    $motivo = opg_notif_dato($d, 'motivo');
    return $motivo !== '' ? ' Motivo: ' . $motivo : '';
}

/** Nombre del tema del foro para la Bitacora: «Titulo» o "el tema #tid". */
function opg_notif_nombre_tema($d)
{
    $tema = opg_notif_dato($d, 'tema');
    if ($tema !== '') {
        return '«' . $tema . '»';
    }
    $tid = (int) opg_notif_dato($d, 'tid', 0);
    return $tid > 0 ? 'el tema #' . $tid : 'un tema';
}

/** Numero con separador de miles "." y, si tiene decimales, coma y hasta dos decimales: 1.000.000 o 33,5. */
function opg_notif_numero($valor)
{
    $num = is_numeric($valor) ? round((float) $valor, 2) : 0.0;
    if ($num == floor($num)) {
        return number_format($num, 0, ',', '.');
    }
    return rtrim(rtrim(number_format($num, 2, ',', '.'), '0'), ',');
}

/** Une una lista de textos: "a", "a y b", "a, b y c". */
function opg_notif_enumerar($partes)
{
    $partes = array_values($partes);
    $ultimo = array_pop($partes);
    return $partes ? implode(', ', $partes) . ' y ' . $ultimo : (string) $ultimo;
}

/**
 * Resumen de una recompensa (issue #20): solo los importes mayores que cero, en orden XP, Nikas, Berries, Kuros y
 * reputacion, mas los objetos si los hay. Por ejemplo "50 XP, 5 Nikas y 1.000.000 Berries". '' si no hay nada.
 */
function opg_notif_resumen_importes($d)
{
    $etiquetas = array('xp' => ' XP', 'nikas' => ' Nikas', 'berries' => ' Berries', 'kuros' => ' Kuros', 'reputacion' => ' de reputación');
    $partes = array();
    foreach ($etiquetas as $clave => $etiqueta) {
        $valor = opg_notif_dato($d, $clave, '0');
        if (is_numeric($valor) && (float) $valor > 0) {
            $partes[] = opg_notif_numero($valor) . $etiqueta;
        }
    }
    $objetos = opg_notif_dato($d, 'objetos');
    if ($objetos !== '') {
        $partes[] = $objetos;
    }
    return $partes ? opg_notif_enumerar($partes) : '';
}

// ── Constructores de enlaces (rutas internas relativas a la raiz del foro) ──────────────────────────────────────────────

function opg_notif_url_ficha($d)
{
    return 'op/personaje.php';
}

function opg_notif_url_aventura($d)
{
    $id = (int) opg_notif_dato($d, 'peticion_id', 0);
    return $id > 0 ? 'op/tablon_de_aventuras.php?id=' . $id : '';
}

function opg_notif_url_tema_nuevo($d)
{
    $tid = (int) opg_notif_dato($d, 'tid', 0);
    return $tid > 0 ? 'showthread.php?tid=' . $tid . '&action=newpost' : '';
}

function opg_notif_url_ofertas($d)
{
    return 'op/tablon_de_aventuras.php?tab=narradores';
}

function opg_notif_url_bitacora($d)
{
    return 'op/bitacora.php';
}

// ── Catalogo ────────────────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Definicion de todos los tipos, por clave. Los textos del Tablon son los mismos que ya ve el usuario
 * (opg_av_v_novedad_fila en lib/aventuras_vistas.php).
 */
function opg_notif_catalogo()
{
    static $catalogo = null;
    if ($catalogo !== null) {
        return $catalogo;
    }

    $catalogo = array(
        // ── Tablon de aventuras: propuestas de narrador ──
        'invitacion_nueva' => array(
            'categoria' => 'aventuras', 'origen' => 'usuario', 'importancia' => 'importante', 'agrupable' => false,
            'icono' => 'fa-envelope-open-text',
            'texto' => function ($d, $n) {
                return opg_notif_actor($d, 'El creador') . ' te invitó a narrar ' . opg_notif_nombre_aventura($d)
                    . '. Acepta o rechaza la invitación en Narradores.' . opg_notif_lugar($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),
        'candidatura_nueva' => array(
            'categoria' => 'aventuras', 'origen' => 'usuario', 'importancia' => 'importante', 'agrupable' => false,
            'icono' => 'fa-hand',
            'texto' => function ($d, $n) {
                return opg_notif_dato($d, 'narrador', 'Un narrador') . ' se ofrece a narrar ' . opg_notif_nombre_aventura($d)
                    . '. Acepta o rechaza su candidatura en Mis aventuras.' . opg_notif_lugar($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),
        'propuesta_aceptada' => array(
            'categoria' => 'aventuras', 'origen' => 'usuario', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-handshake',
            'texto' => function ($d, $n) {
                return 'Se aceptó la propuesta de narrador de ' . opg_notif_nombre_aventura($d) . ' ('
                    . opg_notif_dato($d, 'narrador', 'un narrador') . '). La solicitud pasa a revisión del staff.' . opg_notif_lugar($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),
        'propuesta_rechazada' => array(
            'categoria' => 'aventuras', 'origen' => 'usuario', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-circle-xmark',
            'texto' => function ($d, $n) {
                return opg_notif_actor($d, 'La otra parte') . ' rechazó la propuesta de narrador ('
                    . opg_notif_dato($d, 'narrador', 'un narrador') . ') para ' . opg_notif_nombre_aventura($d) . '.' . opg_notif_lugar($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),
        'propuesta_retirada' => array(
            'categoria' => 'aventuras', 'origen' => 'usuario', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-rotate-left',
            'texto' => function ($d, $n) {
                return opg_notif_actor($d, 'Quien la envió') . ' retiró su propuesta de narrador ('
                    . opg_notif_dato($d, 'narrador', 'un narrador') . ') para ' . opg_notif_nombre_aventura($d) . '.' . opg_notif_lugar($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),
        'propuesta_cancelada' => array(
            'categoria' => 'aventuras', 'origen' => 'usuario', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-ban',
            'texto' => function ($d, $n) {
                return 'Se asignó otro narrador a ' . opg_notif_nombre_aventura($d) . ', así que tu propuesta quedó cancelada.' . opg_notif_lugar($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),
        'propuesta_vencida' => array(
            'categoria' => 'aventuras', 'origen' => 'sistema', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-hourglass-end',
            'texto' => function ($d, $n) {
                return 'La propuesta de narrador (' . opg_notif_dato($d, 'narrador', 'un narrador') . ') para '
                    . opg_notif_nombre_aventura($d) . ' venció sin respuesta.' . opg_notif_lugar($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),

        // ── Tablon de aventuras: resolucion del staff y entregas ──
        'aventura_aprobada' => array(
            'categoria' => 'aventuras', 'origen' => 'gestion', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-circle-check',
            'texto' => function ($d, $n) {
                return opg_notif_actor($d) . ' aprobó la solicitud de ' . opg_notif_nombre_aventura($d) . '. Ya puede jugarse.' . opg_notif_lugar($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),
        'aventura_denegada' => array(
            'categoria' => 'aventuras', 'origen' => 'gestion', 'importancia' => 'importante', 'agrupable' => false,
            'icono' => 'fa-circle-xmark',
            'texto' => function ($d, $n) {
                return opg_notif_actor($d) . ' denegó la solicitud de ' . opg_notif_nombre_aventura($d)
                    . '. El creador puede corregirla y reenviarla desde el tablón.' . opg_notif_lugar($d) . opg_notif_motivo($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),
        'narrador_salio' => array(
            'categoria' => 'aventuras', 'origen' => 'usuario', 'importancia' => 'importante', 'agrupable' => false,
            'icono' => 'fa-person-walking-arrow-right',
            'texto' => function ($d, $n) {
                return opg_notif_actor($d) . ' dejó de narrar ' . opg_notif_nombre_aventura($d)
                    . '. La solicitud vuelve a buscar narrador.' . opg_notif_lugar($d) . opg_notif_motivo($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),
        'entrega_enviada' => array(
            'categoria' => 'aventuras', 'origen' => 'usuario', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-paper-plane',
            'texto' => function ($d, $n) {
                return opg_notif_actor($d, 'El narrador') . ' envió la entrega de ' . opg_notif_nombre_aventura($d)
                    . ' para su revisión.' . opg_notif_lugar($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),
        'entrega_rechazada' => array(
            'categoria' => 'aventuras', 'origen' => 'gestion', 'importancia' => 'importante', 'agrupable' => false,
            'icono' => 'fa-rotate-left',
            'texto' => function ($d, $n) {
                return opg_notif_actor($d) . ' rechazó la entrega de ' . opg_notif_nombre_aventura($d)
                    . '. Corrígela y vuelve a enviarla desde el tablón.' . opg_notif_lugar($d) . opg_notif_motivo($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),
        'recompensas_entregadas' => array(
            'categoria' => 'recompensas', 'origen' => 'gestion', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-gift',
            'texto' => function ($d, $n) {
                return opg_notif_actor($d) . ' entregó las recompensas de ' . opg_notif_nombre_aventura($d) . '. Revisa tu ficha.' . opg_notif_lugar($d);
            },
            'url' => 'opg_notif_url_aventura',
        ),
        'oferta_por_vencer' => array(
            'categoria' => 'aventuras', 'origen' => 'sistema', 'importancia' => 'importante', 'agrupable' => false,
            'icono' => 'fa-hourglass-half',
            'texto' => function ($d, $n) {
                $dias = (int) opg_notif_dato($d, 'dias', 3);
                return 'Tu oferta como narrador vence en ' . ($dias > 0 ? $dias : 3) . ' días. Renuévala para seguir apareciendo.';
            },
            'url' => 'opg_notif_url_ofertas',
        ),

        // ── Recompensas y salarios entregados por el staff (issue #20) ──
        'recompensa_entregada' => array(
            'categoria' => 'recompensas', 'origen' => 'gestion', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-gift',
            'texto' => function ($d, $n) {
                $resumen = opg_notif_resumen_importes($d);
                $concepto = opg_notif_dato($d, 'concepto');
                $tid = (int) opg_notif_dato($d, 'tid', 0);
                return 'Recibiste ' . ($resumen !== '' ? $resumen : 'una recompensa')
                    . ($concepto !== '' ? ' por ' . $concepto : '')
                    . ($tid > 0 ? ' (tema #' . $tid . ')' : '') . '. Revisa tu ficha.';
            },
            'url' => 'opg_notif_url_ficha',
        ),
        'salario_recibido' => array(
            'categoria' => 'recompensas', 'origen' => 'gestion', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-sack-dollar',
            'texto' => function ($d, $n) {
                $monto = opg_notif_dato($d, 'monto', '0');
                $moneda = opg_notif_dato($d, 'moneda');
                $concepto = opg_notif_dato($d, 'concepto');
                $importe = is_numeric($monto) && (float) $monto > 0 && $moneda !== ''
                    ? opg_notif_numero($monto) . ' ' . $moneda . ' de salario'
                    : 'tu salario';
                return 'Recibiste ' . $importe . ($concepto !== '' ? ' (' . $concepto . ')' : '') . '.';
            },
            'url' => 'opg_notif_url_ficha',
        ),

        // ── Bitacora de temas ──
        'bitacora_post' => array(
            'categoria' => 'temas', 'origen' => 'usuario', 'importancia' => 'normal', 'agrupable' => true,
            'icono' => 'fa-comment',
            'texto' => function ($d, $n) {
                $n = max(1, (int) $n);
                return $n > 1
                    ? $n . ' publicaciones nuevas en ' . opg_notif_nombre_tema($d) . '.'
                    : 'Nueva publicación en ' . opg_notif_nombre_tema($d) . '.';
            },
            'url' => 'opg_notif_url_tema_nuevo',
        ),
        'bitacora_ronda_iniciada' => array(
            'categoria' => 'temas', 'origen' => 'usuario', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-dice',
            'texto' => function ($d, $n) {
                return opg_notif_dato($d, 'nombre', 'El narrador') . ' inició una ronda narrada en ' . opg_notif_nombre_tema($d) . '.';
            },
            'url' => 'opg_notif_url_bitacora',
        ),
        'bitacora_narrador_asignado' => array(
            'categoria' => 'temas', 'origen' => 'usuario', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-user-pen',
            'texto' => function ($d, $n) {
                return opg_notif_dato($d, 'nombre', 'Un personaje') . ' fue establecido como narrador de ' . opg_notif_nombre_tema($d) . '.';
            },
            'url' => 'opg_notif_url_bitacora',
        ),
        'bitacora_narrador_cambiado' => array(
            'categoria' => 'temas', 'origen' => 'usuario', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-user-pen',
            'texto' => function ($d, $n) {
                return 'El narrador de ' . opg_notif_nombre_tema($d) . ' se cambió a ' . opg_notif_dato($d, 'nombre', 'otro personaje') . '.';
            },
            'url' => 'opg_notif_url_bitacora',
        ),
        'bitacora_narrador_retirado' => array(
            'categoria' => 'temas', 'origen' => 'usuario', 'importancia' => 'normal', 'agrupable' => false,
            'icono' => 'fa-user-minus',
            'texto' => function ($d, $n) {
                return 'Se retiró a ' . opg_notif_dato($d, 'nombre', 'un personaje') . ' como narrador de ' . opg_notif_nombre_tema($d) . '.';
            },
            'url' => 'opg_notif_url_bitacora',
        ),
    );
    return $catalogo;
}

/** Definicion de un tipo, o null si no esta en el catalogo. */
function opg_notif_tipo($tipo)
{
    $catalogo = opg_notif_catalogo();
    return is_string($tipo) && isset($catalogo[$tipo]) ? $catalogo[$tipo] : null;
}

function opg_notif_icono($tipo)
{
    $def = opg_notif_tipo($tipo);
    return $def ? $def['icono'] : OPG_NOTIF_ICONO_GENERICO;
}

function opg_notif_importancia($tipo)
{
    $def = opg_notif_tipo($tipo);
    return $def ? $def['importancia'] : 'normal';
}

/**
 * Texto de una notificacion, ya ESCAPADO con htmlspecialchars (seguro para imprimir en HTML).
 * Un tipo desconocido devuelve "Actualización". $cantidad solo importa en los tipos agrupables.
 */
function opg_notif_texto($tipo, $datos, $cantidad = 1)
{
    $def = opg_notif_tipo($tipo);
    if (!$def) {
        return OPG_NOTIF_TEXTO_GENERICO;
    }
    $datos = is_array($datos) ? $datos : array();
    $texto = (string) call_user_func($def['texto'], $datos, $def['agrupable'] ? (int) $cantidad : 1);
    return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Enlace de una notificacion a partir de sus datos, o '' si el tipo es desconocido, no hay datos suficientes
 * o el resultado no es una ruta interna. (Un enlace guardado en la fila prevalece sobre este: lo decide quien muestra.)
 */
function opg_notif_url($tipo, $datos)
{
    $def = opg_notif_tipo($tipo);
    if (!$def) {
        return '';
    }
    $url = call_user_func($def['url'], is_array($datos) ? $datos : array());
    return opg_notif_url_interna($url) ? $url : '';
}

/**
 * Indica si un enlace es una ruta interna: relativa ("op/x.php?id=1") o absoluta del sitio ("/op/x.php").
 *
 * Lista blanca estricta sobre la cadena ya normalizada: se decodifican las entidades HTML (con "&amp;#106;" o "&#x6A;"
 * alguien podria esconder un esquema que el navegador leeria como "javascript:") hasta que la cadena no cambie, y se valida
 * el resultado. Solo se aceptan letras, numeros y "_ - . / ~ %" en la ruta, con una consulta opcional ("? = & + [ ] ,") y un
 * ancla opcional. Sin ":" no hay esquema posible, y sin espacios, controles, comillas, "<", ">" ni barras invertidas;
 * tampoco se acepta "//" al inicio. Cualquier otra cosa se rechaza.
 *
 * Como se guarda y se escapa el enlace: el catalogo genera la URL CRUDA ("showthread.php?tid=7&action=newpost") y asi se
 * guarda; se escapa una sola vez al imprimirla en un atributo, con htmlspecialchars(..., ENT_QUOTES) y no con
 * htmlspecialchars_uni (que deja pasar las entidades numericas). Si llegara ya con "&amp;" tambien es valida, porque se
 * decodifica antes de comprobarla.
 */
function opg_notif_url_interna($url)
{
    if (!is_string($url) || $url === '' || strlen($url) > 500) {
        return false;
    }
    $normal = $url;
    for ($i = 0; $i < 10; $i++) {
        $siguiente = html_entity_decode($normal, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($siguiente === $normal) {
            break;
        }
        $normal = $siguiente;
    }
    if ($i === 10 || strpos($normal, '//') === 0) {
        return false;
    }
    return preg_match('@^/?[A-Za-z0-9_.%~-]+(?:/[A-Za-z0-9_.%~-]*)*(?:\?[A-Za-z0-9_.%~/=&+\[\],-]*)?(?:#[A-Za-z0-9_-]*)?$@D', $normal) === 1;
}
