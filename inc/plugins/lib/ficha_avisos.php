<?php
/**
 * Avisos de ficha modificada: avisar al usuario cuando el staff cambia su ficha (issue #22; 108, apartado 7,
 * requisitos 39, 40 y 41).
 *
 * Donde se avisa:
 *  - op/staff/ficha_atributos.php y op/staff/ficha_atributos2.php (editor de mybb_op_fichas): un guardado, un aviso.
 *  - op/staff/modificar_ficha.php (editor antiguo de mybb_sg_sg_fichas): ejecuta muchas escrituras por peticion; se lee la
 *    fila antes de la primera y despues de la ultima, y se envia un unico aviso al final con todos los cambios.
 * Donde no se avisa:
 *  - op/staff/ficha_recalcular_pasivas.php: recalculo tecnico (usa la opcion 'silenciosa', ver ese archivo).
 *  - op/staff/ficha_atributos_secret.php: identidad secreta, no se avisa (alcance de la issue).
 *  - La columna akuma: tiene su propio aviso (akuma_asignada / akuma_retirada, issue #18). No se repite aqui.
 *
 * El aviso lista los NOMBRES de los campos que cambiaron (no los valores, ni la razon, ni quien lo hizo). Solo cuentan los
 * campos de la lista blanca de cada editor; un campo interno o desconocido nunca aparece. Si no cambio ningun campo de la
 * lista, no se avisa.
 *
 * Reglas del aviso: una sola llamada a opg_notificar por guardado, despues de las escrituras (sin transacciones propias), y
 * nunca impide la accion (try/catch; sin la libreria de notificaciones o sin sus tablas, no pasa nada).
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/** Nombres de campo que se escriben en el aviso; el resto se resume como "y N mas". */
const OPG_FICHA_AVISO_MAX_CAMPOS = 8;

/** Largo maximo del texto de campos guardado (el sistema admite 255 por valor). */
const OPG_FICHA_AVISO_MAX_TEXTO = 200;

// ── Campos visibles por editor (puros) ──────────────────────────────────────────────────────────────────────────────────

/**
 * Campos de mybb_op_fichas (mas la experiencia, que vive en mybb_users.newpoints) que el usuario ve y que se avisan desde
 * ficha_atributos*.php: columna => nombre que se muestra. Quedan fuera akuma (aviso propio), secret1 (identidad secreta) y
 * los datos internos o tecnicos (hao_chance, wantedGuardado, movidoInframundo, temporada, dia, fx, como_nos_conociste).
 */
function opg_ficha_campos_atributos()
{
    $campos = array(
        'nombre' => 'Nombre', 'edad' => 'Edad', 'altura' => 'Altura', 'peso' => 'Peso', 'sexo' => 'Sexo',
        'raza' => 'Raza', 'faccion' => 'Facción', 'rango' => 'Rango', 'rango_inframundo' => 'Rango del Inframundo',
        'fama' => 'Fama', 'nivelnarrador' => 'Rango de narrador', 'camino' => 'Camino',
        'fisico_de_pj' => 'Apariencia', 'origen_de_pj' => 'Origen', 'cronologia' => 'Cronología',
        'nivel' => 'Nivel', 'puntos_experiencia' => 'Experiencia', 'puntos_estadistica' => 'Puntos de estadística',
        'berries' => 'Berries', 'nika' => 'Nikas', 'kuro' => 'Kuros', 'puntos_oficio' => 'Puntos de oficio',
        'fuerza' => 'Fuerza', 'resistencia' => 'Resistencia', 'destreza' => 'Destreza', 'voluntad' => 'Voluntad',
        'punteria' => 'Puntería', 'agilidad' => 'Agilidad', 'reflejos' => 'Reflejos', 'control_akuma' => 'Control de Akuma',
        'vitalidad_pasiva' => 'Vitalidad pasiva', 'energia_pasiva' => 'Energía pasiva', 'haki_pasiva' => 'Haki pasivo',
        'fuerza_pasiva' => 'Fuerza pasiva', 'resistencia_pasiva' => 'Resistencia pasiva',
        'destreza_pasiva' => 'Destreza pasiva', 'voluntad_pasiva' => 'Voluntad pasiva',
        'punteria_pasiva' => 'Puntería pasiva', 'agilidad_pasiva' => 'Agilidad pasiva',
        'reflejos_pasiva' => 'Reflejos pasivos', 'control_akuma_pasiva' => 'Control de Akuma pasivo',
        'akuma_subnombre' => 'Subnombre de la Akuma', 'akuma_origen' => 'Origen de la Akuma',
        'dominio_akuma' => 'Dominio de la Akuma',
        'kenbun' => 'Haki de observación', 'buso' => 'Haki de armadura', 'hao' => 'Haki del rey',
        'oficio1' => 'Oficio 1', 'oficio2' => 'Oficio 2', 'oficios' => 'Oficios', 'belicas' => 'Disciplinas',
        'elementos' => 'Elementos', 'implantes' => 'Implantes', 'equipamiento' => 'Equipamiento',
        'equipamiento_espacio' => 'Espacio de equipamiento', 'espacios' => 'Espacios', 'ranuras' => 'Ranuras',
        'wanted' => 'Recompensa', 'wanted_repu' => 'Reputación de la recompensa', 'muerto' => 'Estado de vida',
        'reputacion' => 'Reputación', 'reputacion_positiva' => 'Reputación positiva',
        'reputacion_negativa' => 'Reputación negativa', 'reputacion2' => 'Reputación secundaria',
        'reputacion_positiva2' => 'Reputación secundaria positiva', 'reputacion_negativa2' => 'Reputación secundaria negativa',
    );
    for ($i = 1; $i <= 12; $i++) {
        $campos['belica' . $i] = 'Disciplina ' . $i;
    }
    for ($i = 1; $i <= 4; $i++) {
        $campos['estilo' . $i] = 'Estilo ' . $i;
    }
    return $campos;
}

