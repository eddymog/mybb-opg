<?php
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026.php';
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026_store.php';

$failures = array();
function hlw_expect($condition, $message)
{
    global $failures;
    if (!$condition) $failures[] = $message;
}

$config = array(
    'status' => 'active',
    'earn_start_utc' => '2026-10-26 05:00:00',
    'earn_end_utc' => '2026-11-10 05:00:00',
    'timezone' => 'Europe/Madrid',
    'settings' => array(
        'role_root_fid' => 10,
        'common_prefix_id' => 1,
        'adventure_prefix_id' => 3,
        'spooky_prefix_id' => 15,
    ),
);

hlw_expect(!opg_hlw_timestamp_in_earning_window(strtotime('2026-10-26 04:59:59 UTC'), $config), 'Premia antes de abrir.');
hlw_expect(opg_hlw_timestamp_in_earning_window(strtotime('2026-10-26 05:00:00 UTC'), $config), 'No premia al abrir.');
hlw_expect(opg_hlw_timestamp_in_earning_window(strtotime('2026-11-10 04:59:59 UTC'), $config), 'No incluye el ultimo segundo.');
hlw_expect(!opg_hlw_timestamp_in_earning_window(strtotime('2026-11-10 05:00:00 UTC'), $config), 'Premia despues de cerrar.');

hlw_expect(opg_hlw_event_day(strtotime('2026-10-27 04:59:59 UTC')) === '2026-10-26', 'El dia cambia antes de las 06:00.');
hlw_expect(opg_hlw_event_day(strtotime('2026-10-27 05:00:00 UTC')) === '2026-10-27', 'El dia no cambia a las 06:00.');
hlw_expect(opg_hlw_forum_is_role(10, '', $config), 'El FID raiz no cuenta como rol.');
hlw_expect(opg_hlw_forum_is_role(44, '1,10,44', $config), 'Un descendiente de rol no cuenta.');
hlw_expect(!opg_hlw_forum_is_role(110, '1,110', $config), 'Coincidencia parcial de FID aceptada.');
hlw_expect(opg_hlw_thread_reward(1, $config)['amount'] === 2, 'Tema Comun con recompensa incorrecta.');
hlw_expect(opg_hlw_thread_reward(3, $config)['amount'] === 2, 'Aventura con recompensa incorrecta.');
hlw_expect(opg_hlw_thread_reward(15, $config)['amount'] === 3, 'Spooky no sustituye la recompensa normal.');
hlw_expect(opg_hlw_thread_reward(0, $config)['amount'] === 0, 'Un tema sin prefijo recibe recompensa.');

$testConfig = $config;
$testConfig['status'] = 'draft';
$testConfig['settings']['live_test'] = array(
    'enabled' => true,
    'uid' => 315,
    'run_id' => 'domain-test',
    'started_at_utc' => gmdate('Y-m-d H:i:s', time() - 60),
    'expires_at_utc' => gmdate('Y-m-d H:i:s', time() + 60),
);
$testPublication = array('uid' => 315, 'original_dateline' => time());
hlw_expect(opg_hlw_live_test_context($testPublication, $testConfig) !== null, 'La prueba real no admite al UID autorizado.');
$testPublication['uid'] = 314;
hlw_expect(opg_hlw_live_test_context($testPublication, $testConfig) === null, 'La prueba real admite otro UID.');
$testConfig['status'] = 'active';
$testPublication['uid'] = 315;
hlw_expect(opg_hlw_live_test_context($testPublication, $testConfig) === null, 'La prueba real funciona fuera de draft.');

$quantities = opg_hlw_store_parse_quantities(array('2' => '150', '3' => '-4', 'x' => '8'));
hlw_expect($quantities === array(2 => 100), 'El carrito no limita o descarta cantidades invalidas.');

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "Halloween domain: OK\n";
