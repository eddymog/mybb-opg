<?php
/**
 * Tablón de aventuras: la entrega de una aventura terminada.
 *
 * El narrador envía la entrega (tema, resumen y reputación) y el staff la revisa y
 * paga en op/staff/aventuras_recompensas.php. Esta biblioteca contiene los temas
 * elegibles, el envío, el análisis y el pago único por destinatario.
 *
 * Requiere inc/plugins/lib/aventuras.php y recompensas_aventura.php ya cargados.
 * Documentos: docs/201_DesignPlan_Registro.md (4.12) y docs/301_ImplementationPlan_Registro.md.
 */

const OPG_ENT_PREFIJO_AVENTURA = 3;        // mybb_threads.prefix: Aventura
const OPG_ENT_PREFIJO_AUTONARRADA = 9;     // mybb_threads.prefix: Autonarrada
const OPG_ENT_MIN_BUSQUEDA = 3;            // caracteres mínimos del typeahead de temas
const OPG_ENT_MAX_RESUMEN = 2000;
const OPG_ENT_BLOQUEO_SEG = 120;           // vigencia del reclamo de una entrega en proceso
const OPG_ENT_FORO_ROL = '10,';            // parentlist de los foros de rol (como cronologia.php)

// ── Temas elegibles ─────────────────────────────────────────────────────────

/** Foros que el usuario actual no puede ver, como lista de enteros. */
function opg_ent_foros_inaccesibles()
{
    if (!function_exists('get_unviewable_forums')) {
        return array();
    }
    $s = get_unviewable_forums(true);
    if (is_string($s) && $s !== '' && preg_match('/^[0-9,]+$/', $s)) {
        return array_map('intval', explode(',', $s));
    }
    return array();
}

/** Tema con su foro: fila cruda o null. */
function opg_ent_tema_fila($tid)
{
    return opg_av_uno(
        'SELECT t.tid, t.subject, t.visible, t.closed, t.prefix, t.fid, t.year, t.estacion, t.day, f.name AS foro, f.parentlist'
        . ' FROM ' . opg_av_t('threads') . ' t JOIN ' . opg_av_t('forums') . ' f ON f.fid = t.fid'
        . ' WHERE t.tid = ' . (int) $tid . ' LIMIT 1'
    );
}

/** El tema está en el foro de la isla (o de sus subforos); sin isla, en un foro de rol. */
function opg_ent_en_zona($fila, $av)
{
    $lista = ',' . (string) $fila['parentlist'] . ',';
    if (!empty($av['isla'])) {
        return strpos($lista, ',' . (int) $av['isla']['fid'] . ',') !== false;
    }
    return strpos((string) $fila['parentlist'], OPG_ENT_FORO_ROL) === 0;
}

/** Motivos por los que un tema no puede usarse para esta solicitud (vacío si es válido). */
function opg_ent_problemas_tema($fila, $av)
{
    $p = array();
    if ((int) $fila['visible'] !== 1) {
        $p[] = 'El tema no es visible.';
    }
    if (in_array((int) $fila['fid'], opg_ent_foros_inaccesibles(), true)) {
        $p[] = 'No tienes acceso al foro de ese tema.';
    }
    $prefijo_esperado = opg_av_es_autonarrada($av) ? OPG_ENT_PREFIJO_AUTONARRADA : OPG_ENT_PREFIJO_AVENTURA;
    if ((int) $fila['prefix'] !== $prefijo_esperado) {
        $p[] = opg_av_es_autonarrada($av) ? 'El tema no es del tipo Autonarrada.' : 'El tema no es del tipo Aventura.';
    }
    if (!opg_ent_en_zona($fila, $av)) {
        $p[] = !empty($av['isla']) ? 'El tema no está en el foro de la isla de la solicitud.' : 'El tema no está en un foro de rol.';
    }
    list($pagado, $motivo) = opg_rec_tema_pagado((int) $fila['tid']);
    if ($pagado) {
        $p[] = $motivo;
    }
    $otra = opg_av_uno(
        'SELECT peticion_id FROM ' . opg_av_t('op_aventuras_entregas') . ' WHERE tid_activo = ' . (int) $fila['tid']
        . ' AND peticion_id <> ' . (int) $av['id'] . ' LIMIT 1'
    );
    if ($otra) {
        $p[] = 'Ese tema ya está en la entrega de otra aventura.';
    }
    $vinc = opg_av_uno(
        'SELECT id FROM ' . opg_av_t('op_peticionAventuras') . ' WHERE id <> ' . (int) $av['id']
        . ' AND estado NOT IN (2, 5)'   // una solicitud denegada o eliminada libera su tema
        . " AND aventura_url REGEXP " . opg_av_sql('tid=' . (int) $fila['tid'] . '([^0-9]|$)') . ' LIMIT 1'
    );
    if ($vinc) {
        $p[] = 'Ese tema ya está vinculado a otra aventura.';
    }
    return $p;
}

function opg_ent_tema_resumen($fila)
{
    return array(
        'tid' => (int) $fila['tid'],
        'nombre' => (string) $fila['subject'],
        'foro' => (string) $fila['foro'],
        'year' => (string) $fila['year'],
        'estacion' => (string) $fila['estacion'],
        'day' => (string) $fila['day'],
    );
}

/**
 * Valida un tema para una solicitud. Devuelve array('ok', 'tema', 'errores').
 * El nombre del tema sale siempre de la base, nunca de la entrada.
 */
function opg_ent_tema_valido($tid, $av, $uid)
{
    $fila = opg_ent_tema_fila($tid);
    if (!$fila) {
        return array('ok' => false, 'tema' => null, 'errores' => array('No existe un tema con ese número.'));
    }
    $problemas = opg_ent_problemas_tema($fila, $av);
    if ($problemas) {
        return array('ok' => false, 'tema' => null, 'errores' => $problemas);
    }
    return array('ok' => true, 'tema' => opg_ent_tema_resumen($fila), 'errores' => array());
}

/**
 * Typeahead de temas elegibles: desde OPG_ENT_MIN_BUSQUEDA caracteres, solo del tipo
 * Aventura, visibles, abiertos, de la isla y no usados. Máximo 20.
 * Devuelve array('ok', 'temas', 'mensaje').
 */
function opg_ent_temas_buscar($texto, $av, $uid)
{
    global $db;
    $texto = trim((string) $texto);
    if (opg_av_longitud($texto) < OPG_ENT_MIN_BUSQUEDA) {
        return array('ok' => true, 'temas' => array(), 'mensaje' => 'Escribe al menos ' . OPG_ENT_MIN_BUSQUEDA . ' caracteres.');
    }
    $zona = !empty($av['isla'])
        ? 'FIND_IN_SET(' . (int) $av['isla']['fid'] . ', f.parentlist)'
        : "f.parentlist LIKE '" . OPG_ENT_FORO_ROL . "%'";
    $cond = 't.visible = 1 AND t.closed = 0 AND t.prefix = ' . OPG_ENT_PREFIJO_AVENTURA
        . " AND t.subject LIKE '%" . $db->escape_string_like($texto) . "%' AND " . $zona;
    $inaccesibles = opg_ent_foros_inaccesibles();
    if ($inaccesibles) {
        $cond .= ' AND t.fid NOT IN (' . implode(',', $inaccesibles) . ')';
    }
    $cond .= ' AND NOT EXISTS (SELECT 1 FROM ' . opg_av_t('op_aventuras_entregas') . ' e WHERE e.tid_activo = t.tid AND e.peticion_id <> ' . (int) $av['id'] . ')'
        . ' AND NOT EXISTS (SELECT 1 FROM ' . opg_av_t('op_peticionAventuras') . ' o WHERE o.id <> ' . (int) $av['id']
        . ' AND o.estado NOT IN (2, 5)'
        . " AND o.aventura_url REGEXP CONCAT('tid=', t.tid, '([^0-9]|$)'))";

    $filas = opg_av_todos(
        'SELECT t.tid, t.subject, t.year, t.estacion, t.day, f.name AS foro FROM ' . opg_av_t('threads') . ' t'
        . ' JOIN ' . opg_av_t('forums') . ' f ON f.fid = t.fid WHERE ' . $cond . ' ORDER BY t.dateline DESC LIMIT 40'
    );
    $temas = array();
    foreach ($filas as $f) {
        // Descarta los que ya llevan el mensaje de recompensas.
        list($pagado, $m) = opg_rec_tema_pagado((int) $f['tid']);
        if ($pagado) {
            continue;
        }
        $temas[] = opg_ent_tema_resumen($f);
        if (count($temas) >= 20) {
            break;
        }
    }
    return array('ok' => true, 'temas' => $temas, 'mensaje' => $temas ? '' : 'No hay temas de aventura con ese nombre.');
}

