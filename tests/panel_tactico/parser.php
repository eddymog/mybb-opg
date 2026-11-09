<?php

define('IN_MYBB', 1);

function htmlspecialchars_uni($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

class PanelTacticoFakeDb
{
    public function escape_string($value)
    {
        return addslashes($value);
    }

    public function simple_select($table, $columns, $where = '', $options = array())
    {
        if ($table === 'op_tecnicas') {
            return array(array(
                'tid' => 'TEC1',
                'nombre' => 'Corte del Alba',
                'tier' => 3,
                'clase' => 'Ofensiva',
                'energia' => 35,
                'energia_turno' => 0,
                'haki' => 0,
                'haki_turno' => 0,
            ));
        }
        if ($table === 'op_objetos') {
            return array(array(
                'objeto_id' => 'OBJ1',
                'nombre' => 'Bala explosiva',
                'apodo' => '',
                'tier' => 2,
                'subcategoria' => 'Municion',
            ));
        }
        return array();
    }

    public function fetch_array(&$query)
    {
        $row = current($query);
        if ($row === false) {
            return false;
        }
        next($query);
        return $row;
    }
}

$db = new PanelTacticoFakeDb();
require_once dirname(__DIR__, 2) . '/inc/plugins/lib/panel_tactico.php';

function pt_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FALLO: {$message}\n");
        exit(1);
    }
}

$message = '[quote][tecnica=NO_CONTAR][/quote]' . "\n"
    . '[tecnica=TEC1]' . "\n"
    . '[code][objeto=NO_CONTAR][/code]' . "\n"
    . '[objeto=OBJ1]' . "\n"
    . '[hide][recursos=1/2/3][/hide]' . "\n"
    . '[recursos="400/120/50"]';

$events = opg_pt_extract_events($message);
pt_assert(count($events) === 3, 'Debe ignorar quote, code y hide.');
pt_assert($events[0]['type'] === 'tecnica' && $events[0]['entity_id'] === 'TEC1', 'Debe extraer la tecnica valida.');
pt_assert($events[0]['data']['nombre_snapshot'] === 'Corte del Alba', 'Debe guardar el nombre historico de la tecnica.');
pt_assert($events[1]['type'] === 'objeto_mostrado' && $events[1]['entity_id'] === 'OBJ1', 'Debe extraer el objeto valido.');
pt_assert($events[2]['type'] === 'recurso', 'Debe extraer recursos.');
pt_assert($events[2]['data']['vida'] === 400 && $events[2]['data']['energia'] === 120 && $events[2]['data']['haki'] === 50, 'Debe conservar el orden vida/energia/haki.');

$nested = '[quote=Usuario][quote][tecnica=TEC1][/quote][/quote][objeto=OBJ1]';
$events = opg_pt_extract_events($nested);
pt_assert(count($events) === 1 && $events[0]['type'] === 'objeto_mostrado', 'Debe ignorar citas anidadas.');

$persistedHide = '[hide=2][tecnica=TEC1]';
$events = opg_pt_extract_events($persistedHide);
pt_assert(count($events) === 1, 'Un marcador hide persistido no debe ocultar el resto del post.');

$unclosed = '[code][tecnica=TEC1] texto';
$events = opg_pt_extract_events($unclosed);
pt_assert(count($events) === 0, 'Un bloque ignorado sin cerrar debe ocultar conservadoramente el resto.');

echo "Panel tactico parser: OK\n";
