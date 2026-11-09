<?php
/**
 * Registro y lectura del historial público de cambios de técnicas.
 *
 * Los cambios nuevos se guardan como JSON precedido por una marca estable dentro
 * del log general. Los registros históricos conservan su formato y se interpretan
 * de forma tolerante en op/historial_tecnicas.php.
 */

const OPG_TECNICAS_CAMBIO_MARCA = '[CAMBIO_TECNICA] ';

function opg_tecnicas_cambio_campos()
{
    return array(
        'nombre' => 'Nombre',
        'estilo' => 'Estilo',
        'clase' => 'Clase',
        'tier' => 'Tier',
        'rama' => 'Rama',
        'tipo' => 'Tipo',
        'exclusiva' => 'Exclusiva',
        'energia' => 'Energía',
        'energia_turno' => 'Energía por turno',
        'haki' => 'Haki',
        'haki_turno' => 'Haki por turno',
        'enfriamiento' => 'Enfriamiento',
        'efectos' => 'Efectos',
        'requisitos' => 'Requisitos',
        'descripcion' => 'Descripción',
    );
}

function opg_tecnicas_cambio_valor($valor)
{
    $valor = str_replace(array("\r\n", "\r"), "\n", trim((string) $valor));
    if (function_exists('mb_strlen')) {
        return mb_strlen($valor, 'UTF-8') > 6000
            ? mb_substr($valor, 0, 6000, 'UTF-8') . "\n[… contenido abreviado en el historial]"
            : $valor;
    }
    return strlen($valor) > 6000 ? substr($valor, 0, 6000) . "\n[… contenido abreviado en el historial]" : $valor;
}

/** Registra un alta o solo los campos que realmente cambiaron. */
function opg_tecnicas_registrar_cambio($tid, $anterior, $nuevo, $uid, $username, $origen = 'Editor de técnicas')
{
    global $db;

    $es_nueva = !is_array($anterior);
    $cambios = array();
    foreach (opg_tecnicas_cambio_campos() as $campo => $etiqueta) {
        $antes = $es_nueva ? '' : opg_tecnicas_cambio_valor(isset($anterior[$campo]) ? $anterior[$campo] : '');
        $despues = opg_tecnicas_cambio_valor(isset($nuevo[$campo]) ? $nuevo[$campo] : '');
        if ($es_nueva) {
            if ($despues !== '') {
                $cambios[] = array('campo' => $campo, 'antes' => '', 'despues' => $despues);
            }
        } elseif ($antes !== $despues) {
            $cambios[] = array('campo' => $campo, 'antes' => $antes, 'despues' => $despues);
        }
    }

    if (!$es_nueva && !$cambios) {
        return false;
    }

    $payload = array(
        'version' => 1,
        'tid' => (string) $tid,
        'nombre' => isset($nuevo['nombre']) ? (string) $nuevo['nombre'] : '',
        'accion' => $es_nueva ? 'creada' : 'modificada',
        'origen' => (string) $origen,
        'cambios' => $cambios,
    );
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    $db->query("INSERT INTO `mybb_op_audit_consola_mod` (`staff`, `username`, `razon`, `log`) VALUES ("
        . "'" . (int) $uid . "', '" . $db->escape_string($username) . "', 'Cambio de técnica', '"
        . $db->escape_string(OPG_TECNICAS_CAMBIO_MARCA . $json) . "')");
    return true;
}

/** Convierte tanto el formato nuevo como los formatos históricos conocidos. */
function opg_tecnicas_interpretar_log($log)
{
    $log = trim((string) $log);
    if (strpos($log, OPG_TECNICAS_CAMBIO_MARCA) === 0) {
        $data = json_decode(substr($log, strlen(OPG_TECNICAS_CAMBIO_MARCA)), true);
        if (is_array($data) && !empty($data['tid']) && isset($data['cambios']) && is_array($data['cambios'])) {
            return array(
                'formato' => 'estructurado',
                'tid' => (string) $data['tid'],
                'nombre' => isset($data['nombre']) ? (string) $data['nombre'] : '',
                'accion' => isset($data['accion']) && $data['accion'] === 'creada' ? 'creada' : 'modificada',
                'cambios' => $data['cambios'],
                'detalle' => '',
            );
        }
        return null;
    }

    if (preg_match('/^Técnica\s+([A-Z0-9_-]+)\s+\((modificada|creada)[^)]*\):\s*(.*)$/isu', $log, $m)) {
        return array('formato' => 'legacy', 'tid' => $m[1], 'nombre' => '', 'accion' => mb_strtolower($m[2], 'UTF-8'), 'cambios' => array(), 'detalle' => trim($m[3]));
    }
    if (preg_match('/^Cambio a técnica ID\s+([^\s]+)\s+\(([^)]*)\)\.\s*(.*)$/isu', $log, $m)) {
        return array('formato' => 'legacy', 'tid' => $m[1], 'nombre' => $m[2], 'accion' => 'modificada', 'cambios' => array(), 'detalle' => trim($m[3]));
    }
    if (preg_match('/^Técnicas creadas:\s*(.*)$/isu', $log, $m)) {
        return array('formato' => 'legacy', 'tid' => '', 'nombre' => 'Varias técnicas', 'accion' => 'creada', 'cambios' => array(), 'detalle' => trim($m[1]));
    }
    return null;
}

