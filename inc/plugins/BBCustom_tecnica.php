<?php
/**
 * BBCustom Tecnica Plugin
 * Procesa BBCode [tecnica=TID]
 */

if (!defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.");
}

// Cargar librería de spoilers
require_once MYBB_ROOT . "inc/plugins/lib/spoiler.php";

// Hook para procesar DESPUÉS de que MyBB termine todo el procesamiento
$plugins->add_hook("postbit", "BBCustom_tecnica_run");
$plugins->add_hook("postbit_prev", "BBCustom_tecnica_run");

function BBCustom_tecnica_info()
{
	return array(
		"name"			=> "Tecnica BBCode",
		"description"	=> "BBCode [tecnica=TID] modular para mostrar técnicas",
		"website"		=> "",
		"author"		=> "Cascabelles",
		"authorsite"	=> "",
		"version"		=> "1.0",
		"codename"		=> "BBCustom_tecnica",
		"compatibility"	=> "*"
	);
}

function BBCustom_tecnica_activate() {}
function BBCustom_tecnica_deactivate() {}

function BBCustom_tecnica_run(&$post)
{
	global $db;
	
	static $processed_pids = array();
	$current_pid = isset($post['pid']) ? $post['pid'] : md5($post['message']);
	
	if (in_array($current_pid, $processed_pids)) {
		return;
	}
	
	$message = &$post['message'];
	$uid = $post['uid'];
	
	while (preg_match('#\[tecnica=(.*?)\]#si', $message, $matches))
	{
		$tec_tid = $matches[1];
		
		// Buscar técnica en base de datos
		$query = $db->query("SELECT * FROM mybb_op_tecnicas WHERE tid='$tec_tid'");
		$tecnica = $db->fetch_array($query);
		
		if (!$tecnica) {
			$message = preg_replace('#\[tecnica=' . preg_quote($tec_tid, '#') . '\]#si', 
				'<div style="background: #f44336; color: white; padding: 10px; border-radius: 5px;">Técnica no encontrada: ' . $tec_tid . '</div>', 
				$message, 1);
			continue;
		}
		
		// Verificar si el usuario la tiene aprendida
		$query_aprendida = $db->query("SELECT tiempo FROM mybb_op_tec_aprendidas WHERE uid='$uid' AND tid='$tec_tid'");
		$aprendida = $db->fetch_array($query_aprendida);
		$fecha_aprendida = $aprendida ? date('j/n/Y', strtotime($aprendida['tiempo'])) : 'No Aprendida';
		
		// Generar HTML
		$html = BBCustom_tecnica_generate_html($tecnica, $fecha_aprendida);
		$spoiler = create_technique_spoiler($tecnica['nombre'], $html);
		
		$message = preg_replace('#\[tecnica=' . preg_quote($tec_tid, '#') . '\]#si', $spoiler, $message, 1);
	}
	
	$processed_pids[] = $current_pid;
}

