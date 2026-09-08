<?php
/**
 * Tarea programada (registrada por inc/plugins/op_tasks.php): resuelve en
 * segundo plano las colas de entrenamiento de estadística
 * (mybb_op_entrenamientos_usuarios), oficio (mybb_op_oficios_usuarios) y
 * técnicas (mybb_op_tecnicas_usuarios) cuyo tiempo ya venció. Replica
 * exactamente la lógica de recompensa de entrenamiento.php / oficios.php /
 * entrenamiento_tecnicas.php (mismo resultado que si el jugador hubiera
 * entrado y pulsado "Reclamar"/"Culminar"), solo que no hace falta que el
 * jugador vuelva a la página para que se aplique.
 *
 * No sustituye la resolución al cargar página (que sigue intacta): el DELETE
 * con la condición de tiempo como único punto de exclusión mutua evita que
 * ambos caminos apliquen la recompensa dos veces (si el jugador reclama
 * primero, esta tarea simplemente no encuentra la fila y no hace nada).
 *
 * Fuera de alcance a propósito: crafteo.php y creacion.php (clonan objetos
 * dinámicamente vía darObjeto(), se abordarán aparte).
 */

function task_op_worldtick($task)
{
    global $db;

    require_once MYBB_ROOT . 'op/functions/op_functions.php';
    require_once MYBB_ROOT . 'inc/plugins/op_facciones.php';
    require_once MYBB_ROOT . 'inc/plugins/op_ficha.php';
    require_once MYBB_ROOT . 'inc/plugins/op_fama.php';
    require_once MYBB_ROOT . 'inc/plugins/op_niveles.php';

    $now = time();
    $total = 0;

    $total += op_worldtick_resolver_entrenamiento($db, $now);
    $total += op_worldtick_resolver_oficios($db, $now);
    $total += op_worldtick_resolver_tecnicas($db, $now);
    $total += op_worldtick_resolver_rango($db, $now);
    $total += op_worldtick_resolver_fama($db, $now);
    $total += op_worldtick_resolver_nivel($db, $now);
    $total += op_worldtick_resolver_nivel_bajada($db, $now);

    add_task_log($task, "op_worldtick: $total cola(s) resuelta(s).");
}

/**
 * Reputación de un personaje con los mismos modificadores de virtud/defecto
 * que op/personaje.php: V017 (virtud) la sube un 10%, D013 (defecto) la baja
 * un 10% (mutuamente excluyentes, V017 gana si un personaje tuviera los dos).
 * Devuelve [reputacion, reputacion_positiva, reputacion_negativa] ya con el
 * modificador aplicado a los 3 valores, igual que hace la página.
 */
function op_worldtick_reputacion_modificada($db, $uid, $reputacion_raw, $reputacion_positiva_raw = 0, $reputacion_negativa_raw = 0)
{
    $factor = 1;
    $tiene_v017 = $db->fetch_field($db->simple_select('op_virtudes_usuarios', 'virtud_id', "uid='".(int)$uid."' AND virtud_id='V017'", array('limit' => 1)), 'virtud_id');
    if ($tiene_v017) {
        $factor = 1.10;
    } else {
        $tiene_d013 = $db->fetch_field($db->simple_select('op_virtudes_usuarios', 'virtud_id', "uid='".(int)$uid."' AND virtud_id='D013'", array('limit' => 1)), 'virtud_id');
        if ($tiene_d013) {
            $factor = 0.9;
        }
    }

    return array(
        intval($reputacion_raw) * $factor,
        intval($reputacion_positiva_raw) * $factor,
        intval($reputacion_negativa_raw) * $factor,
    );
}

/**
 * Ascenso automático de rango — genérico para CUALQUIER facción/rango dado
 * de alta desde Admin CP → Configuración del foro → OPG Facciones, no solo
 * las 3 que tenían la escala hardcodeada antes (Marina/CipherPol/
 * Revolucionario). La lógica real vive en op_facciones_resolver_ascenso()
 * (inc/plugins/op_facciones.php), compartida con op/personaje.php, para que
 * ambos caminos apliquen siempre el mismo umbral configurado en el panel.
 * Solo sube un escalón por pasada (si la reputación diera para varios
 * saltos, se resuelve uno cada 5 minutos, no todos de golpe).
 */
