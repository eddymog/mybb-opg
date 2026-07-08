<?php
/**
 * Librería de Spoiler Reutilizable
 * 
 * Sistema de spoiler independiente que puede ser usado por cualquier plugin.
 * No depende del sistema de spoiler nativo de MyBB porque me estoy cabreando mucho con esta mierda de sistema.
 * Besos y abrazos - Casca
 * 
 * Uso:
 *   require_once MYBB_ROOT."inc/plugins/lib/spoiler.php";
 *   $spoiler_html = create_custom_spoiler("Título", "Contenido aquí");
 */

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

/**
 * Crea un spoiler colapsable con ID único
 * 
 * @param string $title Título del spoiler
 * @param string $content Contenido HTML dentro del spoiler
 * @param array $options Opciones de personalización (por si lo amplio en el futuro)
 *   - 'bg_color': Color de fondo del botón (default: gradiente morado)
 *   - 'text_color': Color del texto (default: white)
 *   - 'icon': Icono/emoji antes del título (default: 📋)
 *   - 'open': Si debe mostrarse abierto inicialmente (default: false)
 * @return string HTML del spoiler
 */
function create_custom_spoiler($title, $content, $options = array())
{
	try {
	// Opciones por defecto (nuevo diseño: dorado, borde negro, fuente MOON GET!)
	$defaults = array(
		'bg_color'        => '#e8c96a',
		'bg_hover'        => '#f5d97a',
		'text_color'      => '#8964dc',
		'icon'            => '',
		'open'            => false,
		'content_bg'      => 'rgba(232, 207, 144, 0.65)',
		'btn_border'      => '2px solid #000',
		'btn_radius'      => '6px',
		'btn_font'        => "'MOON GET!', sans-serif",
		'btn_text_shadow' => '1px 1px 0 #000, -1px 1px 0 #000, 1px -1px 0 #000, -1px -1px 0 #000',
		'outer_border'    => '2px solid #000',
		'outer_radius'    => '5px',
		'force_content_color' => false,
	);
	
	$opts = array_merge($defaults, $options);
	
	$title_safe = htmlspecialchars($title);
	$unique_id = 'spoiler_' . uniqid() . '_' . mt_rand(1000, 9999);
	$initial_max_height = $opts['open'] ? 'none' : '0px';
	$initial_padding    = $opts['open'] ? '15px' : '0px';
	$radius_closed      = $opts['btn_radius'];
	$radius_open        = "{$opts['btn_radius']} {$opts['btn_radius']} 0 0";
	$initial_btn_radius = $opts['open'] ? $radius_open : $radius_closed;
	$display_icon       = !empty($opts['icon']) ? $opts['icon'] . ' ' : '';
	
	$content_color_style = $opts['force_content_color'] ? " #$unique_id *:not([data-spoiler]):not([data-spoiler] *) { color: #000 !important; }" : '';
	$btn_color = htmlspecialchars($opts['text_color']);
	$bg_color_safe  = htmlspecialchars($opts['bg_color']);
	$bg_hover_safe  = htmlspecialchars($opts['bg_hover']);
	$cnt_bg_safe    = htmlspecialchars($opts['content_bg']);

	$night_script = '';
	// El color nocturno del botón se gestiona desde showthread.php via .op_spoiler_btn

	$night_data_btn = '';
	$night_data_cnt = !empty($opts['force_content_color'])
		? " data-spoiler-generic data-day-cnt=\"$cnt_bg_safe\" data-night-cnt=\"rgba(232,180,210,0.65)\""
		: '';

	return "<style>
		#btn_$unique_id { color: $btn_color !important; transition: background 0.3s ease, transform 0.3s ease; }
		#btn_$unique_id:hover { transform: translateY(-2px); }
		{$content_color_style}
	</style>
	<div data-spoiler=\"1\" style=\"margin: 15px 0; border-radius: {$opts['outer_radius']}; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: {$opts['outer_border']};\">
		<button type=\"button\" id=\"btn_$unique_id\"{$night_data_btn} onclick=\"
			var c = document.getElementById('$unique_id');
			var b = this;
			if(c.style.maxHeight === '0px' || c.style.maxHeight === '') {
				c.style.maxHeight = c.scrollHeight + 'px';
				c.style.padding = '15px';
				b.style.borderRadius = '$radius_open';
				setTimeout(function() { c.style.maxHeight = 'none'; }, 400);
			} else {
				c.style.maxHeight = c.scrollHeight + 'px';
				setTimeout(function() {
					c.style.maxHeight = '0px';
					c.style.padding = '0px';
					b.style.borderRadius = '$radius_closed';
				}, 10);
			}
		\" style=\"
			background: {$opts['bg_color']};
			color: {$opts['text_color']};
			border: {$opts['btn_border']};
			border-radius: $initial_btn_radius;
			padding: 12px 25px;
			font-weight: 800;
			font-size: 16px;
			font-family: {$opts['btn_font']};
			text-shadow: {$opts['btn_text_shadow']};
			cursor: pointer;
			width: 100%;
			text-align: center;
			text-transform: uppercase;
		\" onmouseover=\"this.style.transform='translateY(-2px)';\" onmouseout=\"this.style.transform='translateY(0)';\">
			{$display_icon}$title_safe
		</button>
	<div id=\"$unique_id\" class=\"op_spoiler_content\"{$night_data_cnt} style=\"max-height: $initial_max_height; padding: $initial_padding; background: {$opts['content_bg']}; border-radius: 0 0 {$opts['outer_radius']} {$opts['outer_radius']}; overflow: hidden; transition: max-height 0.4s ease, padding 0.3s ease; color: inherit;\">
			$content
		</div>
	</div>{$night_script}";
	} catch (\Throwable $e) {
		return '<div style="background:#ffcccc;border:2px solid red;padding:10px;margin:10px 0;font-family:monospace;font-size:13px;">'
			. '<strong>[create_custom_spoiler DEBUG]</strong> '
			. htmlspecialchars($e->getMessage())
			. ' in ' . htmlspecialchars($e->getFile()) . ' on line ' . $e->getLine()
			. '<br><pre style="white-space:pre-wrap;word-break:break-all;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>'
			. '<br><strong>T&iacute;tulo:</strong> ' . htmlspecialchars($title)
			. '<br><strong>Contenido raw:</strong><br>' . nl2br(htmlspecialchars($content))
			. '</div>';
	}
}

