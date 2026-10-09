<?php
/**
 * Pruebas de las funciones PURAS de inc/plugins/lib/aventuras.php (sin base de datos).
 *
 *   php tests/aventuras/dominio_puro.php
 *
 * Las pruebas que necesitan MySQL (límites, listados, escritura) son aparte y se
 * ejecutan contra la base de desarrollo; ver docs/301_ImplementationPlan_Registro.md.
 */

define('TABLE_PREFIX', 'mybb_');
define('TIME_NOW', 1800000000);

// Dobles mínimos de lo que aporta MyBB.
class FakeDb
{
    public function escape_string($s) { return addslashes($s); }
    public function escape_string_like($s) { return addcslashes(addslashes($s), '%_'); }
}
$db = new FakeDb();
$mybb = new stdClass();
$mybb->settings = array('bburl' => 'https://onepiecegaiden.com');

$GLOBALS['roles'] = array('mod' => array(), 'staff' => array(), 'narra' => array());
function is_mod($u) { return in_array($u, $GLOBALS['roles']['mod']); }
function is_staff($u) { return in_array($u, $GLOBALS['roles']['staff']); }
function is_narra($u) { return in_array($u, $GLOBALS['roles']['narra']); }

require __DIR__ . '/../../inc/plugins/lib/aventuras.php';

$fallos = 0;
$total = 0;
function prueba($nombre, $obtenido, $esperado)
{
    global $fallos, $total;
    $total++;
    if ($obtenido !== $esperado) {
        $fallos++;
        echo "FALLA: $nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true) . "\n";
    }
}

// ── Constantes que deben coincidir con op/cronologia.php ────────────────────
$cron = file_get_contents(__DIR__ . '/../../op/cronologia.php');
preg_match('/const CRON_DIAS\s*=\s*(\d+)/', $cron, $m);
prueba('OPG_AV_DIAS coincide con CRON_DIAS', OPG_AV_DIAS, isset($m[1]) ? (int) $m[1] : null);
preg_match('/const CRON_ANO_DEFECTO\s*=\s*(\d+)/', $cron, $m);
prueba('OPG_AV_ANO coincide con CRON_ANO_DEFECTO', OPG_AV_ANO, isset($m[1]) ? (int) $m[1] : null);

// ── Día y tier ───────────────────────────────────────────────────────────────
prueba('día 1', opg_av_validar_dia('1'), 1);
prueba('día 90', opg_av_validar_dia(90), 90);
prueba('día 0', opg_av_validar_dia('0'), null);
prueba('día 91', opg_av_validar_dia('91'), null);
prueba('día texto', opg_av_validar_dia('abc'), null);
prueba('día decimal', opg_av_validar_dia('1.5'), null);
prueba('día negativo', opg_av_validar_dia('-3'), null);
prueba('día vacío', opg_av_validar_dia(''), null);
prueba('día null', opg_av_validar_dia(null), null);
prueba('tier 1', opg_av_validar_tier('1'), 1);
prueba('tier 10', opg_av_validar_tier(10), 10);
prueba('tier 0', opg_av_validar_tier('0'), null);
prueba('tier 11', opg_av_validar_tier('11'), null);
prueba('tier texto', opg_av_validar_tier('uno'), null);
prueba('fecha texto', opg_av_fecha_texto(12), 'Verano 725, día 12');
prueba('fecha sin día', opg_av_fecha_texto(0), null);

// ── Transiciones ─────────────────────────────────────────────────────────────
foreach (array(array(0, 1), array(0, 2), array(0, 4), array(0, 5), array(1, 2), array(1, 3), array(1, 4), array(1, 5),
               array(2, 5), array(3, 5), array(4, 0), array(4, 2), array(4, 5)) as $t) {
    prueba("transición {$t[0]}->{$t[1]} válida", opg_av_transicion_valida($t[0], $t[1]), true);
}
foreach (array(array(2, 0), array(2, 1), array(3, 1), array(3, 0), array(4, 1), array(4, 3), array(5, 0), array(5, 1), array(0, 3), array(1, 0)) as $t) {
    prueba("transición {$t[0]}->{$t[1]} inválida", opg_av_transicion_valida($t[0], $t[1]), false);
}
prueba('clave de estado', opg_av_estado_desde_clave('pendiente-narrador'), 4);
prueba('clave desconocida', opg_av_estado_desde_clave('x'), null);
prueba('etiquetas estadísticas compartidas', opg_av_etiquetas_estadisticas(), array(
    'total' => 'Total', 'creado' => 'En revisión', 'pendiente-narrador' => 'Buscando narrador',
    'aprobado' => 'Aprobadas', 'denegado' => 'Denegadas', 'finalizado' => 'Finalizadas',
    'pendiente-borrado' => 'Pendientes de borrado',
));