// ── Lectura de entregas ─────────────────────────────────────────────────────

/** Fila de entrega normalizada o null. */
function opg_ent_normalizar($f)
{
    return array(
        'id' => (int) $f['id'],
        'peticion_id' => (int) $f['peticion_id'],
        'tid' => (int) $f['tid'],
        'tema_nombre' => (string) $f['tema_nombre'],
        'resumen' => (string) $f['resumen'],
        'reputacion' => (string) $f['reputacion'],
        'estado' => (string) $f['estado'],
        'tier' => $f['tier'] !== null ? (int) $f['tier'] : null,
        'infra' => $f['infra'] !== null ? (bool) $f['infra'] : null,
        'ajustes' => $f['ajustes_json'] !== null && $f['ajustes_json'] !== '' ? json_decode((string) $f['ajustes_json'], true) : null,
        'intentos' => (int) $f['intentos'],
        'narrador_uid' => isset($f['narrador_uid']) && (int) $f['narrador_uid'] > 0 ? (int) $f['narrador_uid'] : (int) $f['enviado_por'],
        'enviado_por' => (int) $f['enviado_por'],
        'enviado_at' => (int) $f['enviado_at'],
        'revisado_por' => $f['revisado_por'] !== null ? (int) $f['revisado_por'] : null,
        'revisado_at' => $f['revisado_at'] !== null ? (int) $f['revisado_at'] : null,
        'motivo_rechazo' => $f['motivo_rechazo'] !== null ? (string) $f['motivo_rechazo'] : '',
        'procesando_at' => $f['procesando_at'] !== null ? (int) $f['procesando_at'] : null,
        'entregado_at' => $f['entregado_at'] !== null ? (int) $f['entregado_at'] : null,
    );
}

function opg_ent_obtener($peticion_id)
{
    $f = opg_av_uno('SELECT * FROM ' . opg_av_t('op_aventuras_entregas') . ' WHERE peticion_id = ' . (int) $peticion_id . ' LIMIT 1');
    return $f ? opg_ent_normalizar($f) : null;
}

function opg_ent_obtener_por_id($id)
{
    $f = opg_av_uno('SELECT * FROM ' . opg_av_t('op_aventuras_entregas') . ' WHERE id = ' . (int) $id . ' LIMIT 1');
    return $f ? opg_ent_normalizar($f) : null;
}

/** Entrega vigente de un tema (pendiente o entregada) o null. */
function opg_ent_obtener_por_tid($tid)
{
    $f = opg_av_uno('SELECT * FROM ' . opg_av_t('op_aventuras_entregas') . ' WHERE tid_activo = ' . (int) $tid . ' LIMIT 1');
    return $f ? opg_ent_normalizar($f) : null;
}

/** Conteo de entregas por estado. */
function opg_ent_conteos()
{
    $c = array('pendiente' => 0, 'rechazada' => 0, 'entregada' => 0);
    foreach (opg_av_todos('SELECT estado, COUNT(*) AS n FROM ' . opg_av_t('op_aventuras_entregas') . ' GROUP BY estado') as $f) {
        $c[(string) $f['estado']] = (int) $f['n'];
    }
    return $c;
}

/** Entregas de un estado con su solicitud normalizada, de la más reciente a la más antigua. */
function opg_ent_listar($estado, $pagina)
{
    $estado = in_array($estado, array('pendiente', 'rechazada', 'entregada'), true) ? $estado : 'pendiente';
    $pagina = max(1, (int) $pagina);
    $f = opg_av_uno('SELECT COUNT(*) AS n FROM ' . opg_av_t('op_aventuras_entregas') . ' WHERE estado = ' . opg_av_sql($estado));
    $total = $f ? (int) $f['n'] : 0;
    $paginas = max(1, (int) ceil($total / OPG_AV_POR_PAGINA));
    $pagina = min($pagina, $paginas);
    $filas = opg_av_todos(
        'SELECT * FROM ' . opg_av_t('op_aventuras_entregas') . ' WHERE estado = ' . opg_av_sql($estado)
        . ' ORDER BY enviado_at DESC, id DESC LIMIT ' . OPG_AV_POR_PAGINA . ' OFFSET ' . (($pagina - 1) * OPG_AV_POR_PAGINA)
    );
    $items = array();
    $ids = array();
    foreach ($filas as $e) {
        $items[] = array('entrega' => opg_ent_normalizar($e), 'av' => null);
        $ids[] = (int) $e['peticion_id'];
    }
    if ($ids) {
        $avs = array();
        foreach (opg_av_todos(opg_av_select_base() . ' WHERE pa.id IN (' . implode(',', $ids) . ')') as $r) {
            $avs[(int) $r['id']] = opg_av_normalizar_fila($r, true);
        }
        foreach ($items as $i => $it) {
            $items[$i]['av'] = isset($avs[$it['entrega']['peticion_id']]) ? $avs[$it['entrega']['peticion_id']] : null;
        }
    }
    return array('items' => $items, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas);
}

// ── Envío de la entrega (cualquier miembro de la aventura) ──────────────────

/**
 * Entregar: el narrador o cualquiera de los jugadores de una aventura aprobada, mientras no haya una entrega
 * vigente. Quien envía confirma que lo ha acordado con el resto del grupo (casilla obligatoria). Una crónica
 * autonarrada sigue siendo cosa de su narradora.
 */
function opg_ent_puede_enviar($av, $uid, $entrega = null)
{
    $uid = (int) $uid;
    if ($uid <= 0 || $av['estado'] !== 1) {
        return false;
    }
    $miembro = opg_av_es_autonarrada($av) ? $av['narrador_uid'] === $uid : opg_av_es_miembro($av, $uid);
    if (!$miembro) {
        return false;
    }
    return $entrega === null || $entrega['estado'] === 'rechazada';
}

/**
 * Datos del formulario de entrega, con el tema precargado desde la vinculación (o
 * desde el tema de una entrega rechazada) si todavía es válido.
 */
function opg_ent_form($peticion_id, $uid)
{
    $av = opg_av_obtener($peticion_id);
    if (!$av) {
        return array('ok' => false, 'codigo' => 404, 'errores' => array('La solicitud no existe.'));
    }
    $entrega = opg_ent_obtener($peticion_id);
    if (!opg_ent_puede_enviar($av, $uid, $entrega)) {
        return array('ok' => false, 'codigo' => 403, 'errores' => array('No puedes entregar esta aventura.'));
    }
    // El nivel que se muestra es el del narrador de la aventura, no el de quien entrega (puede ser un jugador).
    $ficha = opg_av_uno('SELECT nivelnarrador FROM ' . opg_av_t('op_fichas') . ' WHERE fid = ' . (int) $av['narrador_uid'] . ' LIMIT 1');
    $tid = $entrega ? $entrega['tid'] : $av['tid_vinculado'];
    $tema = null;
    $tema_error = null;
    if ($tid > 0) {
        $v = opg_ent_tema_valido($tid, $av, $uid);
        if ($v['ok']) {
            $tema = $v['tema'];
        } else {
            $tema_error = $v['errores'][0];
        }
    }
    return array(
        'ok' => true,
        'av' => $av,
        'entrega' => $entrega,
        'tema' => $tema,
        'tema_error' => $tema_error,
        'nivel_narrador' => $ficha ? (string) $ficha['nivelnarrador'] : '',
        'resumen' => $entrega ? $entrega['resumen'] : '',
        'reputacion' => $entrega ? $entrega['reputacion'] : '',
    );
}