/**
 * Campos de mybb_sg_sg_fichas que escribe op/staff/modificar_ficha.php y se avisan: columna => nombre. Queda fuera notas
 * (nota privada del staff).
 */
function opg_ficha_campos_modificar()
{
    return array(
        'espe' => 'Especialización', 'espe_estilo' => 'Estilo de especialización',
        'maestria' => 'Maestría primaria', 'maestria_secundaria' => 'Maestría secundaria',
        'elemento1' => 'Elemento 1', 'elemento2' => 'Elemento 2', 'elemento3' => 'Elemento 3',
        'elemento4' => 'Elemento 4', 'elemento5' => 'Elemento 5', 'renunciar_elemento' => 'Renuncia al elemento extra',
        'invocacion' => 'Invocación primaria', 'invocacion_secundaria' => 'Invocación secundaria',
        'rango' => 'Rango', 'edad' => 'Edad', 'limite_nivel' => 'Límite de nivel', 'villa' => 'Villa',
        'slots' => 'Slots', 'pasiva_slot' => 'Pasiva de slot',
    );
}

// ── Comparacion y aviso (puros) ─────────────────────────────────────────────────────────────────────────────────────────

/** Valor comparable de una celda: texto sin espacios al borde; null, arrays y objetos cuentan como ''. */
function opg_ficha_valor($fila, $campo)
{
    if (!is_array($fila) || !array_key_exists($campo, $fila) || !is_scalar($fila[$campo])) {
        return '';
    }
    return trim((string) $fila[$campo]);
}

/**
 * Campos de $campos (columna => nombre) cuyo valor es distinto en $antes y en $despues, en el orden de $campos:
 * columna => nombre. Una columna que no esta en $despues no cuenta (no se leyo, no se sabe si cambio).
 */
function opg_ficha_cambios($antes, $despues, $campos)
{
    $cambios = array();
    if (!is_array($antes) || !is_array($despues) || !is_array($campos)) {
        return $cambios;
    }
    foreach ($campos as $campo => $nombre) {
        if (!array_key_exists($campo, $despues)) {
            continue;
        }
        if (opg_ficha_valor($antes, $campo) !== opg_ficha_valor($despues, $campo)) {
            $cambios[$campo] = (string) $nombre;
        }
    }
    return $cambios;
}

/**
 * Resumen de los nombres de campo para el aviso: array('campos' => 'A, B, C', 'mas' => N). Escribe hasta
 * OPG_FICHA_AVISO_MAX_CAMPOS nombres (sin repetir y sin pasar de OPG_FICHA_AVISO_MAX_TEXTO caracteres); 'mas' cuenta el
 * resto.
 */
function opg_ficha_resumen_campos($nombres)
{
    $unicos = array();
    foreach ((array) $nombres as $n) {
        $n = is_scalar($n) ? trim(preg_replace('/\s+/u', ' ', strip_tags((string) $n))) : '';
        if ($n !== '' && !in_array($n, $unicos, true)) {
            $unicos[] = $n;
        }
    }
    $escritos = array();
    $texto = '';
    foreach ($unicos as $n) {
        $siguiente = $texto === '' ? $n : $texto . ', ' . $n;
        if (count($escritos) >= OPG_FICHA_AVISO_MAX_CAMPOS || strlen($siguiente) > OPG_FICHA_AVISO_MAX_TEXTO) {
            break;
        }
        $escritos[] = $n;
        $texto = $siguiente;
    }
    return array('campos' => $texto, 'mas' => count($unicos) - count($escritos));
}

/**
 * Aviso de un guardado de ficha: array('tipo', 'destinatario', 'datos', 'clave'), o null si no hay que avisar (ningun
 * campo visible cambio o fid no valido). Si quien hace el cambio es el propio dueño de la ficha tambien se avisa: queda en
 * su historial.
 *
 * $cambios es lo que devuelve opg_ficha_cambios (columna => nombre); $despues da los valores nuevos para la marca. La clave
 * es ficha_mod:FID:MARCA, donde la marca es la hora del guardado y un resumen de los campos y sus valores nuevos: dos
 * guardados distintos en el mismo segundo no se pisan, y reenviar el mismo guardado no lo duplica.
 */