function op_worldtick_resolver_rango($db, $now)
{
    $resueltos = 0;
    $query = $db->query("
        SELECT f.fid, f.faccion, f.rango, f.nivel, f.reputacion
        FROM mybb_op_fichas f
        INNER JOIN mybb_op_facciones_rangos r ON r.faccion = f.faccion AND r.valor = f.rango
    ");
    while ($ficha = $db->fetch_array($query)) {
        $uid = intval($ficha['fid']);
        $faccion = $ficha['faccion'];
        $rango_actual = $ficha['rango'];
        $nivel = intval($ficha['nivel']);

        list($reputacion) = op_worldtick_reputacion_modificada($db, $uid, $ficha['reputacion']);

        $rango_nuevo = op_facciones_resolver_ascenso($faccion, $rango_actual, $nivel, $reputacion);

        if ($rango_nuevo !== $rango_actual) {
            $db->query("UPDATE mybb_op_fichas SET rango='".$db->escape_string($rango_nuevo)."' WHERE fid='$uid'");
            $resueltos++;
        }
    }
    return $resueltos;
}

/**
 * Entrenamiento de estadística — mybb_op_entrenamientos_usuarios.
 * Misma recompensa que entrenamiento.php ($_POST['culminar']): suma
 * `recompensa` a mybb_users.newpoints vía log_audit_currency() + inserta en
 * mybb_op_audit_entrenamientos, igual que el original.
 */
function op_worldtick_resolver_entrenamiento($db, $now)
{
    $resueltos = 0;
    $query = $db->query("SELECT * FROM mybb_op_entrenamientos_usuarios WHERE timestamp_end <= $now");
    while ($entreno = $db->fetch_array($query)) {
        $uid = intval($entreno['uid']);

        // El DELETE con la misma condición de tiempo es el punto de exclusión
        // mutua: si el jugador ya lo reclamó entre el SELECT y aquí,
        // affected_rows() será 0 y no se aplica nada.
        $db->query("DELETE FROM mybb_op_entrenamientos_usuarios WHERE id='{$entreno['id']}' AND timestamp_end <= $now");
        if ($db->affected_rows() < 1) { continue; }

        $ficha_q = $db->query("SELECT nombre FROM mybb_op_fichas WHERE fid='$uid' LIMIT 1");
        $ficha = $db->fetch_array($ficha_q);
        if (!$ficha) { continue; }

        $user_q = $db->query("SELECT username, newpoints FROM mybb_users WHERE uid='$uid' LIMIT 1");
        $user = $db->fetch_array($user_q);
        if (!$user) { continue; }

        $nombre   = $ficha['nombre'];
        $username = $user['username'];
        $old_exp  = $user['newpoints'];
        $new_exp  = floatval($old_exp) + floatval($entreno['recompensa']);

        log_audit_currency($uid, $username, $uid, '[Entrenamiento][Experiencia]', 'experiencia', $new_exp);

        $db->query("
            INSERT INTO `mybb_op_audit_entrenamientos` (`fid`, `nombre`, `puntos_estadistica`, `pr`) VALUES
            ('$uid', '".$db->escape_string($nombre)."', '2', '$old_exp->$new_exp');
        ");

        $resueltos++;
    }
    return $resueltos;
}

/**
 * Oficio (habilidad de comercio) — mybb_op_oficios_usuarios.
 * Misma recompensa que oficios.php ($_POST['culminar']): suma `experiencia`
 * a mybb_op_fichas.puntos_oficio vía log_audit_currency() + inserta en
 * mybb_op_audit_oficios.
 */
function op_worldtick_resolver_oficios($db, $now)
{
    $resueltos = 0;
    $query = $db->query("SELECT * FROM mybb_op_oficios_usuarios WHERE timestamp_end <= $now");
    while ($entreno = $db->fetch_array($query)) {
        $uid = intval($entreno['uid']);

        $db->query("DELETE FROM mybb_op_oficios_usuarios WHERE id='{$entreno['id']}' AND timestamp_end <= $now");
        if ($db->affected_rows() < 1) { continue; }

        $ficha_q = $db->query("SELECT nombre, puntos_oficio FROM mybb_op_fichas WHERE fid='$uid' LIMIT 1");
        $ficha = $db->fetch_array($ficha_q);
        if (!$ficha) { continue; }

        $user_q = $db->query("SELECT username FROM mybb_users WHERE uid='$uid' LIMIT 1");
        $user = $db->fetch_array($user_q);
        if (!$user) { continue; }

        $nombre               = $ficha['nombre'];
        $username             = $user['username'];
        $old_exp              = $ficha['puntos_oficio'];
        $experienciaDeEntreno = $entreno['experiencia'];
        $new_exp              = floatval($old_exp) + floatval($experienciaDeEntreno);

        log_audit_currency($uid, $username, $uid, '[Entrenamiento][Puntos oficio]', 'puntos_oficio', $new_exp);

        $db->query("
            INSERT INTO `mybb_op_audit_oficios` (`fid`, `nombre`, `oficio`, `experiencia`, `progreso`) VALUES
            ('$uid', '".$db->escape_string($nombre)."', 'oficio', '$experienciaDeEntreno', '$old_exp->$new_exp');
        ");

        $resueltos++;
    }
    return $resueltos;
}

/**
 * Técnicas — mybb_op_tecnicas_usuarios.
 * Misma recompensa que entrenamiento_tecnicas.php ($_POST['tid_completo']):
 * desbloquea la técnica (mybb_op_tec_aprendidas), aplica el bonus pasivo
 * +5 fijo para las técnicas que lo llevan (mismo mapa que la página
 * original), e inserta en mybb_op_audit_entrenamiento_tecnicas.
 *
 * OJO: a diferencia de la página original (que confía en $_POST['tid_completo']
 * y podía no coincidir con la fila real en cola), aquí se usa el `tid`
 * guardado en la propia fila de mybb_op_tecnicas_usuarios — más correcto,
 * porque siempre coincide con lo que el jugador puso en cola.
 *
 * El original también calcula un campo `pr` de auditoría a partir de una
 * variable de página (`$puntos_rol`) con un coste que siempre es 0 (no hay
 * cambio real); aquí se simplifica ese campo de texto a "0->0" en vez de
 * reproducir esa variable de contexto, sin afectar a ninguna recompensa real.
 */
function op_worldtick_resolver_tecnicas($db, $now)
{
    $resueltos = 0;
    $query = $db->query("SELECT * FROM mybb_op_tecnicas_usuarios WHERE tiempo_finaliza <= $now");
    while ($entreno = $db->fetch_array($query)) {
        $uid = intval($entreno['uid']);
        $tid = $entreno['tid'];

        $db->query("DELETE FROM mybb_op_tecnicas_usuarios WHERE id='{$entreno['id']}' AND tiempo_finaliza <= $now");
        if ($db->affected_rows() < 1) { continue; }

        $ficha_q = $db->query("SELECT nombre, puntos_estadistica FROM mybb_op_fichas WHERE fid='$uid' LIMIT 1");
        $ficha = $db->fetch_array($ficha_q);
        if (!$ficha) { continue; }

        $nombre = $ficha['nombre'];
        $old_pe = $ficha['puntos_estadistica'];
        // El original no aplica ningún cambio real de puntos_estadistica pese
        // a calcular "$new_pe" (la línea que lo actualizaría está comentada
        // en entrenamiento_tecnicas.php) — se replica ese mismo comportamiento
        // tal cual, sin "arreglarlo", para no cambiar el balance del juego.
        $new_pe = $old_pe;

        $db->query("INSERT INTO `mybb_op_tec_aprendidas` (`tid`, `uid`) VALUES ('".$db->escape_string($tid)."', '$uid')");
        $db->query("
            INSERT INTO `mybb_op_audit_entrenamiento_tecnicas` (`fid`, `nombre`, `tid`, `puntos_estadistica`, `pr`, `tiempo_iniciado`, `tiempo_finaliza`) VALUES
            ('$uid', '".$db->escape_string($nombre)."', '".$db->escape_string($tid)."', '$old_pe->$new_pe', '0->0', '{$entreno['tiempo_iniciado']}', '{$entreno['tiempo_finaliza']}');
        ");

        // Mismo mapa fijo +5 de bonus pasivo que entrenamiento_tecnicas.php
        // (líneas 128-154 de ese archivo).
        $pasiva_map = array(
            'fuerza_pasiva'      => array('DCO001', 'DEP001', 'DGU001', 'DAQ002', 'DAM001'),
            'resistencia_pasiva' => array('DCO002', 'DAT002', 'DEC001'),
            'agilidad_pasiva'    => array('DAS002', 'DAM002'),
            'destreza_pasiva'    => array('DEP002', 'DGU002', 'DTC001', 'DRT002', 'DAS001'),
            'punteria_pasiva'    => array('DTI001', 'DAQ001', 'DAT001', 'DPI001'),
            'reflejos_pasiva'    => array('DTI002', 'DTC002', 'DPI002', 'DEC002'),
            'voluntad_pasiva'    => array('DRT001'),
        );
        foreach ($pasiva_map as $columna => $tids) {
            if (in_array($tid, $tids, true)) {
                $db->query("UPDATE `mybb_op_fichas` SET `$columna`=`$columna`+5 WHERE fid='$uid'");
            }
        }

        $resueltos++;
    }
    return $resueltos;
}

/**
 * Recálculo de fama por reputación — misma tabla que op/personaje.php,
 * compartida vía op_fama_tabla() (inc/plugins/op_fama.php), gestionable
 * desde Configuración del foro → OPG Fama.
 */
function op_worldtick_resolver_fama($db, $now)
{
    $resueltos = 0;
    $query = $db->query("SELECT fid, fama, reputacion, reputacion_positiva, reputacion_negativa FROM mybb_op_fichas");
    while ($ficha = $db->fetch_array($query)) {
        $uid = intval($ficha['fid']);

        list($reputacion, $reputacionPositiva, $reputacionNegativa) = op_worldtick_reputacion_modificada(
            $db, $uid, $ficha['reputacion'], $ficha['reputacion_positiva'], $ficha['reputacion_negativa']
        );

        $reputacionPerc = 50;
        if ($reputacion != 0) {
            $reputacionPerc = round(($reputacionPositiva / $reputacion) * 100);
        }

        $fama = 'Desconocido';
        foreach (op_fama_tabla() as $fila) {
            list($rep_min, $perc_malo, $perc_bueno, $nombre_b, $nombre_n, $nombre_m) = $fila;
            if ($reputacion >= $rep_min) {
                if      ($reputacionPerc >= $perc_bueno) $fama = $nombre_b;
                else if ($reputacionPerc <= $perc_malo)  $fama = $nombre_m;
                else                                     $fama = $nombre_n;
                break;
            }
        }

        if ($fama != $ficha['fama']) {
            $db->query("UPDATE mybb_op_fichas SET fama='".$db->escape_string($fama)."' WHERE fid='$uid'");
            $resueltos++;
        }
    }
    return $resueltos;
}

/**
 * Subida de nivel — misma tabla que op/personaje.php, compartida vía
 * op_niveles_tabla()/op_niveles_bono_puntos() (inc/plugins/op_niveles.php),
 * gestionable desde Configuración del foro → OPG Niveles. Solo sube un
 * nivel por pasada, igual que la página (si la experiencia diera para
 * varios, se resuelve uno cada 5 minutos, no todos de golpe).
 */
function op_worldtick_resolver_nivel($db, $now)
{
    $resueltos = 0;
    $exp_tabla = op_niveles_tabla();
    $puntos_bonus_nivel = op_niveles_bono_puntos();
    $limite_temporal_activo = op_niveles_limite_temporal() ?? PHP_INT_MAX;

    $query = $db->query("
        SELECT f.fid, f.nivel, f.limite_nivel, f.raza, f.puntos_estadistica, u.newpoints
        FROM mybb_op_fichas f
        INNER JOIN mybb_users u ON u.uid = f.fid
    ");
    while ($ficha = $db->fetch_array($query)) {
        $uid = intval($ficha['fid']);
        $experiencia = intval(floor($ficha['newpoints']));
        $nivel = $ficha['nivel'];
        $limite_nivel = min(intval($ficha['limite_nivel']), $limite_temporal_activo);
        $raza = $ficha['raza'];
        $puntos_estadistica = intval($ficha['puntos_estadistica']);

        foreach ($exp_tabla as $nv => $rango_vals) {
            $exp_min_nv = $rango_vals[0];
            $nivel_anterior = $nv - 1;
            $requiere_limite = $nv > 20;
            if ($experiencia >= $exp_min_nv && $nivel == (string)$nivel_anterior &&
                (!$requiere_limite || $limite_nivel > $nivel_anterior)) {
                $nivel_nuevo = $nv;
                $puntos_estadistica += isset($puntos_bonus_nivel[$nv]) ? $puntos_bonus_nivel[$nv] : 10;
                $db->query("UPDATE mybb_op_fichas SET nivel='".$db->escape_string($nivel_nuevo)."', puntos_estadistica='$puntos_estadistica' WHERE fid='$uid'");
                if ($raza == 'Skypian') {
                    $db->query("UPDATE mybb_op_fichas SET energia_pasiva=energia_pasiva + 5 WHERE fid='$uid'");
                }
                $resueltos++;
                break;
            }
        }
    }
    return $resueltos;
}

/**
 * Bajada de nivel (reversa) — mismo criterio que op/personaje.php: si al
 * retocar la tabla de experiencia el mínimo del nivel actual del personaje
 * queda por encima de su experiencia real, se baja de nivel automáticamente
 * y se retiran los puntos de estadística otorgados por cada nivel perdido
 * (puede quedar negativo a propósito, señal de que hay más stats asignadas
 * de las que el nuevo nivel permite). A diferencia de la subida, resuelve
 * TODOS los niveles perdidos en una sola pasada (es una corrección, no un
 * momento a saborear), igual que la página.
 */
function op_worldtick_resolver_nivel_bajada($db, $now)
{
    $resueltos = 0;
    $exp_tabla = op_niveles_tabla();
    $puntos_bonus_nivel = op_niveles_bono_puntos();

    $query = $db->query("
        SELECT f.fid, f.nivel, f.raza, f.puntos_estadistica, u.newpoints, u.username
        FROM mybb_op_fichas f
        INNER JOIN mybb_users u ON u.uid = f.fid
        WHERE f.nivel > 1
    ");
    while ($ficha = $db->fetch_array($query)) {
        $uid = intval($ficha['fid']);
        $experiencia = intval(floor($ficha['newpoints']));
        $nivel = $ficha['nivel'];
        $raza = $ficha['raza'];
        $puntos_estadistica = intval($ficha['puntos_estadistica']);
        $username = $ficha['username'];

        $niveles_bajados = 0;
        while (intval($nivel) > 1) {
            $exp_min_actual = isset($exp_tabla[intval($nivel)]) ? $exp_tabla[intval($nivel)][0] : 0;
            if ($experiencia >= $exp_min_actual) break;
            $nivel_perdido = intval($nivel);
            $nivel = $nivel_perdido - 1;
            $puntos_estadistica -= isset($puntos_bonus_nivel[$nivel_perdido]) ? $puntos_bonus_nivel[$nivel_perdido] : 10;
            $niveles_bajados++;
        }

        if ($niveles_bajados > 0) {
            $db->query("UPDATE mybb_op_fichas SET nivel='".$db->escape_string($nivel)."', puntos_estadistica='$puntos_estadistica', nivel_ajustado='1' WHERE fid='$uid'");
            if ($raza == 'Skypian') {
                $energia_pasiva_resta = $niveles_bajados * 5;
                $db->query("UPDATE mybb_op_fichas SET energia_pasiva=energia_pasiva - $energia_pasiva_resta WHERE fid='$uid'");
            }
            log_audit($uid, $username ?: ('UID '.$uid), '[Sistema][Ajuste de Nivel]',
                "Nivel bajado automáticamente $niveles_bajados nivel(es) (reajuste de tabla de experiencia) a $nivel. Puntos de estadística resultantes: $puntos_estadistica.");
            $resueltos++;
        }
    }
    return $resueltos;
}