/** Campos que pueden identificarse con seguridad en un registro. */
function opg_tecnicas_cambio_campos_detectados($cambio)
{
    $detectados = array();
    if (!is_array($cambio)) {
        return $detectados;
    }
    if ($cambio['formato'] === 'estructurado') {
        foreach ($cambio['cambios'] as $fila) {
            if (is_array($fila) && !empty($fila['campo'])) {
                $detectados[(string) $fila['campo']] = true;
            }
        }
        return array_keys($detectados);
    }

    $detalle = isset($cambio['detalle']) ? (string) $cambio['detalle'] : '';
    foreach (array_keys(opg_tecnicas_cambio_campos()) as $campo) {
        if (preg_match('/(?:^|[\s,])' . preg_quote($campo, '/') . '\s*=/iu', $detalle)) {
            $detectados[$campo] = true;
        }
    }
    return array_keys($detectados);
}

function opg_tecnicas_cambio_grupos()
{
    return array(
        'efectos' => array('efectos'),
        'costes' => array('energia', 'energia_turno', 'haki', 'haki_turno'),
        'enfriamiento' => array('enfriamiento'),
        'descripcion' => array('descripcion'),
        'requisitos' => array('requisitos'),
        'clasificacion' => array('nombre', 'estilo', 'clase', 'tier', 'rama', 'tipo', 'exclusiva'),
    );
}

function opg_tecnicas_cambio_coincide_grupo($campos, $grupo)
{
    $grupos = opg_tecnicas_cambio_grupos();
    if ($grupo === '' || !isset($grupos[$grupo])) {
        return true;
    }
    return (bool) array_intersect($campos, $grupos[$grupo]);
}

/**
 * Determina si una técnica pertenece a los estilos del personaje.
 *
 * Las categorías generales, como "Estilo Canon", no deben incluir ramas que
 * el personaje nunca aprendió. Cuando existe una rama específica, esa rama es
 * la que determina la pertenencia; el estilo solo se usa si no hay rama propia.
 */
function opg_tecnicas_coincide_estilos_personaje($tecnica, $estilos, $ramas)
{
    if (!is_array($tecnica)) {
        return false;
    }

    $estilo = trim(isset($tecnica['estilo']) ? (string) $tecnica['estilo'] : '');
    $rama = trim(isset($tecnica['rama']) ? (string) $tecnica['rama'] : '');

    if ($rama !== '' && strcasecmp($rama, $estilo) !== 0) {
        return isset($ramas[$rama]);
    }
    if ($rama !== '' && isset($ramas[$rama])) {
        return true;
    }
    return $estilo !== '' && isset($estilos[$estilo]);
}

/** Representación legible del formato estructurado para el log interno de staff. */
function opg_tecnicas_cambio_a_texto($cambio)
{
    if (!is_array($cambio) || $cambio['formato'] !== 'estructurado') {
        return '';
    }
    $accion = $cambio['accion'] === 'creada' ? 'creada' : 'modificada';
    $texto = 'Técnica ' . $cambio['tid'] . ' (' . $accion . '): ' . $cambio['nombre'] . ".\nCambios:";
    $etiquetas = opg_tecnicas_cambio_campos();
    foreach ($cambio['cambios'] as $fila) {
        if (!is_array($fila) || empty($fila['campo'])) {
            continue;
        }
        $campo = (string) $fila['campo'];
        $etiqueta = isset($etiquetas[$campo]) ? $etiquetas[$campo] : ucfirst(str_replace('_', ' ', $campo));
        $antes = isset($fila['antes']) ? trim((string) $fila['antes']) : '';
        $despues = isset($fila['despues']) ? trim((string) $fila['despues']) : '';
        $texto .= "\n- " . $etiqueta . ': ';
        if ($cambio['accion'] === 'creada') {
            $texto .= $despues !== '' ? $despues : '(vacío)';
        } else {
            $texto .= ($antes !== '' ? $antes : '(vacío)') . ' → ' . ($despues !== '' ? $despues : '(vacío)');
        }
    }
    return $texto;
}