/** Crea o reenvía la entrega (estado de la solicitud 1, entrega inexistente o rechazada). */
function opg_ent_enviar($peticion_id, $entrada, $uid)
{
    $uid = (int) $uid;
    opg_av_iniciar();
    $fila = opg_av_fila($peticion_id, true);
    if (!$fila) {
        opg_av_revertir();
        return opg_av_fallo(404, array('La solicitud no existe.'));
    }
    $av = opg_av_normalizar_fila($fila);
    $miembro = opg_av_es_autonarrada($av) ? ($uid > 0 && $av['narrador_uid'] === $uid) : opg_av_es_miembro($av, $uid);
    if (!$miembro) {
        opg_av_revertir();
        return opg_av_fallo(403, array('Solo el narrador o un jugador de la aventura puede entregarla.'));
    }
    if ($av['estado'] !== 1) {
        opg_av_revertir();
        return opg_av_fallo(409, array('Solo se puede entregar una aventura aprobada.'));
    }
    $previa = opg_av_uno('SELECT * FROM ' . opg_av_t('op_aventuras_entregas') . ' WHERE peticion_id = ' . (int) $peticion_id . ' LIMIT 1 FOR UPDATE');
    if ($previa && $previa['estado'] !== 'rechazada') {
        opg_av_revertir();
        return opg_av_fallo(409, array($previa['estado'] === 'entregada' ? 'Esta aventura ya fue entregada.' : 'Ya hay una entrega pendiente de revisión.'));
    }

    $errores = array();
    $tid = opg_av_tid_de_entrada(isset($entrada['tema']) ? $entrada['tema'] : '');
    $tema = null;
    if ($tid <= 0) {
        $errores[] = 'Elige el tema de la aventura (o pega su enlace o su número).';
    } else {
        $v = opg_ent_tema_valido($tid, $av, $uid);
        if (!$v['ok']) {
            $errores = array_merge($errores, $v['errores']);
        } else {
            $tema = $v['tema'];
        }
    }
    list($resumen, $e) = opg_av_texto(isset($entrada['resumen']) ? $entrada['resumen'] : '', OPG_ENT_MAX_RESUMEN, 'el resumen', true);
    if ($e) {
        $errores[] = $e;
    }
    $reputacion = isset($entrada['reputacion']) ? (string) $entrada['reputacion'] : '';
    if ($reputacion !== 'positiva' && $reputacion !== 'negativa') {
        $errores[] = 'Indica si la reputación es positiva o negativa.';
    }
    // Cualquiera del grupo puede entregar, así que quien lo hace confirma que lo ha acordado con los demás.
    $confirmo = isset($entrada['confirmar_grupo']) && in_array((string) $entrada['confirmar_grupo'], array('1', 'on', 'true', 'si'), true);
    if (!$confirmo) {
        $errores[] = 'Confirma que has hablado con el resto del grupo y que estáis de acuerdo en entregar esta aventura.';
    }
    if ($errores) {
        opg_av_revertir();
        return opg_av_fallo(422, $errores);
    }

    $ahora = opg_av_ahora();
    if ($previa) {
        $ok = opg_av_w(
            'UPDATE ' . opg_av_t('op_aventuras_entregas') . ' SET tid = ' . $tid . ', tid_activo = ' . $tid . ', tema_nombre = ' . opg_av_sql($tema['nombre'])
            . ', resumen = ' . opg_av_sql($resumen) . ', reputacion = ' . opg_av_sql($reputacion) . ", estado = 'pendiente', intentos = intentos + 1"
            . ', narrador_uid = ' . (int) $av['narrador_uid'] . ', enviado_por = ' . $uid . ', enviado_at = ' . $ahora . ', revisado_por = NULL, revisado_at = NULL, motivo_rechazo = NULL,'
            . ' procesando_por = NULL, procesando_at = NULL WHERE id = ' . (int) $previa['id'] . " AND estado = 'rechazada'"
        );
        $entrega_id = (int) $previa['id'];
    } else {
        $ok = opg_av_w(
            'INSERT INTO ' . opg_av_t('op_aventuras_entregas') . ' (peticion_id, tid, tid_activo, tema_nombre, resumen, reputacion, estado, intentos, narrador_uid, enviado_por, enviado_at) VALUES ('
            . (int) $peticion_id . ', ' . $tid . ', ' . $tid . ', ' . opg_av_sql($tema['nombre']) . ', ' . opg_av_sql($resumen) . ', ' . opg_av_sql($reputacion)
            . ", 'pendiente', 1, " . (int) $av['narrador_uid'] . ', ' . $uid . ', ' . $ahora . ')'
        );
        global $db;
        $entrega_id = $ok ? (int) $db->insert_id() : 0;
    }
    if (!$ok) {
        $msg = (stripos(opg_av_error_sql(), 'uq_tid_activo') !== false || stripos(opg_av_error_sql(), 'Duplicate') !== false)
            ? 'Ese tema ya está en la entrega de otra aventura.' : 'No se pudo guardar la entrega.';
        opg_av_revertir();
        return opg_av_fallo(409, array($msg));
    }
    // El tema de la entrega pasa a ser el tema vinculado de la aventura.
    $url = opg_av_url_tema($tid);
    if ($av['tid_vinculado'] !== $tid && strlen($url) <= 255) {
        opg_av_w('UPDATE ' . opg_av_t('op_peticionAventuras') . ' SET aventura_url = ' . opg_av_sql($url) . ' WHERE id = ' . (int) $peticion_id);
    }
    opg_av_auditar($peticion_id, $uid, 'enviar_entrega', null, array('entrega' => $entrega_id, 'tid' => $tid, 'tema' => $tema['nombre'], 'reputacion' => $reputacion, 'reenvio' => (bool) $previa, 'confirmo_grupo' => true));
    opg_av_confirmar();
    return array('ok' => true, 'id' => $entrega_id);
}

// ── Rechazo (el staff) ──────────────────────────────────────────────────────

function opg_ent_rechazar($entrega_id, $motivo, $uid_staff)
{
    $uid_staff = (int) $uid_staff;
    list($motivo, $e) = opg_av_texto($motivo, OPG_AV_MAX_MOTIVO, 'el motivo', true);
    if ($e) {
        return opg_av_fallo(422, array($e));
    }
    $entrega = opg_ent_obtener_por_id($entrega_id);
    if (!$entrega) {
        return opg_av_fallo(404, array('La entrega no existe.'));
    }
    $av = opg_av_obtener($entrega['peticion_id'], true);
    if (!$av || !opg_av_puede_resolver($av, $uid_staff)) {
        return opg_av_fallo(403, array('Sin permisos.'));
    }
    $ok = opg_av_w(
        'UPDATE ' . opg_av_t('op_aventuras_entregas') . " SET estado = 'rechazada', tid_activo = NULL, motivo_rechazo = " . opg_av_sql($motivo)
        . ', revisado_por = ' . $uid_staff . ', revisado_at = ' . opg_av_ahora() . ', procesando_por = NULL, procesando_at = NULL'
        . ' WHERE id = ' . (int) $entrega_id . " AND estado = 'pendiente'"
    );
    if (!$ok || opg_av_afectadas() === 0) {
        return opg_av_fallo(409, array('La entrega ya no está pendiente.'));
    }
    opg_av_auditar($entrega['peticion_id'], $uid_staff, 'rechazar_entrega', array('entrega' => (int) $entrega_id), array('motivo' => $motivo));
    opg_av_notificar_partes($av, $uid_staff, 'entrega_rechazada', 'entrega:' . (int) $entrega_id . ':rechazada:' . opg_av_ahora(), array('motivo' => $motivo));
    return array('ok' => true);
}

