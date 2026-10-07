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

function opg_belico_resolver_formula($formula, $atributos, $golpe_basico = null, $bloqueo_basico = null)
{
    $formula = trim((string) $formula);
    $pasos = array();
    $resuelta = preg_replace_callback('/\[([A-ZÁÉÍÓÚ]+)\s*[x×]\s*(\d+(?:[,.]\d+)?)\]/iu', function ($match) use ($atributos, &$pasos) {
        $codigo = opg_belico_codigo_atributo($match[1]);
        if ($codigo === null || !isset($atributos[$codigo])) { return $match[0]; }
        $multiplicador = (float) str_replace(',', '.', $match[2]);
        $valor = (int) ceil($atributos[$codigo] * $multiplicador);
        $pasos[] = $codigo . ' ' . $atributos[$codigo] . ' × ' . $match[2] . ' = ' . $valor;
        return (string) $valor;
    }, $formula);
    $resuelta = preg_replace_callback('/\[(?:(\d+(?:[,.]\d+)?)\s*[x×]?\s*Nivel|Nivel\s*[x×]\s*(\d+(?:[,.]\d+)?))\]/iu', function ($match) use ($atributos, &$pasos) {
        $multiplicador = (float) str_replace(',', '.', !empty($match[1]) ? $match[1] : $match[2]);
        $valor = (int) ceil($atributos['NIV'] * $multiplicador);
        $pasos[] = 'NIV ' . $atributos['NIV'] . ' × ' . opg_belico_formatear_numero($multiplicador) . ' = ' . $valor;
        return (string) $valor;
    }, $resuelta);
    if ($golpe_basico !== null && preg_match('/\[?Golpe\s+Básico\]?/iu', $resuelta)) {
        $resuelta = preg_replace('/\[?Golpe\s+Básico\]?/iu', (string) $golpe_basico, $resuelta);
        $pasos[] = 'Golpe básico = ' . (int) $golpe_basico;
    }
    if ($bloqueo_basico !== null && preg_match('/\[?Bloqueo\s+Básico\]?/iu', $resuelta)) {
        $resuelta = preg_replace('/\[?Bloqueo\s+Básico\]?/iu', (string) $bloqueo_basico, $resuelta);
        $pasos[] = 'Bloqueo básico = ' . (int) $bloqueo_basico;
    }
    $resuelta = preg_replace_callback('/\[([A-ZÁÉÍÓÚ]+)\s*\/\s*(\d+(?:[,.]\d+)?)\]/iu', function ($match) use ($atributos, &$pasos) {
        $codigo = opg_belico_codigo_atributo($match[1]);
        $divisor = (float) str_replace(',', '.', $match[2]);
        if ($codigo === null || !isset($atributos[$codigo]) || $divisor <= 0) { return $match[0]; }
        $valor = $atributos[$codigo] / $divisor;
        $texto_valor = opg_belico_formatear_numero($valor);
        $pasos[] = $codigo . ' ' . $atributos[$codigo] . ' / ' . opg_belico_formatear_numero($divisor) . ' = ' . $texto_valor;
        return $texto_valor;
    }, $resuelta);
    $resuelta = preg_replace_callback('/\[([A-ZÁÉÍÓÚ]+)\]/iu', function ($match) use ($atributos, &$pasos) {
        $codigo = opg_belico_codigo_atributo($match[1]);
        if ($codigo === null || !isset($atributos[$codigo])) { return $match[0]; }
        $pasos[] = $codigo . ' = ' . $atributos[$codigo];
        return (string) $atributos[$codigo];
    }, $resuelta);
    preg_match_all('/(?<![\d,])\d+(?:[,.]\d+)?/u', $resuelta, $numeros);
    $total = 0;
    foreach ($numeros[0] as $numero) { $total += (float) str_replace(',', '.', $numero); }
    return array('valor' => (int) ceil($total), 'resuelta' => $resuelta, 'pasos' => $pasos, 'reconocida' => !empty($pasos) || !empty($numeros[0]));
}

function opg_belico_calcular_arma($arma, $atributos, $dos_manos = false)
{
    $formula = isset($arma['dano']) ? (string) $arma['dano'] : '';
    $calculo = opg_belico_resolver_formula($formula, $atributos);
    $valor = $calculo['reconocida'] || $calculo['valor'] > 0 ? $calculo['valor'] : (int) $atributos['FUE'];
    if ($dos_manos) {
        $valor = (int) ceil($valor * 1.5);
        $calculo['pasos'][] = 'Empuñadura a dos manos: × 1,5 = ' . $valor;
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
        return array('valor' => $calculo['valor'], 'tipo' => $calculo['tipo'], 'pasos' => $calculo['pasos'], 'configuracion' => 'Una arma con ambas manos');
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
        return array('valor' => $valor, 'tipo' => implode('/', array_unique($tipos)), 'pasos' => $calculos, 'configuracion' => $cantidad . ' armas (TA −' . ($cantidad === 2 ? 10 : $cantidad * 5) . ')');
    }
    $calculo = opg_belico_calcular_arma($armas[0], $atributos);
    return array('valor' => $calculo['valor'], 'tipo' => $calculo['tipo'], 'pasos' => $calculo['pasos'], 'configuracion' => 'Una arma');
}