function opg_ficha_aviso($fid, $cambios, $despues, $actor_uid, $hora)
{
    $fid = (int) $fid;
    if ($fid <= 0 || !is_array($cambios) || !$cambios) {
        return null;
    }
    $resumen = opg_ficha_resumen_campos(array_values($cambios));
    if ($resumen['campos'] === '') {
        return null;
    }
    $huella = array();
    foreach (array_keys($cambios) as $campo) {
        $huella[] = $campo . '=' . opg_ficha_valor($despues, $campo);
    }
    $datos = array('fid' => $fid, 'campos' => $resumen['campos']);
    if ($resumen['mas'] > 0) {
        $datos['mas'] = $resumen['mas'];
    }
    return array(
        'tipo' => 'ficha_modificada',
        'destinatario' => $fid,
        'datos' => $datos,
        'clave' => 'ficha_mod:' . $fid . ':' . max(0, (int) $hora) . '.' . substr(md5(implode("\n", $huella)), 0, 10),
    );
}

// ── Lectura y envio ─────────────────────────────────────────────────────────────────────────────────────────────────────

/**
 * Fila de $tabla (mybb_op_fichas o mybb_sg_sg_fichas) con las columnas $columnas para la ficha $fid, o null si no existe,
 * la tabla no esta o la consulta falla. Solo se leen columnas que existen en la fila (SELECT *).
 */
function opg_ficha_leer($tabla, $fid, $columnas)
{
    global $db;
    $fid = (int) $fid;
    if ($fid <= 0 || !is_object($db) || !in_array($tabla, array('mybb_op_fichas', 'mybb_sg_sg_fichas'), true)) {
        return null;
    }
    try {
        $consulta = $db->query("SELECT * FROM `{$tabla}` WHERE `fid`='{$fid}' LIMIT 1", true);
        $fila = $consulta ? $db->fetch_array($consulta) : null;
        if (!is_array($fila)) {
            return null;
        }
        return array_intersect_key($fila, array_flip(array_keys($columnas)));
    } catch (Throwable $e) {
        return null;
    }
}

/** Experiencia (mybb_users.newpoints) del usuario $uid, o null si no se pudo leer. */
function opg_ficha_leer_experiencia($uid)
{
    global $db;
    $uid = (int) $uid;
    if ($uid <= 0 || !is_object($db)) {
        return null;
    }
    try {
        $consulta = $db->query("SELECT `newpoints` FROM `mybb_users` WHERE `uid`='{$uid}' LIMIT 1", true);
        $fila = $consulta ? $db->fetch_array($consulta) : null;
        return is_array($fila) && array_key_exists('newpoints', $fila) ? $fila['newpoints'] : null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Compara $antes y $despues en los campos $campos y, si cambio alguno, envia UN aviso ficha_modificada al dueno de la
 * ficha. Nunca lanza: devuelve lo que devuelve opg_notificar, o false si no hay aviso, la libreria no esta o algo falla.
 */
function opg_ficha_avisar_cambios($fid, $antes, $despues, $campos, $actor_uid, $hora = null)
{
    try {
        $aviso = opg_ficha_aviso($fid, opg_ficha_cambios($antes, $despues, $campos), $despues, $actor_uid,
            $hora === null ? time() : $hora);
        if ($aviso === null) {
            return false;
        }
        if (!function_exists('opg_notificar')) {
            $lib = __DIR__ . '/notificaciones.php';
            if (!is_file($lib)) {
                return false;
            }
            require_once $lib;
        }
        return opg_notificar($aviso['destinatario'], $aviso['tipo'], $aviso['datos'],
            array('actor' => (int) $actor_uid, 'incluir_actor' => true, 'clave' => $aviso['clave']));
    } catch (Throwable $e) {
        error_log('opg_ficha_avisar_cambios: ' . $e->getMessage());
        return false;
    }
}

/**
 * Guardado de ficha_atributos*.php: vuelve a leer la ficha y la experiencia despues de las escrituras y avisa una vez con
 * todo lo que cambio. $antes_ficha es la fila leida antes de guardar y $antes_experiencia el newpoints de entonces (o null
 * si no se leyo, y entonces la experiencia no se compara). Nunca lanza.
 */
function opg_ficha_avisar_atributos($fid, $antes_ficha, $antes_experiencia, $actor_uid, $hora = null)
{
    try {
        $campos = opg_ficha_campos_atributos();
        $despues = opg_ficha_leer('mybb_op_fichas', $fid, $campos);
        if (!is_array($despues) || !is_array($antes_ficha)) {
            return false;
        }
        $antes = $antes_ficha;
        if ($antes_experiencia !== null) {
            $experiencia = opg_ficha_leer_experiencia($fid);
            if ($experiencia !== null) {
                $antes['puntos_experiencia'] = $antes_experiencia;
                $despues['puntos_experiencia'] = $experiencia;
            }
        }
        return opg_ficha_avisar_cambios($fid, $antes, $despues, $campos, $actor_uid, $hora);
    } catch (Throwable $e) {
        error_log('opg_ficha_avisar_atributos: ' . $e->getMessage());
        return false;
    }
}