// ── Análisis (el staff, sin escribir nada) ──────────────────────────────────

/** Fichas de los usuarios que escribieron en el tema, por fid. */
function opg_ent_fichas_para_pago($uids)
{
    $uids = opg_av_enteros($uids);
    $mapa = array();
    if (!$uids) {
        return $mapa;
    }
    $filas = opg_av_todos(
        'SELECT fid, nombre, oficios, nivelnarrador, expNarradorMensualActual FROM ' . opg_av_t('op_fichas')
        . ' WHERE fid IN (' . implode(',', $uids) . ')'
    );
    foreach ($filas as $f) {
        $mapa[(int) $f['fid']] = $f;
    }
    return $mapa;
}

/** Nombre visible del narrador conservado en la entrega. */
function opg_ent_nombre_narrador($uid, $av)
{
    $uid = (int) $uid;
    if ($uid > 0 && $av && (int) $av['narrador_uid'] === $uid && $av['narrador_nombre'] !== '') {
        return $av['narrador_nombre'];
    }
    $f = opg_av_uno(
        'SELECT f.nombre, u.username FROM ' . opg_av_t('users') . ' u LEFT JOIN ' . opg_av_t('op_fichas')
        . ' f ON f.fid = u.uid WHERE u.uid = ' . $uid . ' LIMIT 1'
    );
    if ($f) {
        return $f['nombre'] !== null && $f['nombre'] !== '' ? (string) $f['nombre'] : (string) $f['username'];
    }
    return 'Usuario ' . $uid;
}

/** Pagos ya registrados de una entrega, por uid. */
function opg_ent_pagos($entrega_id)
{
    $mapa = array();
    foreach (opg_av_todos('SELECT * FROM ' . opg_av_t('op_aventuras_entregas_pagos') . ' WHERE entrega_id = ' . (int) $entrega_id . ' ORDER BY id ASC') as $p) {
        $mapa[(int) $p['uid']] = $p;
    }
    return $mapa;
}

/**
 * Análisis de una entrega pendiente con los ajustes del staff: quién narró, posts
 * válidos, porcentajes y recompensas por destinatario, advertencias y bloqueos.
 * No escribe nada. Devuelve un fallo o array('ok' => true, ...).
 */
function opg_ent_analizar($entrega_id, $ajustes_entrada)
{
    $entrega = opg_ent_obtener_por_id($entrega_id);
    if (!$entrega) {
        return opg_av_fallo(404, array('La entrega no existe.'));
    }
    if ($entrega['estado'] !== 'pendiente') {
        return opg_av_fallo(409, array('La entrega ya no está pendiente.'));
    }
    $av = opg_av_obtener($entrega['peticion_id'], true);
    if (!$av) {
        return opg_av_fallo(404, array('La solicitud de la entrega no existe.'));
    }
    $narrador_uid = (int) $entrega['narrador_uid'];
    $narradores = $narrador_uid > 0 ? array($narrador_uid => opg_ent_nombre_narrador($narrador_uid, $av)) : array();
    $analisis = opg_rec_analizar_tema($entrega['tid'], $narradores);
    if (!$analisis['ok']) {
        return opg_av_fallo(422, $analisis['errores']);
    }

    // Una entrega ya reclamada reutiliza sus ajustes congelados.
    $ajustes_entrada = is_array($entrega['ajustes']) ? $entrega['ajustes'] : $ajustes_entrada;
    $tier_defecto = $entrega['tier'] ? $entrega['tier'] : ($av['tier'] ? $av['tier'] : 0);
    $norm = opg_rec_normalizar_ajustes($ajustes_entrada, $tier_defecto, $entrega['reputacion'], count($analisis['usuarios']));
    $ajustes = $norm['ajustes'];
    $bloqueos = array_merge($analisis['bloqueos'], $norm['errores']);
    $advertencias = array();

    // Roles de narrador elegidos por el staff (cambio de narrador, varias cuentas...). Sin elección, manda el narrador de la entrega.
    $principal = $narrador_uid;
    $roles = array();
    foreach ($ajustes['destinatarios'] as $uid_r => $d_r) {
        if ($d_r['rol'] !== null && isset($analisis['usuarios'][$uid_r])) {
            $roles[$uid_r] = $d_r['rol'];
        }
    }
    if ($roles) {
        foreach ($roles as $uid_r => $rol_r) {
            $analisis['usuarios'][$uid_r]['es_narrador'] = $rol_r !== 'jugador';
        }
        $principales = array_keys(array_filter($roles, function ($r) { return $r === 'principal'; }));
        if (count($principales) === 1) {
            $principal = (int) $principales[0];
        } else {
            $principal = 0;
            $bloqueos[] = 'Elige un único narrador principal.';
        }
    }
    $ajustes['narrador_principal'] = $principal;

    if (!$ajustes['tier'] || !isset(OPG_REC_POSTS_TIER[$ajustes['tier']])) {
        $bloqueos[] = 'La solicitud no tiene tier: elígelo en los ajustes.';
        return array('ok' => true, 'entrega' => $entrega, 'av' => $av, 'ajustes' => $ajustes, 'analisis' => $analisis,
            'destinatarios' => array(), 'minimos' => null, 'advertencias' => $advertencias, 'bloqueos' => array_values(array_unique($bloqueos)),
            'requiere_confirmar_minimos' => false, 'puede_pagar' => false, 'infra' => $av['inframundo']);
    }

    $pagos = opg_ent_pagos($entrega_id);
    if (!$pagos) {
        list($pagado, $motivo) = opg_rec_tema_pagado($entrega['tid']);
        if ($pagado) {
            $bloqueos[] = $motivo . ' Si lo pagó la herramienta antigua, rechaza esta entrega; si lo cerró otra persona, ábrelo.';
        }
    }

    $porcentajes = opg_rec_calcular_porcentajes($analisis, $ajustes);
    $fichas = opg_ent_fichas_para_pago(array_keys($analisis['usuarios']));

    $en_solicitud = array();
    foreach ($av['jugadores'] as $j) {
        if ($j['uid'] !== null) {
            $en_solicitud[(int) $j['uid']] = $j['nombre'];
        }
    }
    if ($narrador_uid > 0) {
        $en_solicitud[$narrador_uid] = $narradores[$narrador_uid];
    }

    // Narradores primero, luego jugadores; dentro de cada grupo, por posts válidos.
    $orden = array_keys($analisis['usuarios']);
    usort($orden, function ($a, $b) use ($analisis) {
        $ua = $analisis['usuarios'][$a];
        $ub = $analisis['usuarios'][$b];
        if ($ua['es_narrador'] !== $ub['es_narrador']) {
            return $ua['es_narrador'] ? -1 : 1;
        }
        return $ub['validos'] <=> $ua['validos'];
    });

    $destinatarios = array();
    $incluidos = 0;
    foreach ($orden as $uid) {
        $u = $analisis['usuarios'][$uid];
        $p = $porcentajes[$uid];
        $ficha = isset($fichas[$uid]) ? $fichas[$uid] : null;
        $dest_aj = isset($ajustes['destinatarios'][$uid]) ? $ajustes['destinatarios'][$uid] : null;
        $incluir = $dest_aj ? $dest_aj['incluir'] : isset($en_solicitud[$uid]);
        $rep = $dest_aj ? $dest_aj['rep'] : $ajustes['reputacion_defecto'];
        $avisos = array();
        if (!isset($en_solicitud[$uid])) {
            $avisos[] = 'Escribió en el tema sin estar en la solicitud.';
        }
        $calculo = null;
        if (!$ficha) {
            $avisos[] = 'No tiene ficha: no puede recibir recompensas.';
        } elseif ($p['rol'] === 'narrador') {
            $calculo = opg_rec_calcular_narrador($ficha, $p['porcentaje'], $ajustes['tier'], $ficha['expNarradorMensualActual'], $uid === $principal);
            if (!$calculo['nivel_conocido']) {
                $avisos[] = 'Su nivel de narrador no es válido (' . ($ficha['nivelnarrador'] !== '' ? $ficha['nivelnarrador'] : 'vacío') . '): se calcula como Aprendiz.';
            }
        } else {
            $calculo = opg_rec_calcular_jugador($ficha, $p['porcentaje'], $ajustes['tier'], $av['inframundo'], $rep);
        }
        if ($incluir && !$ficha) {
            $bloqueos[] = ($fichas && isset($fichas[$uid]) ? '' : $u['username'] . ' no tiene ficha: exclúyelo para poder pagar.');
        }
        if ($incluir) {
            $incluidos++;
        }
        $destinatarios[] = array(
            'uid' => $uid,
            'username' => $u['username'],
            'rol' => $p['rol'],
            'posts' => $u['posts'],
            'validos' => $u['validos'],
            'variacion' => $p['variacion'],
            'porcentaje_base' => $p['base'],
            'porcentaje' => $p['porcentaje'],
            'en_solicitud' => isset($en_solicitud[$uid]),
            'tiene_ficha' => (bool) $ficha,
            'incluir' => $incluir,
            'rep' => $rep,
            'calculo' => $calculo,
            'pago' => isset($pagos[$uid]) ? $pagos[$uid] : null,
            'avisos' => $avisos,
            'principal' => $uid === $principal,
        );
        if ($uid === $principal && !$incluir) {
            $advertencias[] = $u['username'] . ' es el narrador principal pero no cobrará: nadie recibirá los cofres.';
        }
    }
    if ($incluidos === 0) {
        $bloqueos[] = 'Selecciona al menos un destinatario.';
    }

    // Quien está en la solicitud pero no escribió.
    foreach ($en_solicitud as $uid => $nombre) {
        if (!isset($analisis['usuarios'][$uid]) || (int) $analisis['usuarios'][$uid]['posts'] === 0) {
            $advertencias[] = $nombre . ' está en la solicitud pero no escribió en el tema.';
        }
    }

    if ($narrador_uid > 0 && $principal !== $narrador_uid) {
        $advertencias[] = 'El narrador principal difiere del de la solicitud (' . $narradores[$narrador_uid] . '). Revisa el resumen de la entrega.';
    }

    $minimos = opg_rec_minimos($analisis, $ajustes);
    $requiere = !$minimos['cumple'] && !$ajustes['confirma_minimos'];
    if ($requiere) {
        $bloqueos[] = 'No se cumplen los posts mínimos (' . $minimos['validos'] . ' de ' . $minimos['minimo'] . '): confirma que quieres pagar igualmente.';
    }
    $bloqueos = array_values(array_unique(array_filter($bloqueos)));
    return array(
        'ok' => true,
        'entrega' => $entrega,
        'av' => $av,
        'ajustes' => $ajustes,
        'analisis' => $analisis,
        'destinatarios' => $destinatarios,
        'minimos' => $minimos,
        'advertencias' => $advertencias,
        'bloqueos' => $bloqueos,
        'requiere_confirmar_minimos' => $requiere,
        'puede_pagar' => !$bloqueos,
        'infra' => $av['inframundo'],
        'principal' => $principal,
    );
}

