<?php
/** Proceso de apoyo de concurrencia.php: php worker.php <accion> <argumentos...> <instante_de_salida> */
require __DIR__ . '/bootstrap_db.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
require MYBB_ROOT . 'inc/plugins/lib/recompensas_aventura.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras_entregas.php';
class PostDataHandler
{
    public $action; private $d;
    public function __construct($a) {}
    public function set_data($d) { $this->d = $d; }
    public function validate_post() { return true; }
    public function get_friendly_errors() { return array(); }
    public function insert_post()
    {
        global $db;
        $d = $this->d;
        $db->query("SET SESSION sql_mode = ''", 1);
        $pid = random_int(100000, 999999999);
        $db->query("INSERT INTO mybb_posts (pid, tid, uid, username, message, dateline) VALUES ($pid, " . (int) $d['tid'] . ", " . (int) $d['uid'] . ", 'U', '" . $db->escape_string($d['message']) . "', " . time() . ")", 1);
        return array('pid' => $pid);
    }
}
$GLOBALS['roles'] = array('mod' => array(), 'staff' => array(50), 'narra' => array());
$accion = $argv[1];
$salida = (float) end($argv);
while (microtime(true) < $salida) { usleep(200); }   // salen todos a la vez
switch ($accion) {
    case 'crear':
        $v = opg_av_validar_solicitud(array('rol' => 'jugador', 'participantes' => array(5),
            'tiene_narrador' => '0', 'isla_fid' => 40, 'dia' => (int) $argv[2],
            'tier' => 2, 'comentario' => 'Aventura concurrente'), 3);
        $r = $v['errores'] ? opg_av_fallo(422, $v['errores']) : opg_av_crear($v['datos'], 3);
        break;
    case 'pagar':
        $r = opg_ent_pagar((int) $argv[2], array('confirma_minimos' => '1'), 50);
        break;
    case 'postular':
        $r = opg_av_postular_narrador((int) $argv[2], (int) $argv[3], true);
        break;
    case 'aceptar_propuesta':
        $r = opg_av_propuesta_aceptar((int) $argv[2], (int) $argv[3]);
        break;
    case 'aprobar':
        $r = opg_av_cambiar_estado((int) $argv[2], 1, 50);
        break;
    case 'enviar':
        $r = opg_ent_enviar((int) $argv[2], array('tema' => $argv[3], 'resumen' => 'x', 'reputacion' => 'positiva', 'confirmar_grupo' => '1'), (int) $argv[4]);
        break;
}
echo json_encode(array('ok' => !empty($r['ok']), 'codigo' => isset($r['codigo']) ? $r['codigo'] : 200));