/**
 * Crea un spoiler con estilo específico para fichas de personaje
 * 
 * @param string $character_name Nombre del personaje
 * @param string $content Contenido de la ficha
 * @return string HTML del spoiler
 */
function create_character_spoiler($character_name, $content)
{
	return create_custom_spoiler($character_name, $content, array(
		'icon'            => '📋',
		'bg_color'        => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
		'bg_hover'        => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
		'text_color'      => 'white',
		'btn_border'      => 'none',
		'btn_radius'      => '25px',
		'btn_font'        => 'inherit',
		'btn_text_shadow' => 'none',
		'outer_border'    => 'none',
		'outer_radius'    => '25px',
		'content_bg'      => 'transparent',
	));
}

/**
 * Crea un spoiler con estilo para NPCs
 * 
 * @param string $npc_name Nombre del NPC
 * @param string $content Contenido del NPC
 * @return string HTML del spoiler
 */
function create_npc_spoiler($npc_name, $content)
{
	return create_custom_spoiler($npc_name, $content, array(
		'icon'            => '👤',
		'bg_color'        => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
		'bg_hover'        => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
		'text_color'      => 'white',
		'btn_border'      => 'none',
		'btn_radius'      => '25px',
		'btn_font'        => 'inherit',
		'btn_text_shadow' => 'none',
		'outer_border'    => 'none',
		'outer_radius'    => '25px',
		'content_bg'      => 'transparent',
	));
}