// ── Pago ─────────────────────────────────────────────────────────────────────

/** Suma objetos al inventario (InnoDB), dentro de la transacción del pago. */
function opg_ent_dar_objeto($fid, $objeto_id, $cantidad)
{
    $fila = opg_av_uno(
        'SELECT cantidad FROM ' . opg_av_t('op_inventario') . ' WHERE uid = ' . (int) $fid . ' AND objeto_id = ' . opg_av_sql($objeto_id) . ' FOR UPDATE'
    );
    if ($fila) {
        return opg_av_w(
            'UPDATE ' . opg_av_t('op_inventario') . ' SET cantidad = cantidad + ' . (int) $cantidad
            . ' WHERE uid = ' . (int) $fid . ' AND objeto_id = ' . opg_av_sql($objeto_id)
        );
    }
    return opg_av_w(
        'INSERT INTO ' . opg_av_t('op_inventario') . ' (objeto_id, uid, cantidad) VALUES (' . opg_av_sql($objeto_id) . ', ' . (int) $fid . ', ' . (int) $cantidad . ')'
    );
}

/** Texto del log general de un pago, con el formato de recompensasAventuras.php. */
function opg_ent_texto_log($rol, $staff_uid, $staff_nombre, $username, $uid, $tid, $tier, $c, $peticion_id, $entrega_id)
{
    $e = function ($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); };
    $t = "\n        <strong><i><u>Moderador</i></u></strong>: " . $e($staff_nombre) . " ({$staff_uid}) <br>"
        . "\n        <strong><i><u>Usuario</i></u></strong>: " . $e($username) . " ({$uid}) <br>"
        . "\n        <strong><i><u>ID del tema</i></u></strong>: {$tid} <br>"
        . "\n        <strong><i><u>Petición</i></u></strong>: #{$peticion_id} (entrega #{$entrega_id}) <br>"
        . "\n        * <strong><i><u>Tier</i></u></strong>: {$tier} <br>";
    if ($rol === 'narrador') {
        $exp_info = $c['excedente'] > 0
            ? "{$c['experiencia']} (límite mensual alcanzado, {$c['excedente']} EXP extra → +{$c['kuros']} kuros)" : (string) $c['experiencia'];
        $t .= "\n        * <strong><i><u>Tier Narrador</i></u></strong>: {$c['nivel']} <br>"
            . "\n        * <strong><i><u>Experiencia</i></u></strong>: +{$exp_info} <br>"
            . "\n        * <strong><i><u>Exp mensual acumulada</i></u></strong>: {$c['exp_mensual_antes']} + {$c['experiencia']} = {$c['exp_mensual_despues']} / " . OPG_REC_EXP_NARRADOR_MENSUAL . " <br>"
            . "\n        * <strong><i><u>Nikas</i></u></strong>: +{$c['nikas']} <br>"
            . ($c['kuros'] > 0 ? "\n        * <strong><i><u>Kuros (por excedente)</i></u></strong>: +{$c['kuros']} <br>" : '')
            . ($c['cofre_cantidad'] > 0 ? "\n        * <strong><i><u>Cofres</i></u></strong>: {$c['cofre_cantidad']}x {$c['cofre']} <br>" : '')
            . "\n        * <strong><i><u>Porcentaje aplicado</i></u></strong>: {$c['porcentaje']}% <br>\n    ";
    } else {
        $rep_txt = $c['reputacion_tipo'] === 'positiva' ? 'Positiva' : ($c['reputacion_tipo'] === 'negativa' ? 'Negativa' : 'Ninguna');
        $t .= "\n        * <strong><i><u>Experiencia</i></u></strong>: +{$c['experiencia']} <br>"
            . "\n        * <strong><i><u>Nikas</i></u></strong>: +{$c['nikas']} <br>"
            . "\n        * <strong><i><u>Reputación ({$rep_txt})</i></u></strong>: +{$c['reputacion']} <br>"
            . "\n        * <strong><i><u>Berries</i></u></strong>: +{$c['berries']} ฿ <br>"
            . "\n        * <strong><i><u>Porcentaje aplicado</i></u></strong>: {$c['porcentaje']}% <br>"
            . "\n        <strong><i><u>Contrabandista</i></u></strong>: Nivel {$c['nivel_contrabandista']}. Multiplicador = {$c['mult_contrabandista']}. <br>"
            . "\n        <strong><i><u>Arqueologo</i></u></strong>: Nivel {$c['nivel_arqueologo']}. Multiplicador XP = {$c['mult_xp_arqueologo']}. Nikas Extra = {$c['nikas_extra_arqueologo']}. <br>\n    ";
    }
    return $t;
}