function opg_belico_calcular_bloqueo_arma($arma, $atributos, $dos_manos = false)
{
    $formula = isset($arma['bloqueo']) ? (string) $arma['bloqueo'] : '';
    $calculo = opg_belico_resolver_formula($formula, $atributos);
    $valor = $calculo['reconocida'] || $calculo['valor'] > 0 ? $calculo['valor'] : (int) $atributos['FUE'];
    if ($dos_manos) {
        $valor = (int) ceil($valor * 1.5);
        $calculo['pasos'][] = 'Empuñadura a dos manos: × 1,5 = ' . $valor;
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
        return array('valor' => $calculo['valor'], 'tipo' => $calculo['tipo'], 'pasos' => $calculo['pasos'], 'configuracion' => 'Una arma con ambas manos');
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
        return array('valor' => $valor, 'tipo' => 'mitigado', 'pasos' => $calculos, 'configuracion' => $cantidad . ' armas (TA −' . ($cantidad === 2 ? 10 : $cantidad * 5) . ')');
    }
    $calculo = opg_belico_calcular_bloqueo_arma($armas[0], $atributos);
    return array('valor' => $calculo['valor'], 'tipo' => $calculo['tipo'], 'pasos' => $calculo['pasos'], 'configuracion' => 'Una arma');
}

function opg_belico_calcular_efectos_contextuales($texto, $atributos, $golpe_basico, $bloqueo_basico = null)
{
    $atributo_token = '(?:NIVEL|FUERZA|AGILIDAD|PUNTER[IÍ]A|DESTREZA|RESISTENCIA|REFLEJOS|VOLUNTAD|NIV|FUE|AGI|PUN|DES|RES|REF|VOL)';
    $formula_token = '\[(?:(?:\d+(?:[,.]\d+)?\s*[x×]?\s*Nivel)|(?:Nivel\s*[x×]\s*\d+(?:[,.]\d+)?)|(?:' . $atributo_token . ')(?:\s*(?:[x×\/]\s*\d+(?:[,.]\d+)?))?)\]';
    $base_token = '\[?\s*(?:Golpe|Bloqueo)\s+Básico\s*\]?';
    $token = '(?:\d+(?:[,.]\d+)?|' . $base_token . '|' . $formula_token . ')';
    $patron = '/((?:' . $token . ')(?:\s*\+\s*' . $token . ')*)\s*(?:de\s*)?\[?(Daño\s+[^\]\n<]+|Mitigación|Bloqueo)\]?/iu';
    preg_match_all($patron, (string) $texto, $coincidencias, PREG_SET_ORDER);
    $resultados = array();
    $codigos_principales = array();
    foreach ($coincidencias as $coincidencia) {
        $calculo = opg_belico_resolver_formula($coincidencia[1], $atributos, $golpe_basico['valor'], $bloqueo_basico === null ? null : $bloqueo_basico['valor']);
        if ($calculo['reconocida']) {
            $etiqueta = trim($coincidencia[2]);
            $calculo['es_danio'] = stripos($etiqueta, 'daño') === 0;
            $calculo['tipo'] = $calculo['es_danio'] ? trim(preg_replace('/^daño\s*/iu', '', $etiqueta)) : $etiqueta;
            if (preg_match('/bloqueo\s+básico/iu', $coincidencia[1])) {
                $calculo['base'] = 'bloqueo';
            } elseif (preg_match('/golpe\s+básico/iu', $coincidencia[1])) {
                $calculo['base'] = 'golpe';
            }
            $calculo['expresion'] = $coincidencia[1];
            $calculo['codigo'] = $coincidencia[0];
            $resultados[] = $calculo;
            $codigos_principales[] = $coincidencia[0];
        }
    }
    $patron_efecto = '/(?<![\pL\]])((?:[+-]\s*\d+(?:[,.]\d+)?|[+-]?\s*' . $formula_token . '))\s*(%?)\s*(?:de\s+)?(?:\[([^\]\n<]+)\]|([A-Za-zÁÉÍÓÚÑáéíóúñ][^\[\]\n<+]*?))(?=\s*(?:y\s+)?[+-]?\s*(?:\d|\[)|\s*(?:[.,;]|$))/iu';
    preg_match_all($patron_efecto, (string) $texto, $coincidencias_efecto, PREG_SET_ORDER);
    foreach ($coincidencias_efecto as $coincidencia) {
        $codigo = $coincidencia[0];
        $ya_calculado = false;
        foreach ($codigos_principales as $codigo_principal) {
            if (stripos($codigo_principal, $codigo) !== false) { $ya_calculado = true; break; }
        }
        if ($ya_calculado) { continue; }
        $calculo = opg_belico_resolver_formula($coincidencia[1], $atributos, $golpe_basico['valor'], $bloqueo_basico === null ? null : $bloqueo_basico['valor']);
        if (!$calculo['reconocida']) { continue; }
        $etiqueta = trim(!empty($coincidencia[3]) ? $coincidencia[3] : $coincidencia[4]);
        $es_danio = stripos($etiqueta, 'daño') === 0;
        $tipo = $es_danio ? trim(preg_replace('/^daño\s*/iu', '', $etiqueta)) : $etiqueta;
        $texto_valor = opg_belico_formatear_numero($calculo['valor']) . $coincidencia[2];
        $resultados[] = array(
            'valor' => $calculo['valor'],
            'valor_texto' => $texto_valor,
            'tipo' => $tipo,
            'pasos' => $calculo['pasos'],
            'reconocida' => true,
            'es_danio' => $es_danio,
            'codigo' => $codigo,
        );
    }
    return $resultados;
}
