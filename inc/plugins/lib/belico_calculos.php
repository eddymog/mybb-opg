<?php
/**
 * Cálculos bélicos reutilizables.
 *
 * No renderiza HTML ni depende de una página concreta. Las pantallas que
 * necesiten mostrar fórmulas usan estas funciones como fuente de verdad.
 */

function opg_belico_atributos_finales($stats)
{
    return array(
        'NIV' => (int) $stats['nivel'],
        'FUE' => (int) $stats['fuerza'] + (int) $stats['fuerza_pasiva'],
        'AGI' => (int) $stats['agilidad'] + (int) $stats['agilidad_pasiva'],
        'PUN' => (int) $stats['punteria'] + (int) $stats['punteria_pasiva'],
        'DES' => (int) $stats['destreza'] + (int) $stats['destreza_pasiva'],
        'RES' => (int) $stats['resistencia'] + (int) $stats['resistencia_pasiva'],
        'REF' => (int) $stats['reflejos'] + (int) $stats['reflejos_pasiva'],
        'VOL' => (int) $stats['voluntad'] + (int) $stats['voluntad_pasiva'],
    );
}

function opg_belico_cargar_stats_actuales($db, $uid)
{
    $uid = (int) $uid;
    $cols = 'nivel, fuerza, fuerza_pasiva, agilidad, agilidad_pasiva, punteria, punteria_pasiva, destreza, destreza_pasiva, resistencia, resistencia_pasiva, reflejos, reflejos_pasiva, voluntad, voluntad_pasiva';
    $query = $db->query("SELECT {$cols} FROM mybb_op_fichas WHERE fid='{$uid}' LIMIT 1");
    $stats = $db->fetch_array($query);
    return $stats ? opg_belico_atributos_finales($stats) : null;
}

function opg_belico_cargar_armas_usuario($db, $uid)
{ 
    $uid = (int) $uid;
    $armas = array();
    $query = $db->query("SELECT o.*, i.cantidad, i.apodo, i.editado FROM mybb_op_objetos o INNER JOIN mybb_op_inventario i ON o.objeto_id=i.objeto_id WHERE i.uid='{$uid}' AND LOWER(o.categoria)='armas' AND o.objeto_id!='LLST001' ORDER BY o.nombre");
    while ($arma = $db->fetch_array($query)) {
        $armas[$arma['objeto_id']] = $arma;
    }
    return $armas;
}

function opg_belico_tipo_danio($texto)
{
    $texto = strtolower((string) $texto);
    foreach (array('cortante', 'perforante', 'contundente', 'mitigado', 'verdadero', 'variable', 'fuego', 'agua', 'viento', 'hielo', 'rayo', 'eléctrico', 'electrico', 'sónico', 'sonico', 'espiritual') as $tipo) {
        if (strpos($texto, 'daño ' . $tipo) !== false || strpos($texto, 'dano ' . $tipo) !== false) {
            if ($tipo === 'sonico') { return 'sónico'; }
            if ($tipo === 'electrico') { return 'eléctrico'; }
            return $tipo;
        }
    }
    return 'contundente';
}