/**
 * Crea un spoiler con estilo para técnicas
 * 
 * @param string $technique_name Nombre de la técnica
 * @param string $content Contenido de la técnica
 * @return string HTML del spoiler
 */
function create_technique_spoiler($technique_name, $content)
{
	return create_custom_spoiler($technique_name, $content, array(
		'icon'            => '⚡',
		'bg_color'        => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
		'bg_hover'        => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
		'text_color'      => 'white',
		'btn_border'      => 'none',
		'btn_radius'      => '25px',
		'btn_font'        => 'inherit',
		'btn_text_shadow' => 'none',
		'outer_border'    => 'none',
		'outer_radius'    => '25px',
		'content_bg'      => 'transparent',
	));
}

/**
 * Crea un spoiler con estilo neutral/genérico
 * 
 * @param string $title Título del spoiler
 * @param string $content Contenido
 * @return string HTML del spoiler
 */
function create_info_spoiler($title, $content)
{
	return create_custom_spoiler($title, $content, array(
		'icon'            => 'ℹ️',
		'bg_color'        => '#2c2c2c',
		'bg_hover'        => '#3a3a3a',
		'text_color'      => 'white',
		'btn_border'      => 'none',
		'btn_radius'      => '25px',
		'btn_font'        => 'inherit',
		'btn_text_shadow' => 'none',
		'outer_border'    => 'none',
		'outer_radius'    => '25px',
		'content_bg'      => 'transparent',
	));
}

/**
 * Obtiene el estilo predeterminado para spoilers contenedores de técnicas
 * 
 * @return array Array con opciones de estilo para spoilers de técnicas
 */
function get_technique_container_style()
{
	return array(
		'icon'            => '',
		'bg_color'        => 'linear-gradient(135deg, #2c82c9 0%, #1e5a8e 100%)',
		'bg_hover'        => 'linear-gradient(135deg, #2c82c9 0%, #1e5a8e 100%)',
		'text_color'      => 'white',
		'open'            => false,
		'content_bg'      => 'rgba(44, 130, 201, 0.5)',
		'btn_border'      => 'none',
		'btn_radius'      => '25px',
		'btn_font'        => 'inherit',
		'btn_text_shadow' => 'none',
		'outer_border'    => 'none',
		'outer_radius'    => '25px',
	);
}

/**
 * Crea una tarjeta de técnica con diseño estructurado
 * 
 * @param array $tecnica Array con datos de la técnica:
 *   - nombre: Nombre de la técnica
 *   - tid: ID de la técnica
 *   - clase: Clase de la técnica
 *   - estilo: Estilo de combate
 *   - tier: Nivel de la técnica
 *   - energia: Costo de energía
 *   - energia_turno: Costo de energía por turno
 *   - haki: Costo de haki
 *   - haki_turno: Costo de haki por turno
 *   - enfriamiento: Tiempo de enfriamiento
 *   - requisitos: Requisitos previos
 *   - descripcion: Descripción de la técnica
 *   - efectos: Efectos de la técnica
 * @param bool $is_staff Si el usuario es staff (para mostrar link de edición)
 * @param bool $show_train_button Si debe mostrar el botón de entrenar
 * TODO - Añadir opción para mostrar botón de utilizar (cuando se haga el bélico automático)
 * @return string HTML de la tarjeta de técnica
 */
