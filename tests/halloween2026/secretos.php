<?php
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026_secretos.php';
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026_cliente.php';

$failures = array();
/** Secretos propios de una pagina: sin los globales ('pagina' => '*'), que salen en todas. */
function hlw_local($entries)
{
    return array_filter($entries, function ($secret) { return $secret['pagina'] !== '*'; });
}
function hlw_expect($condition, $message)
{
    global $failures;
    if (!$condition) $failures[] = $message;
}

// Sin base de datos: la clave de cifrado sale de la configuracion de MyBB.
$GLOBALS['mybb'] = (object)array('config' => array('database' => array('password' => 'prueba', 'database' => 'bd', 'table_prefix' => 'mybb_')));

$catalog = opg_hlw_secret_catalog();
$numbers = array();
foreach ($catalog as $key => $secret) {
    hlw_expect(preg_match('/^hs-[a-z0-9]{6}$/', $key) === 1, "Clave de secreto con formato inesperado: {$key}");
    hlw_expect($secret['numero'] >= 1 && $secret['numero'] <= OPG_HLW_SECRET_TOTAL, "Numero fuera de rango en {$key}.");
    hlw_expect($secret['nombre'] !== '' && $secret['pista'] !== '', "Faltan nombre o pista en {$key}.");
    if (($secret['tipo'] ?? '') !== 'link') {
        hlw_expect(!empty($secret['pagina']), "Falta la pagina en {$key}.");
    }
    if (($secret['tipo'] ?? '') === 'link') {
        hlw_expect(isset($secret['codigo']) && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', (string)$secret['codigo']) === 1 && strlen((string)$secret['codigo']) >= 6 && strlen((string)$secret['codigo']) <= 40 && !empty($secret['imagen']) && empty($secret['pagina']), "El secreto de enlace {$key} necesita un codigo (minusculas, numeros y guiones, de 6 a 40 caracteres), una imagen y ninguna pagina.");
    } elseif (($secret['tipo'] ?? '') === 'compra') {
        hlw_expect(!empty($secret['vigilar']) && !empty($secret['item']), "El secreto de compra {$key} necesita la zona a vigilar y el articulo.");
    } elseif (($secret['tipo'] ?? '') === 'chat') {
        hlw_expect(!empty($secret['vigilar']), "El secreto del chat {$key} necesita la zona a vigilar.");
    } elseif (($secret['tipo'] ?? '') === 'visita') {
        hlw_expect(!empty($secret['imagen']), "El secreto de visita {$key} necesita una imagen para la felicitacion.");
    } else {
        hlw_expect(!empty($secret['selector']) && strpos($secret['selector'], '#ib-') === false, "Falta el selector en {$key} (y no debe usar ids ib-N).");
    }
    $numbers[] = $secret['numero'];
}
hlw_expect(count($numbers) === count(array_unique($numbers)), 'Hay dos secretos con el mismo numero.');
hlw_expect(OPG_HLW_SECRET_TOTAL === 30, 'El evento tiene 30 secretos.');
hlw_expect(isset($catalog['hs-k7q2m9']) && $catalog['hs-k7q2m9']['numero'] === 1, 'El secreto 1 es el boton gris de Luffy.');

$first = opg_hlw_secret_by_number(1);
hlw_expect($first !== null && $first['clave'] === 'hs-k7q2m9', 'by_number(1) debe devolver el secreto 1.');
$second = opg_hlw_secret_by_number(2);
hlw_expect($second !== null && $second['clave'] === 'hs-p4x8d1', 'by_number(2) debe devolver el secreto 2.');
$third = opg_hlw_secret_by_number(3);
hlw_expect($third !== null && $third['clave'] === 'hs-m2c6w5' && $third['tipo'] === 'chat', 'El secreto 3 es el del chat.');
$fourth = opg_hlw_secret_by_number(4);
hlw_expect($fourth !== null && $fourth['clave'] === 'hs-r9t3b6' && $fourth['pagina'] === 'estadisticas.php' && !empty($fourth['navegar']), 'El secreto 4 es el podio de estadisticas y es un enlace.');
$fifth = opg_hlw_secret_by_number(5);
hlw_expect($fifth !== null && $fifth['clave'] === 'hs-d3k7a9' && $fifth['pagina'] === 'objetos.php' && $fifth['selector'] === '#CALPOD' && !empty($fifth['delegar']), 'El secreto 5 es la Calabaza Podrida en objetos.php, con clic delegado.');
$sixth = opg_hlw_secret_by_number(6);
hlw_expect($sixth !== null && $sixth['clave'] === 'hs-h6j2q4' && $sixth['selector'] === '.testDraw' && !empty($sixth['delegar']), 'El secreto 6 es la tirada de prueba del Haki del Rey, con clic delegado.');
$seventh = opg_hlw_secret_by_number(7);
hlw_expect($seventh !== null && $seventh['clave'] === 'hs-g7w4e8' && $seventh['archivo'] === 'tirada_cofre.php' && !empty($seventh['delegar']), 'El secreto 7 es el GIF de cofres en tirada_cofre.php.');
$eighth = opg_hlw_secret_by_number(8);
hlw_expect($eighth !== null && $eighth['clave'] === 'hs-q4x7n2' && $eighth['selector'] === '#expl001' && $eighth['pagina'] === 'objetos.php' && !empty($eighth['delegar']), 'El secreto 8 es la Granada de Humo en objetos.php, con clic delegado.');
$ninth = opg_hlw_secret_by_number(9);
hlw_expect($ninth !== null && $ninth['clave'] === 'hs-w9m3k6' && $ninth['selector'] === '#DFUE003' && $ninth['pagina'] === 'objetos.php' && !empty($ninth['delegar']), 'El secreto 9 es el Dial Abrasador en objetos.php, con clic delegado.');
hlw_expect(array_keys(hlw_local(opg_hlw_secret_page_entries('tirada_rey.php', array(), 'tirada_rey.php'))) === array('hs-h6j2q4'), 'En tirada_rey.php esta solo el secreto 6.');
hlw_expect(array_keys(hlw_local(opg_hlw_secret_page_entries('tirada_rey.php', array(), 'tirada_cofre.php'))) === array('hs-g7w4e8'), 'Los cofres comparten THIS_SCRIPT con el Haki del Rey, pero solo llevan el secreto 7.');
hlw_expect(hlw_local(opg_hlw_secret_page_entries('tirada_rey.php', array(), 'tirada_cofre2.php')) === array(), 'tirada_cofre2.php no lleva ningun secreto.');
hlw_expect(hlw_local(opg_hlw_secret_page_entries('tirada_rey.php', array(), 'tirada_haki.php')) === array(), 'Tampoco la pagina de Haki.');
hlw_expect(array_keys(hlw_local(opg_hlw_secret_page_entries('prensarosa.php', array()))) === array('hs-e8m5j3'), 'La Prensa Rosa lleva el secreto 23 (el de visita, que ya no existe, no).');
$objectEntries = opg_hlw_secret_issue(77, 'objetos.php', array('hs-f3d8m1' => true), 1000);
hlw_expect(count($objectEntries) === 3 + OPG_HLW_SECRET_DECOYS, 'En objetos.php se emiten los secretos 5, 8 y 9 mas los señuelos.');
$objectSelectors = array_column($objectEntries, 's');
foreach (array('#CALPOD', '#expl001', '#DFUE003') as $objectSelector) {
    hlw_expect(in_array($objectSelector, $objectSelectors, true), 'objetos.php debe llevar ' . $objectSelector);
}
$objectReal = array_values(array_filter($objectEntries, function ($e) { return $e['s'] === '#CALPOD'; }));
hlw_expect(count($objectReal) === 1 && $objectReal[0]['g'] === 1 && $objectReal[0]['n'] === 0, 'El objeto lleva g = 1 (delegado) y n = 0.');
foreach ($objectEntries as $e) { hlw_expect(array_key_exists('g', $e) && in_array($e['g'], array(0, 1), true), 'Todas las entradas llevan el campo g.'); }

// ── Secretos que solo valen en una vista de la pagina ──
hlw_expect(hlw_local(opg_hlw_secret_page_entries('estadisticas.php', array())) === array(), 'Sin el parametro accion no hay secreto en estadisticas.');
hlw_expect(hlw_local(opg_hlw_secret_page_entries('estadisticas.php', array('accion' => 'mas-posts'))) === array(), 'En otra vista de estadisticas no hay secreto.');
hlw_expect(array_keys(hlw_local(opg_hlw_secret_page_entries('estadisticas.php', array('accion' => 'mas-experiencia')))) === array('hs-r9t3b6'), 'En mas-experiencia esta el secreto 4.');
hlw_expect(array_keys(hlw_local(opg_hlw_secret_page_entries('estadisticas.php', array('accion' => 'mas-experiencia', 'tiempo' => '7')))) === array('hs-r9t3b6'), 'El secreto 4 vale con cualquier tiempo.');
$_GET = array('accion' => 'mas-experiencia', 'tiempo' => '14');
$statEntries = opg_hlw_secret_issue(77, 'estadisticas.php', array('hs-f3d8m1' => true), 1000);
hlw_expect(count($statEntries) === 1 + OPG_HLW_SECRET_DECOYS, 'En mas-experiencia se emite el secreto 4 mas los señuelos.');
$statReal = array_values(array_filter($statEntries, function ($e) { return $e['s'] === 'a.ranking-podio__puesto--1'; }));
hlw_expect(count($statReal) === 1 && $statReal[0]['n'] === 1, 'El podio es un enlace: lleva n = 1.');
$_GET = array('accion' => 'censo');
hlw_expect(opg_hlw_secret_issue(77, 'estadisticas.php', array('hs-f3d8m1' => true), 1000) === array(), 'En otra vista no se emite nada.');
$_GET = array();

// ── Secreto del chat ──
foreach (array('calabaza', 'Calabaza', 'CALABAZA', 'hola, calabaza!', 'Calabazas malditas', 'la CALABAZA es mia', '¡calabaza!', 'una calabaza', 'cALaBaZa') as $text) {
    hlw_expect(opg_hlw_chat_message_matches($text) === true, "Debe detectar la palabra en: {$text}");
}
foreach (array('', 'hola', 'calabacin', 'calabaz', 'xcalabaza', '/ban calabaza', '  /clear calabaza', 'cala baza') as $text) {
    hlw_expect(opg_hlw_chat_message_matches($text) === false, "No debe detectar la palabra en: {$text}");
}
hlw_expect(opg_hlw_secret_page_watch('index.php', array()) === '.chatbox_div', 'En el indice se vigila el chat mientras no se haya descubierto.');
hlw_expect(opg_hlw_secret_page_watch('index.php', array('hs-m2c6w5' => true)) === '.chatbox_div', 'Con un secreto de chat pendiente, el chat se sigue vigilando.');
hlw_expect(opg_hlw_secret_page_watch('index.php', array('hs-m2c6w5' => true, 'hs-t2u5l9' => true)) === '', 'Con todos los de chat descubiertos, el chat no se vigila.');

// ── Palabra halloween2026 ──
$tenth = opg_hlw_secret_by_number(10);
hlw_expect($tenth !== null && $tenth['clave'] === 'hs-t2u5l9' && $tenth['tipo'] === 'chat', 'El secreto 10 es la palabra halloween2026 del chat.');
$eleventh = opg_hlw_secret_by_number(11);
hlw_expect($eleventh !== null && $eleventh['clave'] === 'hs-b1y6s4' && $eleventh['pagina'] === 'compas.php' && $eleventh['selector'] === 'div.akumaBanner', 'El secreto 11 es el banner de compas.php.');
hlw_expect(empty($eleventh['delegar']) && empty($eleventh['navegar']), 'El banner es un clic directo, sin delegar ni navegar.');
$twelfth = opg_hlw_secret_by_number(12);
hlw_expect($twelfth !== null && $twelfth['clave'] === 'hs-c8z2f7' && $twelfth['selector'] === 'img.hlw-cart__decoration' && !empty($twelfth['delegar']), 'El secreto 12 es la imagen del carrito, con clic delegado.');
$thirteenth = opg_hlw_secret_by_number(13);
hlw_expect($thirteenth !== null && $thirteenth['clave'] === 'hs-s9p3v1' && $thirteenth['tipo'] === 'compra' && $thirteenth['item'] === 'cofre-calabaza', 'El secreto 13 es la compra del Cofre Calabaza.');
// En la pagina del evento hay un secreto de clic (12) y uno de compra (13): el de compra no lleva codigos ni señuelos.
hlw_expect(array_keys(hlw_local(opg_hlw_secret_page_entries('halloween2026.php', array(), '/op/halloween2026.php'))) === array('hs-c8z2f7', 'hs-s9p3v1', 'hs-n5k9r3'), 'La pagina del evento lleva los secretos 12, 13 y 15.');
hlw_expect(opg_hlw_secret_page_watch('halloween2026.php', array(), '/op/halloween2026.php') === '.hlw-panel', 'Con la compra pendiente se vigila la zona del evento.');
hlw_expect(opg_hlw_secret_page_watch('halloween2026.php', array('hs-s9p3v1' => true), '/op/halloween2026.php') === '', 'Con la compra descubierta no se vigila nada.');
hlw_expect(opg_hlw_secret_page_watch('halloween2026.php', array(), '/op/staff/halloween2026.php') === '', 'La consola de staff no vigila nada.');
hlw_expect(array_keys(hlw_local(opg_hlw_secret_page_entries('halloween2026.php', array(), '/var/www/op/halloween2026.php'))) === array('hs-c8z2f7', 'hs-s9p3v1', 'hs-n5k9r3'), 'En la pagina del evento estan los secretos 12, 13 y 15.');
hlw_expect(hlw_local(opg_hlw_secret_page_entries('halloween2026.php', array(), '/var/www/op/staff/halloween2026.php')) === array(), 'La consola de staff comparte THIS_SCRIPT y nombre, pero no lleva el secreto.');
hlw_expect(hlw_local(opg_hlw_secret_page_entries('halloween2026.php', array(), '/var/www/op/otro_halloween2026.php')) === array(), 'Un archivo de otro nombre no debe coincidir.');
hlw_expect(array_keys(hlw_local(opg_hlw_secret_page_entries('compas.php', array(), 'compas.php'))) === array('hs-b1y6s4', 'hs-z5q9d2', 'hs-b4t6x9'), 'En compas.php estan los secretos 11, 21 y 22.');
hlw_expect(hlw_local(opg_hlw_secret_page_entries('compas.php', array(), 'companeros.php')) === array(), 'companeros.php comparte THIS_SCRIPT pero no lleva el secreto.');
$_SERVER['SCRIPT_NAME'] = '/op/compas.php';
$compasEntries = opg_hlw_secret_issue(77, 'compas.php', array('hs-f3d8m1' => true), 1000);
$_SERVER['SCRIPT_NAME'] = '/tests/halloween2026/secretos.php';
hlw_expect(count($compasEntries) === 3 + OPG_HLW_SECRET_DECOYS, 'En compas.php se emiten los secretos 11, 21 y 22 mas los señuelos.');
hlw_expect(in_array('div.akumaBanner', array_column($compasEntries, 's'), true), 'compas.php lleva el selector del banner.');
foreach (array('halloween2026', 'Halloween2026', 'HALLOWEEN2026', 'feliz halloween2026!', '¡halloween2026!', 'halloween2026, calabaza') as $text) {
    hlw_expect(in_array('hs-t2u5l9', opg_hlw_chat_matching_keys($text), true), "Debe detectar halloween2026 en: {$text}");
}
foreach (array('halloween', 'halloween 2026', 'halloween2025', 'halloween20267', 'xhalloween2026', 'halloween2026x', '/ban halloween2026') as $text) {
    hlw_expect(!in_array('hs-t2u5l9', opg_hlw_chat_matching_keys($text), true), "No debe detectar halloween2026 en: {$text}");
}
hlw_expect(opg_hlw_chat_matching_keys('halloween2026 y calabaza') === array('hs-m2c6w5', 'hs-t2u5l9'), 'Un mensaje con las dos palabras reclama los dos secretos.');
hlw_expect(opg_hlw_chat_matching_keys('solo calabaza') === array('hs-m2c6w5'), 'Solo calabaza reclama el secreto 3.');
hlw_expect(opg_hlw_chat_matching_keys('hola') === array(), 'Sin palabras no se reclama nada.');
hlw_expect(opg_hlw_secret_page_watch('otra.php', array()) === '', 'Otras paginas no vigilan el chat.');

hlw_expect(opg_hlw_secret_date('2026-10-26 05:00:00') === '26/10/2026 06:00', 'Fecha UTC -> Madrid (horario de verano) incorrecta.');
hlw_expect(opg_hlw_secret_date('2026-11-10 05:00:00') === '10/11/2026 06:00', 'Fecha UTC -> Madrid (horario de invierno) incorrecta.');

$activeConfig = array('status' => 'active', 'earn_start_utc' => '2000-01-01 00:00:00', 'earn_end_utc' => '2999-01-01 00:00:00',
    'settings' => array('secrets_enabled' => true));
hlw_expect(opg_hlw_secrets_enabled($activeConfig) === true, 'secrets_enabled = true debe habilitarlos.');
hlw_expect(opg_hlw_secrets_enabled(array('settings' => array())) === false, 'Por defecto los secretos estan deshabilitados.');
$disabled = array_merge($activeConfig, array('settings' => array('secrets_enabled' => false)));
hlw_expect(opg_hlw_secrets_open($disabled, 999) === false, 'Deshabilitado, un usuario normal no descubre.');
hlw_expect(opg_hlw_secrets_open($disabled, 315) === true, 'Deshabilitado, la cuenta 315 si puede descubrir.');
hlw_expect(opg_hlw_secrets_open($disabled, 172) === true, 'Deshabilitado, la cuenta 172 si puede descubrir.');
hlw_expect(opg_hlw_secrets_open(array_merge($disabled, array('status' => 'draft')), 315) === true, 'Las cuentas de prueba descubren aunque el evento este en borrador.');
hlw_expect(opg_hlw_secrets_open($activeConfig, 999) === true, 'Habilitado y con el evento activo, todos pueden descubrir.');
hlw_expect(opg_hlw_secrets_open(array_merge($activeConfig, array('status' => 'draft')), 999) === false, 'Habilitado pero en borrador, un usuario normal no descubre.');
hlw_expect(OPG_HLW_SECRET_TESTERS === array(315, 172), 'Las cuentas de prueba son 315 y 172.');
hlw_expect(opg_hlw_secrets_open(array('status' => 'active', 'earn_start_utc' => '2000-01-01 00:00:00', 'earn_end_utc' => '2999-01-01 00:00:00')) === false, 'Sin el ajuste, los secretos estan cerrados.');
hlw_expect(opg_hlw_secrets_open($activeConfig) === true, 'Con el evento activo y dentro de fechas se pueden reclamar.');
hlw_expect(opg_hlw_secrets_open(array_merge($activeConfig, array('status' => 'draft')), 999) === false, 'En borrador no se reclaman.');
hlw_expect(opg_hlw_secrets_open(array_merge($activeConfig, array('earn_end_utc' => '2000-01-02 00:00:00'))) === false, 'Fuera de la ventana no se reclaman.');
hlw_expect(opg_hlw_secrets_open(array()) === false, 'Sin configuracion no se reclaman.');

// ── Codigos de visita y señuelos ──
$entries = opg_hlw_secret_issue(77, 'index.php', array('hs-f3d8m1' => true), 1000);
hlw_expect(count($entries) === 3 + OPG_HLW_SECRET_DECOYS, 'Debe haber 3 secretos reales + los señuelos.');
$selectors = array_column($entries, 's');
hlw_expect(count($selectors) === count(array_unique($selectors)), 'Dos entradas con el mismo selector.');
$realSelectors = array('hs-k7q2m9' => 'button.button-op-custom[style*="grayscale(0.8)"]', 'hs-p4x8d1' => 'img[src$="Titulo05_One_Piece_Gaiden_Foro_Rol.jpg"]', 'hs-j4h8t5' => 'img[src$="20DeapOcean_One_Piece_Gaiden_Foro_Rol.jpg"]');
foreach ($realSelectors as $selector) {
    hlw_expect(in_array($selector, $selectors, true), 'Falta el selector de un secreto real: ' . $selector);
}
foreach ($entries as $entry) {
    hlw_expect(strpos($entry['s'], 'ib-') === false, 'No puede quedar ningun id fijo ib-N.');
    hlw_expect(array_key_exists('n', $entry) && in_array($entry['n'], array(0, 1), true), 'Todas las entradas llevan el campo n (0 o 1).');
}
$real = 0;
$decoys = 0;
foreach ($entries as $entry) {
    foreach (array_keys(opg_hlw_secret_catalog()) as $catalogKey) {
        // Se busca la clave completa (9 caracteres): un trozo corto como 'hs-' puede aparecer por azar en una cadena aleatoria.
        hlw_expect(strpos($entry['t'], $catalogKey) === false && strpos($entry['t'], substr($catalogKey, 3)) === false, 'El codigo no puede dejar ver la clave del secreto.');
    }
    $payload = opg_hlw_secret_token_open($entry['t']);
    hlw_expect($payload !== null && (int)$payload['u'] === 77, 'El codigo debe abrirse y pertenecer al usuario.');
    if ($payload['d'] === 1) {
        $decoys++;
        hlw_expect(preg_match('/^#v[0-9a-f]{6}$/', $entry['s']) === 1 && strpos($payload['s'], 'd-') === 0, 'Un señuelo lleva un id aleatorio por visita.');
    } else {
        $real++;
        hlw_expect(isset($realSelectors[$payload['s']]) && $entry['s'] === $realSelectors[$payload['s']], 'El codigo real debe llevar al selector de su secreto.');
    }
}
hlw_expect($real === 3 && $decoys === OPG_HLW_SECRET_DECOYS, 'Conteo de reales y señuelos incorrecto.');
hlw_expect(count(opg_hlw_secret_issue(77, 'index.php', array('hs-k7q2m9' => true, 'hs-f3d8m1' => true), 1000)) === 2 + OPG_HLW_SECRET_DECOYS, 'Con uno ya descubierto, solo se emiten los otros mas los señuelos.');
hlw_expect(opg_hlw_secret_issue(77, 'index.php', array('hs-k7q2m9' => true, 'hs-p4x8d1' => true, 'hs-j4h8t5' => true, 'hs-f3d8m1' => true), 1000) === array(), 'Si ya descubrio todos los de la pagina, no se emite nada.');
hlw_expect(opg_hlw_secret_issue(77, 'otra.php', array('hs-f3d8m1' => true), 1000) === array(), 'Una pagina sin secretos no emite nada.');
hlw_expect(opg_hlw_secret_token_open('basura') === null, 'Un codigo inventado debe rechazarse.');
hlw_expect(opg_hlw_secret_token_open('') === null, 'Un codigo vacio debe rechazarse.');
$token = $entries[0]['t'];
$tampered = substr($token, 0, -3) . (substr($token, -3) === 'AAA' ? 'BBB' : 'AAA');
hlw_expect(opg_hlw_secret_token_open($tampered) === null, 'Un codigo alterado debe rechazarse.');
$GLOBALS['mybb'] = (object)array('config' => array('database' => array('password' => 'otra', 'database' => 'bd', 'table_prefix' => 'mybb_')));
hlw_expect(opg_hlw_secret_token_open($token) === null, 'Con otra clave del servidor el codigo no debe abrirse.');

// ── Secreto global: el nombre del encabezado ──
$fourteenth = opg_hlw_secret_by_number(14);
hlw_expect($fourteenth !== null && $fourteenth['clave'] === 'hs-f3d8m1' && $fourteenth['pagina'] === '*' && $fourteenth['selector'] === '#ficha' && !empty($fourteenth['delegar']), 'El secreto 14 es #ficha del encabezado, global y delegado.');
$fifteenth = opg_hlw_secret_by_number(15);
hlw_expect($fifteenth !== null && $fifteenth['clave'] === 'hs-n5k9r3' && $fifteenth['selector'] === 'div.hlw-header__mark' && empty($fifteenth['delegar']), 'El secreto 15 es el fantasma de la cabecera del evento, con clic directo.');
$sixteenth = opg_hlw_secret_by_number(16);
hlw_expect($sixteenth !== null && $sixteenth['clave'] === 'hs-j4h8t5' && $sixteenth['pagina'] === 'index.php' && !empty($sixteenth['navegar']) && empty($sixteenth['delegar']), 'El secreto 16 es la imagen de Deep Ocean del indice, un enlace con clic directo.');
$seventeenth = opg_hlw_secret_by_number(17);
hlw_expect($seventeenth !== null && $seventeenth['clave'] === 'hs-a2e6c9' && $seventeenth['pagina'] === 'akumas.php' && $seventeenth['selector'] === 'div.akumaItem' && $seventeenth['texto'] === 'Batto Batto no Mi' && !empty($seventeenth['delegar']), 'El secreto 17 es la Batto Batto no Mi en akumas.php, con clic delegado y texto obligatorio.');
$eighteenth = opg_hlw_secret_by_number(18);
hlw_expect($eighteenth !== null && $eighteenth['clave'] === 'hs-u6r1q8' && $eighteenth['pagina'] === 'akumas.php' && $eighteenth['texto'] === 'Kumo Kumo no Mi' && !empty($eighteenth['delegar']), 'El secreto 18 es la Kumo Kumo no Mi en akumas.php.');
$nineteenth = opg_hlw_secret_by_number(19);
hlw_expect($nineteenth !== null && $nineteenth['clave'] === 'hs-y3l8e5' && $nineteenth['pagina'] === 'registro_virtudes.php' && $nineteenth['selector'] === 'div.virtudCaja' && $nineteenth['texto'] === 'D046' && !empty($nineteenth['delegar']), 'El secreto 19 es el defecto Pesimista (D046) en el registro de virtudes.');
$twentieth = opg_hlw_secret_by_number(20);
hlw_expect($twentieth !== null && $twentieth['clave'] === 'hs-v7n2z4' && $twentieth['pagina'] === 'mapa_mundo.php' && $twentieth['selector'] === 'button.boton-mapa' && $twentieth['texto'] === 'North Blue' && !empty($twentieth['delegar']), 'El secreto 20 es el boton North Blue del mapa del mundo.');
$twentyfirst = opg_hlw_secret_by_number(21);
hlw_expect($twentyfirst !== null && $twentyfirst['clave'] === 'hs-z5q9d2' && $twentyfirst['pagina'] === 'compas.php' && $twentyfirst['archivo'] === 'compas.php' && $twentyfirst['selector'] === 'img#npcAvatar2[src$="Barbanieves1.gif"]' && !empty($twentyfirst['delegar']), 'El secreto 21 es el avatar 2 de Barbanieves en compas.php, solo cuando el src es el suyo.');
$twentysecond = opg_hlw_secret_by_number(22);
hlw_expect($twentysecond !== null && $twentysecond['clave'] === 'hs-b4t6x9' && $twentysecond['selector'] === '#aparienciaBox' && $twentysecond['condicion'] === array('#npcNombre', 'Lisstez M. Shingehardz') && !empty($twentysecond['delegar']), 'El secreto 22 es el apartado Apariencia de Lisstez, con condicion sobre el nombre.');
$twentythird = opg_hlw_secret_by_number(23);
hlw_expect($twentythird !== null && $twentythird['clave'] === 'hs-e8m5j3' && $twentythird['pagina'] === 'prensarosa.php' && strpos($twentythird['selector'], 'bab46b493579593fd6eef846bc3c9869.jpg') !== false && !empty($twentythird['delegar']), 'El secreto 23 es la imagen de Lance Turner en la Prensa Rosa, con clic delegado.');
$twentyfourth = opg_hlw_secret_by_number(24);
hlw_expect($twentyfourth !== null && $twentyfourth['clave'] === 'hs-k2d7p6' && $twentyfourth['pagina'] === 'recompensas.php' && $twentyfourth['archivo'] === '/op/recompensas.php' && $twentyfourth['texto'] === '30 días:' && !empty($twentyfourth['delegar']), 'El secreto 24 es la recompensa de 30 dias en op/recompensas.php.');
$twentyfifth = opg_hlw_secret_by_number(25);
hlw_expect($twentyfifth !== null && $twentyfifth['clave'] === 'hs-m9c4h2' && $twentyfifth['pagina'] === 'personaje.php' && strpos($twentyfifth['selector'], '5421605-200.png') !== false && !empty($twentyfifth['delegar']), 'El secreto 25 es el icono de Puntos de Oficio en personaje.php, con clic delegado.');
$twentysixth = opg_hlw_secret_by_number(26);
hlw_expect($twentysixth !== null && $twentysixth['clave'] === 'hs-r6u3b8' && $twentysixth['pagina'] === 'tienda_nika.php' && $twentysixth['archivo'] === '/op/tienda_kuros.php' && $twentysixth['selector'] === '#saldoActual + img.nikab' && !empty($twentysixth['delegar']), 'El secreto 26 es el icono de Kuros del Saldo actual en tienda_kuros.php.');
$twentyseventh = opg_hlw_secret_by_number(27);
hlw_expect($twentyseventh !== null && $twentyseventh['clave'] === 'hs-l5g7y1' && $twentyseventh['tipo'] === 'link' && $twentyseventh['codigo'] === 'ficha-belica-asfixia', 'El secreto 27 es el enlace escondido.');
$twentyeighth = opg_hlw_secret_by_number(28);
hlw_expect($twentyeighth !== null && $twentyeighth['clave'] === 'hs-v4d9s2' && $twentyeighth['tipo'] === 'link' && $twentyeighth['codigo'] === 'beta-opg', 'El secreto 28 es el enlace beta-opg.');
$twentyninth = opg_hlw_secret_by_number(29);
hlw_expect($twentyninth !== null && $twentyninth['clave'] === 'hs-x2j6c4' && $twentyninth['tipo'] === 'link' && $twentyninth['codigo'] === 'enciclopedia-razas', 'El secreto 29 es el enlace enciclopedia-razas.');
$thirtieth = opg_hlw_secret_by_number(30);
hlw_expect($thirtieth !== null && $thirtieth['clave'] === 'hs-m8q2t7' && $thirtieth['tipo'] === 'link' && $thirtieth['codigo'] === 'la-muerte-mas-epica', 'El secreto 30 es el enlace la-muerte-mas-epica.');
hlw_expect(opg_hlw_secret_by_number(31) === null, 'No hay mas secretos que el total.');
hlw_expect(count(opg_hlw_secret_catalog()) === OPG_HLW_SECRET_TOTAL, 'El catalogo tiene tantos secretos como el total del evento (30).');
$byMuerte = opg_hlw_secret_by_code('la-muerte-mas-epica');
hlw_expect($byMuerte !== null && $byMuerte['clave'] === 'hs-m8q2t7', 'El codigo la-muerte-mas-epica encuentra su secreto.');
hlw_expect(opg_hlw_secret_by_code('la-muerte') === null && opg_hlw_secret_by_code('La-Muerte-Mas-Epica') === null, 'Un codigo incompleto o con otras mayusculas no encuentra nada.');
$byRazas = opg_hlw_secret_by_code('enciclopedia-razas');
hlw_expect($byRazas !== null && $byRazas['clave'] === 'hs-x2j6c4', 'El codigo enciclopedia-razas encuentra su secreto.');
hlw_expect(opg_hlw_secret_by_code('enciclopedia') === null && opg_hlw_secret_by_code('Enciclopedia-Razas') === null, 'Un codigo incompleto o con otras mayusculas no encuentra nada.');
hlw_expect(opg_hlw_secret_by_code('beta-opg')['clave'] === 'hs-v4d9s2' && opg_hlw_secret_by_code('ficha-belica-asfixia')['clave'] === 'hs-l5g7y1', 'Cada codigo sigue llevando a su propio secreto.');
$byBeta = opg_hlw_secret_by_code('beta-opg');
hlw_expect($byBeta !== null && $byBeta['clave'] === 'hs-v4d9s2', 'El codigo beta-opg encuentra su secreto.');
hlw_expect(opg_hlw_secret_by_code('beta') === null && opg_hlw_secret_by_code('Beta-OPG') === null && opg_hlw_secret_by_code('beta-opg-') === null, 'Un codigo incompleto o con otras mayusculas no encuentra nada.');
hlw_expect(opg_hlw_secret_by_code('ficha-belica-asfixia')['clave'] === 'hs-l5g7y1', 'Cada codigo lleva a su propio secreto.');
// Secretos de enlace: se buscan por su codigo y no cuentan como secretos de ninguna pagina.
$byCode = opg_hlw_secret_by_code('ficha-belica-asfixia');
hlw_expect($byCode !== null && $byCode['clave'] === 'hs-l5g7y1', 'El codigo del enlace encuentra su secreto.');
hlw_expect(opg_hlw_secret_by_code('') === null && opg_hlw_secret_by_code('Ficha-Belica-Asfixia') === null && opg_hlw_secret_by_code('ficha-belica-asfixi') === null && opg_hlw_secret_by_code('ficha-belica-asfixia ') === null && opg_hlw_secret_by_code(str_repeat('a', 100)) === null, 'Un codigo vacio, con otras mayusculas, incompleto o demasiado largo no encuentra nada.');
hlw_expect(opg_hlw_secret_by_code('hs-l5g7y1') === null, 'La clave interna del secreto no sirve como codigo del enlace.');
foreach (array('index.php', 'objetos.php', 'descubrir.php', 'otra.php') as $anyPage2) {
    hlw_expect(!isset(opg_hlw_secret_page_entries($anyPage2, array(), 'x.php')['hs-l5g7y1']), "El secreto de enlace no debe aparecer en la pagina {$anyPage2}.");
}
hlw_expect(array_search('hs-l5g7y1', array_keys(opg_hlw_secret_issue(77, 'index.php', array(), 1000))) === false, 'Un secreto de enlace no genera codigos de visita.');
$linkCodes = array();
foreach (opg_hlw_secret_catalog() as $linkSecret) {
    if (($linkSecret['tipo'] ?? '') === 'link') { $linkCodes[] = $linkSecret['codigo']; }
}
hlw_expect(count($linkCodes) === count(array_unique($linkCodes)), 'Dos secretos de enlace comparten codigo.');
hlw_expect(array_keys(hlw_local(opg_hlw_secret_page_entries('tienda_nika.php', array(), '/var/www/op/tienda_kuros.php'))) === array('hs-r6u3b8'), 'En tienda_kuros.php esta el secreto 26.');
hlw_expect(hlw_local(opg_hlw_secret_page_entries('tienda_nika.php', array(), '/var/www/op/tienda_nika.php')) === array(), 'Otro archivo con el mismo THIS_SCRIPT no lleva el secreto.');
hlw_expect(hlw_local(opg_hlw_secret_page_entries('tecnicas_show.php', array(), '/var/www/op/tecnicas_show.php')) === array(), 'tecnicas_show.php no lleva ningun secreto.');
$kuroEntries = opg_hlw_secret_issue(77, 'tienda_nika.php', array('hs-f3d8m1' => true), 1000, array(), '/var/www/op/tienda_kuros.php');
hlw_expect(count($kuroEntries) === 1 + OPG_HLW_SECRET_DECOYS, 'En la tienda de Kuros se emite el secreto 26 mas los señuelos.');
hlw_expect(array_keys(hlw_local(opg_hlw_secret_page_entries('personaje.php', array(), '/var/www/op/personaje.php'))) === array('hs-m9c4h2'), 'En personaje.php esta el secreto 25.');
$personEntries = opg_hlw_secret_issue(77, 'personaje.php', array('hs-f3d8m1' => true), 1000, array(), '/var/www/op/personaje.php');
hlw_expect(count($personEntries) === 1 + OPG_HLW_SECRET_DECOYS, 'En personaje.php se emite el secreto 25 mas los señuelos.');
hlw_expect(array_keys(hlw_local(opg_hlw_secret_page_entries('recompensas.php', array(), '/var/www/op/recompensas.php'))) === array('hs-k2d7p6'), 'En op/recompensas.php esta el secreto 24.');
hlw_expect(hlw_local(opg_hlw_secret_page_entries('recompensas.php', array(), '/var/www/opg/recompensas.php')) === array(), 'opg/recompensas.php comparte THIS_SCRIPT y nombre, pero no lleva el secreto.');
hlw_expect(hlw_local(opg_hlw_secret_page_entries('recompensas.php', array(), '/var/www/op/recompensas2.php')) === array(), 'op/recompensas2.php tampoco.');
$pressEntries = opg_hlw_secret_issue(77, 'prensarosa.php', array('hs-f3d8m1' => true), 1000);
hlw_expect(count($pressEntries) === 1 + OPG_HLW_SECRET_DECOYS, 'En la Prensa Rosa se emite el secreto 23 mas los señuelos.');
$lisstezEntry = array_values(array_filter(opg_hlw_secret_issue(77, 'compas.php', array('hs-f3d8m1' => true), 1000, array(), '/op/compas.php'), function ($e) { return $e['s'] === '#aparienciaBox'; }));
hlw_expect(count($lisstezEntry) === 1 && $lisstezEntry[0]['y'] === array('#npcNombre', 'Lisstez M. Shingehardz') && $lisstezEntry[0]['g'] === 1, 'La entrada lleva la condicion del nombre del NPC y es delegada.');
foreach (opg_hlw_secret_issue(77, 'compas.php', array('hs-f3d8m1' => true), 1000, array(), '/op/compas.php') as $e) { hlw_expect(array_key_exists('y', $e), 'Todas las entradas llevan el campo y.'); }
hlw_expect(hlw_local(opg_hlw_secret_page_entries('compas.php', array(), 'companeros.php')) === array(), 'companeros.php no lleva ninguno de los secretos de compas.php.');
$mapEntries = opg_hlw_secret_issue(77, 'mapa_mundo.php', array('hs-f3d8m1' => true), 1000);
hlw_expect(count($mapEntries) === 1 + OPG_HLW_SECRET_DECOYS, 'En el mapa del mundo se emite el secreto 20 mas los señuelos.');
$mapReal = array_values(array_filter($mapEntries, function ($e) { return $e['s'] === 'button.boton-mapa'; }));
hlw_expect(count($mapReal) === 1 && $mapReal[0]['x'] === 'North Blue' && $mapReal[0]['g'] === 1, 'El boton lleva el texto que lo distingue de los demas mapas y es delegado.');
$virtudEntries = opg_hlw_secret_issue(77, 'registro_virtudes.php', array('hs-f3d8m1' => true), 1000);
hlw_expect(count($virtudEntries) === 1 + OPG_HLW_SECRET_DECOYS, 'En el registro de virtudes se emite el secreto 19 mas los señuelos.');
$virtudReal = array_values(array_filter($virtudEntries, function ($e) { return $e['s'] === 'div.virtudCaja'; }));
hlw_expect(count($virtudReal) === 1 && $virtudReal[0]['x'] === 'D046' && $virtudReal[0]['g'] === 1, 'La tarjeta de virtud lleva el ID que la distingue y es delegada.');
$akumaEntries = opg_hlw_secret_issue(77, 'akumas.php', array('hs-f3d8m1' => true), 1000);
hlw_expect(count($akumaEntries) === 2 + OPG_HLW_SECRET_DECOYS, 'En akumas.php se emiten los secretos 17 y 18 mas los señuelos.');
$akumaReal = array_values(array_filter($akumaEntries, function ($e) { return $e['s'] === 'div.akumaItem'; }));
$akumaTexts = array_column($akumaReal, 'x');
sort($akumaTexts);
hlw_expect(count($akumaReal) === 2 && $akumaTexts === array('Batto Batto no Mi', 'Kumo Kumo no Mi') && $akumaReal[0]['g'] === 1 && $akumaReal[1]['g'] === 1, 'Las dos tarjetas comparten selector, se distinguen por el texto y son delegadas.');
$akumaTokens = array_column($akumaReal, 't');
hlw_expect(count(array_unique($akumaTokens)) === 2, 'Cada fruta lleva su propio codigo de visita.');
foreach ($akumaEntries as $e) { hlw_expect(array_key_exists('x', $e), 'Todas las entradas llevan el campo x.'); }
$_SERVER['SCRIPT_NAME'] = '/op/halloween2026.php';
$eventPageEntries = opg_hlw_secret_issue(77, 'halloween2026.php', array('hs-f3d8m1' => true), 1000);
hlw_expect(in_array('div.hlw-header__mark', array_column($eventPageEntries, 's'), true), 'La pagina del evento emite el selector del fantasma.');
hlw_expect(count($eventPageEntries) === 2 + OPG_HLW_SECRET_DECOYS, 'La pagina del evento emite los secretos 12 y 15 (el 13 es de compra y no lleva codigo) mas los señuelos.');
$_SERVER['SCRIPT_NAME'] = '/tests/halloween2026/secretos.php';
foreach (array('otra.php', 'index.php', 'viajes.php') as $anyPage) {
    hlw_expect(isset(opg_hlw_secret_page_entries($anyPage, array(), 'x.php')['hs-f3d8m1']), "El secreto global debe estar en {$anyPage}.");
}
$anyEntries = opg_hlw_secret_issue(77, 'otra.php', array(), 1000);
hlw_expect(count($anyEntries) === 1 + OPG_HLW_SECRET_DECOYS && in_array('#ficha', array_column($anyEntries, 's'), true), 'En una pagina cualquiera sale el secreto global mas los señuelos.');
hlw_expect(opg_hlw_secret_issue(77, 'otra.php', array('hs-f3d8m1' => true), 1000) === array(), 'Con el global descubierto, una pagina cualquiera no emite nada.');

// ── Atajo por cookie ──
$_COOKIE['ei_g'] = '';
hlw_expect(opg_hlw_secret_cookie_found() === array(), 'Sin cookie no hay secretos conocidos.');
hlw_expect(array_keys(opg_hlw_secret_pending_by_cookie(opg_hlw_secret_page_entries('otra.php', array(), 'x.php'))) === array('hs-f3d8m1'), 'Sin cookie, el global sigue pendiente.');
$_COOKIE['ei_g'] = '1.14';
hlw_expect(opg_hlw_secret_cookie_found() === array(1, 14), 'La cookie se lee como lista de numeros.');
hlw_expect(opg_hlw_secret_pending_by_cookie(opg_hlw_secret_page_entries('otra.php', array(), 'x.php')) === array(), 'Con el 14 en la cookie, una pagina cualquiera no tiene nada pendiente.');
$_COOKIE['ei_g'] = '1.14';
$pendingKeys = array_keys(opg_hlw_secret_pending_by_cookie(opg_hlw_secret_page_entries('index.php', array(), 'x.php')));
sort($pendingKeys);
$expectedPending = array('hs-k7q2m9', 'hs-p4x8d1', 'hs-m2c6w5', 'hs-t2u5l9', 'hs-j4h8t5');
sort($expectedPending);
hlw_expect($pendingKeys === $expectedPending, 'La cookie solo descarta globales: los secretos de la pagina siguen pendientes.');
$_COOKIE['ei_g'] = 'basura.14.x';
hlw_expect(opg_hlw_secret_cookie_found() === array(0, 14), 'Una cookie rara no rompe nada.');
unset($_COOKIE['ei_g']);

// ── Billete, configuracion cifrada y script del navegador ──
$ticket = opg_hlw_secret_ticket(77, 'estadisticas.php', '/op/estadisticas.php', array('accion' => 'mas-experiencia'), 1000);
$ticketPayload = opg_hlw_secret_token_open($ticket);
hlw_expect($ticketPayload !== null && (int)$ticketPayload['d'] === 2 && (int)$ticketPayload['u'] === 77 && $ticketPayload['p'] === 'estadisticas.php' && $ticketPayload['q'] === array('accion' => 'mas-experiencia'), 'El billete lleva usuario, pagina y parametros, y se distingue de un codigo (d = 2).');
hlw_expect(strpos($ticket, 'estadisticas') === false && strpos($ticket, 'accion') === false, 'El billete es opaco: no deja ver la pagina ni los parametros.');

// La pagina, el archivo y los parametros del billete son los que se usan al emitir la configuracion.
$fromTicket = opg_hlw_secret_issue(77, 'estadisticas.php', array('hs-f3d8m1' => true), 1000, array('accion' => 'mas-experiencia'), '/op/estadisticas.php');
hlw_expect(count($fromTicket) === 1 + OPG_HLW_SECRET_DECOYS, 'Con los parametros del billete, estadisticas lleva el secreto 4 mas los señuelos.');
hlw_expect(opg_hlw_secret_issue(77, 'estadisticas.php', array('hs-f3d8m1' => true), 1000, array('accion' => 'censo'), '/op/estadisticas.php') === array(), 'Con otros parametros no sale nada.');

// Cifrado de la configuracion: no se parece a un JSON en base64 y se deshace con el mismo billete.
$config = array_map(function ($e) { return array($e['t'], $e['s'], $e['n'], $e['g'], $e['x']); }, $fromTicket);
$encoded = opg_hlw_secret_config_encode($config, $ticket);
hlw_expect(strpos($encoded, 'eyJ') !== 0 && strpos($encoded, 'W1s') !== 0, 'La configuracion cifrada no empieza como el base64 de un JSON.');
hlw_expect(strpos(base64_decode(strtr($encoded, '-_', '+/')), 'a.ranking') === false, 'Los selectores no se leen en la configuracion cifrada.');
hlw_expect(opg_hlw_secret_config_decode($encoded, $ticket) === $config, 'La configuracion cifrada se recupera con el mismo billete.');
hlw_expect(opg_hlw_secret_config_decode($encoded, opg_hlw_secret_ticket(77, 'estadisticas.php', '/op/estadisticas.php', array(), 1000)) !== $config, 'Con otro billete no se recupera.');
hlw_expect(opg_hlw_secret_config_decode('%%%', $ticket) === null, 'Una cadena rota no se decodifica.');

// Resultado compacto para el navegador.
$compact = opg_hlw_secret_client_result(array('estado' => 'new', 'numero' => 5, 'nombre' => 'X', 'mensaje' => 'm', 'premio' => 'p', 'imagen' => '/i.png', 'kicker' => 'k', 'titulo' => 't', 'boton' => 'b', 'motivo' => 'secreto'));
hlw_expect($compact === array('new', 5, 'X', 'm', 'p', '/i.png', 'k', 't', 'b', ''), 'El resultado compacto no incluye el motivo salvo para las cuentas de prueba.');
hlw_expect(opg_hlw_secret_client_result(array('estado' => 'closed', 'motivo' => 'senuelo'), true)[9] === 'senuelo', 'Las cuentas de prueba reciben el motivo.');
hlw_expect(opg_hlw_secret_client_result(array())[0] === 'closed', 'Sin estado se considera cerrado.');

// El script que se escribe en la pagina no debe decir nada del evento ni de su mecanismo.
$script = opg_hlw_client_script($ticket, 'clave');
hlw_expect(strpos($script, '<script>') === 0 && substr($script, -9) === '</script>', 'El script se escribe inline.');
hlw_expect(strpos($script, $ticket) !== false && strpos($script, "'clave'") !== false, 'El script lleva el billete y la clave de sesion.');
// Las palabras se buscan con un billete fijo: el real es aleatorio y podria contener cualquier secuencia de letras por azar.
$neutral = opg_hlw_client_script('AAAAAAAAAAAAAAAA', 'clave');
foreach (array('secret', 'señuelo', 'senuelo', 'halloween', 'hlw', 'calabaza', 'premio', 'felicid', 'descubier', 'prensa', 'indice-extra') as $word) {
    hlw_expect(stripos($neutral, $word) === false, "El script del navegador no debe contener la palabra: {$word}");
}
hlw_expect(strpos($script, '{{') === false, 'No quedan marcadores sin sustituir.');

// ── Donde se escribe el script ──
hlw_expect(opg_hlw_secret_insert_position('<html><body>x</body></html>') === strpos('<html><body>x</body></html>', '</body>'), 'Con </body>, se escribe antes de el.');
hlw_expect(opg_hlw_secret_insert_position('<div>x</div></html>') === strpos('<div>x</div></html>', '</html>'), 'Sin </body> pero con </html>, antes de </html>.');
$ownPage = "<html>\n<head><title>Prensa Rosa</title></head>\n<div class=\"portada\">pagina propia sin cierre</div>";
hlw_expect(opg_hlw_secret_insert_position($ownPage) === strlen($ownPage), 'Una pagina propia sin cierre (la Prensa Rosa) recibe el script al final.');
hlw_expect(opg_hlw_secret_insert_position('<head><title>x</title></head><div>y</div>') === strlen('<head><title>x</title></head><div>y</div>'), 'Un HTML sin cierre con <head> y <div> recibe el script al final.');
hlw_expect(opg_hlw_secret_insert_position('{"ok":true}') === false, 'Un JSON no recibe el script.');
hlw_expect(opg_hlw_secret_insert_position('  [1,2,3]') === false, 'Un JSON con lista tampoco.');
hlw_expect(opg_hlw_secret_insert_position('texto plano sin etiquetas') === false, 'El texto plano no recibe el script.');
hlw_expect(opg_hlw_secret_insert_position('') === false && opg_hlw_secret_insert_position(null) === false, 'Una respuesta vacia no recibe el script.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo 'Halloween secretos: OK' . PHP_EOL;