function BBCustom_tecnica_generate_html($tec, $fecha_aprendida)
{
	$tid = strtoupper($tec['tid']);
	$nombre = $tec['nombre'];
	$estilo = $tec['estilo'];
	$clase = strtoupper($tec['clase']);
	$tier = $tec['tier'];
	$requisitos = $tec['requisitos'];
	$descripcion = nl2br($tec['descripcion']);
	$efectos = $tec['efectos'];
	$enfriamiento = $tec['enfriamiento'];
	
	// Costos
	$energia = $tec['energia'];
	$energia_turno = $tec['energia_turno'];
	$haki = $tec['haki'];
	$haki_turno = $tec['haki_turno'];
	
	// Generar iconos de costos
	$costos_html = '';
	
	if ($energia) {
		$costos_html .= "
			<div style='display: flex; align-items: center; gap: 5px;' title='Costo de Energía'>
				<div style='font-size: 24px; font-weight: bold; color: #ffa100; text-shadow: 1px 1px 2px black;'>$energia</div>
				<div style='font-size: 14px; color: #ffa100;'>⚡</div>
			</div>";
	}
	
	if ($energia_turno) {
		$costos_html .= "
			<div style='display: flex; align-items: center; gap: 5px;' title='Costo de Energía por Turno'>
				<div style='font-size: 24px; font-weight: bold; color: #ffa100; text-shadow: 1px 1px 2px black;'>$energia_turno</div>
				<div style='font-size: 14px; color: #ffa100;'>⚡/T</div>
			</div>";
	}
	
	if ($haki) {
		$costos_html .= "
			<div style='display: flex; align-items: center; gap: 5px;' title='Costo de Haki'>
				<div style='font-size: 24px; font-weight: bold; color: #21DEFF; text-shadow: 1px 1px 2px black;'>$haki</div>
				<div style='font-size: 14px; color: #21DEFF;'>🔮</div>
			</div>";
	}
	
	if ($haki_turno) {
		$costos_html .= "
			<div style='display: flex; align-items: center; gap: 5px;' title='Costo de Haki por Turno'>
				<div style='font-size: 24px; font-weight: bold; color: #21DEFF; text-shadow: 1px 1px 2px black;'>$haki_turno</div>
				<div style='font-size: 14px; color: #21DEFF;'>🔮/T</div>
			</div>";
	}
	
	if ($enfriamiento) {
		$costos_html .= "
			<div style='display: flex; align-items: center; gap: 5px;' title='Enfriamiento'>
				<div style='font-size: 24px; font-weight: bold; color: #D8EC13; text-shadow: 1px 1px 2px black;'>$enfriamiento</div>
				<div style='font-size: 14px; color: #D8EC13;'>⏱️</div>
			</div>";
	}
	
	$requisitos_html = $requisitos ? "<div style='background: rgba(255,193,7,0.2); padding: 10px; border-radius: 8px; border-left: 4px solid #ffc107; margin-bottom: 10px;'><strong>Requisitos:</strong> $requisitos</div>" : '';
	
	return "
		<div style='border-radius: 15px; padding: 20px; margin: 10px 0; box-shadow: 0 8px 32px rgba(0,0,0,0.3); color: white; background: linear-gradient(135deg, #8a2be2, #6a1bb2, #4a0b82); border: 2px solid #dda0dd;'>
			<div style='display: flex; gap: 20px;'>
				<div style='flex: 0 0 200px;'>
					<div style='background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px; margin-bottom: 8px; text-align: center;'>
						<div style='font-size: 14px; font-weight: bold; color: #dda0dd;'>$tid</div>
					</div>
					<div style='background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px; margin-bottom: 8px; text-align: center;'>
						<div style='font-size: 14px; font-weight: bold;'>$clase</div>
					</div>
					<div style='background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px; margin-bottom: 8px; text-align: center;'>
						<div style='font-size: 14px;'>$estilo</div>
					</div>
					<div style='background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px; margin-bottom: 8px; text-align: center;'>
						<div style='font-size: 14px; font-weight: bold; color: #ffd700;'>Tier $tier</div>
					</div>
					<div style='background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px; margin-bottom: 8px; text-align: center;'>
						<div style='font-size: 12px; color: " . ($fecha_aprendida != 'No Aprendida' ? '#4caf50' : '#f44336') . ";'>$fecha_aprendida</div>
					</div>
					
					<div style='display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 15px;'>
						$costos_html
					</div>
				</div>
				
				<div style='flex: 1;'>
					$requisitos_html
					<div style='background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; margin-bottom: 10px;'>
						<h4 style='margin: 0 0 10px 0; color: #dda0dd; font-size: 16px;'>Descripción</h4>
						<div style='line-height: 1.6; font-size: 14px;'>$descripcion</div>
					</div>
					<div style='background: rgba(138,43,226,0.3); padding: 15px; border-radius: 8px; border-left: 4px solid #8a2be2;'>
						<h4 style='margin: 0 0 10px 0; color: #dda0dd; font-size: 16px;'>Efectos</h4>
						<div style='line-height: 1.6; font-size: 14px;'>$efectos</div>
					</div>
				</div>
			</div>
		</div>
	";
}