function create_technique_card($tecnica, $is_staff = false, $show_train_button = false, $fid = 0)
{
	global $mybb, $db;

	// Comprobar si el usuario es staff usando los permisos mergeados de MyBB
	// canmodcp: 1 en los grupos 3, 4, 6, 14, 15, 16 (todos los grupos con acceso al panel de moderación)
	$is_staff = !empty($mybb->usergroup['canmodcp']) || !empty($mybb->usergroup['cancp']);

	// Sanitizar datos
	$nombre = htmlspecialchars($tecnica['nombre']);
	$tid = htmlspecialchars($tecnica['tid']);
	$clase = htmlspecialchars($tecnica['clase']);
	$estilo = htmlspecialchars($tecnica['estilo']);
	$tipo = htmlspecialchars($tecnica['tipo']);
	$tier = htmlspecialchars($tecnica['tier']);
	$rama = htmlspecialchars($tecnica['rama'] ?? '');
	$requisitos = $tecnica['requisitos'];
	$descripcion = $tecnica['descripcion'];
	$efectos = $tecnica['efectos'];
	$fecha_display = '';
	if (!empty($tecnica['tiempo'])) {
		$ts = is_numeric($tecnica['tiempo']) ? (int)$tecnica['tiempo'] : strtotime($tecnica['tiempo']);
		if ($ts) $fecha_display = date('d/m/Y', $ts);
	}
	
	$unique_id = 'tecnica_' . uniqid() . '_' . mt_rand(1000, 9999);
	
	// Generar sección de metadatos
	$id_display = $is_staff 
		? '<a href="/op/staff/tecnicas_modificar.php?tecnica_id='.$tid.'" style="color: #fee140;">'.$tid.'</a>'
		: '<span style="color: #fff;">'.$tid.'</span>';
	
	// Generar badges de costos
	$costos_html = '';
	
	if (!empty($tecnica['energia'])) {
		$costos_html .= '<div style="display: flex; align-items: center; gap: 5px; background: rgba(255, 161, 0, 0.2); border: 1px solid #ffa100; border-radius: 20px; padding: 5px 12px;">
			<img src="/images/op/uploads/Energia_One_Piece_Gaiden_Foro_Rol.png" style="height: 20px;" alt="Energía" />
			<span style="color: #ffa100; font-weight: bold; font-size: 14px;">'.$tecnica['energia'].'</span>
		</div>';
	}
	
	if (!empty($tecnica['energia_turno'])) {
		$costos_html .= '<div style="display: flex; align-items: center; gap: 5px; background: rgba(255, 161, 0, 0.2); border: 1px solid #ffa100; border-radius: 20px; padding: 5px 12px;">
			<img src="/images/op/uploads/EnergiaPorTurno_One_Piece_Gaiden_Foro_Rol.png" style="height: 20px;" alt="Energía/Turno" />
			<span style="color: #ffa100; font-weight: bold; font-size: 14px;">'.$tecnica['energia_turno'].'/t</span>
		</div>';
	}
	
	if (!empty($tecnica['haki'])) {
		$costos_html .= '<div style="display: flex; align-items: center; gap: 5px; background: rgba(33, 222, 255, 0.2); border: 1px solid #21DEFF; border-radius: 20px; padding: 5px 12px;">
			<img src="/images/op/uploads/PuntosHaki_One_Piece_Gaiden_Foro_Rol.png" style="height: 20px;" alt="Haki" />
			<span style="color: #21DEFF; font-weight: bold; font-size: 14px;">'.$tecnica['haki'].'</span>
		</div>';
	}
	
	if (!empty($tecnica['haki_turno'])) {
		$costos_html .= '<div style="display: flex; align-items: center; gap: 5px; background: rgba(33, 222, 255, 0.2); border: 1px solid #21DEFF; border-radius: 20px; padding: 5px 12px;">
			<img src="/images/op/uploads/PuntosHakiMantenido_One_Piece_Gaiden_Foro_Rol.png" style="height: 20px;" alt="Haki/Turno" />
			<span style="color: #21DEFF; font-weight: bold; font-size: 14px;">'.$tecnica['haki_turno'].'/t</span>
		</div>';
	}
	
	if (!empty($tecnica['enfriamiento']) && $tecnica['enfriamiento'] != '') {
		$costos_html .= '<div style="display: flex; align-items: center; gap: 5px; background: rgba(216, 236, 19, 0.2); border: 1px solid #D8EC13; border-radius: 20px; padding: 5px 12px;">
			<img src="/images/op/uploads/CD_One_Piece_Gaiden_Foro_Rol.png" style="height: 20px;" alt="CD" />
			<span style="color: #D8EC13; font-weight: bold; font-size: 14px;">'.$tecnica['enfriamiento'].'</span>
		</div>';
	}
	
	// Generar sección de requisitos (si existen)
	$requisitos_html = '';
	if (!empty($requisitos) && $requisitos != '') {
		$requisitos_html = '<div style="background: rgba(115, 77, 200, 0.4); border-left: 4px solid #fee140; border-radius: 6px; padding: 12px 15px; margin-bottom: 15px;">
			<div style="color: #fee140; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; font-weight: bold;">
				⚠️ Requisitos
			</div>
			<div class="tecnica_requisito" style="color: rgba(255,255,255,0.9); line-height: 1.6;">
				'.$requisitos.'
			</div>
		</div>';
	}
	
	return '<div class="tecnica_spoiler tecnica_card"
		data-tier="'.intval($tier).'"
		data-tipo="'.strtolower($tipo).'"
		data-clase="'.strtolower($clase).'"
		data-rama="'.$rama.'"
		data-fecha="'.$fecha_display.'"
		style="background: linear-gradient(180deg, #8964dc, #734dc8);
		border-radius: 8px;
		margin: 7.5px 0;
		padding: 0;
		box-shadow: 0 4px 15px rgba(0,0,0,0.4);
		overflow: hidden;
		transition: all 1s ease;">
		
		<!-- Header: Nombre de la técnica -->
		<div class="tecnica_header" onclick="
			var content = document.getElementById(\''.$unique_id.'\');
			var header = this;
			if(content.style.maxHeight && content.style.maxHeight !== \'0px\'){
				content.style.maxHeight = \'0px\';
				header.style.borderBottom = \'none\';
			} else {
				content.style.maxHeight = content.scrollHeight + \'px\';
				header.style.borderBottom = \'2px solid rgba(250, 112, 154, 0.3)\';
			}
		" style="background: linear-gradient(90deg, rgba(250, 112, 154, 0.2) 0%, rgba(254, 225, 64, 0.1) 100%); padding: 15px 20px; cursor: pointer; transition: all 0.3s ease; border-bottom: none;">
			<div style="display: flex; justify-content: center; align-items: center; position: relative;">
				<h3 style="margin: 0; color: #fff; font-size: 18px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); text-align: center;">
					⚡ '.$nombre.'
				</h3>
				<span style="background: rgba(115, 77, 200, 0.65); color: #fee140; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; position: absolute; right: 0;">
					Tier '.$tier.'
				</span>
			</div>
		</div>
		
		<!-- Content: Información detallada -->
		<div id="'.$unique_id.'" class="tecnica_content" style="max-height: 0; overflow: hidden; transition: max-height 0.3s ease;">
			
			<div style="padding: 20px;">
				
				<!-- Fila superior: Metadatos y costos -->
				<div style="display: flex; gap: 20px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid rgba(115, 77, 200, 0.4);">
					
					<!-- Columna izquierda: Metadatos -->
					<div style="flex: 1;">
						<div style="background: rgba(115,77,200,0.65); border-radius: 8px; padding: 15px;">
							<div style="color: rgba(255,255,255,0.6); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">
								📋 Información
							</div>
							<div style="color: #fee140; margin: 5px 0;">
								<strong>ID:</strong> '.$id_display.'
							</div>
							<div style="color: #fee140; margin: 5px 0;">
								<strong>Clase:</strong> <span style="color: #fff;">'.$clase.'</span>
							</div>
							<div style="color: #fee140; margin: 5px 0;">
								<strong>Estilo:</strong> <span style="color: #fff;">'.$estilo.'</span>
							</div>
							<div style="color: #fee140; margin: 5px 0;">
								<strong>Tipo:</strong> <span style="color: #fff;">'.$tipo.'</span>
							</div>
						</div>
					</div>
					
					<!-- Columna derecha: Costos -->
					<div style="flex: 1;">
						<div style="background: rgba(115,77,200,0.65); border-radius: 8px; padding: 15px;">
							<div style="color: rgba(255,255,255,0.6); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">
								💰 Costos
							</div>
							<div style="display: flex; flex-wrap: wrap; gap: 10px;">
								'.$costos_html.'
							</div>
						</div>
					'.($show_train_button ? '
					<!-- Botón de Entrenar -->
					<div style="background: rgba(115,77,200,0.65); border-radius: 8px; padding: 10px; margin-top: 10px;">
						<form method="post" action="entrenamiento_tecnicas.php" style="margin: 0;">
							<input type="hidden" name="tid" value="'.$tid.'" />
							<button type="submit" class="button entreno-button" style="
								background-color: #d16f19 !important;
								transition: all 0.5s ease-out;
								font-size: 18px !important;
								border: 1px solid black !important;
								font-family: InterRegular !important;
								padding: 8px 20px;
								cursor: pointer;
								border-radius: 8px;
								color: white;
								width: 100%;
								font-weight: bold;
							" onmouseover="this.style.backgroundColor=\'#df7416\';" onmouseout="this.style.backgroundColor=\'#d16f19\';">APRENDER</button>
						</form>
					</div>' : '').'
					</div>
				</div>
				
				<!-- Requisitos (si existen) -->
				'.$requisitos_html.'
				
				<!-- Efectos -->
				<div style="background: rgba(115,77,200,0.65); border-radius: 8px; padding: 15px; border: 1px solid rgba(115,77,200,0.8); margin-bottom: 15px;">
					<div style="color: #fee140; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; font-weight: bold;">
						✨ Efectos
					</div>
					<div class="tecnica_efecto" style="color: rgba(255,255,255,0.95); line-height: 1.8;">
						'.$efectos.'
					</div>
				</div>
				
				<!-- Descripción -->
				<div style="background: rgba(115,77,200,0.65); border-radius: 8px; padding: 15px;">
					<div style="color: rgba(255,255,255,0.6); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">
						📖 Descripción
					</div>
					<div class="tecnica_descripcion" style="color: rgba(255,255,255,0.95); line-height: 1.8; text-align: justify;">
						'.$descripcion.'
					</div>
				</div>
				
				'.(($fid > 0) ? (function() use ($db, $fid, $tid) {
					$q = $db->simple_select('op_audit_entrenamiento_tecnicas', 'tiempo_completado', "fid='".$db->escape_string($fid)."' AND tid='".$db->escape_string($tid)."' AND tiempo_completado IS NOT NULL AND tiempo_completado != ''", array('order_by' => 'tiempo_completado', 'order_dir' => 'DESC', 'limit' => 1));
					$row = $db->fetch_array($q);
					if ($row && !empty($row['tiempo_completado'])) {
						$fecha = htmlspecialchars($row['tiempo_completado']);
						return '
						<!-- Fecha de entrenamiento -->
						<div style="margin-top: 15px; background: rgba(40, 167, 69, 0.15); border: 2px solid rgba(40, 167, 69, 0.6); border-radius: 10px; padding: 15px 20px; text-align: center;">
							<span style="color: #5cb85c; font-size: 16px; font-weight: bold;">✅ Técnica entrenada: <span style="color: #fff;">'.$fecha.'</span></span>
						</div>';
					}
					return '';
				})() : '').' 

				'.($is_staff ? '
				<!-- Botón editar (solo staff) -->
				<div style="margin-top: 15px; text-align: center;">
					<a href="https://onepiecegaiden.com/op/staff/tecnicas_modificar.php?tecnica_id='.$tid.'"
					   style="display: inline-block; background: linear-gradient(135deg, #e53935, #b71c1c); color: #fff; font-weight: bold; font-size: 14px; padding: 10px 25px; border-radius: 20px; text-decoration: none; box-shadow: 0 3px 10px rgba(0,0,0,0.4); transition: all 0.2s ease;"
					   onmouseover="this.style.transform=\'translateY(-2px)\';" onmouseout="this.style.transform=\'translateY(0)\';">
						✏️ Editar técnica
					</a>
				</div>
				' : '').'
				
			</div>
		</div>
	</div>';
}
