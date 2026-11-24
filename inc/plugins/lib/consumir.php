<?php
/**
 * BBCode [consumir=OBJETO_ID]: logica compartida del plugin BBCustom_consumir (issue #32).
 *
 * - Buscar las etiquetas de un mensaje (sin contar las que estan dentro de [quote], [code] o [php]) y decidir cuales son
 *   nuevas en una edicion (las que sobran respecto al mensaje anterior: una etiqueta ya reemplazada o una cruda que ya
 *   estaba no vuelve a consumir).
 * - Consumir una unidad de forma atomica (UPDATE ... WHERE cantidad >= 1 y affected_rows), devolverla si falla un paso
 *   posterior, guardar el HTML del objeto en mybb_op_consumir y dejar auditoria.
 * - Al mostrar un post, cambiar [consumir=ID] cruda y [consumibleinvalido=ID] por una nota discreta en vez de codigo.
 *
 * Las funciones puras no tocan la base de datos (tests/consumir/consumir.php). Las que reciben $db usan solo la interfaz
 * del $db de MyBB (query, write_query, fetch_array, escape_string, affected_rows) y se prueban con MySQL real
 * (tests/consumir/*_db.php).
 *
 * Los mensajes que entran y salen de aqui son el texto tal cual lo escribio el usuario (sin escapar para SQL): quien
 * llama escapa una sola vez al guardarlo.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

require_once __DIR__ . '/spoiler.php';

const OPG_CONSUMIR_PATRON = '#\[consumir=([^\[\]\r\n]{1,255})\]#i';
const OPG_CONSUMIR_AUDITORIA = '[Consumir]';

// ── Logica pura ──────────────────────────────────────────────────────────────────────────────────────────────────────

/** Clave para comparar codigos de objeto (MySQL los compara sin distinguir mayusculas). */
function opg_consumir_clave($id)
{
    return strtoupper(trim((string)$id));
}

/**
 * Rangos [inicio, fin) en bytes de los bloques [quote]...[/quote] (con anidamiento), [code]...[/code] y [php]...[/php].
 * Como en el parser de MyBB, el codigo se busca primero: un [/quote] dentro de [code] no cierra la cita. Un bloque sin
 * cerrar no protege nada (MyBB tampoco lo muestra como cita o codigo).
 */
