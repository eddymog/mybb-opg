<?php
// staff/importar_tecnicas.php
// Permite a usuarios staff importar técnicas desde un archivo CSV y actualizar la tabla op_tecnicas

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'importar_tecnicas.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";
require_once MYBB_ROOT . 'inc/plugins/lib/tecnicas_cambios.php';

global $templates, $mybb, $db;
$uid = (int)$mybb->user['uid'];

if (!is_staff($uid)) {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¡Villano!";
    eval("$page = \"".$templates->get("op_redireccion")."\"");
    output_page($page);
    exit;
}

$table = 'mybb_op_tecnicas';
$cols = [
    'tid', 'nombre', 'estilo', 'clase', 'tier',
    'rama', 'tipo', 'exclusiva', 'energia', 'energia_turno',
    'haki', 'haki_turno', 'enfriamiento', 'efectos',
    'requisitos', 'descripcion'
];

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
    $csvFile = $_FILES['csv']['tmp_name'];
    // Leer todo el archivo y convertir a UTF-8 si es necesario
    $csv_content = file_get_contents($csvFile);
    // Detectar codificación (Windows-1252, ISO-8859-1, UTF-8)
    $encoding = mb_detect_encoding($csv_content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding !== 'UTF-8') {
        $csv_content = mb_convert_encoding($csv_content, 'UTF-8', $encoding);
    }
    // Guardar en un temporal UTF-8 para fgetcsv
    $tmp_utf8 = tmpfile();
    fwrite($tmp_utf8, $csv_content);
    rewind($tmp_utf8);
    $handle = $tmp_utf8;
    // Detectar delimitador automáticamente (coma o punto y coma)
    $firstLine = fgets($handle);
    rewind($handle);
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
    // Leer cabecera
    $header = fgetcsv($handle, 0, $delimiter);
    if ($header === false || array_map('strtolower', $header) !== array_map('strtolower', $cols)) {
        $mensaje = '<div style="color:red">El CSV no tiene la cabecera esperada.<br>Cabecera recibida: <code>' . htmlspecialchars(implode($delimiter, $header)) . '</code></div>';
    } else {
        $count = 0;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($data) !== count($cols)) continue; // Saltar filas mal formateadas
            $row = array_combine($cols, $data);
            // Escapar y limpiar datos
            foreach ($row as $k => $v) {
                $row[$k] = $db->escape_string($v);
            }
            // Actualizar por tid
            $tid = $row['tid'];
            $anterior = $db->fetch_array($db->query("SELECT * FROM `$table` WHERE `tid`='$tid' LIMIT 1"));
            $update = $row;
            unset($update['tid']);
            $set = [];
            foreach ($update as $k => $v) {
                $set[] = "`$k`='$v'";
            }
            $set_sql = implode(',', $set);
            $db->write_query("UPDATE `$table` SET $set_sql WHERE `tid`='$tid'");
            if ($anterior) {
                $nuevo = $db->fetch_array($db->query("SELECT * FROM `$table` WHERE `tid`='$tid' LIMIT 1"));
                opg_tecnicas_registrar_cambio($tid, $anterior, $nuevo, $uid, $mybb->user['username'], 'Importación CSV');
            }
            $count++;
        }
        $mensaje = "<div style='color:green'>Se han actualizado $count técnicas correctamente.</div>";
    }
    fclose($handle);
}

// Formulario HTML
$page = "<h2>Importar técnicas desde CSV</h2>
<form method='post' enctype='multipart/form-data'>
    <input type='file' name='csv' accept='.csv' required> <br><br>
    <button type='submit'>Importar CSV</button>
</form>
$mensaje
<p>El archivo debe tener la cabecera exacta:<br><code>".implode(',', $cols)."</code></p>";

echo $page;
