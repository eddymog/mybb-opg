<?php
/**
 * Staff - Salario de staff (kuros por rol)
 *
 * Paga kuros a miembros del staff según su grupo: 500 (admin), 400 (staff/mod)
 * o 200 (narrador) — en lote (checkboxes) o a una UID puntual, con monto
 * personalizado opcional.
 *
 * Cada pago corre en una transacción (todo o nada), lee el saldo con FOR UPDATE
 * y se registra en `mybb_op_salario_staff_pagos` (historial filtrable/paginado).
 *
 * Reescrito por 3 problemas reales que tenía:
 * 1. CSRF: ninguna de las dos acciones llevaba my_post_key — sobre una
 *    herramienta que acuña kuros para admin/staff, eso es manipulación de
 *    economía al nivel más alto posible.
 * 2. `$is_admin` se recalculaba a mano leyendo `usergroup`/`additionalgroups`
 *    en vez de usar `is_admin($uid)`, que ya existe en op_functions.php y
 *    hace exactamente eso.
 * 3. El log de repartos (`$salarios_kuros`) se pintaba sin escapar.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'salario_staff.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];
$username = $mybb->user['username'];

if (!is_admin($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}

define('SALARIO_STAFF_MSG_COOKIE', 'salario_staff_msg');
define('SALARIO_STAFF_POR_PAGINA', 25);
$SALARIO_ADMIN_GROUPS = array('4');
$SALARIO_STAFF_GROUPS = array('3', '6', '14', '16');
$SALARIO_NARRA_GROUPS = array('15');
$SALARIO_MONTO_ADMIN = 500;
$SALARIO_MONTO_STAFF = 400;
$SALARIO_MONTO_NARRA = 200;

// Historial propio: antes se rastreaba con LIKE sobre el texto libre de consola_mod.
$db->query("
    CREATE TABLE IF NOT EXISTS `mybb_op_salario_staff_pagos` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tiempo` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `pagador_uid` int(11) NOT NULL,
        `pagador` varchar(100) NOT NULL,
        `uid` int(11) NOT NULL,
        `nombre` varchar(100) NOT NULL,
        `monto` int(11) NOT NULL,
        `tipo` varchar(10) NOT NULL,
        `saldo_antes` int(11) NOT NULL,
        `saldo_despues` int(11) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `uid` (`uid`),
        KEY `tiempo` (`tiempo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8
");

// Quién cobra de verdad: sin fila = cobra (por defecto todas las cuentas con rol cobran).
$db->query("
    CREATE TABLE IF NOT EXISTS `mybb_op_salario_staff_config` (
        `uid` int(11) NOT NULL,
        `cobra` tinyint(1) NOT NULL DEFAULT 1,
        `actualizado` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `actualizado_por` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`uid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8
");

/** Rol efectivo (el más alto) y monto de un usuario según sus grupos. */
function salario_staff_rol($row, $admin_groups, $staff_groups, $narra_groups)
{
    global $SALARIO_MONTO_ADMIN, $SALARIO_MONTO_STAFF, $SALARIO_MONTO_NARRA;
    $groups = array_map('trim', explode(',', $row['additionalgroups']));
    $groups[] = (string) (int) $row['usergroup'];

    if (array_intersect($admin_groups, $groups)) { return array('rol' => 'Administradores', 'monto' => $SALARIO_MONTO_ADMIN); }
    if (array_intersect($staff_groups, $groups)) { return array('rol' => 'Staff / Mods', 'monto' => $SALARIO_MONTO_STAFF); }
    if (array_intersect($narra_groups, $groups)) { return array('rol' => 'Narradores', 'monto' => $SALARIO_MONTO_NARRA); }
    return array('rol' => '', 'monto' => 0);
}

/**
 * Cuentas con rol de salario (con o sin ficha), ordenadas por nombre.
 * Cada fila trae además 'rol' y 'monto'. LEFT JOIN: el staff sin ficha no desaparece.
 */