/** Escribe el log general de un pago (categorías de siempre, para que log_entregas los muestre). */
function opg_ent_log_pago($rol, $staff_uid, $username, $uid, $tid, $tier, $c, $peticion_id, $entrega_id)
{
    global $db;
    if (!function_exists('log_audit')) {
        return;
    }
    $staff = function_exists('get_user') ? get_user((int) $staff_uid) : null;
    $nombre_staff = $staff && isset($staff['username']) ? $staff['username'] : '';
    $texto = opg_ent_texto_log($rol, $staff_uid, $nombre_staff, $username, $uid, $tid, $tier, $c, $peticion_id, $entrega_id);
    $cat = $rol === 'narrador' ? '[Entregas][Aventura Narradores]' : '[Entregas][Aventura Usuario]';
    log_audit((int) $staff_uid, $db->escape_string($nombre_staff), $cat, $db->escape_string($texto));
}

// ── Historial del personaje ──────────────────────────────────────────────────

/** Número para el historial: sin ceros de más (14217.58, 120). */
function opg_ent_num_historial($v)
{
    $s = rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    return ($s === '' || $s === '-0') ? '0' : $s;
}

/**
 * Texto de una fila del historial: "Nikas: 10->15 (5) · Aventura #12 · tema #345". El principio sigue el formato
 * que lee la ficha (jscripts/ficha/portada.js: "Etiqueta: antes->después (diferencia)").
 */
function opg_ent_texto_historial($moneda, $antes, $despues, $peticion_id, $tid)
{
    return $moneda . ': ' . opg_ent_num_historial($antes) . '->' . opg_ent_num_historial($despues)
        . ' (' . (int) round((float) $despues - (float) $antes) . ') · Aventura #' . (int) $peticion_id . ' · tema #' . (int) $tid;
}

/**
 * Anota en el historial de la ficha (pestañas Experiencia, Nikas, Berries y Kuros) lo que dio una aventura, con la
 * categoría [Aventura][Moneda]. Solo escribe el registro: no cambia ningún valor de la ficha. Si la tabla de
 * auditoría falla, el pago no se ve afectado.
 */
function opg_ent_historial($uid, $uid_staff, $moneda, $antes, $despues, $peticion_id, $tid)
{
    $uid = (int) $uid;
    $actor = (int) $uid_staff > 0 ? (int) $uid_staff : $uid;
    $u = opg_av_uno('SELECT username FROM ' . opg_av_t('users') . ' WHERE uid = ' . $actor . ' LIMIT 1');
    $nombre = $u ? (string) $u['username'] : '';
    return opg_av_w(
        'INSERT INTO ' . opg_av_t('op_audit_general') . ' (uid, username, user_uid, categoria, log) VALUES ('
        . $actor . ', ' . opg_av_sql($nombre) . ', ' . $uid . ', ' . opg_av_sql('[Aventura][' . $moneda . ']') . ', '
        . opg_av_sql(opg_ent_texto_historial($moneda, $antes, $despues, $peticion_id, $tid)) . ')'
    );
}

/**
 * Fase de experiencia de un pago: suma newpoints (MyBB: MyISAM, sin ROLLBACK) con un
 * estado en tres pasos para no repetirla. Devuelve 'aplicado', 'dudoso' o 'error'.
 */
function opg_ent_aplicar_xp($pago, $uid_staff = 0)
{
    $estado = (string) $pago['xp_estado'];
    if ($estado === 'aplicado') {
        return 'aplicado';
    }
    if ($estado === 'aplicando' || $estado === 'dudoso') {
        opg_av_w('UPDATE ' . opg_av_t('op_aventuras_entregas_pagos') . " SET xp_estado = 'dudoso' WHERE id = " . (int) $pago['id'] . " AND xp_estado = 'aplicando'");
        return 'dudoso';
    }
    $xp = (int) $pago['xp_cantidad'];
    if ($xp <= 0) {
        opg_av_w('UPDATE ' . opg_av_t('op_aventuras_entregas_pagos') . " SET xp_estado = 'aplicado' WHERE id = " . (int) $pago['id']);
        return 'aplicado';
    }
    opg_av_w('UPDATE ' . opg_av_t('op_aventuras_entregas_pagos') . " SET xp_estado = 'aplicando' WHERE id = " . (int) $pago['id'] . " AND xp_estado = 'pendiente'");
    if (opg_av_afectadas() === 0) {
        return 'error';   // otro proceso la está aplicando
    }
    $antes = opg_av_uno('SELECT newpoints FROM ' . opg_av_t('users') . ' WHERE uid = ' . (int) $pago['uid'] . ' LIMIT 1');
    $ok = opg_av_w('UPDATE ' . opg_av_t('users') . ' SET newpoints = newpoints + ' . $xp . ' WHERE uid = ' . (int) $pago['uid']);
    if (!$ok) {
        opg_av_w('UPDATE ' . opg_av_t('op_aventuras_entregas_pagos') . " SET xp_estado = 'pendiente' WHERE id = " . (int) $pago['id']);
        return 'error';
    }
    opg_av_w('UPDATE ' . opg_av_t('op_aventuras_entregas_pagos') . " SET xp_estado = 'aplicado' WHERE id = " . (int) $pago['id']);
    if ($antes) {
        $entrega_pago = opg_ent_obtener_por_id((int) $pago['entrega_id']);
        if ($entrega_pago) {
            opg_ent_historial($pago['uid'], $uid_staff, 'Experiencia', $antes['newpoints'], (float) $antes['newpoints'] + $xp, $entrega_pago['peticion_id'], $entrega_pago['tid']);
        }
    }
    return 'aplicado';
}

/**
 * Paga a un destinatario una sola vez. Devuelve array('estado' => 'pagado'|'omitido'|'error', 'error' => ...).
 * 'omitido' significa que ya había cobrado (o que otro proceso lo está pagando).
 */