// ── Utilidades ───────────────────────────────────────────────────────────────
prueba('enteros únicos', opg_av_enteros(array('3', 3, '5', 'a', -1, 0, '5x', 7)), array(3, 5, 7));
prueba('enteros no lista', opg_av_enteros('3'), array());
prueba('texto ok', opg_av_texto('  hola  ', 10, 'el texto'), array('hola', null));
prueba('texto largo', opg_av_texto(str_repeat('a', 11), 10, 'el texto')[1], 'El texto supera los 10 caracteres.');
prueba('texto obligatorio', opg_av_texto('   ', 10, 'el motivo', true)[1], 'Falta el motivo.');
prueba('texto con tilde cuenta caracteres', opg_av_texto(str_repeat('ñ', 10), 10, 'x')[1], null);
prueba('sql null', opg_av_sql(null), 'NULL');
prueba('sql bool', opg_av_sql(true), '1');
prueba('sql int', opg_av_sql(5), '5');
prueba('sql cadena escapa', opg_av_sql("O'Hara"), "'O\\'Hara'");
prueba('tabla con prefijo', opg_av_t('users'), '`mybb_users`');
prueba('verdadero', array(opg_av_verdadero('1'), opg_av_verdadero(true), opg_av_verdadero('0'), opg_av_verdadero(''), opg_av_verdadero(null)), array(true, true, false, false, false));

// ── tid de entrada ───────────────────────────────────────────────────────────
prueba('tid numérico', opg_av_tid_de_entrada(' 9302 '), 9302);
prueba('tid en enlace', opg_av_tid_de_entrada('https://onepiecegaiden.com/showthread.php?tid=9302&pid=5'), 9302);
prueba('tid en enlace http', opg_av_tid_de_entrada('http://onepiecegaiden.com/showthread.php?tid=12'), 12);
prueba('tid en enlace SEO', opg_av_tid_de_entrada('https://onepiecegaiden.com/thread-9302.html'), 9302);
prueba('tid en enlace SEO con slug', opg_av_tid_de_entrada('https://onepiecegaiden.com/thread-9302-nombre-del-tema.html'), 9302);
prueba('tid otro host', opg_av_tid_de_entrada('https://example.com/showthread.php?tid=9302'), 0);
prueba('tid host parecido', opg_av_tid_de_entrada('https://onepiecegaiden.com.malo.net/showthread.php?tid=9302'), 0);
prueba('tid sin esquema', opg_av_tid_de_entrada('onepiecegaiden.com/showthread.php?tid=9302'), 0);
prueba('tid esquema javascript', opg_av_tid_de_entrada('javascript:alert(1)'), 0);
prueba('tid texto', opg_av_tid_de_entrada('hola'), 0);
prueba('tid negativo', opg_av_tid_de_entrada('-5'), 0);
prueba('tid vacío', opg_av_tid_de_entrada(''), 0);
prueba('tid con tid no numérico', opg_av_tid_de_entrada('https://onepiecegaiden.com/showthread.php?tid=abc'), 0);
prueba('tid en query arreglo', opg_av_tid_de_entrada('https://onepiecegaiden.com/showthread.php?tid[]=5'), 0);
prueba('url de tema', opg_av_url_tema(77), 'https://onepiecegaiden.com/showthread.php?tid=77');
prueba('tid de url guardada', opg_av_tid_de_url('https://onepiecegaiden.com/showthread.php?tid=9302'), 9302);
prueba('tid de url vacía', opg_av_tid_de_url(''), 0);

// ── Discord ──────────────────────────────────────────────────────────────────
prueba('discord ok', opg_av_validar_discord('  user#123 ')[1], null);
prueba('discord vacío', opg_av_validar_discord('')[1], 'Falta tu usuario de Discord.');
prueba('discord corto', opg_av_validar_discord('a')[1], 'El usuario de Discord es demasiado corto.');
prueba('discord 64', opg_av_validar_discord(str_repeat('a', 64))[1], null);
prueba('discord 65', opg_av_validar_discord(str_repeat('a', 65))[1], 'El usuario de Discord supera los 64 caracteres.');
prueba('discord salto de línea', opg_av_validar_discord("a\nb")[1] !== null, true);

// ── Jugadores y normalización de filas (formato nuevo y antiguo) ─────────────
prueba('jugadores nuevos', opg_av_decodificar_jugadores('[{"uid":5,"nombre":"Luffy"},{"uid":9,"nombre":"Zoro"}]'),
    array(array('uid' => 5, 'nombre' => 'Luffy'), array('uid' => 9, 'nombre' => 'Zoro')));
