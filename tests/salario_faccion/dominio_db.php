<?php
/** Prueba con MySQL 5.7 desechable. Requiere el contenedor opg-test-mysql. */
define('IN_MYBB', 1);
define('MYBB_ROOT', realpath(__DIR__ . '/../../') . '/');

class SfDbPrueba
{
    public $link;
    public $table_prefix = 'mybb_';

    public function __construct()
    {
        mysqli_report(MYSQLI_REPORT_OFF);
        $this->link = mysqli_connect('127.0.0.1', 'root', 'opgtest', 'opg_test', 33307);
        if (!$this->link) {
            fwrite(STDERR, "No se pudo conectar al MySQL de pruebas.\n");
            exit(2);
        }
        mysqli_set_charset($this->link, 'utf8');
    }
    public function query($sql) { return mysqli_query($this->link, $sql); }
    public function fetch_array($q) { return mysqli_fetch_assoc($q); }
    public function escape_string($s) { return mysqli_real_escape_string($this->link, (string) $s); }
    public function affected_rows() { return mysqli_affected_rows($this->link); }
    public function table_exists($tabla)
    {
        $tabla = $this->escape_string($this->table_prefix . $tabla);
        $q = $this->query("SHOW TABLES LIKE '{$tabla}'");
        return $q && mysqli_num_rows($q) > 0;
    }
}

$db = new SfDbPrueba();

function sf_script($sql)
{
    global $db;
    if (!mysqli_multi_query($db->link, $sql)) {
        fwrite(STDERR, 'Error preparando esquema: ' . mysqli_error($db->link) . "\n");
        exit(2);
    }
    do {
        if ($r = mysqli_store_result($db->link)) { mysqli_free_result($r); }
    } while (mysqli_more_results($db->link) && mysqli_next_result($db->link));
}

sf_script("
DROP TABLE IF EXISTS mybb_op_salario_faccion_pagos;
DROP TABLE IF EXISTS mybb_op_salario_faccion_repartos;
DROP TABLE IF EXISTS mybb_op_audit_general;
DROP TABLE IF EXISTS mybb_op_audit_consola_mod;
DROP TABLE IF EXISTS mybb_op_fichas;
DROP TABLE IF EXISTS mybb_tasks;
CREATE TABLE mybb_op_fichas (
    fid int NOT NULL, nombre varchar(50) NOT NULL, faccion varchar(30) NOT NULL,
    rango varchar(255) NOT NULL, berries int NOT NULL DEFAULT 0, PRIMARY KEY (fid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE mybb_op_audit_general (
    id int NOT NULL AUTO_INCREMENT, uid varchar(20) NOT NULL, username varchar(100) NOT NULL,
    user_uid varchar(20) NOT NULL, categoria varchar(255) NOT NULL, log text NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE mybb_op_audit_consola_mod (
    id int NOT NULL AUTO_INCREMENT, staff varchar(20) NOT NULL, username varchar(100) NOT NULL,
    razon varchar(100) NOT NULL, log text NOT NULL, PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE mybb_tasks (
    tid int unsigned NOT NULL AUTO_INCREMENT, title varchar(120) NOT NULL DEFAULT '', description text NOT NULL,
    file varchar(30) NOT NULL DEFAULT '', minute varchar(200) NOT NULL DEFAULT '', hour varchar(200) NOT NULL DEFAULT '',
    day varchar(100) NOT NULL DEFAULT '', month varchar(30) NOT NULL DEFAULT '', weekday varchar(15) NOT NULL DEFAULT '',
    nextrun int unsigned NOT NULL DEFAULT 0, lastrun int unsigned NOT NULL DEFAULT 0, enabled tinyint NOT NULL DEFAULT 1,
    logging tinyint NOT NULL DEFAULT 0, locked int unsigned NOT NULL DEFAULT 0, PRIMARY KEY (tid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");
sf_script(file_get_contents(MYBB_ROOT . 'docs/salario_faccion_automatico_migration.sql'));

function op_facciones_sueldos_semanales()
{
    return array('ReclutaM' => 10000, 'Capitan' => 25000);
}

require MYBB_ROOT . 'inc/plugins/lib/salario_faccion.php';

$fallos = 0;
function sf_db_prueba($nombre, $actual, $esperado)
{
    global $fallos;
    if ($actual !== $esperado) {
        $fallos++;
        echo "FALLO {$nombre}: " . var_export($actual, true) . ' != ' . var_export($esperado, true) . "\n";
    } else {
        echo "ok {$nombre}\n";
    }
}
function sf_valor($sql)
{
    global $db;
    $fila = mysqli_fetch_assoc($db->query($sql));
    return $fila ? reset($fila) : null;
}

$db->query("INSERT INTO mybb_op_fichas VALUES
    (10,'Nami','Marina','ReclutaM',500),(11,'Smoker','Marina','Capitan',1000),(12,'Luffy','Pirata','Pirata',900)");
$lunes = (new DateTimeImmutable('2026-11-23 06:00:00', new DateTimeZone('Europe/Madrid')))->getTimestamp();
$primero = opg_sf_repartir(0, 'Sistema automático', 'automatico', $lunes);
$segundo = opg_sf_repartir(0, 'Sistema automático', 'automatico', $lunes);

sf_db_prueba('primer lote pagado', array($primero['codigo'], $primero['pagados'], $primero['total']), array('repartido', 2, 35000));
sf_db_prueba('segundo lote idempotente', $segundo['codigo'], 'ya_repartido');
sf_db_prueba('saldo recluta', (int) sf_valor('SELECT berries FROM mybb_op_fichas WHERE fid=10'), 10500);
sf_db_prueba('saldo capitán', (int) sf_valor('SELECT berries FROM mybb_op_fichas WHERE fid=11'), 26000);
sf_db_prueba('no elegible intacto', (int) sf_valor('SELECT berries FROM mybb_op_fichas WHERE fid=12'), 900);
sf_db_prueba('dos pagos registrados', (int) sf_valor('SELECT COUNT(*) FROM mybb_op_salario_faccion_pagos'), 2);
sf_db_prueba('dos auditorías personales', (int) sf_valor('SELECT COUNT(*) FROM mybb_op_audit_general'), 2);

// La falta de la auditoría provoca error y toda la semana siguiente se revierte.
$db->query('DROP TABLE mybb_op_audit_general');
$lunesSiguiente = (new DateTimeImmutable('2026-11-30 06:00:00', new DateTimeZone('Europe/Madrid')))->getTimestamp();
$fallido = opg_sf_repartir(0, 'Sistema automático', 'automatico', $lunesSiguiente);
sf_db_prueba('fallo detectado', $fallido['codigo'], 'error');
sf_db_prueba('saldo revertido', (int) sf_valor('SELECT berries FROM mybb_op_fichas WHERE fid=10'), 10500);
sf_db_prueba('lote fallido no reservado', (int) sf_valor("SELECT COUNT(*) FROM mybb_op_salario_faccion_repartos WHERE semana_inicio='2026-11-30'"), 0);

exit($fallos ? 1 : 0);