function opg_ent_pagar_destinatario($entrega, $av, $d, $tier, $infra, $uid_staff)
{
    $uid = (int) $d['uid'];
    $tabla = opg_av_t('op_aventuras_entregas_pagos');
    opg_av_w(
        'INSERT IGNORE INTO ' . $tabla . ' (entrega_id, uid, rol, porcentaje) VALUES ('
        . (int) $entrega['id'] . ', ' . $uid . ', ' . opg_av_sql($d['rol']) . ', ' . opg_av_sql((float) $d['porcentaje']) . ')'
    );
    $pago = opg_av_uno('SELECT * FROM ' . $tabla . ' WHERE entrega_id = ' . (int) $entrega['id'] . ' AND uid = ' . $uid . ' LIMIT 1');
    if (!$pago) {
        return array('estado' => 'error', 'error' => 'No se pudo registrar el pago de ' . $d['username'] . '.');
    }
    if ($pago['aplicado_at'] !== null) {
        $x = opg_ent_aplicar_xp($pago, $uid_staff);
        return array('estado' => $x === 'aplicado' ? 'omitido' : 'error', 'error' => $x === 'aplicado' ? '' : 'La experiencia de ' . $d['username'] . ' necesita revisión.');
    }

    opg_av_iniciar();
    $ficha = opg_av_uno(
        'SELECT fid, nombre, oficios, nivelnarrador, expNarradorMensualActual, nika, berries, kuro FROM ' . opg_av_t('op_fichas') . ' WHERE fid = ' . $uid . ' LIMIT 1 FOR UPDATE'
    );
    if (!$ficha) {
        opg_av_revertir();
        return array('estado' => 'error', 'error' => $d['username'] . ' no tiene ficha.');
    }
    if ($d['rol'] === 'narrador') {
        $c = opg_rec_calcular_narrador($ficha, (float) $d['porcentaje'], $tier, $ficha['expNarradorMensualActual'], !empty($d['principal']));
    } else {
        $c = opg_rec_calcular_jugador($ficha, (float) $d['porcentaje'], $tier, $infra, $d['rep']);
    }
    $detalle = json_encode(array(
        'rol' => $d['rol'], 'username' => $d['username'], 'variacion' => (int) $d['variacion'], 'calculo' => $c,
    ), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    opg_av_w(
        'UPDATE ' . $tabla . ' SET aplicado_at = ' . opg_av_ahora() . ', xp_cantidad = ' . (int) $c['xp'] . ', detalle_json = ' . opg_av_sql($detalle)
        . ', xp_estado = ' . ($c['xp'] > 0 ? "'pendiente'" : "'aplicado'")
        . ' WHERE entrega_id = ' . (int) $entrega['id'] . ' AND uid = ' . $uid . ' AND aplicado_at IS NULL'
    );
    if (opg_av_afectadas() === 0) {
        opg_av_revertir();
        return array('estado' => 'omitido', 'error' => '');
    }
    $ok = opg_av_w('UPDATE ' . opg_av_t('op_fichas') . ' SET ' . implode(', ', $c['sets_ficha']) . ' WHERE fid = ' . $uid);
    if ($ok && $d['rol'] === 'narrador' && $c['cofre_cantidad'] > 0) {
        $ok = opg_ent_dar_objeto($uid, $c['cofre'], $c['cofre_cantidad']);
    }
    if (!$ok) {
        opg_av_revertir();
        return array('estado' => 'error', 'error' => 'No se pudo pagar a ' . $d['username'] . '.');
    }
    // Historial de la ficha: [Aventura][Nikas], [Aventura][Berries] y [Aventura][Kuros] (la experiencia se anota al aplicarla).
    $cambios_historial = array(
        'Nikas' => array((int) $ficha['nika'], isset($c['nikas']) ? (int) $c['nikas'] : 0),
        'Berries' => array((int) $ficha['berries'], isset($c['berries']) ? (int) $c['berries'] : 0),
        'Kuros' => array((int) $ficha['kuro'], isset($c['kuros']) ? (int) $c['kuros'] : 0),
    );
    foreach ($cambios_historial as $moneda => $par) {
        if ($par[1] !== 0) {
            opg_ent_historial($uid, $uid_staff, $moneda, $par[0], $par[0] + $par[1], $entrega['peticion_id'], $entrega['tid']);
        }
    }
    opg_av_confirmar();

    $pago = opg_av_uno('SELECT * FROM ' . $tabla . ' WHERE entrega_id = ' . (int) $entrega['id'] . ' AND uid = ' . $uid . ' LIMIT 1');
    $x = opg_ent_aplicar_xp($pago, $uid_staff);
    opg_ent_log_pago($d['rol'], $uid_staff, $d['username'], $uid, $entrega['tid'], $tier, $c, $entrega['peticion_id'], $entrega['id']);
    return array('estado' => 'pagado', 'error' => $x === 'aplicado' ? '' : 'La experiencia de ' . $d['username'] . ' necesita revisión.');
}

/** Libera el reclamo de una entrega (sin cambiar su estado). */
function opg_ent_liberar($entrega_id)
{
    opg_av_w('UPDATE ' . opg_av_t('op_aventuras_entregas') . ' SET procesando_por = NULL, procesando_at = NULL WHERE id = ' . (int) $entrega_id . " AND estado = 'pendiente'");
}

/**
 * Si todos los destinatarios cobraron (y su experiencia está aplicada), publica el
 * mensaje en el tema, lo cierra, deja la entrega entregada y finaliza la solicitud.
 * Devuelve array('cerrada' => bool, 'errores' => [...]).
 */
function opg_ent_cerrar_si_completo($entrega, $av, $ajustes, $uid_staff)
{
    $tabla = opg_av_t('op_aventuras_entregas_pagos');
    $pagos = opg_av_todos('SELECT * FROM ' . $tabla . ' WHERE entrega_id = ' . (int) $entrega['id'] . ' ORDER BY id ASC');
    $pendientes = array();
    foreach ($pagos as $p) {
        if ($p['aplicado_at'] === null || $p['xp_estado'] !== 'aplicado') {
            $pendientes[] = $p;
        }
    }
    if (!$pagos || $pendientes) {
        return array('cerrada' => false, 'errores' => $pendientes ? array('Faltan pagos por completar o revisar.') : array('No hay pagos registrados.'));
    }

    $staff = function_exists('get_user') ? get_user((int) $uid_staff) : null;
    $datos = array(
        'tier' => (int) $ajustes['tier'], 'staff_uid' => (int) $uid_staff, 'staff_nombre' => $staff && isset($staff['username']) ? $staff['username'] : '',
        'extra_jugadores' => (int) $ajustes['extra_jugadores'], 'narradores' => array(), 'jugadores' => array(),
    );
    foreach ($pagos as $p) {
        $det = json_decode((string) $p['detalle_json'], true);
        if (!is_array($det) || !isset($det['calculo'])) {
            continue;
        }
        $linea = array('uid' => (int) $p['uid'], 'username' => (string) $det['username'], 'variacion' => (int) $det['variacion'], 'calculo' => $det['calculo']);
        if ($p['rol'] === 'narrador') {
            $datos['narradores'][] = $linea;
        } else {
            $datos['jugadores'][] = $linea;
        }
    }

    $ya_publicado = opg_rec_tema_tiene_marcador($entrega['tid']);
    if (!$ya_publicado) {
        $r = opg_rec_publicar_y_cerrar($entrega['tid'], opg_rec_mensaje_resumen($datos));
        if (!$r['ok']) {
            return array('cerrada' => false, 'errores' => array('Los pagos están hechos, pero no se pudo publicar el mensaje en el tema: ' . $r['error'] . ' Usa "Completar entrega" para reintentarlo.'));
        }
    } else {
        // El mensaje ya existe: asegura que el tema esté cerrado.
        opg_av_w('UPDATE ' . opg_av_t('threads') . ' SET closed = 1 WHERE tid = ' . (int) $entrega['tid']);
    }

    opg_av_iniciar();
    opg_av_w(
        'UPDATE ' . opg_av_t('op_aventuras_entregas') . " SET estado = 'entregada', tier = " . (int) $ajustes['tier'] . ', infra = ' . ($av['inframundo'] ? '1' : '0')
        . ', revisado_por = ' . (int) $uid_staff . ', revisado_at = ' . opg_av_ahora() . ', entregado_at = ' . opg_av_ahora()
        . ', procesando_por = NULL, procesando_at = NULL WHERE id = ' . (int) $entrega['id'] . " AND estado = 'pendiente'"
    );
    opg_av_w('UPDATE ' . opg_av_t('op_peticionAventuras') . ' SET estado = 3 WHERE id = ' . (int) $entrega['peticion_id'] . ' AND estado = 1');
    opg_av_auditar($entrega['peticion_id'], $uid_staff, 'pagar_entrega', null, array(
        'entrega' => (int) $entrega['id'], 'tier' => (int) $ajustes['tier'], 'destinatarios' => count($pagos),
    ));
    opg_av_notificar_partes($av, $uid_staff, 'recompensas_entregadas', 'entrega:' . (int) $entrega['id'] . ':entregada', array());
    opg_av_confirmar();
    return array('cerrada' => true, 'errores' => array());
}

/**
 * Paga la entrega: reclama, valida con los ajustes (o con los congelados de un intento
 * anterior), paga a cada destinatario incluido una sola vez y cierra el tema.
 */
function opg_ent_pagar($entrega_id, $ajustes_entrada, $uid_staff)
{
    $uid_staff = (int) $uid_staff;
    $entrega = opg_ent_obtener_por_id($entrega_id);
    if (!$entrega) {
        return opg_av_fallo(404, array('La entrega no existe.'));
    }
    if ($entrega['estado'] !== 'pendiente') {
        return opg_av_fallo(409, array('La entrega ya no está pendiente.'));
    }
    $av = opg_av_obtener($entrega['peticion_id'], true);
    if (!$av || !opg_av_puede_resolver($av, $uid_staff)) {
        return opg_av_fallo(403, array('Sin permisos.'));
    }

    opg_av_w(
        'UPDATE ' . opg_av_t('op_aventuras_entregas') . ' SET procesando_por = ' . $uid_staff . ', procesando_at = ' . opg_av_ahora()
        . ' WHERE id = ' . (int) $entrega_id . " AND estado = 'pendiente' AND (procesando_at IS NULL OR procesando_at < " . (opg_av_ahora() - OPG_ENT_BLOQUEO_SEG) . ')'
    );
    if (opg_av_afectadas() === 0) {
        return opg_av_fallo(409, array('Otra persona está procesando esta entrega. Espera un momento.'));
    }

    $a = opg_ent_analizar($entrega_id, $ajustes_entrada);
    if (!isset($a['ok']) || !$a['ok'] || !$a['puede_pagar']) {
        opg_ent_liberar($entrega_id);
        return opg_av_fallo(422, isset($a['bloqueos']) && $a['bloqueos'] ? $a['bloqueos'] : (isset($a['errores']) ? $a['errores'] : array('No se puede pagar.')));
    }
    $ajustes = $a['ajustes'];

    // La entrega queda a nombre del narrador principal elegido en la revisión.
    if ((int) $a['principal'] > 0 && (int) $a['principal'] !== (int) $entrega['narrador_uid']) {
        opg_av_w('UPDATE ' . opg_av_t('op_aventuras_entregas') . ' SET narrador_uid = ' . (int) $a['principal'] . ' WHERE id = ' . (int) $entrega_id);
    }

    // Congela tier, inframundo y ajustes la primera vez, para que reanudar no cambie montos.
    if (!is_array($entrega['ajustes'])) {
        opg_av_w(
            'UPDATE ' . opg_av_t('op_aventuras_entregas') . ' SET tier = ' . (int) $ajustes['tier'] . ', infra = ' . ($av['inframundo'] ? '1' : '0')
            . ', ajustes_json = ' . opg_av_sql(json_encode($ajustes, JSON_UNESCAPED_UNICODE)) . ' WHERE id = ' . (int) $entrega_id
        );
    }

    opg_rec_reset_mensual();
    $errores = array();
    $pagados = 0;
    foreach ($a['destinatarios'] as $d) {
        if (!$d['incluir'] || !$d['tiene_ficha']) {
            continue;
        }
        $r = opg_ent_pagar_destinatario($entrega, $av, $d, $ajustes['tier'], $av['inframundo'], $uid_staff);
        if ($r['estado'] === 'pagado') {
            $pagados++;
        }
        if ($r['error'] !== '') {
            $errores[] = $r['error'];
        }
    }

    $entrega = opg_ent_obtener_por_id($entrega_id);
    $cierre = array('cerrada' => false, 'errores' => array());
    if (!$errores) {
        $cierre = opg_ent_cerrar_si_completo($entrega, $av, $ajustes, $uid_staff);
        $errores = $cierre['errores'];
    }
    if (!$cierre['cerrada']) {
        opg_ent_liberar($entrega_id);
        return array('ok' => false, 'codigo' => 500, 'errores' => $errores, 'parcial' => true, 'pagados' => $pagados);
    }
    return array('ok' => true, 'pagados' => $pagados);
}

/** Termina una entrega interrumpida con los ajustes congelados, sin repetir pagos. */
function opg_ent_completar($entrega_id, $uid_staff)
{
    $entrega = opg_ent_obtener_por_id($entrega_id);
    if (!$entrega) {
        return opg_av_fallo(404, array('La entrega no existe.'));
    }
    if (!is_array($entrega['ajustes'])) {
        return opg_av_fallo(409, array('Esta entrega no tiene pagos iniciados: paga desde la página de recompensas.'));
    }
    return opg_ent_pagar($entrega_id, $entrega['ajustes'], $uid_staff);
}

/** Resuelve un pago con experiencia dudosa: 'aplicada' (ya estaba) o 'aplicar' (sumarla ahora). */
function opg_ent_xp_dudosa($entrega_id, $uid_destinatario, $decision, $uid_staff)
{
    $uid_staff = (int) $uid_staff;
    $entrega = opg_ent_obtener_por_id($entrega_id);
    if (!$entrega) {
        return opg_av_fallo(404, array('La entrega no existe.'));
    }
    $av = opg_av_obtener($entrega['peticion_id'], true);
    if (!$av || !opg_av_puede_resolver($av, $uid_staff)) {
        return opg_av_fallo(403, array('Sin permisos.'));
    }
    if ($decision !== 'aplicada' && $decision !== 'aplicar') {
        return opg_av_fallo(422, array('Elige una opción válida.'));
    }
    $tabla = opg_av_t('op_aventuras_entregas_pagos');
    $pago = opg_av_uno('SELECT * FROM ' . $tabla . ' WHERE entrega_id = ' . (int) $entrega_id . ' AND uid = ' . (int) $uid_destinatario . ' LIMIT 1');
    if (!$pago || !in_array($pago['xp_estado'], array('dudoso', 'aplicando'), true)) {
        return opg_av_fallo(409, array('Ese pago no tiene experiencia en duda.'));
    }
    if ($decision === 'aplicar') {
        $antes = opg_av_uno('SELECT newpoints FROM ' . opg_av_t('users') . ' WHERE uid = ' . (int) $uid_destinatario . ' LIMIT 1');
        $ok = opg_av_w('UPDATE ' . opg_av_t('users') . ' SET newpoints = newpoints + ' . (int) $pago['xp_cantidad'] . ' WHERE uid = ' . (int) $uid_destinatario);
        if (!$ok) {
            return opg_av_fallo(500, array('No se pudo aplicar la experiencia.'));
        }
        if ($antes) {
            opg_ent_historial($uid_destinatario, $uid_staff, 'Experiencia', $antes['newpoints'], (float) $antes['newpoints'] + (int) $pago['xp_cantidad'], $entrega['peticion_id'], $entrega['tid']);
        }
    }
    opg_av_w('UPDATE ' . $tabla . " SET xp_estado = 'aplicado' WHERE id = " . (int) $pago['id'] . " AND xp_estado IN ('dudoso', 'aplicando')");
    opg_av_auditar($entrega['peticion_id'], $uid_staff, 'xp_dudosa', array('uid' => (int) $uid_destinatario, 'xp' => (int) $pago['xp_cantidad']), array('decision' => $decision));
    return array('ok' => true);
}

/** Lo recibido por cada destinatario de una entrega (pagada o en curso). */
function opg_ent_resultado($entrega_id)
{
    $salida = array();
    foreach (opg_av_todos('SELECT * FROM ' . opg_av_t('op_aventuras_entregas_pagos') . ' WHERE entrega_id = ' . (int) $entrega_id . ' ORDER BY id ASC') as $p) {
        $det = json_decode((string) $p['detalle_json'], true);
        $salida[] = array(
            'uid' => (int) $p['uid'],
            'rol' => (string) $p['rol'],
            'username' => is_array($det) && isset($det['username']) ? (string) $det['username'] : '',
            'porcentaje' => (float) $p['porcentaje'],
            'pagado' => $p['aplicado_at'] !== null,
            'xp_estado' => (string) $p['xp_estado'],
            'calculo' => is_array($det) && isset($det['calculo']) ? $det['calculo'] : null,
        );
    }
    return $salida;
}