prueba('jugadores antiguos con nivel y sin uid', opg_av_decodificar_jugadores('[{"nombre":"Nami","nivel":20,"uid":null},{"nombre":"Sanji","nivel":30,"uid":7}]'),
    array(array('uid' => null, 'nombre' => 'Nami'), array('uid' => 7, 'nombre' => 'Sanji')));
prueba('jugadores JSON inválido', opg_av_decodificar_jugadores('no es json'), array());
prueba('jugadores vacío', opg_av_decodificar_jugadores(''), array());

$fila_antigua = array(
    'id' => '15', 'uid' => '3', 'narrador_uid' => '8', 'narrador_nombre' => 'Robin', 'estado' => '2', 'tier_seleccionado' => '4',
    'jugadores_json' => '[{"nombre":"Luffy","nivel":10,"uid":5}]', 'comentario_publico' => '', 'meta_public_comment' => 'Desde meta',
    'comentario_staff' => '', 'meta_staff_note' => 'Nota meta', 'aventura_url' => 'https://onepiecegaiden.com/showthread.php?tid=9302',
    'inframundo' => '1', 'created_at' => '1700000000', 'creador_nombre' => 'Eddy',
);
$av = opg_av_normalizar_fila($fila_antigua, true);
prueba('antigua: isla nula', $av['isla'], null);
prueba('antigua: día nulo', $av['dia'], null);
prueba('antigua: fecha nula', $av['fecha_texto'], null);
prueba('antigua: comentario cae a meta', $av['comentario_publico'], 'Desde meta');
prueba('antigua: nota cae a meta (consola)', $av['nota_staff'], 'Nota meta');
prueba('antigua denegada sin motivo', $av['motivo_denegacion'], 'Sin motivo registrado');
prueba('antigua: tid vinculado', $av['tid_vinculado'], 9302);
prueba('antigua: tier', $av['tier'], 4);
prueba('antigua: inframundo', $av['inframundo'], true);
prueba('antigua: estado clave', $av['estado_clave'], 'denegado');
$av_jug = opg_av_normalizar_fila($fila_antigua, false);
prueba('jugadores no ven la nota de staff', array_key_exists('nota_staff', $av_jug), false);

$fila_nueva = array(
    'id' => '16', 'uid' => '3', 'narrador_uid' => null, 'narrador_nombre' => null, 'estado' => '4', 'tier_seleccionado' => '0',
    'jugadores_json' => '[{"uid":3,"nombre":"Eddy"}]', 'comentario_publico' => 'Hola', 'isla_fid' => '40', 'isla_nombre' => 'Isla X',
    'dia' => '12', 'idea_narrador' => ' Una idea ', 'motivo_renuncia' => 'Se fue', 'inframundo' => '0', 'created_at' => '1700000001',
    'entrega_estado' => 'rechazada', 'entrega_motivo' => 'Falta resumen', 'entrega_intentos' => '2', 'entrega_entregado_at' => null,
);
$av = opg_av_normalizar_fila($fila_nueva);
prueba('nueva: isla', $av['isla'], array('fid' => 40, 'nombre' => 'Isla X'));
prueba('nueva: fecha', $av['fecha_texto'], 'Verano 725, día 12');
prueba('nueva: sin narrador', $av['narrador_uid'], 0);
prueba('nueva: idea recortada', $av['idea_narrador'], 'Una idea');
prueba('nueva: motivo de renuncia visible en estado 4', $av['motivo_renuncia'], 'Se fue');
prueba('nueva: tier 0 queda nulo', $av['tier'], null);
prueba('nueva: entrega', $av['entrega']['estado'], 'rechazada');
prueba('nueva: intentos', $av['entrega']['intentos'], 2);
$fila_nueva['estado'] = '1';
prueba('motivo de renuncia solo en estado 4', opg_av_normalizar_fila($fila_nueva)['motivo_renuncia'], '');