function opg_consumir_rangos_protegidos($mensaje)
{
    $rangos = array();
    if (!preg_match_all('#\[(/?)(quote|code|php)(?:=[^\]]*)?\]#i', $mensaje, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return $rangos;
    }
    $profundidad = 0;   // citas abiertas
    $inicio = null;     // inicio del rango exterior abierto
    $codigo = null;     // 'code' o 'php' si estamos dentro de un bloque de codigo
    $inicio_codigo = null;
    foreach ($m as $t) {
        $offset = $t[0][1];
        $fin = $offset + strlen($t[0][0]);
        $cierre = $t[1][0] === '/';
        $tipo = strtolower($t[2][0]);
        if ($codigo !== null) {
            if ($cierre && $tipo === $codigo) {
                $codigo = null;
                if ($profundidad === 0) {
                    $rangos[] = array($inicio_codigo, $fin);
                }
            }
            continue;
        }
        if ($tipo === 'quote') {
            if (!$cierre) {
                if ($profundidad === 0) {
                    $inicio = $offset;
                }
                $profundidad++;
            } elseif ($profundidad > 0) {
                $profundidad--;
                if ($profundidad === 0) {
                    $rangos[] = array($inicio, $fin);
                }
            }
        } elseif (!$cierre) {
            $codigo = $tipo;
            $inicio_codigo = $offset;
        }
    }
    return $rangos;
}

/**
 * Etiquetas [consumir=ID] del mensaje que no estan dentro de una cita o de un bloque de codigo, en orden.
 * Cada una: array('inicio' => byte, 'largo' => bytes, 'id' => codigo sin espacios alrededor).
 */
function opg_consumir_etiquetas($mensaje)
{
    $mensaje = (string)$mensaje;
    $salida = array();
    if (stripos($mensaje, '[consumir=') === false || !preg_match_all(OPG_CONSUMIR_PATRON, $mensaje, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return $salida;
    }
    $rangos = opg_consumir_rangos_protegidos($mensaje);
    foreach ($m as $t) {
        $id = trim($t[1][0]);
        if ($id === '') {
            continue;
        }
        $offset = $t[0][1];
        foreach ($rangos as $r) {
            if ($offset >= $r[0] && $offset < $r[1]) {
                continue 2;
            }
        }
        $salida[] = array('inicio' => $offset, 'largo' => strlen($t[0][0]), 'id' => $id);
    }
    return $salida;
}

/**
 * Etiquetas de $mensaje que hay que procesar: las que sobran, por codigo, respecto a $mensaje_anterior ('' al crear).
 * Asi, al editar, una etiqueta cruda que ya estaba (un post viejo, o una que no se pudo consumir) no consume, y la que
 * ya se reemplazo por [consumido=N] tampoco: solo cuentan las que el usuario anade.
 */
function opg_consumir_etiquetas_nuevas($mensaje, $mensaje_anterior)
{
    $previas = array();
    foreach (opg_consumir_etiquetas($mensaje_anterior) as $e) {
        $k = opg_consumir_clave($e['id']);
        $previas[$k] = isset($previas[$k]) ? $previas[$k] + 1 : 1;
    }
    $nuevas = array();
    foreach (opg_consumir_etiquetas($mensaje) as $e) {
        $k = opg_consumir_clave($e['id']);
        if (!empty($previas[$k])) {
            $previas[$k]--;
            continue;
        }
        $nuevas[] = $e;
    }
    return $nuevas;
}

/** Aplica reemplazos por posicion (array('inicio','largo','texto')), de atras hacia delante para no mover los demas. */
function opg_consumir_reemplazar($mensaje, array $reemplazos)
{
    usort($reemplazos, function ($a, $b) { return $b['inicio'] - $a['inicio']; });
    foreach ($reemplazos as $r) {
        $mensaje = substr_replace($mensaje, $r['texto'], $r['inicio'], $r['largo']);
    }
    return $mensaje;
}

function opg_consumir_e($texto)
{
    return htmlspecialchars((string)$texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Nota discreta que sustituye a una etiqueta que no se convirtio en objeto consumido.
 * $tipo: 'sin_consumir' ([consumir=ID] cruda) o 'invalido' ([consumibleinvalido=ID]). $nombre: nombre del objeto o, si
 * no existe, su codigo (texto plano; aqui se escapa). Estilo en linea con tokens de opg-tokens.css (docs/style.md §7) y
 * su valor como respaldo; sin clases propias. Gris de "bloqueado" para la cruda y borde rojo de error para la invalida.
 */
function opg_consumir_nota_html($tipo, $nombre)
{
    $nombre = opg_consumir_e($nombre);
    if ($tipo === 'invalido') {
        $texto = "No se pudo consumir «{$nombre}»: no estaba en el inventario";
        $borde = 'var(--opg-rojo-error, #dc3545)';
    } else {
        $texto = "Objeto sin consumir: «{$nombre}»";
        $borde = 'var(--opg-gris-bloqueado, #71706f)';
    }
    return '<span role="note" style="display:inline-block;max-width:100%;box-sizing:border-box;vertical-align:baseline;'
        . 'margin:0 2px;padding:1px var(--opg-espacio-2, 8px);'
        . 'border:1px dashed ' . $borde . ';border-left:3px solid ' . $borde . ';border-radius:var(--opg-radio-sm, 6px);'
        . 'background:var(--opg-crema-clara, #fff3e0);color:var(--opg-gris-texto, #5e5e5e);'
        . 'font-family:var(--opg-fuente-cuerpo, sans-serif);font-size:12px;font-style:italic;line-height:1.5;'
        . 'text-align:left;white-space:normal;overflow-wrap:anywhere;">' . $texto . '</span>';
}

/** Patron de las etiquetas que se convierten en nota, sobre el HTML ya procesado por el parser de MyBB. */
const OPG_CONSUMIR_PATRON_NOTA = '#\[(consumir|consumibleinvalido)=([^\[\]<>\r\n]{1,255})\]#i';

/** Aplica $funcion a los trozos del HTML que no estan dentro de <code>...</code> (bloques [code] y [php] ya renderizados). */
function opg_consumir_fuera_de_codigo($html, $funcion)
{
    $trozos = preg_split('#(<code\b[^>]*>.*?</code>)#si', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($trozos as $i => $trozo) {
        if ($i % 2 === 0) {
            $trozos[$i] = $funcion($trozo);
        }
    }
    return implode('', $trozos);
}

/** Codigo (texto plano) que lleva una etiqueta del HTML: el parser escapa &, comillas y < >. */
function opg_consumir_id_de_html($id_html)
{
    return trim(html_entity_decode($id_html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/** Codigos de las etiquetas [consumir=] y [consumibleinvalido=] del HTML de un post (fuera de los bloques de codigo). */
function opg_consumir_ids_en_html($html)
{
    $ids = array();
    if (stripos($html, '[consumir=') === false && stripos($html, '[consumibleinvalido=') === false) {
        return $ids;
    }
    opg_consumir_fuera_de_codigo($html, function ($trozo) use (&$ids) {
        if (preg_match_all(OPG_CONSUMIR_PATRON_NOTA, $trozo, $m)) {
            foreach ($m[2] as $id) {
                $id = opg_consumir_id_de_html($id);
                if ($id !== '' && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }
        return $trozo;
    });
    return $ids;
}

/**
 * Sustituye en el HTML de un post las etiquetas [consumir=ID] y [consumibleinvalido=ID] por su nota.
 * $nombres: clave (opg_consumir_clave) o codigo => nombre del objeto; si falta, la nota muestra el codigo.
 */
function opg_consumir_renderizar_notas($html, array $nombres)
{
    if (stripos($html, '[consumir=') === false && stripos($html, '[consumibleinvalido=') === false) {
        return $html;
    }
    $por_clave = array();
    foreach ($nombres as $id => $nombre) {
        $por_clave[opg_consumir_clave($id)] = $nombre;
    }
    return opg_consumir_fuera_de_codigo($html, function ($trozo) use ($por_clave) {
        return preg_replace_callback(OPG_CONSUMIR_PATRON_NOTA, function ($m) use ($por_clave) {
            $id = opg_consumir_id_de_html($m[2]);
            if ($id === '') {
                return $m[0];
            }
            $k = opg_consumir_clave($id);
            $nombre = isset($por_clave[$k]) && trim((string)$por_clave[$k]) !== '' ? $por_clave[$k] : $id;
            return opg_consumir_nota_html(strtolower($m[1]) === 'consumibleinvalido' ? 'invalido' : 'sin_consumir', $nombre);
        }, $trozo);
    });
}

/** URL de imagen aceptable en un atributo: ruta del sitio o http(s). Si no, la del catalogo. */
function opg_consumir_url_imagen($url, $por_defecto)
{
    $url = trim((string)$url);
    if ($url !== '' && preg_match('#^(https?://|/)#i', $url)) {
        return $url;
    }
    return $por_defecto;
}

/** Texto de un campo del catalogo para el HTML: escapado y con saltos de linea. */
function opg_consumir_campo($texto)
{
    return nl2br(opg_consumir_e($texto));
}

/**
 * HTML del objeto consumido que se guarda en mybb_op_consumir.content (mismo aspecto que el plugin original). Todo dato
 * del catalogo y del usuario va escapado: nombre, descripcion, requisitos, escalado, dano, efecto, imagen y usuario.
 */
function opg_consumir_html_objeto($objeto, $username, $cantidad_restante)
{
    $objeto_id = opg_consumir_e($objeto['objeto_id']);
    $nombre_texto = (string)$objeto['nombre'];
    $nombre = opg_consumir_e($nombre_texto);
    $subcategoria_texto = (string)$objeto['subcategoria'];
    $subcategoria = opg_consumir_e($subcategoria_texto);
    $tier = (int)$objeto['tier'];
    $descripcion = opg_consumir_campo($objeto['descripcion']);
    $usuario = opg_consumir_e($username);
    $cantidad_restante = (int)$cantidad_restante;

    // Color segun tier
    $tier_colors = array(
        1 => '#808080', // Gris
        2 => '#4dfe45', // Verde
        3 => '#457bfe', // Azul
        4 => '#cf44ff', // Purpura
        5 => '#febb46', // Dorado
    );
    $color_tier = isset($tier_colors[$tier]) ? $tier_colors[$tier] : '#faa500'; // Naranja por defecto

    // Imagen: la propia del objeto o la del catalogo de iconos
    $subcategoria_lower = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $subcategoria_texto)));
    $img_type = ($subcategoria_lower == 'tecnicas') ? 'gif' : 'jpg';
    $imagen_por_defecto = "/images/op/iconos/{$subcategoria_lower}_" . (int)$objeto['imagen_id'] . "_One_Piece_Gaiden_Foro_Rol.{$img_type}";
    $imagen_url = opg_consumir_e(opg_consumir_url_imagen(isset($objeto['imagen_avatar']) ? $objeto['imagen_avatar'] : '', $imagen_por_defecto));

    // Secciones opcionales
    $requisitos_html = trim((string)$objeto['requisitos']) !== '' ? "<div style='padding: 8px; border-bottom: 1px solid #444; background: #222;'><strong>Requisitos:</strong> " . opg_consumir_campo($objeto['requisitos']) . "</div>" : '';
    $escalado_html = trim((string)$objeto['escalado']) !== '' ? "<div style='padding: 8px; border-bottom: 1px solid #444; background: #222;'><strong>Escalado:</strong> " . opg_consumir_campo($objeto['escalado']) . "</div>" : '';
    $dano_html = trim((string)$objeto['dano']) !== '' ? "<div style='padding: 8px; border-bottom: 1px solid #444; background: #222;'><strong>Daño:</strong> " . opg_consumir_campo($objeto['dano']) . "</div>" : '';
    $efecto_html = trim((string)$objeto['efecto']) !== '' ? "<div style='padding: 8px; background: #252525; border-top: 2px solid {$color_tier};'><strong>Efecto:</strong> " . opg_consumir_campo($objeto['efecto']) . "</div>" : '';

    // 1. Bloque de la imagen (izquierda)
    $objeto_visual = "
        <div style='text-align: center; flex: 0 0 auto; min-width: 150px; margin: 10px;'>
            <img src='{$imagen_url}' alt='{$nombre}' style='width: 120px; height: 120px; border-radius: 10px; border: 3px solid {$color_tier}; box-shadow: 0 4px 8px rgba(0,0,0,0.4);' />
            <div style='margin-top: 10px; font-weight: bold; color: {$color_tier}; text-shadow: 1px 1px 2px black;'>{$nombre}</div>
        </div>
    ";

    // 2. Bloque de la tabla de detalles (derecha)
    $detalles_html = "
        <div style='flex: 1 1 300px; background: #2c3e50; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.3);'>
            <div style='background: {$color_tier}; padding: 10px; text-align: center; font-weight: bold; color: white; text-shadow: 1px 1px 2px black;'>
                {$nombre} ({$objeto_id})
            </div>
            {$requisitos_html}
            {$escalado_html}
            <div style='padding: 10px; background: #1a1a1a; color: #ddd; line-height: 1.4;'>{$descripcion}</div>
            {$dano_html}
            {$efecto_html}
            <div style='background: {$color_tier}; padding: 5px; text-align: center; font-size: 11px; color: white; text-shadow: 1px 1px 2px black;'>
                {$subcategoria} - Tier {$tier}
            </div>
        </div>
    ";

    // 3. Contenedor del spoiler (imagen y detalles con flexbox)
    $tooltip_content = "
        <div style='display: flex; flex-wrap: wrap; align-items: center; justify-content: center; background: #967e95; padding: 15px; border-radius: 5px; border: 2px solid #222;'>
            {$objeto_visual}
            {$detalles_html}
        </div>
    ";

    // 4. Texto general del consumo (fuera del spoiler)
    $info_html = "
        <div style='text-align: center; margin: 15px 0;'>
            <strong style='color: #e74c3c;'>{$usuario}</strong> ha consumido
            <strong style='color: {$color_tier};'>{$nombre}</strong>
            <br />
            <span style='color: #95a5a6;'>Cantidad restante: <strong style='color: white;'>{$cantidad_restante}</strong></span>
        </div>
    ";

    // 5. Spoiler (create_custom_spoiler escapa el titulo: se le pasa el nombre sin escapar)
    $spoiler_html = create_custom_spoiler("{$nombre_texto} ha sido consumido", $tooltip_content, array(
        'bg_color' => '#ca3c78',
        'text_color' => 'white',
        'open' => false
    ));

    return "<hr style='border: 1px solid #444; margin: 20px 0;' />{$info_html}{$spoiler_html}<hr style='border: 1px solid #444; margin: 20px 0;' />";
}

/**
 * Quita un objeto del JSON de equipamiento (ropa, bolsa o el primer espacio que lo lleve).
 * Devuelve true si se modifico.
 */
function opg_consumir_quitar_de_json(&$equipamiento, $objeto_id)
{
    $modificado = false;

    if (isset($equipamiento['ropa']) && $equipamiento['ropa'] === $objeto_id) {
        $equipamiento['ropa'] = '';
        $modificado = true;
    }

    if (!$modificado && isset($equipamiento['bolsa']) && $equipamiento['bolsa'] === $objeto_id) {
        $equipamiento['bolsa'] = '';
        $modificado = true;
    }

    if (!$modificado && isset($equipamiento['espacios']) && is_array($equipamiento['espacios'])) {
        foreach ($equipamiento['espacios'] as $key => $espacio) {
            if (isset($espacio['objetoId']) && $espacio['objetoId'] === $objeto_id) {
                unset($equipamiento['espacios'][$key]);
                $modificado = true;
                break; // Solo la primera ocurrencia
            }
        }
        if ($modificado && !empty($equipamiento['espacios'])) {
            $equipamiento['espacios'] = array_values($equipamiento['espacios']);
        }
    }

    return $modificado;
}

// ── Base de datos ────────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Resta 1 unidad del inventario de forma atomica. Devuelve la cantidad que queda o false si no habia ninguna.
 * La cantidad restante se lee con LAST_INSERT_ID(expr), que es de esta conexion: es exactamente el valor que dejo este
 * UPDATE aunque otro proceso consuma a la vez. Si queda 0, la fila sigue ahi hasta opg_consumir_limpiar_vacio (asi se
 * puede devolver la unidad sin recrearla).
 */
function opg_consumir_descontar($db, $uid, $objeto_id)
{
    $uid = (int)$uid;
    $oid = $db->escape_string($objeto_id);
    $db->write_query("
        UPDATE mybb_op_inventario
        SET cantidad = LAST_INSERT_ID(cantidad - 1)
        WHERE uid = '{$uid}' AND objeto_id = '{$oid}' AND cantidad >= 1
        LIMIT 1
    ");
    if ($db->affected_rows() < 1) {
        return false;
    }
    $fila = $db->fetch_array($db->query("SELECT LAST_INSERT_ID() AS restante"));
    return $fila ? (int)$fila['restante'] : 0;
}

/** Devuelve la unidad descontada (si la fila ya no existe, la vuelve a crear con 1). */
function opg_consumir_devolver($db, $uid, $objeto_id)
{
    $uid = (int)$uid;
    $oid = $db->escape_string($objeto_id);
    $db->write_query("UPDATE mybb_op_inventario SET cantidad = cantidad + 1 WHERE uid = '{$uid}' AND objeto_id = '{$oid}' LIMIT 1", 1);
    if ($db->affected_rows() < 1) {
        $db->write_query("INSERT INTO mybb_op_inventario (objeto_id, uid, cantidad) VALUES ('{$oid}', '{$uid}', 1)
            ON DUPLICATE KEY UPDATE cantidad = cantidad + 1", 1);
    }
}

/** Borra la fila del inventario si se quedo sin unidades (si otro proceso anadio unidades entretanto, no la toca). */
function opg_consumir_limpiar_vacio($db, $uid, $objeto_id)
{
    $uid = (int)$uid;
    $oid = $db->escape_string($objeto_id);
    $db->write_query("DELETE FROM mybb_op_inventario WHERE uid = '{$uid}' AND objeto_id = '{$oid}' AND cantidad <= 0");
}

/** Mayor contador ya usado en el post (0 si ninguno). */
function opg_consumir_mayor_contador($db, $pid)
{
    $pid = (int)$pid;
    $fila = $db->fetch_array($db->query("SELECT MAX(counter) AS mayor FROM mybb_op_consumir WHERE pid = '{$pid}'"));
    return $fila ? (int)$fila['mayor'] : 0;
}

/** Quita el objeto del equipamiento del tema (mybb_op_equipamiento_personaje) y del de la ficha si estaba equipado. */
function opg_consumir_quitar_de_equipamiento($db, $uid, $tid, $objeto_id)
{
    $uid = (int)$uid;
    $tid = (int)$tid;

    $equip_data = $db->fetch_array($db->query("SELECT equipamiento FROM mybb_op_equipamiento_personaje WHERE uid = '{$uid}' AND tid = '{$tid}' LIMIT 1"));
    if ($equip_data && $equip_data['equipamiento']) {
        $equipamiento = json_decode($equip_data['equipamiento'], true);
        if ($equipamiento && opg_consumir_quitar_de_json($equipamiento, $objeto_id)) {
            $json = $db->escape_string(json_encode($equipamiento));
            $db->write_query("UPDATE mybb_op_equipamiento_personaje SET equipamiento = '{$json}' WHERE uid = '{$uid}' AND tid = '{$tid}'");
        }
    }

    // UPDATE sobre mybb_op_fichas: dispara el trigger de instantaneas (solo si de verdad cambia el equipamiento)
    $ficha_data = $db->fetch_array($db->query("SELECT equipamiento FROM mybb_op_fichas WHERE fid = '{$uid}' LIMIT 1"));
    if ($ficha_data && $ficha_data['equipamiento']) {
        $equipamiento_ficha = json_decode($ficha_data['equipamiento'], true);
        if ($equipamiento_ficha && opg_consumir_quitar_de_json($equipamiento_ficha, $objeto_id)) {
            $json = $db->escape_string(json_encode($equipamiento_ficha));
            $db->write_query("UPDATE mybb_op_fichas SET equipamiento = '{$json}' WHERE fid = '{$uid}'");
        }
    }
}

/** Entrada de auditoria del consumo (log_audit de op/functions/op_functions.php no escapa: se escapa aqui). */
function opg_consumir_auditar($db, $uid, $username, $texto)
{
    if (!function_exists('log_audit')) {
        require_once MYBB_ROOT . 'op/functions/op_functions.php';
    }
    log_audit((int)$uid, $db->escape_string($username), $db->escape_string(OPG_CONSUMIR_AUDITORIA), $db->escape_string($texto));
}

/**
 * Consume una unidad de $id para el autor del post y guarda su HTML con el contador $contador.
 * $ctx: uid (autor, dueno del inventario), username, pid, tid y, opcional, editor_uid (quien edita, si no es el autor).
 * Devuelve 'consumido', 'invalido' (no existe el objeto o no tiene unidades) o 'error' (fallo al guardar: la unidad ya
 * se devolvio y la etiqueta no debe marcarse como consumida).
 */
function opg_consumir_uno($db, $id, array $ctx, $contador)
{
    $uid = (int)$ctx['uid'];
    $pid = (int)$ctx['pid'];
    $tid = (int)$ctx['tid'];
    $contador = (int)$contador;

    $objeto = $db->fetch_array($db->query("SELECT * FROM mybb_op_objetos WHERE objeto_id = '" . $db->escape_string($id) . "' LIMIT 1"));
    if (!$objeto) {
        return 'invalido';
    }
    $objeto_id = (string)$objeto['objeto_id'];

    $restante = opg_consumir_descontar($db, $uid, $objeto_id);
    if ($restante === false) {
        return 'invalido';
    }

    $html = opg_consumir_html_objeto($objeto, $ctx['username'], $restante);
    $oid = $db->escape_string($objeto_id);
    $contenido = $db->escape_string($html);
    $ok = $db->write_query("
        INSERT INTO mybb_op_consumir (tid, pid, uid, counter, objeto_id, content)
        VALUES ('{$tid}', '{$pid}', '{$uid}', '{$contador}', '{$oid}', '{$contenido}')
    ", 1);
    if (!$ok) {
        opg_consumir_devolver($db, $uid, $objeto_id);
        error_log("[OPG consumir] No se pudo guardar el consumo de {$objeto_id} (post {$pid}): " . $db->error_string() . '. Se devolvio la unidad.');
        return 'error';
    }

    if ($restante <= 0) {
        opg_consumir_limpiar_vacio($db, $uid, $objeto_id);
    }
    opg_consumir_quitar_de_equipamiento($db, $uid, $tid, $objeto_id);

    $texto = "Consumió {$objeto_id} ({$objeto['nombre']}) en el post {$pid} (tema {$tid}). Cantidad restante: {$restante}";
    if (!empty($ctx['editor_uid']) && (int)$ctx['editor_uid'] !== $uid) {
        $texto .= '. Al editar el post el usuario ' . (int)$ctx['editor_uid'];
    }
    opg_consumir_auditar($db, $uid, $ctx['username'], $texto);

    return 'consumido';
}

/**
 * Procesa las etiquetas nuevas de $mensaje (respecto a $mensaje_anterior, '' al crear) y devuelve el mensaje con los
 * reemplazos: [consumido=N] si se consumio (N sigue al mayor contador del post), [consumibleinvalido=ID] si no habia
 * objeto, y la etiqueta tal cual si fallo el guardado (la unidad ya se devolvio).
 */
function opg_consumir_procesar_mensaje($db, $mensaje, $mensaje_anterior, array $ctx)
{
    $nuevas = opg_consumir_etiquetas_nuevas($mensaje, $mensaje_anterior);
    if (!$nuevas) {
        return $mensaje;
    }
    $contador = opg_consumir_mayor_contador($db, $ctx['pid']);
    $reemplazos = array();
    foreach ($nuevas as $e) {
        $estado = opg_consumir_uno($db, $e['id'], $ctx, $contador + 1);
        if ($estado === 'consumido') {
            $contador++;
            $reemplazos[] = array('inicio' => $e['inicio'], 'largo' => $e['largo'], 'texto' => "[consumido={$contador}]");
        } elseif ($estado === 'invalido') {
            $reemplazos[] = array('inicio' => $e['inicio'], 'largo' => $e['largo'], 'texto' => "[consumibleinvalido={$e['id']}]");
        }
    }
    return opg_consumir_reemplazar($mensaje, $reemplazos);
}

/** Nombres del catalogo para una lista de codigos: codigo => nombre. */
function opg_consumir_nombres($db, array $ids)
{
    $nombres = array();
    if (!$ids) {
        return $nombres;
    }
    $lista = array();
    foreach (array_slice($ids, 0, 100) as $id) {
        $lista[] = "'" . $db->escape_string($id) . "'";
    }
    $q = $db->query("SELECT objeto_id, nombre FROM mybb_op_objetos WHERE objeto_id IN (" . implode(',', $lista) . ")");
    while ($fila = $db->fetch_array($q)) {
        $nombres[$fila['objeto_id']] = $fila['nombre'];
    }
    return $nombres;
}