function salario_staff_elegibles()
{
    global $db, $SALARIO_ADMIN_GROUPS, $SALARIO_STAFF_GROUPS, $SALARIO_NARRA_GROUPS;
    $q = $db->query("
        SELECT u.uid, u.usergroup, u.additionalgroups, u.username, f.fid AS ficha, f.nombre
        FROM mybb_users u LEFT JOIN mybb_op_fichas f ON f.fid = u.uid
        WHERE u.usergroup IN (3,4,6,14,15,16)
           OR FIND_IN_SET('3',  u.additionalgroups) > 0
           OR FIND_IN_SET('4',  u.additionalgroups) > 0
           OR FIND_IN_SET('6',  u.additionalgroups) > 0
           OR FIND_IN_SET('14', u.additionalgroups) > 0
           OR FIND_IN_SET('15', u.additionalgroups) > 0
           OR FIND_IN_SET('16', u.additionalgroups) > 0
        ORDER BY f.nombre ASC, u.username ASC
    ");
    $out = array();
    while ($row = $db->fetch_array($q)) {
        $r = salario_staff_rol($row, $SALARIO_ADMIN_GROUPS, $SALARIO_STAFF_GROUPS, $SALARIO_NARRA_GROUPS);
        if (!$r['monto']) { continue; }
        $row['rol'] = $r['rol'];
        $row['monto'] = $r['monto'];
        $out[] = $row;
    }
    return $out;
}

/** uid => bool (cobra). Las cuentas sin fila cobran por defecto. */
function salario_staff_config()
{
    global $db;
    $map = array();
    $q = $db->query("SELECT uid, cobra FROM `mybb_op_salario_staff_config`");
    while ($c = $db->fetch_array($q)) { $map[(int) $c['uid']] = (int) $c['cobra'] === 1; }
    return $map;
}

/** Corre una query y lanza excepción si falla (para poder hacer ROLLBACK). */
function salario_staff_q($sql)
{
    global $db;
    $res = $db->query($sql);
    if ($res === false) {
        throw new RuntimeException('Falló una consulta del pago.');
    }
    return $res;
}

/**
 * Suma `$monto` kuros a la ficha $fid. Debe llamarse dentro de una transacción.
 * El saldo se lee con FOR UPDATE y el UPDATE es relativo, así que el log nunca
 * guarda un saldo viejo aunque otro proceso toque los kuros a la vez.
 */
function salario_staff_pagar($staff_uid, $staff_username, $fid, $nombre, $monto, $tipo)
{
    global $db;
    $f = $db->fetch_array(salario_staff_q("SELECT kuro FROM `mybb_op_fichas` WHERE fid = {$fid} FOR UPDATE"));
    if (!$f) {
        throw new RuntimeException("La ficha {$fid} no existe.");
    }
    $antes = (int) $f['kuro'];
    $despues = $antes + $monto;

    salario_staff_q("UPDATE `mybb_op_fichas` SET kuro = kuro + {$monto} WHERE fid = {$fid}");

    $categoria = $db->escape_string("[Kuros][Salario Roles +{$monto}]");
    $log = $db->escape_string("Kuros: {$antes}->{$despues} (+{$monto})");
    salario_staff_q("INSERT INTO `mybb_op_audit_general` (`uid`, `username`, `user_uid`, `categoria`, `log`) VALUES "
        . "('{$staff_uid}', '" . $db->escape_string($staff_username) . "', '{$fid}', '{$categoria}', '{$log}')");

    salario_staff_q("INSERT INTO `mybb_op_salario_staff_pagos` (`pagador_uid`, `pagador`, `uid`, `nombre`, `monto`, `tipo`, `saldo_antes`, `saldo_despues`) VALUES "
        . "({$staff_uid}, '" . $db->escape_string($staff_username) . "', {$fid}, '" . $db->escape_string($nombre) . "', {$monto}, '" . $db->escape_string($tipo) . "', {$antes}, {$despues})");
}

function salario_staff_kuros($n)
{
    return number_format((int) $n, 0, ',', '.');
}

// ── POST: pagar ───────────────────────────────────────────────────────────────

if ($mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);
    $error = '';
    $ok = '';

    if ($accion === 'lote') {
        $uids_seleccionados = array_values(array_unique(array_filter(array_map('intval', $mybb->get_input('uids_seleccionados', MyBB::INPUT_ARRAY)))));

        if (empty($uids_seleccionados)) {
            $error = 'No se seleccionó a ningún miembro del staff.';
        } else {
            $uids_in = implode(',', $uids_seleccionados);
            $q_roles = $db->query("
                SELECT u.uid, u.usergroup, u.additionalgroups, f.nombre
                FROM mybb_users u INNER JOIN mybb_op_fichas f ON f.fid = u.uid
                WHERE u.uid IN ({$uids_in})
                ORDER BY u.uid ASC
            ");

            $pagos = array();
            while ($row = $db->fetch_array($q_roles)) {
                $r = salario_staff_rol($row, $SALARIO_ADMIN_GROUPS, $SALARIO_STAFF_GROUPS, $SALARIO_NARRA_GROUPS);
                if ($r['monto'] > 0) { $pagos[] = array('row' => $row, 'monto' => $r['monto']); }
            }

            if (empty($pagos)) {
                $error = 'Ninguno de los seleccionados tiene ficha y rol elegible. No se pagó nada.';
            } else {
                try {
                    // Todo el lote en una transacción: o cobran todos o no cobra nadie.
                    salario_staff_q("START TRANSACTION");
                    $nombres_id = array();
                    $total = 0;
                    foreach ($pagos as $p) {
                        $fid = (int) $p['row']['uid'];
                        $monto = (int) $p['monto'];
                        salario_staff_pagar($uid, $username, $fid, $p['row']['nombre'], $monto, 'rol');
                        $total += $monto;
                        // Nombre escapado a mano porque el log se guarda y se pinta como HTML de
                        // verdad (el link es intencional, igual que en log_entregas.php).
                        $nombres_id[] = htmlspecialchars($p['row']['nombre'], ENT_QUOTES, 'UTF-8') . " (<a href='/op/personaje.php?uid={$fid}'>{$fid}</a>) +{$monto}";
                    }

                    $log_texto = "[Salario Kuros Roles] Kuros por rol entregados por {$username} ({$uid}) a las: " . date('Y-m-d H:i:s') . ". Pagos: " . implode(', ', $nombres_id) . '.';
                    salario_staff_q("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
                        . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Salario Roles', '" . $db->escape_string($log_texto) . "')");
                    salario_staff_q("COMMIT");

                    $ok = 'Se pagó a ' . count($pagos) . ' miembros del staff (' . salario_staff_kuros($total) . ' kuros en total).';
                    $omitidos = count($uids_seleccionados) - count($pagos);
                    if ($omitidos > 0) {
                        $ok .= " Se omitieron {$omitidos} sin ficha o sin rol elegible.";
                    }
                } catch (Throwable $e) {
                    $db->query("ROLLBACK");
                    $error = 'El pago falló y se revirtió completo, no se entregó ningún kuro. Intenta de nuevo.';
                }
            }
        }
    } elseif ($accion === 'uid') {
        $uid_pagar = (int) $mybb->get_input('uid_pagar', MyBB::INPUT_INT);
        $custom_kuros = (int) $mybb->get_input('custom_kuros', MyBB::INPUT_INT);

        if (!$uid_pagar) {
            $error = 'UID inválida.';
        } else {
            $row = $db->fetch_array($db->query("
                SELECT u.uid, u.usergroup, u.additionalgroups, f.nombre
                FROM mybb_users u INNER JOIN mybb_op_fichas f ON f.fid = u.uid
                WHERE u.uid = {$uid_pagar} LIMIT 1
            "));

            if (!$row) {
                $error = 'No se encontró ficha para esa UID.';
            } else {
                $r = salario_staff_rol($row, $SALARIO_ADMIN_GROUPS, $SALARIO_STAFF_GROUPS, $SALARIO_NARRA_GROUPS);
                $amount = $custom_kuros > 0 ? $custom_kuros : $r['monto'];
                if (!$amount) {
                    $error = 'El usuario no pertenece a ningún grupo de staff.';
                } else {
                    try {
                        salario_staff_q("START TRANSACTION");
                        salario_staff_pagar($uid, $username, $uid_pagar, $row['nombre'], $amount, 'uid');

                        // Nombre escapado a mano: el log se guarda y se pinta como HTML de verdad
                        // (ver el mismo criterio en la rama "lote" de arriba y en log_entregas.php).
                        $nombre_esc = htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8');
                        $log_texto = "[Salario Kuros Roles UID] +{$amount} kuros entregados a {$nombre_esc} (<a href='/op/personaje.php?uid={$uid_pagar}'>{$uid_pagar}</a>) por {$username} ({$uid}) a las: " . date('Y-m-d H:i:s') . '.';
                        salario_staff_q("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
                            . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Salario Roles UID', '" . $db->escape_string($log_texto) . "')");
                        salario_staff_q("COMMIT");

                        $ok = '+' . salario_staff_kuros($amount) . ' kuros entregados a ' . $row['nombre'] . '.';
                    } catch (Throwable $e) {
                        $db->query("ROLLBACK");
                        $error = 'El pago falló y se revirtió, no se entregó ningún kuro. Intenta de nuevo.';
                    }
                }
            }
        }
    } elseif ($accion === 'config') {
        // Solo se tocan cuentas que realmente tienen rol de salario (lo demás se ignora).
        $elegibles = array();
        foreach (salario_staff_elegibles() as $e) { $elegibles[(int) $e['uid']] = $e; }
        $cobran = array_flip(array_map('intval', $mybb->get_input('cobran', MyBB::INPUT_ARRAY)));
        $antes = salario_staff_config();

        $dejan = array();
        $vuelven = array();
        try {
            salario_staff_q("START TRANSACTION");
            foreach ($elegibles as $fid => $e) {
                $nuevo = isset($cobran[$fid]);
                $previo = isset($antes[$fid]) ? $antes[$fid] : true;
                $nombre = $e['ficha'] !== null ? $e['nombre'] : $e['username'];
                if ($nuevo !== $previo) {
                    if ($nuevo) { $vuelven[] = "{$nombre} ({$fid})"; } else { $dejan[] = "{$nombre} ({$fid})"; }
                }
                salario_staff_q("REPLACE INTO `mybb_op_salario_staff_config` (`uid`, `cobra`, `actualizado_por`) VALUES ({$fid}, " . ($nuevo ? 1 : 0) . ", {$uid})");
            }
            if ($dejan || $vuelven) {
                $log_texto = "[Salario Staff Config] {$username} ({$uid}) actualizó quién cobra el salario a las: " . date('Y-m-d H:i:s') . '.'
                    . ($dejan ? ' Dejan de cobrar: ' . implode(', ', $dejan) . '.' : '')
                    . ($vuelven ? ' Vuelven a cobrar: ' . implode(', ', $vuelven) . '.' : '');
                salario_staff_q("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES "
                    . "('" . $uid . "', '" . $db->escape_string($username) . "', 'Salario Config', '" . $db->escape_string(htmlspecialchars($log_texto, ENT_QUOTES, 'UTF-8')) . "')");
            }
            salario_staff_q("COMMIT");
            $ok = 'Configuración guardada: cobran ' . count($cobran) . ' de ' . count($elegibles) . ' cuentas.';
        } catch (Throwable $e) {
            $db->query("ROLLBACK");
            $error = 'No se pudo guardar la configuración, no se cambió nada.';
        }
    } else {
        $error = 'Acción desconocida.';
    }

    my_setcookie(SALARIO_STAFF_MSG_COOKIE, base64_encode(json_encode(array(
        'tipo'  => $error !== '' ? 'err' : 'ok',
        'texto' => $error !== '' ? $error : $ok,
    ))), 15, true);
    header('Location: salario_staff.php');
    exit;
}

// ── GET: panel + historial ───────────────────────────────────────────────────

$post_key = generate_post_check();

// El mensaje viaja en una cookie (el cliente puede alterarla): se escapa al pintarlo.
$msg_ok = '';
$msg_err = '';
if (!empty($mybb->cookies[SALARIO_STAFF_MSG_COOKIE])) {
    $msg = json_decode(base64_decode($mybb->cookies[SALARIO_STAFF_MSG_COOKIE]), true);
    if (is_array($msg) && isset($msg['tipo'], $msg['texto'])) {
        $texto = htmlspecialchars((string) $msg['texto'], ENT_QUOTES, 'UTF-8');
        if ($msg['tipo'] === 'ok') { $msg_ok = $texto; } else { $msg_err = $texto; }
    }
    my_unsetcookie(SALARIO_STAFF_MSG_COOKIE);
}

$monto_admin = salario_staff_kuros($SALARIO_MONTO_ADMIN);
$monto_staff = salario_staff_kuros($SALARIO_MONTO_STAFF);
$monto_narra = salario_staff_kuros($SALARIO_MONTO_NARRA);

// Último pago por persona, para mostrarlo junto a cada checkbox.
$ultimo_pago = array();
$q_ultimo = $db->query("SELECT uid, MAX(tiempo) AS t FROM `mybb_op_salario_staff_pagos` GROUP BY uid");
while ($u = $db->fetch_array($q_ultimo)) {
    $ultimo_pago[(int) $u['uid']] = strtotime($u['t']);
}

$config = salario_staff_config();
$elegibles = salario_staff_elegibles();

$grupos = array('Administradores' => '', 'Staff / Mods' => '', 'Narradores' => '', 'Sin ficha' => '', 'No cobran' => '');
$conteo = array('Administradores' => 0, 'Staff / Mods' => 0, 'Narradores' => 0, 'Sin ficha' => 0, 'No cobran' => 0);
$config_grupos = array('Administradores' => '', 'Staff / Mods' => '', 'Narradores' => '');
$cobran_total = 0;
$ahora = time();
foreach ($elegibles as $row) {
    $fid = (int) $row['uid'];
    $tiene_ficha = $row['ficha'] !== null;
    $cobra = isset($config[$fid]) ? $config[$fid] : true;
    $nombre = htmlspecialchars($tiene_ficha ? $row['nombre'] : $row['username'], ENT_QUOTES, 'UTF-8');
    $buscar = htmlspecialchars(mb_strtolower($nombre . ' ' . $row['username'] . ' ' . $fid), ENT_QUOTES, 'UTF-8');
    if ($cobra) { $cobran_total++; }

    // Panel de configuración: todas las cuentas, marcadas las que cobran.
    $config_grupos[$row['rol']] .= '<label class="ss-item" data-buscar="' . $buscar . '">'
        . '<input type="checkbox" name="cobran[]" value="' . $fid . '" class="chk-config"' . ($cobra ? ' checked' : '') . '>'
        . '<span class="ss-nombre">' . $nombre . ' <small>(UID ' . $fid . ($tiene_ficha ? '' : ', sin ficha') . ')</small></span>'
        . '<span class="ss-monto">+' . salario_staff_kuros($row['monto']) . ' kuros</span>'
        . '</label>';

    $ultimo = '<span class="ss-ultimo ss-ultimo--nunca">Sin pagos</span>';
    if (isset($ultimo_pago[$fid])) {
        $dias = (int) floor(($ahora - $ultimo_pago[$fid]) / 86400);
        $reciente = $dias < 7 ? ' ss-ultimo--reciente' : '';
        $cuando = $dias <= 0 ? 'hoy' : 'hace ' . $dias . ($dias == 1 ? ' día' : ' días');
        $ultimo = '<span class="ss-ultimo' . $reciente . '" title="' . date('Y-m-d H:i', $ultimo_pago[$fid]) . '">Último: ' . $cuando . '</span>';
    }

    // Las que no cobran quedan en un bloque oculto: se pueden marcar a mano, pero "Todos" no las toma.
    $clave = !$cobra ? 'No cobran' : ($tiene_ficha ? $row['rol'] : 'Sin ficha');
    $disabled = $tiene_ficha ? '' : ' disabled';
    $extra = $clave === 'No cobran' ? ' chk-extra' : '';
    $grupos[$clave] .= '<label class="ss-item' . ($tiene_ficha ? '' : ' ss-item--off') . '" data-buscar="' . $buscar . '">'
        . '<input type="checkbox" name="uids_seleccionados[]" value="' . $fid . '" class="chk-staff-rol' . $extra . '" data-monto="' . $row['monto'] . '" data-grupo="' . htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') . '"' . $disabled . '>'
        . '<span class="ss-nombre">' . $nombre . ' <small>(UID ' . $fid . ')</small></span>'
        . ($tiene_ficha ? $ultimo : '<span class="ss-ultimo ss-ultimo--nunca">No tiene ficha, no se puede pagar</span>')
        . '<span class="ss-monto">+' . salario_staff_kuros($row['monto']) . ' kuros</span>'
        . '</label>';
    $conteo[$clave]++;
}

$checkboxes_staff = '';
foreach ($grupos as $titulo => $html) {
    if ($html === '' || $titulo === 'No cobran') { continue; }
    $titulo_esc = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $todos = $titulo === 'Sin ficha' ? '' : '<label class="ss-grupo-todos"><input type="checkbox" class="chk-grupo" data-grupo="' . $titulo_esc . '"> Todos</label>';
    $checkboxes_staff .= '<div class="ss-grupo">'
        . '<div class="ss-grupo-cabecera"><span>' . $titulo_esc . ' (' . $conteo[$titulo] . ')</span>' . $todos . '</div>'
        . $html . '</div>';
}
if ($checkboxes_staff === '') {
    $checkboxes_staff = '<p class="opg-vacio">No hay cuentas marcadas para cobrar. Revisa la configuración de abajo.</p>';
}

// Bloque oculto con las cuentas que no cobran (se muestra con el enlace de abajo).
$bloque_no_cobran = '';
if ($conteo['No cobran'] > 0) {
    $bloque_no_cobran = '<a href="#" class="ss-toggle-extras" id="ss-toggle-extras">Mostrar las ' . $conteo['No cobran'] . ' cuentas que no cobran</a>'
        . '<div class="ss-grupo ss-grupo--extras" id="ss-extras" style="display:none">'
        . '<div class="ss-grupo-cabecera"><span>No cobran (' . $conteo['No cobran'] . ')</span></div>'
        . $grupos['No cobran'] . '</div>';
}

$config_html = '';
foreach ($config_grupos as $titulo => $html) {
    if ($html === '') { continue; }
    $config_html .= '<div class="ss-grupo"><div class="ss-grupo-cabecera"><span>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</span>'
        . '<label class="ss-grupo-todos"><input type="checkbox" class="chk-config-grupo"> Todas</label></div>' . $html . '</div>';
}
$config_resumen = $cobran_total . ' de ' . count($elegibles) . ' cuentas cobran';

// ── Historial: filtro por nombre/UID + paginación por lote ───────────────────

/** "hoy", "ayer" o "hace N días" (con la fecha completa en el title de quien lo use). */
function salario_staff_hace($ts)
{
    $dias = (int) floor((time() - $ts) / 86400);
    if ($dias <= 0) { return 'hoy'; }
    if ($dias == 1) { return 'ayer'; }
    return 'hace ' . $dias . ' días';
}

$filtro = trim($mybb->get_input('q', MyBB::INPUT_STRING));
$filtro_esc = htmlspecialchars($filtro, ENT_QUOTES, 'UTF-8');
$where = '1=1';
if ($filtro !== '') {
    $like = $db->escape_string_like($filtro);
    $where = "(nombre LIKE '%{$like}%' OR pagador LIKE '%{$like}%'" . (ctype_digit($filtro) ? ' OR uid = ' . (int) $filtro : '') . ')';
}

$resumen = $db->fetch_array($db->query("SELECT COUNT(*) AS n, COALESCE(SUM(monto), 0) AS total FROM `mybb_op_salario_staff_pagos` WHERE {$where}"));
$total_filas = (int) $resumen['n'];

// Un "lote" = pagos del mismo pagador dentro del mismo minuto (un clic en "Pagar seleccionados").
$minuto = "DATE_FORMAT(tiempo, '%Y-%m-%d %H:%i')";
$total_lotes = (int) $db->fetch_field($db->query("
    SELECT COUNT(*) AS n FROM (SELECT 1 FROM `mybb_op_salario_staff_pagos` WHERE {$where} GROUP BY pagador_uid, {$minuto}) g
"), 'n');
$total_paginas = max(1, (int) ceil($total_lotes / SALARIO_STAFF_POR_PAGINA));
$pagina = min($total_paginas, max(1, (int) $mybb->get_input('pagina', MyBB::INPUT_INT)));
$offset = ($pagina - 1) * SALARIO_STAFF_POR_PAGINA;

$lotes = array();
$q_lotes = $db->query("
    SELECT pagador_uid, {$minuto} AS minuto, MAX(id) AS ultimo
    FROM `mybb_op_salario_staff_pagos`
    WHERE {$where}
    GROUP BY pagador_uid, minuto
    ORDER BY ultimo DESC LIMIT {$offset}, " . SALARIO_STAFF_POR_PAGINA
);
$condiciones = array();
while ($l = $db->fetch_array($q_lotes)) {
    $lotes[(int) $l['pagador_uid'] . '|' . $l['minuto']] = array('pagos' => array(), 'total' => 0, 'pagador' => '', 'ts' => 0);
    $condiciones[] = '(pagador_uid = ' . (int) $l['pagador_uid'] . " AND {$minuto} = '" . $db->escape_string($l['minuto']) . "')";
}

$hay_saldo = false;
if ($condiciones) {
    $q_pagos = $db->query("
        SELECT *, {$minuto} AS minuto FROM `mybb_op_salario_staff_pagos`
        WHERE {$where} AND (" . implode(' OR ', $condiciones) . ")
        ORDER BY id ASC
    ");
    while ($q = $db->fetch_array($q_pagos)) {
        $clave = (int) $q['pagador_uid'] . '|' . $q['minuto'];
        $lotes[$clave]['pagos'][] = $q;
        $lotes[$clave]['total'] += (int) $q['monto'];
        $lotes[$clave]['pagador'] = $q['pagador'];
        $lotes[$clave]['ts'] = strtotime($q['tiempo']);
        if ((int) $q['saldo_antes'] !== 0 || (int) $q['saldo_despues'] !== 0) { $hay_saldo = true; }
    }
}

$montos_rol = array($SALARIO_MONTO_ADMIN, $SALARIO_MONTO_STAFF, $SALARIO_MONTO_NARRA);
$salarios_historial = '';
$primero = true;
foreach ($lotes as $lote) {
    if (empty($lote['pagos'])) { continue; }
    $n = count($lote['pagos']);
    $filas = '';
    foreach ($lote['pagos'] as $q) {
        $fid = (int) $q['uid'];
        $personalizado = !in_array((int) $q['monto'], $montos_rol, true);
        if ($q['tipo'] === 'uid' && !$personalizado) { $tipo = '<span class="ss-chip">UID puntual</span>'; }
        elseif ($personalizado) { $tipo = '<span class="ss-chip ss-chip--custom">Personalizado</span>'; }
        else { $tipo = '<span class="ss-chip ss-chip--rol">Por rol</span>'; }

        $saldo = '';
        if ($hay_saldo) {
            $sin = (int) $q['saldo_antes'] === 0 && (int) $q['saldo_despues'] === 0;
            $saldo = '<td class="ss-num ss-oculta-movil">' . ($sin ? '<span class="ss-nd">n/d</span>' : salario_staff_kuros($q['saldo_antes']) . ' &rarr; ' . salario_staff_kuros($q['saldo_despues'])) . '</td>';
        }
        $filas .= '<tr>'
            . '<td><a href="/op/personaje.php?uid=' . $fid . '">' . htmlspecialchars($q['nombre'], ENT_QUOTES, 'UTF-8') . '</a> <small>(' . $fid . ')</small></td>'
            . '<td class="ss-num">+' . salario_staff_kuros($q['monto']) . '</td>'
            . $saldo
            . '<td>' . $tipo . '</td>'
            . '</tr>';
    }
    $cabecera_saldo = $hay_saldo ? '<th class="ss-num ss-oculta-movil">Saldo</th>' : '';

    $salarios_historial .= '<details class="ss-lote"' . ($n === 1 || $primero ? ' open' : '') . '>'
        . '<summary><span class="ss-lote-fecha" title="' . date('Y-m-d H:i', $lote['ts']) . '">' . salario_staff_hace($lote['ts']) . ' <small>' . date('d/m/Y H:i', $lote['ts']) . '</small></span>'
        . '<span class="ss-lote-quien">' . htmlspecialchars($lote['pagador'], ENT_QUOTES, 'UTF-8') . '</span>'
        . '<span class="ss-lote-total">' . $n . ($n === 1 ? ' pago' : ' pagos') . ' &middot; <strong>' . salario_staff_kuros($lote['total']) . ' kuros</strong></span></summary>'
        . '<table class="ss-historial"><thead><tr><th>Receptor</th><th class="ss-num">Monto</th>' . $cabecera_saldo . '<th>Tipo</th></tr></thead><tbody>' . $filas . '</tbody></table>'
        . '</details>';
    $primero = false;
}
if ($salarios_historial === '') {
    $salarios_historial = '<p class="opg-vacio">' . ($filtro !== '' ? 'Ningún pago coincide con la búsqueda.' : 'Todavía no se pagó ningún salario de staff.') . '</p>';
}

// Totales por persona (los 10 que más cobraron, respetando el filtro).
$totales_persona = '';
$q_tot = $db->query("
    SELECT uid, MAX(nombre) AS nombre, COUNT(*) AS pagos, SUM(monto) AS total
    FROM `mybb_op_salario_staff_pagos` WHERE {$where}
    GROUP BY uid ORDER BY total DESC LIMIT 10
");
while ($t = $db->fetch_array($q_tot)) {
    $totales_persona .= '<tr><td><a href="/op/personaje.php?uid=' . (int) $t['uid'] . '">' . htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') . '</a> <small>(' . (int) $t['uid'] . ')</small></td>'
        . '<td class="ss-num">' . (int) $t['pagos'] . '</td><td class="ss-num"><strong>' . salario_staff_kuros($t['total']) . '</strong></td></tr>';
}
if ($totales_persona !== '') {
    $totales_persona = '<details class="ss-totales"><summary>Totales por persona (top 10)</summary>'
        . '<table class="ss-historial"><thead><tr><th>Persona</th><th class="ss-num">Pagos</th><th class="ss-num">Kuros</th></tr></thead><tbody>' . $totales_persona . '</tbody></table></details>';
}

// Compatibilidad: la plantilla vieja (antes de importar la nueva) pintaba {$salarios_kuros}.
$salarios_kuros = $salarios_historial;

$paginacion = '';
if ($total_paginas > 1) {
    $qs = $filtro !== '' ? '&q=' . urlencode($filtro) : '';
    if ($pagina > 1) { $paginacion .= '<a class="btn-op btn-op--secundario" href="?pagina=' . ($pagina - 1) . $qs . '">&larr; Anterior</a>'; }
    $paginacion .= '<span class="ss-pagina">Página ' . $pagina . ' de ' . $total_paginas . '</span>';
    if ($pagina < $total_paginas) { $paginacion .= '<a class="btn-op btn-op--secundario" href="?pagina=' . ($pagina + 1) . $qs . '">Siguiente &rarr;</a>'; }
}
$historial_resumen = $total_filas . ' pagos en ' . $total_lotes . ' lotes, ' . salario_staff_kuros($resumen['total']) . ' kuros en total' . ($filtro !== '' ? ' (filtrado)' : '');

eval("\$page = \"".$templates->get("staff_salario_staff")."\";");
output_page($page);