function opg_belico_formatear_numero($valor)
{
    if ((float) $valor === (float) (int) $valor) { return (string) (int) $valor; }
    return str_replace('.', ',', rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.'));
}

function opg_belico_codigo_atributo($codigo)
{
    $codigo = strtoupper(trim((string) $codigo));
    $codigo = strtr($codigo, array('Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U'));
    $mapa = array(
        'NIV' => 'NIV', 'NIVEL' => 'NIV',
        'FUE' => 'FUE', 'FUERZA' => 'FUE',
        'AGI' => 'AGI', 'AGILIDAD' => 'AGI',
        'PUN' => 'PUN', 'PUNTERIA' => 'PUN',
        'DES' => 'DES', 'DESTREZA' => 'DES',
        'RES' => 'RES', 'RESISTENCIA' => 'RES',
        'REF' => 'REF', 'REFLEJOS' => 'REF',
        'VOL' => 'VOL', 'VOLUNTAD' => 'VOL',
    );
    return isset($mapa[$codigo]) ? $mapa[$codigo] : null;
}

function opg_belico_nombre_atributo($codigo)
{
    $nombres = array('NIV' => 'Nivel', 'FUE' => 'Fuerza', 'AGI' => 'Agilidad', 'PUN' => 'Puntería', 'DES' => 'Destreza', 'RES' => 'Resistencia', 'REF' => 'Reflejos', 'VOL' => 'Voluntad');
    return isset($nombres[$codigo]) ? $nombres[$codigo] : $codigo;
}

/** Tipo de daño en minúscula y con tilde ("Sonico" -> "sónico"), igual en todo el texto. */
function opg_belico_normalizar_tipo($tipo)
{
    $tipo = mb_strtolower(trim((string) $tipo), 'UTF-8');
    $acentos = array('sonico' => 'sónico', 'electrico' => 'eléctrico');
    return isset($acentos[$tipo]) ? $acentos[$tipo] : $tipo;
}

/** "Nivel 23 × 7,7 = 177,1, redondeado a 178" (el redondeo solo si hay decimales). */
function opg_belico_paso_producto($nombre, $base, $multiplicador, $valor)
{
    $exacto = $base * $multiplicador;
    $paso = $nombre . ' ' . $base . ' × ' . opg_belico_formatear_numero($multiplicador) . ' = ';
    if (abs($exacto - $valor) > 0.00001) {
        return $paso . opg_belico_formatear_numero($exacto) . ', redondeado a ' . $valor;
    }
    return $paso . $valor;
}

function opg_belico_resolver_formula($formula, $atributos, $golpe_basico = null, $bloqueo_basico = null)
{
    $formula = trim((string) $formula);
    // Fórmula sin corchetes ("-Nivel/2"): se normaliza a "[Nivel/2]" para resolverla igual.
    $formula = preg_replace('/(?<![\[\w])(NIVEL|FUERZA|AGILIDAD|PUNTER[IÍ]A|DESTREZA|RESISTENCIA|REFLEJOS|VOLUNTAD|NIV|FUE|AGI|PUN|DES|RES|REF|VOL)\s*([x×\/])\s*(\d+(?:[,.]\d+)?)(?![\w\]])/iu', '[$1$2$3]', $formula);
    $pasos = array();
    $resuelta = preg_replace_callback('/\[([A-ZÁÉÍÓÚ]+)\s*[x×]\s*(\d+(?:[,.]\d+)?)\]/iu', function ($match) use ($atributos, &$pasos) {
        $codigo = opg_belico_codigo_atributo($match[1]);
        if ($codigo === null || !isset($atributos[$codigo])) { return $match[0]; }
        $multiplicador = (float) str_replace(',', '.', $match[2]);
        $valor = (int) ceil($atributos[$codigo] * $multiplicador);
        $pasos[] = opg_belico_paso_producto(opg_belico_nombre_atributo($codigo), $atributos[$codigo], $multiplicador, $valor);
        return (string) $valor;
    }, $formula);
    $resuelta = preg_replace_callback('/\[(?:(\d+(?:[,.]\d+)?)\s*[x×]?\s*Nivel|Nivel\s*[x×]\s*(\d+(?:[,.]\d+)?))\]/iu', function ($match) use ($atributos, &$pasos) {
        $multiplicador = (float) str_replace(',', '.', !empty($match[1]) ? $match[1] : $match[2]);
        $valor = (int) ceil($atributos['NIV'] * $multiplicador);
        $pasos[] = opg_belico_paso_producto('Nivel', $atributos['NIV'], $multiplicador, $valor);
        return (string) $valor;
    }, $resuelta);
    if ($golpe_basico !== null && preg_match('/\[?Golpe\s+Básico\]?/iu', $resuelta)) {
        $resuelta = preg_replace('/\[?Golpe\s+Básico\]?/iu', (string) $golpe_basico, $resuelta);
    }
    if ($bloqueo_basico !== null && preg_match('/\[?Bloqueo\s+Básico\]?/iu', $resuelta)) {
        $resuelta = preg_replace('/\[?Bloqueo\s+Básico\]?/iu', (string) $bloqueo_basico, $resuelta);
    }
    $resuelta = preg_replace_callback('/\[([A-ZÁÉÍÓÚ]+)\s*\/\s*(\d+(?:[,.]\d+)?)\]/iu', function ($match) use ($atributos, &$pasos) {
        $codigo = opg_belico_codigo_atributo($match[1]);
        $divisor = (float) str_replace(',', '.', $match[2]);
        if ($codigo === null || !isset($atributos[$codigo]) || $divisor <= 0) { return $match[0]; }
        $valor = $atributos[$codigo] / $divisor;
        $texto_valor = opg_belico_formatear_numero($valor);
        $pasos[] = opg_belico_nombre_atributo($codigo) . ' ' . $atributos[$codigo] . ' ÷ ' . opg_belico_formatear_numero($divisor) . ' = ' . $texto_valor;
        return $texto_valor;
    }, $resuelta);
    $resuelta = preg_replace_callback('/\[([A-ZÁÉÍÓÚ]+)\]/iu', function ($match) use ($atributos, &$pasos) {
        $codigo = opg_belico_codigo_atributo($match[1]);
        if ($codigo === null || !isset($atributos[$codigo])) { return $match[0]; }
        $pasos[] = opg_belico_nombre_atributo($codigo) . ' = ' . $atributos[$codigo];
        return (string) $atributos[$codigo];
    }, $resuelta);
    preg_match_all('/(?<![\d,])\d+(?:[,.]\d+)?/u', $resuelta, $numeros);
    $total = 0;
    $terminos = array();
    foreach ($numeros[0] as $numero) {
        $n = (float) str_replace(',', '.', $numero);
        $total += $n;
        $terminos[] = opg_belico_formatear_numero($n);
    }
    // Con varios términos se muestra la suma explícita (y el redondeo si hay decimales).
    if (count($terminos) > 1) {
        $suma = 'Suma: ' . implode(' + ', $terminos) . ' = ' . opg_belico_formatear_numero($total);
        if (abs($total - ceil($total)) > 0.00001) { $suma .= ', redondeado a ' . (int) ceil($total); }
        $pasos[] = $suma;
    } elseif (count($terminos) === 1 && abs($total - ceil($total)) > 0.00001) {
        $pasos[] = 'Redondeado a ' . (int) ceil($total);
    }
    return array('valor' => (int) ceil($total), 'resuelta' => $resuelta, 'pasos' => $pasos, 'reconocida' => !empty($pasos) || !empty($numeros[0]));
}

function opg_belico_calcular_arma($arma, $atributos, $dos_manos = false)
{
    $formula = isset($arma['dano']) ? (string) $arma['dano'] : '';
    $calculo = opg_belico_resolver_formula($formula, $atributos);
    $valor = $calculo['reconocida'] || $calculo['valor'] > 0 ? $calculo['valor'] : (int) $atributos['FUE'];
    if ($dos_manos) {
        if (array_intersect(opg_belico_tipos_arma($arma), opg_belico_tipos_sin_bono_dos_manos())) {
            $calculo['pasos'][] = 'Esta arma no recibe el bono de dos manos';
        } else {
            $valor = (int) ceil($valor * 1.5);
            $calculo['pasos'][] = 'Empuñadura a dos manos: × 1,5 = ' . $valor;
        }
    }
    $calculo['valor'] = $valor;
    $calculo['tipo'] = opg_belico_tipo_danio($formula);
    return $calculo;
}

function opg_belico_calcular_golpe_basico($armas, $atributos, $configuracion)
{
    if ($configuracion === 'sin_arma' || empty($armas[0])) {
        return array('valor' => (int) $atributos['FUE'], 'tipo' => 'contundente', 'pasos' => array('Sin arma: FUE = ' . $atributos['FUE']), 'configuracion' => 'Sin arma');
    }
    if ($configuracion === 'dos_manos') {
        $calculo = opg_belico_calcular_arma($armas[0], $atributos, true);
        return array('valor' => $calculo['valor'], 'tipo' => $calculo['tipo'], 'pasos' => $calculo['pasos'], 'configuracion' => '1 arma con ambas manos');
    }
    if (($configuracion === 'dos_armas' || $configuracion === 'tres_armas') && count($armas) > 1) {
        $calculos = array();
        $valores = array();
        $tipos = array();
        foreach ($armas as $arma) {
            $calculo = opg_belico_calcular_arma($arma, $atributos);
            $calculos = array_merge($calculos, $calculo['pasos']);
            $valores[] = $calculo['valor'];
            $tipos[] = $calculo['tipo'];
        }
        $cantidad = count($valores);
        $porcentaje = $cantidad === 2 ? 0.75 : 0.60;
        $valor = (int) ceil(array_sum($valores) * $porcentaje);
        $calculos[] = $cantidad . ' armas: (' . implode(' + ', $valores) . ') × ' . str_replace('.', ',', (string) $porcentaje) . ' = ' . $valor;
        return array('valor' => $valor, 'tipo' => implode('/', array_unique($tipos)), 'pasos' => $calculos, 'configuracion' => $cantidad . ' armas' . ($cantidad > 2 ? ' (TA −' . ($cantidad * 5) . ')' : ''));
    }
    $calculo = opg_belico_calcular_arma($armas[0], $atributos);
    return array('valor' => $calculo['valor'], 'tipo' => $calculo['tipo'], 'pasos' => $calculo['pasos'], 'configuracion' => '1 arma');
}

function opg_belico_calcular_bloqueo_arma($arma, $atributos, $dos_manos = false)
{
    $formula = isset($arma['bloqueo']) ? (string) $arma['bloqueo'] : '';
    $calculo = opg_belico_resolver_formula($formula, $atributos);
    $valor = $calculo['reconocida'] || $calculo['valor'] > 0 ? $calculo['valor'] : (int) $atributos['FUE'];
    if ($dos_manos) {
        if (array_intersect(opg_belico_tipos_arma($arma), opg_belico_tipos_sin_bono_dos_manos())) {
            $calculo['pasos'][] = 'Esta arma no recibe el bono de dos manos';
        } else {
            $valor = (int) ceil($valor * 1.5);
            $calculo['pasos'][] = 'Empuñadura a dos manos: × 1,5 = ' . $valor;
        }
    }
    $calculo['valor'] = $valor;
    $calculo['tipo'] = 'mitigado';
    return $calculo;
}

function opg_belico_calcular_bloqueo_basico($armas, $atributos, $configuracion)
{
    if ($configuracion === 'sin_arma' || empty($armas[0])) {
        return array('valor' => (int) $atributos['FUE'], 'tipo' => 'mitigado', 'pasos' => array('Sin arma: FUE = ' . $atributos['FUE']), 'configuracion' => 'Sin arma');
    }
    if ($configuracion === 'dos_manos') {
        $calculo = opg_belico_calcular_bloqueo_arma($armas[0], $atributos, true);
        return array('valor' => $calculo['valor'], 'tipo' => $calculo['tipo'], 'pasos' => $calculo['pasos'], 'configuracion' => '1 arma con ambas manos');
    }
    if (($configuracion === 'dos_armas' || $configuracion === 'tres_armas') && count($armas) > 1) {
        $calculos = array();
        $valores = array();
        foreach ($armas as $arma) {
            $calculo = opg_belico_calcular_bloqueo_arma($arma, $atributos);
            $calculos = array_merge($calculos, $calculo['pasos']);
            $valores[] = $calculo['valor'];
        }
        $cantidad = count($valores);
        $porcentaje = $cantidad === 2 ? 0.75 : 0.60;
        $valor = (int) ceil(array_sum($valores) * $porcentaje);
        $calculos[] = $cantidad . ' armas: (' . implode(' + ', $valores) . ') × ' . str_replace('.', ',', (string) $porcentaje) . ' = ' . $valor;
        return array('valor' => $valor, 'tipo' => 'mitigado', 'pasos' => $calculos, 'configuracion' => $cantidad . ' armas' . ($cantidad > 2 ? ' (TA −' . ($cantidad * 5) . ')' : ''));
    }
    $calculo = opg_belico_calcular_bloqueo_arma($armas[0], $atributos);
    return array('valor' => $calculo['valor'], 'tipo' => $calculo['tipo'], 'pasos' => $calculo['pasos'], 'configuracion' => '1 arma');
}

/**
 * Una expresión solo necesita cálculo si depende de algo del personaje: Golpe/Bloqueo
 * básico, Nivel o un atributo. Un número suelto ("+16", "10 + 5") ya es el resultado.
 */
function opg_belico_expresion_tiene_variable($expresion)
{
    $atributo = '(?:NIVEL|FUERZA|AGILIDAD|PUNTER[IÍ]A|DESTREZA|RESISTENCIA|REFLEJOS|VOLUNTAD|NIV|FUE|AGI|PUN|DES|RES|REF|VOL)';
    return (bool) preg_match('/(?:Golpe|Bloqueo)\s+Básico|Nivel|\[\s*NIV\s*\]|\[\s*' . $atributo . '\s*[x×\/]|(?<![\[\w])' . $atributo . '\s*[x×\/]\s*\d/iu', (string) $expresion);
}

function opg_belico_calcular_efectos_contextuales($texto, $atributos, $golpe_basico, $bloqueo_basico = null)
{
    $atributo_token = '(?:NIVEL|FUERZA|AGILIDAD|PUNTER[IÍ]A|DESTREZA|RESISTENCIA|REFLEJOS|VOLUNTAD|NIV|FUE|AGI|PUN|DES|RES|REF|VOL)';
    // Con corchetes ("[Nivel/2]") o sin ellos ("Nivel/2", siempre con su operador para no confundirlo con texto).
    // Un atributo solo, sin operador ([Voluntad], [VOL]), es el nombre de una estadística y no se calcula; [Nivel] solo sí cuenta como valor.
    $formula_token = '(?:\[(?:(?:\d+(?:[,.]\d+)?\s*[x×]?\s*Nivel)|(?:Nivel\s*[x×]\s*\d+(?:[,.]\d+)?)|(?:' . $atributo_token . ')\s*[x×\/]\s*\d+(?:[,.]\d+)?|(?:NIVEL|NIV))\]'
        . '|(?<![\[\w])(?:' . $atributo_token . ')\s*[x×\/]\s*\d+(?:[,.]\d+)?(?![\w\]]))';
    $base_token = '\[?\s*(?:Golpe|Bloqueo)\s+Básico\s*\]?';
    $token = '(?:\d+(?:[,.]\d+)?|' . $base_token . '|' . $formula_token . ')';
    // El signo inicial va dentro de la fórmula, igual que en los efectos de abajo, para que "+27" se reemplace completo.
    $patron = '/((?:[+-]\s*)?(?:' . $token . ')(?:\s*\+\s*' . $token . ')*)\s*(?:de\s*)?\[?(Daño(?:\s+(?!(?:y|e|o|con|de|que|a|en|al|por|para)\b)[A-Za-zÁÉÍÓÚÑáéíóúñ]+){0,2}|Mitigación|Bloqueo)\]?/iu';
    preg_match_all($patron, (string) $texto, $coincidencias, PREG_SET_ORDER);
    $resultados = array();
    $codigos_principales = array();
    foreach ($coincidencias as $coincidencia) {
        if (!opg_belico_expresion_tiene_variable($coincidencia[1])) { continue; }
        $calculo = opg_belico_resolver_formula($coincidencia[1], $atributos, $golpe_basico['valor'], $bloqueo_basico === null ? null : $bloqueo_basico['valor']);
        if ($calculo['reconocida']) {
            $etiqueta = trim($coincidencia[2]);
            $calculo['es_danio'] = stripos($etiqueta, 'daño') === 0;
            $calculo['tipo'] = $calculo['es_danio'] ? opg_belico_normalizar_tipo(preg_replace('/^daño\s*/iu', '', $etiqueta)) : $etiqueta;
            if (preg_match('/bloqueo\s+básico/iu', $coincidencia[1])) {
                $calculo['base'] = 'bloqueo';
            } elseif (preg_match('/golpe\s+básico/iu', $coincidencia[1])) {
                $calculo['base'] = 'golpe';
            }
            $calculo['expresion'] = $coincidencia[1];
            $calculo['signo'] = preg_match('/^\s*([+-])/', $coincidencia[1], $signo_principal) ? $signo_principal[1] : '';
            $calculo['numero_texto'] = (string) (int) $calculo['valor'];
            $calculo['codigo'] = $coincidencia[0];
            $resultados[] = $calculo;
            $codigos_principales[] = $coincidencia[0];
        }
    }
    // El lookbehind con "de" evita tomar como valor un atributo que es el nombre del efecto ("... de [Reflejos]").
    $patron_efecto = '/(?<![\pL\]])(?<!\bde\s)((?:[+-]\s*\d+(?:[,.]\d+)?|(?:[+-]\s*)?' . $formula_token . '))\s*(%?)\s*(?:de\s+)?(?:\[([^\]\n<]+)\]|((?!(?:de|del|y|o|e|el|la|los|las|que|un|una|su|sus)\b)[A-Za-zÁÉÍÓÚÑáéíóúñ][^\[\]\n<+|]*?))(?=\s*(?:(?:y|o)\s+)?[+-]?\s*(?:\d|\[)|\s*(?:[.,;|)]|$|\n|<))/iu';
    preg_match_all($patron_efecto, (string) $texto, $coincidencias_efecto, PREG_SET_ORDER);
    foreach ($coincidencias_efecto as $coincidencia) {
        $codigo = $coincidencia[0];
        $ya_calculado = false;
        foreach ($resultados as $principal) {
            if (stripos($principal['codigo'], $codigo) !== false || stripos($codigo, $principal['expresion']) !== false) { $ya_calculado = true; break; }
        }
        if ($ya_calculado || !opg_belico_expresion_tiene_variable($coincidencia[1])) { continue; }
        $calculo = opg_belico_resolver_formula($coincidencia[1], $atributos, $golpe_basico['valor'], $bloqueo_basico === null ? null : $bloqueo_basico['valor']);
        if (!$calculo['reconocida']) { continue; }
        $etiqueta = trim(!empty($coincidencia[3]) ? $coincidencia[3] : $coincidencia[4]);
        $es_danio = stripos($etiqueta, 'daño') === 0;
        $tipo = $es_danio ? opg_belico_normalizar_tipo(preg_replace('/^daño\s*/iu', '', $etiqueta)) : $etiqueta;
        $texto_valor = opg_belico_formatear_numero($calculo['valor']) . $coincidencia[2];
        $resultados[] = array(
            'valor' => $calculo['valor'],
            'valor_texto' => $texto_valor,
            'tipo' => $tipo,
            'pasos' => $calculo['pasos'],
            'reconocida' => true,
            'es_danio' => $es_danio,
            'expresion' => $coincidencia[1],
            'signo' => preg_match('/^\s*([+-])/', $coincidencia[1], $signo) ? $signo[1] : '',
            'numero_texto' => opg_belico_formatear_numero($calculo['valor']),
            'codigo' => $codigo,
        );
    }
    return $resultados;
}

function opg_belico_nombre_arma($arma)
{
    if (!empty($arma['editado']) && !empty($arma['apodo'])) { return $arma['apodo']; }
    return $arma['nombre'];
}

/**
 * Armas que cuentan según la configuración elegida y los ids de cada ranura.
 * Una misma arma solo se repite si el jugador tiene varias copias. Si la
 * configuración pide armas y no hay ninguna válida, se cae a "sin_arma".
 * Devuelve configuracion (final), solicitada (la pedida, ya validada), armas (filas) y cantidad (ranuras que pedía).
 */
function opg_belico_seleccionar_armas($armas_disponibles, $configuracion, $ids)
{
    $validas = array('sin_arma', 'una_mano', 'dos_manos', 'dos_armas', 'tres_armas');
    if (!in_array($configuracion, $validas, true)) { $configuracion = 'sin_arma'; }
    $solicitada = $configuracion;
    $cantidad = $configuracion === 'tres_armas' ? 3 : ($configuracion === 'dos_armas' ? 2 : ($configuracion === 'sin_arma' ? 0 : 1));
    $elegidas = array();
    $ignoradas = array();
    $conteo = array();
    for ($i = 0; $i < $cantidad; $i++) {
        $arma_id = isset($ids[$i]) ? $ids[$i] : '';
        if (!isset($armas_disponibles[$arma_id])) { continue; }
        $conteo[$arma_id] = isset($conteo[$arma_id]) ? $conteo[$arma_id] + 1 : 1;
        if ($conteo[$arma_id] <= (int) $armas_disponibles[$arma_id]['cantidad']) {
            $elegidas[] = $armas_disponibles[$arma_id];
        } else {
            $ignoradas[] = opg_belico_nombre_arma($armas_disponibles[$arma_id]);
        }
    }
    if ($configuracion !== 'sin_arma' && empty($elegidas)) { $configuracion = 'sin_arma'; }
    return array('configuracion' => $configuracion, 'solicitada' => $solicitada, 'armas' => $elegidas, 'cantidad' => $cantidad, 'ignoradas' => $ignoradas);
}

/**
 * Tasa de Acierto según lo que dice cada arma: el escalado "[Tasa de Acierto = Destreza o Puntería]"
 * (con varias opciones se toma el atributo más alto) y un modificador "+5 Tasa de Acierto" en su efecto.
 * Con varias armas se toma la TA más baja; con más de 2 armas se resta además 5 por arma. Sin arma: Agilidad.
 */
function opg_belico_penalizacion_distancia($metros)
{
    // Regla bélica: -5 de TA por cada 20 metros completos hasta el objetivo.
    return 5 * (int) floor(max(0, (int) $metros) / 20);
}

function opg_belico_calcular_tasa_acierto($armas, $atributos, $distancia = 0)
{
    $penal_distancia = opg_belico_penalizacion_distancia($distancia);
    if (empty($armas)) {
        $valor = (int) $atributos['AGI'];
        $pasos = array('Sin arma: Agilidad = ' . $valor);
        if ($penal_distancia) { $valor -= $penal_distancia; $pasos[] = 'Distancia ' . (int) $distancia . ' m: -' . $penal_distancia . ' = ' . $valor; }
        return array('valor' => $valor, 'pasos' => $pasos);
    }
    $nombres = array('fuerza' => 'FUE', 'resistencia' => 'RES', 'destreza' => 'DES', 'punteria' => 'PUN', 'puntería' => 'PUN', 'agilidad' => 'AGI', 'reflejos' => 'REF', 'voluntad' => 'VOL');
    $pasos = array();
    $valores = array();
    foreach ($armas as $arma) {
        $opciones = array();
        if (preg_match('/\[Tasa de Acierto\s*=\s*([^\]]+)\]/iu', (string) $arma['escalado'], $m)) {
            foreach (preg_split('/\s+o\s+/iu', trim($m[1])) as $parte) {
                $clave = mb_strtolower(trim($parte), 'UTF-8');
                if (isset($nombres[$clave])) { $opciones[] = $nombres[$clave]; }
            }
        }
        $sin_dato = empty($opciones);
        if ($sin_dato) { $opciones = array('AGI'); }
        $mejor = $opciones[0];
        foreach ($opciones as $codigo) {
            if ($atributos[$codigo] > $atributos[$mejor]) { $mejor = $codigo; }
        }
        $modificador = 0;
        if (preg_match('/([+-])\s*(\d+)\s*Tasa de Acierto/iu', (string) $arma['efecto'], $mm)) {
            $modificador = ($mm[1] === '-' ? -1 : 1) * (int) $mm[2];
        }
        $valor = (int) $atributos[$mejor] + $modificador;
        $paso = opg_belico_nombre_arma($arma) . ': ' . opg_belico_nombre_atributo($mejor) . ' ' . (int) $atributos[$mejor];
        if (count($opciones) > 1) {
            $paso .= ' (la mayor entre ' . implode(' y ', array_map('opg_belico_nombre_atributo', $opciones)) . ')';
        }
        if ($modificador !== 0) { $paso .= ' ' . ($modificador > 0 ? '+' : '-') . abs($modificador) . ' del arma'; }
        $paso .= ' = ' . $valor;
        if ($sin_dato) { $paso .= ' (el arma no indica su TA, se usa Agilidad)'; }
        $pasos[] = $paso;
        $valores[] = $valor;
    }
    $total = min($valores);
    $cantidad = count($valores);
    if ($cantidad > 1) {
        $pasos[] = 'Con varias armas se usa la TA más baja: ' . $total;
        if ($cantidad > 2) {
            $penalizacion = 5 * $cantidad;
            $total -= $penalizacion;
            $pasos[] = $cantidad . ' armas: -' . $penalizacion . ' (-5 por arma) = ' . $total;
        }
    }
    if ($penal_distancia) {
        $total -= $penal_distancia;
        $pasos[] = 'Distancia ' . (int) $distancia . ' m: -' . $penal_distancia . ' (-5 cada 20 m) = ' . $total;
    }
    return array('valor' => $total, 'pasos' => $pasos);
}

/** Etiqueta y pasos de un resultado de opg_belico_calcular_efectos_contextuales(), listos para mostrar. */
function opg_belico_detalle_resultado($resultado, $golpe, $bloqueo)
{
    $es_danio = !isset($resultado['es_danio']) || $resultado['es_danio'];
    $valor = isset($resultado['valor_texto']) ? $resultado['valor_texto'] : (string) (int) $resultado['valor'];
    if ($es_danio) {
        $total = rtrim('Total: ' . $valor . ' de daño ' . $resultado['tipo']);
        if (!empty($resultado['base'])) {
            $es_bloqueo = $resultado['base'] === 'bloqueo';
            $base = $es_bloqueo ? $bloqueo : $golpe;
            $nombre_base = $es_bloqueo ? 'Bloqueo básico' : 'Golpe básico';
            $pasos = array_merge(array($nombre_base . ' (' . $base['configuracion'] . '): ' . $base['valor'] . ' de ' . ($es_bloqueo ? 'mitigación' : 'daño ' . $base['tipo'])), $resultado['pasos'], array($total));
        } else {
            $pasos = array_merge($resultado['pasos'], array($total));
        }
        $etiqueta = rtrim($valor . ' de daño ' . $resultado['tipo']);
    } else {
        // Unidades ("17 metros") sin "de"; efectos ("46 de Evasión") con "de".
        $nexo = preg_match('/^(?:metros?|turnos?|casillas?|segundos?|m)$/iu', trim($resultado['tipo'])) ? ' ' : ' de ';
        $pasos = array_merge($resultado['pasos'], array('Resultado: ' . $valor . $nexo . $resultado['tipo']));
        $etiqueta = $valor . $nexo . $resultado['tipo'];
    }
    return array('etiqueta' => $etiqueta, 'pasos' => $pasos);
}

/**
 * Qué parte exacta del texto se reemplaza por su valor: solo la fórmula ("+[Nivel] de Daño" pasa a
 * "+29 de Daño"), el resto de la frase queda igual. Devuelve, de la fórmula más larga a la más corta,
 * expresion (texto a buscar), numero (lo que se muestra: signo + valor), etiqueta y pasos (desglose).
 */
function opg_belico_sustituciones($resultados, $golpe, $bloqueo)
{
    $lista = array();
    foreach ($resultados as $resultado) {
        if (!isset($resultado['expresion']) || $resultado['expresion'] === '') { continue; }
        $info = opg_belico_detalle_resultado($resultado, $golpe, $bloqueo);
        $lista[$resultado['expresion']] = array(
            'expresion' => $resultado['expresion'],
            'numero'    => $resultado['signo'] . $resultado['numero_texto'],
            'etiqueta'  => $info['etiqueta'],
            'pasos'     => $info['pasos'],
        );
    }
    usort($lista, function ($a, $b) { return strlen($b['expresion']) - strlen($a['expresion']); });
    return $lista;
}

/** Texto plano con los valores ya puestos en lugar de las fórmulas (para copiar al post). */
function opg_belico_texto_con_valores($texto, $resultados, $golpe, $bloqueo)
{
    foreach (opg_belico_sustituciones($resultados, $golpe, $bloqueo) as $sustitucion) {
        $texto = str_replace($sustitucion['expresion'], $sustitucion['numero'], $texto);
    }
    return $texto;
}

/**
 * Fórmulas con operador sueltas en cualquier parte del texto ("igual a la [VOL/3] del usuario",
 * "alcance [Nivel/2] metros"). Los atributos sin operador ([Reflejos], [Voluntad]) no cuentan:
 * en las descripciones son nombres de estadística, no valores.
 */
function opg_belico_patron_formula_suelta()
{
    $atributo = '(?:NIVEL|FUERZA|AGILIDAD|PUNTER[IÍ]A|DESTREZA|RESISTENCIA|REFLEJOS|VOLUNTAD|NIV|FUE|AGI|PUN|DES|RES|REF|VOL)';
    return '/\[\s*(?:\d+(?:[,.]\d+)?\s*[x×]?\s*Nivel|Nivel\s*[x×]\s*\d+(?:[,.]\d+)?|' . $atributo . '\s*[x×\/]\s*\d+(?:[,.]\d+)?)\s*\]/iu';
}

function opg_belico_hay_formulas_sueltas($texto)
{
    return (bool) preg_match(opg_belico_patron_formula_suelta(), (string) $texto);
}

function opg_belico_formulas_sueltas($texto, $atributos)
{
    $lista = array();
    if (!preg_match_all(opg_belico_patron_formula_suelta(), (string) $texto, $coincidencias)) { return $lista; }
    foreach (array_unique($coincidencias[0]) as $formula) {
        $calculo = opg_belico_resolver_formula($formula, $atributos);
        if (!$calculo['reconocida']) { continue; }
        $lista[] = array(
            'expresion' => $formula,
            'numero'    => (string) (int) $calculo['valor'],
            'etiqueta'  => (string) (int) $calculo['valor'],
            'pasos'     => array_merge($calculo['pasos'], array('Resultado: ' . (int) $calculo['valor'])),
        );
    }
    return $lista;
}

/**
 * Todo lo que se reemplaza por su valor en un texto (efectos con su signo y fórmulas sueltas), de la
 * fórmula más larga a la más corta. Sirve igual para el campo de efectos que para la descripción.
 */
function opg_belico_sustituciones_texto($texto, $atributos, $golpe, $bloqueo)
{
    $resultados = opg_belico_calcular_efectos_contextuales((string) $texto, $atributos, $golpe, $bloqueo);
    $lista = opg_belico_sustituciones($resultados, $golpe, $bloqueo);
    // Solo se añaden las fórmulas sueltas que siguen apareciendo fuera de lo ya reemplazado.
    $restante = (string) $texto;
    foreach ($lista as $sustitucion) { $restante = str_replace($sustitucion['expresion'], '', $restante); }
    foreach (opg_belico_formulas_sueltas($restante, $atributos) as $suelta) {
        $lista[] = $suelta;
    }
    usort($lista, function ($a, $b) { return strlen($b['expresion']) - strlen($a['expresion']); });
    return $lista;
}

/** Texto plano con los valores ya puestos (para copiar). */
function opg_belico_aplicar_valores($texto, $sustituciones)
{
    foreach ($sustituciones as $sustitucion) {
        $texto = str_replace($sustitucion['expresion'], $sustitucion['numero'], $texto);
    }
    return $texto;
}

/** Quita tildes y pasa a minúscula: clave para sumar el mismo efecto escrito de varias formas. */
function opg_belico_clave_efecto($etiqueta)
{
    $t = mb_strtolower(trim((string) $etiqueta), 'UTF-8');
    $t = strtr($t, array('á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n'));
    $t = preg_replace('/\s+/', ' ', $t);
    return $t === 'ta' ? 'tasa de acierto' : $t;
}

/**
 * Bonos numéricos con signo de un texto de efectos ("+8 [Reflejos]", "-[DES/15] de [Tasa de acierto]",
 * "+[FUE/3] de Defensa Pasiva"), listos para sumar. Devuelve clave (sin tildes), etiqueta, valor (con signo)
 * y porcentaje. Solo cuenta lo que lleva signo y un nombre de efecto claro (entre corchetes o de una lista conocida).
 */
function opg_belico_extraer_bonos($texto, $atributos)
{
    $atributo = '(?:NIVEL|FUERZA|AGILIDAD|PUNTER[IÍ]A|DESTREZA|RESISTENCIA|REFLEJOS|VOLUNTAD|NIV|FUE|AGI|PUN|DES|RES|REF|VOL)';
    $formula = '(?:\[\s*(?:\d+(?:[,.]\d+)?\s*[x×]?\s*Nivel|Nivel\s*[x×]\s*\d+(?:[,.]\d+)?|' . $atributo . '\s*[x×\/]\s*\d+(?:[,.]\d+)?|NIVEL|NIV)\s*\]|(?<![\[\w])' . $atributo . '\s*[x×\/]\s*\d+(?:[,.]\d+)?(?![\w\]]))';
    $conocidos = 'daño verdadero|daño mitigado|daño|defensa pasiva|tasa de acierto|reflejos|penetración|empuje|movimiento|movilidad|vitalidad máxima|vitalidad|colisión|fortaleza espiritual|puntos de energía|voluntad|fuerza|agilidad|destreza|resistencia|puntería';
    $patron = '/(?<![\pL\]\d])([+-])\s*(\d+(?:[,.]\d+)?|' . $formula . ')\s*(%?)\s*(?:(?:de|a)\s+)?(?:\[([^\]\n<]+)\]|(' . $conocidos . ')\b)/iu';
    $bonos = array();
    if (!preg_match_all($patron, (string) $texto, $coincidencias, PREG_SET_ORDER)) { return $bonos; }
    foreach ($coincidencias as $c) {
        $etiqueta = trim(!empty($c[4]) ? $c[4] : $c[5]);
        if ($etiqueta === '') { continue; }
        if (preg_match('/^\d/', $c[2])) {
            $numero = (float) str_replace(',', '.', $c[2]);
        } else {
            $calculo = opg_belico_resolver_formula($c[2], $atributos);
            if (!$calculo['reconocida']) { continue; }
            $numero = (float) $calculo['valor'];
        }
        $clave = opg_belico_clave_efecto($etiqueta);
        $canonicas = array('dano' => 'Daño', 'dano verdadero' => 'Daño Verdadero', 'dano mitigado' => 'Daño Mitigado', 'tasa de acierto' => 'Tasa de Acierto', 'defensa pasiva' => 'Defensa Pasiva');
        $bonos[] = array(
            'k' => $clave,
            'l' => isset($canonicas[$clave]) ? $canonicas[$clave] : mb_strtoupper(mb_substr($etiqueta, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($etiqueta, 1, null, 'UTF-8'),
            'v' => ($c[1] === '-' ? -1 : 1) * $numero,
            'p' => $c[3] === '%' ? 1 : 0,
        );
    }
    return $bonos;
}

// ── Tipos de arma ──────────────────────────────────────────────────────────

/** Tipos que no reciben el bono de empuñar con las dos manos (regla: amuletos, arrojadizas y armas de fuego). */
function opg_belico_tipos_sin_bono_dos_manos()
{
    return array('amuleto', 'arrojadiza', 'fuego');
}

function opg_belico_tipos_por_subcategoria()
{
    return array(
        'armas contundentes' => 'contundente', 'armas corporales' => 'corporal', 'armas de tensión' => 'tension',
        'armas defensivas' => 'defensiva', 'armas exóticas' => 'exotica', 'amuletos' => 'amuleto',
        'arma de asalto' => 'asalto', 'armas de asalto' => 'asalto', 'armas arrojadizas' => 'arrojadiza',
        'armas de asta' => 'asta', 'armas de filo' => 'filo', 'armas de fuego' => 'fuego',
        'armas flexibles' => 'flexible', 'musical' => 'musical', 'wazamono' => 'wazamono',
    );
}

function opg_belico_palabras_tipos_arma()
{
    return array(
        'tension'     => array('tensi', 'arco', 'ballesta'),
        'asalto'      => array('asalto'),
        'filo'        => array('filo', 'cortante', 'espada', 'katana', 'sable', 'daga', 'cuchill'),
        'asta'        => array('asta', 'lanza', 'alabarda'),
        'fuego'       => array('fuego', 'pistola', 'rifle', 'mosquete', 'escopeta'),
        'contundente' => array('contund', 'martillo', 'maza', 'garrote'),
        'corporal'    => array('corporal', 'puño', 'guante', 'garra', 'nudillo'),
        'flexible'    => array('flexible', 'látigo', 'latigo', 'cadena'),
        'exotica'     => array('exótic', 'exotic'),
        'arrojadiza'  => array('arroj', 'shuriken', 'kunai'),
        'defensiva'   => array('defensiv', 'escudo'),
    );
}

/** Tipos de un arma: manda la subcategoría; solo si no se reconoce se buscan palabras clave. */
function opg_belico_tipos_arma($arma)
{
    $sub = mb_strtolower(trim((string) (isset($arma['subcategoria']) ? $arma['subcategoria'] : '')), 'UTF-8');
    $nombre = mb_strtolower((string) (isset($arma['nombre']) ? $arma['nombre'] : ''), 'UTF-8');
    $exactos = opg_belico_tipos_por_subcategoria();
    $tipos = array();
    if (isset($exactos[$sub])) {
        $tipos[] = $exactos[$sub];
        // Las flexibles (látigos, cadenas) no tienen subcategoría propia: viven dentro de las exóticas.
        if ($exactos[$sub] === 'exotica' && preg_match('/flexible|látigo|latigo|cadena/u', $nombre)) { $tipos[] = 'flexible'; }
        return $tipos;
    }
    foreach (array($sub, $nombre) as $texto) {
        foreach (opg_belico_palabras_tipos_arma() as $tipo => $palabras) {
            foreach ($palabras as $palabra) {
                if ($texto !== '' && strpos($texto, $palabra) !== false) { $tipos[] = $tipo; break; }
            }
        }
        if ($tipos) { return $tipos; }
    }
    return array();
}

function opg_belico_etiquetas_tipos_arma()
{
    return array('tension' => 'tensión', 'asalto' => 'asalto', 'filo' => 'filo', 'asta' => 'asta', 'fuego' => 'fuego', 'contundente' => 'contundentes',
        'corporal' => 'corporales', 'flexible' => 'flexibles', 'exotica' => 'exóticas', 'arrojadiza' => 'arrojadizas', 'defensiva' => 'defensivas / escudo',
        'amuleto' => 'amuletos', 'musical' => 'instrumentos musicales', 'wazamono' => 'wazamono');
}

// ── Requisitos y avisos ────────────────────────────────────────────────────

/** Requisitos de atributo de un arma ("tener 10 de [Fuerza] y 10 de [Puntería]") frente a los atributos del personaje. */
function opg_belico_requisitos_arma($arma, $atributos)
{
    $codigos = array('fuerza' => 'FUE', 'resistencia' => 'RES', 'destreza' => 'DES', 'voluntad' => 'VOL', 'punteria' => 'PUN', 'agilidad' => 'AGI', 'reflejos' => 'REF');
    $nombres = array('FUE' => 'Fuerza', 'RES' => 'Resistencia', 'DES' => 'Destreza', 'VOL' => 'Voluntad', 'PUN' => 'Puntería', 'AGI' => 'Agilidad', 'REF' => 'Reflejos');
    $lista = array();
    if (!preg_match_all('/(\d+)\s*de\s*\[?\s*(Fuerza|Resistencia|Destreza|Voluntad|Punter[ií]a|Agilidad|Reflejos)\s*\]?/iu', (string) (isset($arma['requisitos']) ? $arma['requisitos'] : ''), $m, PREG_SET_ORDER)) {
        return $lista;
    }
    foreach ($m as $c) {
        $codigo = $codigos[opg_belico_clave_efecto($c[2])];
        $lista[] = array('atributo' => $nombres[$codigo], 'requerido' => (int) $c[1], 'actual' => (int) $atributos[$codigo], 'cumple' => (int) $atributos[$codigo] >= (int) $c[1]);
    }
    return $lista;
}

/**
 * Avisos sobre la selección de armas: configuración que cayó a "sin arma", armas repetidas sin copias,
 * dos o más amuletos, bono de dos manos que el arma no recibe y requisitos de atributo sin cumplir.
 * Cada aviso es array('nivel' => 'aviso'|'info', 'texto' => ...).
 */
function opg_belico_avisos_armas($seleccion, $atributos)
{
    $avisos = array();
    $etiquetas_config = array('una_mano' => 'Una arma', 'dos_manos' => 'Una arma con ambas manos', 'dos_armas' => 'Dos armas', 'tres_armas' => 'Tres armas');
    $armas = $seleccion['armas'];
    if ($seleccion['solicitada'] !== $seleccion['configuracion']) {
        $avisos[] = array('nivel' => 'info', 'texto' => 'Elegiste "' . $etiquetas_config[$seleccion['solicitada']] . '" pero no hay ningún arma válida elegida: se calcula sin arma (Fuerza para golpe y bloqueo, Agilidad para la TA).');
    } elseif ($armas && count($armas) < $seleccion['cantidad']) {
        $avisos[] = array('nivel' => 'info', 'texto' => 'La configuración pide ' . $seleccion['cantidad'] . ' armas pero solo hay ' . count($armas) . ' válida' . (count($armas) === 1 ? '' : 's') . ': se calcula con ' . count($armas) . '.');
    }
    foreach ($seleccion['ignoradas'] as $nombre) {
        $avisos[] = array('nivel' => 'aviso', 'texto' => '"' . $nombre . '" está repetida y no tienes otra copia: no cuenta.');
    }
    $amuletos = 0;
    foreach ($armas as $arma) {
        if (in_array('amuleto', opg_belico_tipos_arma($arma), true)) { $amuletos++; }
    }
    if ($amuletos >= 2) {
        $avisos[] = array('nivel' => 'aviso', 'texto' => 'No se pueden llevar dos o más amuletos.');
    }
    if ($seleccion['configuracion'] === 'dos_manos' && $armas && array_intersect(opg_belico_tipos_arma($armas[0]), opg_belico_tipos_sin_bono_dos_manos())) {
        $avisos[] = array('nivel' => 'aviso', 'texto' => '"' . opg_belico_nombre_arma($armas[0]) . '" no puede usarse con las dos manos para el bono (amuletos, arrojadizas y armas de fuego): se calcula como una mano.');
    }
    foreach ($armas as $arma) {
        foreach (opg_belico_requisitos_arma($arma, $atributos) as $requisito) {
            if (!$requisito['cumple']) {
                $avisos[] = array('nivel' => 'aviso', 'texto' => 'No cumples el requisito de "' . opg_belico_nombre_arma($arma) . '": ' . $requisito['atributo'] . ' ' . $requisito['actual'] . ' de ' . $requisito['requerido'] . '.');
            }
        }
    }
    return $avisos;
}

/**
 * Tipos de arma que exige la disciplina o el estilo de una técnica ("Sus técnicas requieren Armas de Filo").
 * null = no se sabe o sirve cualquiera; array() = el estilo no usa armas.
 */
function opg_belico_tipos_requeridos_tecnica($tecnica)
{
    $norm = function ($t) { return strtr(mb_strtolower(trim((string) $t), 'UTF-8'), array('á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ū' => 'u', 'ō' => 'o', 'ü' => 'u')); };
    $mapa = array(
        'espadachin' => array('filo'), 'guerrero' => array('contundente'), 'tirador' => array('fuego'), 'arquero' => array('tension'),
        'artillero' => array('asalto'), 'tecnicista' => array('exotica', 'flexible', 'asta'), 'picaro' => array('arrojadiza'),
        'artista' => array('musical'), 'escudero' => array('defensiva'), 'artista marcial' => array('corporal'),
        'ittoryu sekai' => array('filo'), 'wano nitoryu' => array('filo'), 'shiseiju' => array('corporal'),
        'mano de tahur' => array('arrojadiza', 'tension', 'fuego'), 'arcanos del destino' => array(),
        'yama kurai' => array('filo', 'contundente', 'asta', 'exotica', 'flexible'),
        'ashigara godoy' => array('corporal', 'defensiva'), 'ashigara dokoi' => array('corporal', 'defensiva'),
        'kokudan' => array('tension', 'fuego', 'asalto', 'arrojadiza'),
        'sea corsair' => array('filo', 'contundente', 'arrojadiza', 'exotica', 'fuego', 'asalto'),
        'kodai no bushido' => array('corporal', 'filo', 'contundente', 'asta'),
        'cavalry warrior' => array('asta', 'flexible', 'filo', 'contundente', 'tension', 'fuego', 'arrojadiza', 'asalto'),
    );
    foreach (array(isset($tecnica['rama']) ? $tecnica['rama'] : '', isset($tecnica['estilo']) ? $tecnica['estilo'] : '') as $nombre) {
        $clave = $norm($nombre);
        if (isset($mapa[$clave])) { return $mapa[$clave]; }
    }
    return null;
}

// ── Turno: clasificación de técnicas y costes ──────────────────────────────

/** Instantáneas: las de haki (BUSO, KENB, HAO) y los recubrimientos elementales. No ocupan espacio en un bloque. */
function opg_belico_es_instantanea($tecnica)
{
    $tid = strtoupper(trim((string) $tecnica['tid']));
    return (bool) preg_match('/^(BUSO|KENB|HAO)/', $tid) || in_array($tid, array('AER301', 'AQU301', 'CRY301', 'ELE301', 'PIR301'), true);
}

function opg_belico_es_mantenida($tecnica)
{
    return stripos((string) $tecnica['clase'], 'mantenida') !== false;
}

/**
 * Papel de una técnica en un bloque: 'principal' (ataque o defensa activa), 'utilidad' (utilidad activa o
 * cualquier mantenida) u 'otra' (pasivas y lo demás).
 */
function opg_belico_rol_tecnica($tecnica)
{
    $t = mb_strtolower((string) $tecnica['clase'] . ' ' . (string) $tecnica['tipo'], 'UTF-8');
    if (strpos($t, 'pasiva') !== false) { return 'otra'; }
    if (strpos($t, 'mantenida') !== false) { return 'utilidad'; }
    if (strpos($t, 'activa') !== false && strpos($t, 'utilidad') !== false) { return 'utilidad'; }
    if (strpos($t, 'activa') !== false && (strpos($t, 'ofensiva') !== false || strpos($t, 'defensiva') !== false || strpos($t, 'ambiental') !== false)) { return 'principal'; }
    return 'otra';
}

function opg_belico_numero_coste($valor)
{
    $valor = trim((string) $valor);
    return is_numeric($valor) ? max(0, (int) $valor) : 0;
}

/**
 * Coste de una técnica. $continua = true para una mantenida que sigue del turno anterior (coste por turno);
 * si no, es un gasto nuevo (primer coste). Una mantenida sin primer coste en la ficha cobra su coste por turno y avisa.
 * Devuelve energia, haki, enfriamiento (turnos) y avisos.
 */
function opg_belico_coste_tecnica($tecnica, $continua)
{
    $energia = opg_belico_numero_coste($tecnica['energia']);
    $haki = opg_belico_numero_coste($tecnica['haki']);
    $energia_turno = opg_belico_numero_coste($tecnica['energia_turno']);
    $haki_turno = opg_belico_numero_coste($tecnica['haki_turno']);
    $avisos = array();
    if ($continua) {
        $energia = $energia_turno;
        $haki = $haki_turno;
    } elseif (opg_belico_es_mantenida($tecnica)) {
        if ($energia === 0 && $energia_turno > 0) { $energia = $energia_turno; $avisos[] = 'sin coste de activación de energía en la ficha: se cobra el coste por turno'; }
        if ($haki === 0 && $haki_turno > 0) { $haki = $haki_turno; $avisos[] = 'sin coste de activación de haki en la ficha: se cobra el coste por turno'; }
    }
    return array('energia' => $energia, 'haki' => $haki, 'enfriamiento' => opg_belico_numero_coste($tecnica['enfriamiento']), 'avisos' => $avisos);
}

/** Acciones que no son técnicas: clave => [etiqueta, con_movimiento]. Todas consumen una acción adicional. */
function opg_belico_acciones_turno()
{
    return array(
        'golpe'          => array('Golpe básico', true),
        'bloqueo'        => array('Bloqueo básico', true),
        'consumible'     => array('Utilizar un consumible', true),
        'dial'           => array('Utilizar un dial', true),
        'lanzamiento'    => array('Lanzar un objeto sin técnica', true),
        'agarre'         => array('Realizar un agarre', true),
        'cambiar_arma'   => array('Cambiar el tipo de arma dual', true),
        'equipar_arma'   => array('Equipar un arma', true),
        'levantarse'     => array('Levantarse tras Derribo', false),
        'recuperar_arma' => array('Recuperar el arma tras Desarme', false),
        'trampa'         => array('Poner una trampa', false),
        'soltarse'       => array('Soltarse de un agarre', false),
    );
}

/**
 * Tipos de arma que cuentan para un requisito. Los Wazamono son una excepción a la regla: aplican varios tipos a la vez,
 * así que cumplen cualquier requisito de tipo de arma.
 */
function opg_belico_tipos_con_comodin($tipos)
{
    if (in_array('wazamono', $tipos, true)) {
        $tipos = array_merge($tipos, array_diff(array_keys(opg_belico_etiquetas_tipos_arma()), array('wazamono')));
    }
    return array_values(array_unique($tipos));
}