// ── Permisos y partes de la solicitud ────────────────────────────────────────
$av = opg_av_normalizar_fila(array(
    'id' => '20', 'uid' => '3', 'narrador_uid' => '8', 'estado' => '0', 'jugadores_json' => '[{"uid":3,"nombre":"A"},{"uid":5,"nombre":"B"}]',
    'created_at' => '1', 'tier_seleccionado' => '3',
));
prueba('creador es parte', opg_av_es_parte($av, 3), true);
prueba('narrador es parte', opg_av_es_parte($av, 8), true);
prueba('participante es parte', opg_av_es_parte($av, 5), true);
prueba('ajeno no es parte', opg_av_es_parte($av, 99), false);
prueba('invitado no es parte', opg_av_es_parte($av, 0), false);
$GLOBALS['roles']['staff'] = array(5, 50);
$GLOBALS['roles']['narra'] = array(60);
$GLOBALS['roles']['mod'] = array(1);
prueba('staff puede gestionar', opg_av_puede_gestionar(50), true);
prueba('narrador del staff puede gestionar', opg_av_puede_gestionar(60), true);
prueba('mod puede gestionar', opg_av_puede_gestionar(1), true);
prueba('usuario no puede gestionar', opg_av_puede_gestionar(99), false);
prueba('invitado no puede gestionar', opg_av_puede_gestionar(0), false);
prueba('staff parte puede resolver', opg_av_puede_resolver($av, 5), true);
prueba('staff ajeno puede resolver', opg_av_puede_resolver($av, 50), true);
prueba('narrador del staff ajeno puede resolver', opg_av_puede_resolver($av, 60), true);
prueba('usuario ajeno no puede resolver', opg_av_puede_resolver($av, 99), false);
prueba('solo staff elimina', array(opg_av_puede_eliminar(50), opg_av_puede_eliminar(60), opg_av_puede_eliminar(1)), array(true, false, false));
prueba('creador edita en estado 0', opg_av_puede_editar($av, 3), true);
$av4 = $av; $av4['estado'] = 4;
prueba('creador edita en estado 4', opg_av_puede_editar($av4, 3), true);
$av1 = $av; $av1['estado'] = 1;
prueba('creador no edita aprobada', opg_av_puede_editar($av1, 3), false);
prueba('no creador no edita', opg_av_puede_editar($av, 8), false);

// ── Predicados SQL ───────────────────────────────────────────────────────────
$mia = opg_av_sql_mia(7);
prueba('mia contiene creador y narrador', strpos($mia, 'pa.uid = 7') !== false && strpos($mia, 'pa.narrador_uid = 7') !== false, true);
$no = opg_av_sql_no_mia(7);
prueba('no_mia cuida el NULL de narrador_uid', strpos($no, 'pa.narrador_uid IS NULL OR pa.narrador_uid <> 7') !== false, true);
prueba('participa tolera JSON inválido', strpos(opg_av_sql_participa(7), 'JSON_VALID(pa.jugadores_json)') !== false, true);
prueba('uid se castea a entero', strpos(opg_av_sql_mia("7; DROP TABLE x"), 'DROP') === false, true);

// ── Condiciones de los listados ──────────────────────────────────────────────
$c = implode(' AND ', opg_av_condiciones_listado(array('yo' => 7, 'ambito' => 'mias', 'estado' => 'aprobado', 'rol' => 'narrador'), false));
prueba('mis: excluye estado 5', strpos($c, 'pa.estado <> 5') !== false, true);
prueba('mis: filtro de estado', strpos($c, 'pa.estado = 1') !== false, true);
prueba('mis: filtro de rol narrador', strpos($c, 'pa.narrador_uid = 7') !== false, true);
$c = implode(' AND ', opg_av_condiciones_listado(array('yo' => 7, 'ambito' => 'mias', 'estado' => 'pendiente-borrado'), false));
prueba('mis: ignora el estado 5 como filtro', strpos($c, 'pa.estado = 5') === false, true);
$c = implode(' AND ', opg_av_condiciones_listado(array('yo' => 7, 'ambito' => 'otras', 'estado' => 'pendiente-narrador', 'rol' => 'jugador'), false));
prueba('otras: usa estados 0 a 3', strpos($c, 'pa.estado IN (0,1,2,3)') !== false, true);
prueba('otras: ignora pendiente-narrador y rol', strpos($c, 'pa.estado = 4') === false && substr_count($c, 'JSON_CONTAINS') === 1, true);
$c = implode(' AND ', opg_av_condiciones_listado(array('yo' => 7, 'ambito' => 'otras', 'uid' => 9), false));
prueba('otras: filtro uid', strpos($c, 'pa.narrador_uid = 9') !== false, true);
$c = implode(' AND ', opg_av_condiciones_listado(array('yo' => 7, 'ambito' => 'mias', 'uid' => 9), false));
prueba('mis: ignora uid', strpos($c, 'pa.narrador_uid = 9') === false, true);
$c = opg_av_condiciones_listado(array('estado' => 'pendiente-borrado'), true);
prueba('consola: puede ver el estado 5', $c, array('pa.estado = 5'));
prueba('consola: sin filtros no restringe', opg_av_condiciones_listado(array(), true), array());

// ── Resultado ────────────────────────────────────────────────────────────────
echo ($fallos === 0 ? 'OK' : 'CON FALLOS') . ": $total pruebas, $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
