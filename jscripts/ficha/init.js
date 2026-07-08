/**
 * ficha/init.js — Inicialización de la ficha de personaje
 * Requiere: core.js, pestanas.js, tecnicas.js, belicas.js,
 *           oficios.js, inventario.js, modales.js, portada.js
 * Este archivo es el punto de entrada. Todo el código imperativo
 * que antes vivía en el scope global ahora se ejecuta aquí dentro
 * de $(document).ready(), asegurando que todos los módulos estén cargados.
 */
(function (FICHA, w) {

  // ── CSS de modal (una sola inyección) ────────────────────────
  var style = document.createElement('style');
  style.innerHTML = [
    '@-webkit-keyframes animatetop{from{top:-300px;opacity:0}to{top:0;opacity:1}}',
    '@keyframes animatetop{from{top:-300px;opacity:0}to{top:0;opacity:1}}',
    '.modal{display:none;position:fixed;z-index:10000;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.4)}'
  ].join('');
  document.head.appendChild(style);

  // ── Inicialización principal ──────────────────────────────────
  $(document).ready(function () {

    // DEBUG — eliminar tras diagnóstico
    console.group('[OPG] Técnicas cargadas');
    console.log('tec_aprendidas_json (claves→técnicas):', JSON.parse(JSON.stringify(tec_aprendidas_json)));
    console.log('Claves de tecnicas_html_json:', Object.keys(typeof tecnicas_html_json !== 'undefined' ? tecnicas_html_json : {}));
    console.groupEnd();

    // 1. Peso / FUE mínima para alzar
    $('#fuerza_alzar').html(alzarPeso(peso));

    // 2. Implantes
    if (implantes && implantes[0] !== '') {
      for (var i = 0; i < implantes.length; i++) {
        if (!objetos_json[implantes[i]] || !objetos_json[implantes[i]][0]) continue;
        var objeto = objetos_json[implantes[i]][0];
        var colorTier = '#faa500';
        var imagen_id = objeto.imagen_id;
        var efecto = '';

        if (imagen_id == '1') colorTier = '#808080';
        if (imagen_id == '2') colorTier = '#4dfe45';
        if (imagen_id == '3') colorTier = '#457bfe';
        if (imagen_id == '4') colorTier = '#cf44ff';
        if (imagen_id == '5') colorTier = '#febb46';
        if (parseInt(imagen_id) >= 6) colorTier = 'linear-gradient(315deg,rgba(255,0,0,1) 0%,rgba(255,154,0,1) 10%,rgba(208,222,33,1) 20%,rgba(79,220,74,1) 30%,rgba(63,218,216,1) 40%,rgba(47,201,226,1) 50%,rgba(28,127,238,1) 60%,rgba(95,21,242,1) 70%,rgba(186,12,248,1) 80%,rgba(251,7,217,1) 90%,rgba(255,0,0,1) 100%)';

        if (objeto.efecto) {
          efecto = '<div style="font-family:InterRegular;padding:5px;text-align:center;border-top:1px solid #5e5e5e;">' + objeto.efecto + '</div>';
        }

        $('#implantes-container').append(
          '<div style="display:flex;flex-direction:row;border-bottom:2px solid black;">' +
          '<div><div class="tooltip">' +
          '<img style="width:50px;" id="imagen-item" src="' + objeto['imagen_avatar'] + '">' +
          '<div class="tooltiptext item-tooltip" style="left:50px;">' +
          '<div style="font-size:15px;font-family:moonGetHeavy;letter-spacing:1px;text-align:center;background:' + colorTier + ';border-top-left-radius:6px;border:0px;border-top-right-radius:6px;padding:3px;text-shadow:1px 1px 3px black;">' + objeto['nombre'] + '</div>' +
          '<div class="mydescripcion" style="font-family:InterRegular;padding:5px;text-align:justify;">' + objeto.descripcion + '</div>' +
          efecto +
          '<div style="font-family:moonGetHeavy;padding:2px 5px;display:flex;flex-direction:row;justify-content:space-between;background:' + colorTier + ';border-top:1px solid black;border-bottom:1px solid black;font-size:9px;text-shadow:1px 1px 2px black;filter:saturate(0.7);">' +
          '<div>Cantidad: ' + objeto.cantidad + '</div><div>' + objeto.espacios + ' Espacios</div><div>ID: <span>' + objeto.objeto_id + '</span></div></div>' +
          '<div style="font-size:9px;font-family:moonGetHeavy;letter-spacing:1px;text-align:center;background:' + colorTier + ';border-bottom-left-radius:6px;border:0px;border-bottom-right-radius:6px;padding:3px;text-shadow:1px 1px 3px black;">' + objeto.subcategoria + ' - Tier ' + objeto.tier + '</div>' +
          '</div></div></div>' +
          '<div style="background-color:#8062d6;width:100%;border-left:2px solid black;font-size:17px;color:white;font-family:moonGetHeavy;align-content:center;">' + objeto['nombre'] + '</div>' +
          '</div>'
        );
      }
    }

    // 3. Buso variables + DOM
    var nikasCostoBuso = 10000;
    var canGetBuso = false;
    var nivelHakiBuso = 500;

    if (buso == 0) {
      $('#buso_nivel').html('No Despertado');
      $('#buso_img').css('filter', 'grayscale(1)');
    } else if (buso <= 6) {
      $('#buso_nivel').html('Tier ' + (buso + 1) + ' de Poder');
      nikasCostoBuso = 10000; canGetBuso = false; nivelHakiBuso = 500;
      if (buso == 1) { if (nivel >= 15 || (hasFullHaki && nivel >= 10)) canGetBuso = true; nikasCostoBuso = hasFullHaki ? 0 : 10; nivelHakiBuso = 15; }
      if (buso == 2) { if (nivel >= 20 || (hasFullHaki && nivel >= 15)) canGetBuso = true; nivelHakiBuso = 20; nikasCostoBuso = 15; }
      if (buso == 3) { if (nivel >= 25 || (hasFullHaki && nivel >= 20)) canGetBuso = true; nivelHakiBuso = 25; nikasCostoBuso = 25; }
      if (buso == 4) { if (nivel >= 30 || (hasFullHaki && nivel >= 25)) canGetBuso = true; nivelHakiBuso = 30; nikasCostoBuso = 40; }
      if (buso == 5) { if (nivel >= 35 || (hasFullHaki && nivel >= 30)) canGetBuso = true; nivelHakiBuso = 35; nikasCostoBuso = 60; }
      if (buso == 6) { if (nivel >= 40 || (hasFullHaki && nivel >= 35)) canGetBuso = true; nivelHakiBuso = 40; nikasCostoBuso = 150; }
      if (is_owner) $('#buso_img').css('cursor', 'pointer');
    } else {
      if (buso == 1) { $('#buso_nivel').html('Entrenable'); }
      else if (buso == 7 && hasFullHaki) { $('#buso_nivel').html('Tier 9 de Poder'); }
      else { $('#buso_nivel').html('Tier ' + (buso + 1) + ' de Poder'); }
    }

    // 4. Hao variables + DOM
    var nikasCostoHao = 10000;
    var canGetHao = false;
    var nivelHakiHao = 500;

    if (hao == -1) {
      $('#hao_nivel').html('Sin Obtener');
      $('#hao_img').css('filter', 'grayscale(1)');
    } else if (hao == 0) {
      $('#hao_nivel').html('No Despertado');
      $('#hao_img').css('filter', 'grayscale(1)');
    } else if (hao <= 6) {
      $('#hao_nivel').html('Tier ' + (hao + 1) + ' de Poder');
      nikasCostoHao = 10000; canGetHao = false; nivelHakiHao = 500;
      if (hao == 1) { if (nivel >= 15 || (hasFullHaki && nivel >= 10)) canGetHao = true; nikasCostoHao = hasFullHaki ? 0 : 10; nivelHakiHao = 15; }
      if (hao == 2) { if (nivel >= 20 || (hasFullHaki && nivel >= 15)) canGetHao = true; nivelHakiHao = 20; nikasCostoHao = 15; }
      if (hao == 3) { if (nivel >= 25 || (hasFullHaki && nivel >= 20)) canGetHao = true; nivelHakiHao = 25; nikasCostoHao = 25; }
      if (hao == 4) { if (nivel >= 30 || (hasFullHaki && nivel >= 25)) canGetHao = true; nivelHakiHao = 30; nikasCostoHao = 40; }
      if (hao == 5) { if (nivel >= 35 || (hasFullHaki && nivel >= 30)) canGetHao = true; nivelHakiHao = 35; nikasCostoHao = 60; }
      if (hao == 6) { if (nivel >= 40 || (hasFullHaki && nivel >= 35)) canGetHao = true; nivelHakiHao = 40; nikasCostoHao = 150; }
      if (is_owner) $('#hao_img').css('cursor', 'pointer');
    } else {
      if (hao == 1) { $('#hao_nivel').html('Entrenable'); }
      else if (hao == 7 && hasFullHaki) { $('#hao_nivel').html('Tier 9 de Poder'); }
      else { $('#hao_nivel').html('Tier ' + (hao + 1) + ' de Poder'); }
    }

    // 5. Kenbun variables + DOM
    var nikasCostoKenbun = 10000;
    var canGetKenbun = false;
    var nivelHakiKenbun = 500;

    if (kenbun == 0) {
      $('#kenbun_nivel').html('No Despertado');
      $('#kenbun_img').css('filter', 'grayscale(1)');
    } else if (kenbun <= 6) {
      $('#kenbun_nivel').html('Tier ' + (kenbun + 1) + ' de Poder');
      nikasCostoKenbun = 10000; canGetKenbun = false; nivelHakiKenbun = 500;
      if (kenbun == 1) { if (nivel >= 10 || (hasFullHaki && nivel >= 5)) canGetKenbun = true; nikasCostoKenbun = hasFullHaki ? 0 : 10; nivelHakiKenbun = 10; }
      if (kenbun == 2) { if (nivel >= 20 || (hasFullHaki && nivel >= 15)) canGetKenbun = true; nivelHakiKenbun = 20; nikasCostoKenbun = 15; }
      if (kenbun == 3) { if (nivel >= 25 || (hasFullHaki && nivel >= 20)) canGetKenbun = true; nivelHakiKenbun = 25; nikasCostoKenbun = 25; }
      if (kenbun == 4) { if (nivel >= 30 || (hasFullHaki && nivel >= 25)) canGetKenbun = true; nivelHakiKenbun = 30; nikasCostoKenbun = 40; }
      if (kenbun == 5) { if (nivel >= 35 || (hasFullHaki && nivel >= 30)) canGetKenbun = true; nivelHakiKenbun = 35; nikasCostoKenbun = 60; }
      if (kenbun == 6) { if (nivel >= 40 || (hasFullHaki && nivel >= 35)) canGetKenbun = true; nivelHakiKenbun = 40; nikasCostoKenbun = 150; }
      if (is_owner) $('#kenbun_img').css('cursor', 'pointer');
    } else {
      if (kenbun == 1) { $('#kenbun_nivel').html('Entrenable'); }
      else if (kenbun == 7 && hasFullHaki) { $('#kenbun_nivel').html('Tier 9 de Poder'); }
      else { $('#kenbun_nivel').html('Tier ' + (kenbun + 1) + ' de Poder'); }
    }

    // 6. Animación de progreso circular
    var circularProgress = document.querySelectorAll('.circular-progress');
    Array.from(circularProgress).forEach(function (progressBar) {
      var progressValue = progressBar.querySelector('.percentage');
      var innerCircle  = progressBar.querySelector('.inner-circle');
      var startValue   = 0;
      var endValue     = Number(progressBar.getAttribute('data-percentage'));
      var speed        = 25;
      var progressColor = progressBar.getAttribute('data-progress-color');
      var nivelAttr     = progressBar.getAttribute('data-nivel');
      var progress = setInterval(function () {
        startValue++;
        progressValue.textContent = nivelAttr + '';
        progressValue.style.color = 'white';
        innerCircle.style.backgroundColor = progressBar.getAttribute('data-inner-circle-color');
        progressBar.style.background = 'conic-gradient(' + progressColor + ' ' + (startValue * 3.6) + 'deg,' + progressBar.getAttribute('data-bg-color') + ' 0deg)';
        if (startValue === endValue) clearInterval(progress);
      }, speed);
    });

    // 7. belicasArray
    var belicasArray = [belica1, belica2, belica3, belica4, belica5, belica6,
                        belica7, belica8, belica9, belica10, belica11, belica12];

    // 8. addEfectoStats a daños de objetos
    var objetoDanos = $('.dano-objeto');
    for (var d = 0; d < objetoDanos.length; d++) {
      var dano = $(objetoDanos[d]);
      dano.html(addEfectoStats(dano.html()));
    }

    // 9. Pestañas de técnicas
    $('#pestana-tecnicas').append(
      '<div class="pestana-tecnica todo" onclick="showBlock(\'todo\', \'#4856ff\')" style="background-color:#ff6600;margin-top:40px;height:40px;width:209px;position:relative;margin-left:-16px;">' +
      '<div style="color:white;font-size:22px;text-align:center;position:relative;top:4px;">TODO</div></div>'
    );

    $('#pestana-tecnicas').append(
      '<div class="pestana-tecnica belica1 ' + belica1 + '" onclick="showBlock(\'' + belica1 + '\', \'#4856ff\')" style="background-color:#4856ff;">' +
      '<div style="color:white;font-size:19px;text-align:center;position:relative;top:4px;">' + belica1 + '</div></div>'
    );

    var belicaColors = ['#5aae29','#1fc281','#c2a61f','#d462c2','#d462c2','#d462c2','#d462c2','#d462c2','#d462c2','#d462c2','#d462c2'];
    var belicaNums   = [2,3,4,5,6,7,8,9,10,11,12];
    for (var bi = 0; bi < belicaNums.length; bi++) {
      var bn  = belicaNums[bi];
      var bv  = belicasArray[bn - 1];
      var bc  = belicaColors[bi];
      if (bv) {
        $('#pestana-tecnicas').append(
          '<div class="pestana-tecnica belica' + bn + ' ' + bv + '" onclick="showBlock(\'' + bv + '\', \'' + bc + '\')" style="background-color:' + bc + ';margin-top:5px;">' +
          '<div style="color:white;font-size:19px;text-align:center;position:relative;top:4px;">' + bv + '</div></div>'
        );
      }
    }

    // Técnicas variables
    var tecUnicas  = getTecnicas('Única');
    var tecAkumas  = getTecnicas('Akuma');
    var tecEstilo1 = (estilo1 && estilo1 !== 'bloqueado') ? getTecnicas(estilo1) : [];
    var tecEstilo2 = (estilo2 && estilo2 !== 'bloqueado') ? getTecnicas(estilo2) : [];
    var tecEstilo3 = (estilo3 && estilo3 !== 'bloqueado') ? getTecnicas(estilo3) : [];
    var tecEstilo4 = (estilo4 && estilo4 !== 'bloqueado') ? getTecnicas(estilo4) : [];
    var especial   = getTecnicas('Especial');
    var raciales   = getTecnicas('Racial');
    var personaje  = getTecnicas('Personaje');

    var aero    = elementos['Aero'];
    var aqua    = elementos['Aqua'];
    var cryo    = elementos['Cryo'];
    var piro    = elementos['Piro'];
    var electro = elementos['Electro'];

    if (tecUnicas && tecUnicas.length > 0) {
      $('#pestana-tecnicas').append('<div class="pestana-tecnica Única" onclick="showBlock(\'Única\', \'#8c29ae\')" style="background-color:#8c29ae;margin-top:5px;"><div style="color:white;font-size:19px;text-align:center;position:relative;top:4px;">Única</div></div>');
    }
    if (tecAkumas && tecAkumas.length > 0) {
      $('#pestana-tecnicas').append('<div class="pestana-tecnica Akuma" onclick="showBlock(\'Akuma\', \'#5529ae\')" style="background-color:#5529ae;margin-top:5px;"><div style="color:white;font-size:19px;text-align:center;position:relative;top:4px;">Akuma</div></div>');
    }

    var estiloData = [
      { v: estilo1, tecs: tecEstilo1, slot: 'estilo1', color: '#2993ae', mt: '5px' },
      { v: estilo2, tecs: tecEstilo2, slot: 'estilo2', color: '#29ae79', mt: '5px' },
      { v: estilo3, tecs: tecEstilo3, slot: 'estilo3', color: '#97ae29', mt: '5px' },
      { v: estilo4, tecs: tecEstilo4, slot: 'estilo4', color: '#97ae29', mt: '5px' }
    ];
    for (var ei = 0; ei < estiloData.length; ei++) {
      var ed = estiloData[ei];
      if (ed.tecs && ed.tecs.length > 0 && ed.v && ed.v !== 'bloqueado' && ed.v !== 'no_bloqueado') {
        var edP = ed.v.replace(' ', '_');
        $('#pestana-tecnicas').append(
          '<div class="pestana-tecnica ' + ed.slot + ' ' + edP + '" onclick="showBlock(\'' + edP + '\', \'' + ed.color + '\')" style="background-color:' + ed.color + ';margin-top:' + ed.mt + ';">' +
          '<div style="color:white;font-size:15px;text-align:center;position:relative;top:7px;">' + ed.v + '</div></div>'
        );
      }
    }

    if (raciales && raciales.length > 0) {
      $('#pestana-tecnicas').append('<div class="pestana-tecnica racial" onclick="showBlock(\'Racial\', \'#ff8636\')" style="background-color:#ff8636;margin-top:5px;"><div style="color:white;font-size:19px;text-align:center;position:relative;top:4px;">Raciales</div></div>');
    }
    if (personaje && personaje.length > 0) {
      $('#pestana-tecnicas').append('<div class="pestana-tecnica personaje" onclick="showBlock(\'Personaje\', \'#4bf15c\')" style="background-color:#4bf15c;margin-top:5px;"><div style="color:white;font-size:19px;text-align:center;position:relative;top:4px;">Personaje</div></div>');
    }
    if (especial && especial.length > 0) {
      $('#pestana-tecnicas').append('<div class="pestana-tecnica especial" onclick="showBlock(\'Especial\', \'#ae2969\')" style="background-color:#ae2969;margin-top:5px;"><div style="color:white;font-size:19px;text-align:center;position:relative;top:4px;">Especiales</div></div>');
    }
    if (tec_aprendidas_json['Haoshoku'] || tec_aprendidas_json['Kenbunshoku'] || tec_aprendidas_json['Busoshoku']) {
      $('#pestana-tecnicas').append('<div class="pestana-tecnica Haki" onclick="showBlock(\'Haki\', \'#05a3b9\')" style="background-color:#05a3b9;margin-top:5px;"><div style="color:white;font-size:19px;text-align:center;position:relative;top:4px;">Haki</div></div>');
    }
    if (electro == 1 || piro == 1 || cryo == 1 || aqua == 1 || aero == 1) {
      $('#pestana-tecnicas').append('<div class="pestana-tecnica Elementales" onclick="showBlock(\'Elementales\', \'#1e7563\')" style="background-color:#1e7563;margin-top:5px;"><div style="color:white;font-size:19px;text-align:center;position:relative;top:4px;">Elementos</div></div>');
    }

    // 9b. Pestañas para ramas que no encajan en ninguna categoría estándar (ej: ASHURA SANTORYU)
    var _tabsCubiertos = ['todo','Única','Akuma','Racial','Personaje','Especial',
      'Haki','Haoshoku','Kenbunshoku','Busoshoku','Electro','Piro','Cryo','Aqua','Aero',
      'Vanguardia','Bastión','Acróbata','Monje','Berserker','Campeón','Bardo','Trovador',
      'Sombra','Verdugo','Castigador','Warhammer','Samurái','Mosquetero','Diletante',
      'WeaponMaster','Destructor','Juggernaut','Ballestero','Cazador','Duelista',
      'Francotirador','Gambito','Trickster'];
    for (var _bi = 0; _bi < belicasArray.length; _bi++) {
      if (belicasArray[_bi]) _tabsCubiertos.push(belicasArray[_bi]);
    }
    [estilo1, estilo2, estilo3, estilo4].forEach(function (e) {
      if (e && e !== 'bloqueado' && e !== 'no_bloqueado') _tabsCubiertos.push(e);
    });
    for (var _orphanKey in tec_aprendidas_json) {
      if (_tabsCubiertos.indexOf(_orphanKey) === -1 &&
          Array.isArray(tec_aprendidas_json[_orphanKey]) &&
          tec_aprendidas_json[_orphanKey].length > 0) {
        var _orphanKeyUnder = _orphanKey.replace(/ /g, '_');
        $('#pestana-tecnicas').append(
          '<div class="pestana-tecnica ' + _orphanKeyUnder + '" onclick="showBlock(\'' + _orphanKey.replace(/'/g, "\\'") + '\', \'#7a4f9e\')" style="background-color:#7a4f9e;margin-top:5px;">' +
          '<div style="color:white;font-size:15px;text-align:center;position:relative;top:7px;">' + _orphanKey + '</div></div>'
        );
      }
    }

    // 10. showBlock inicial
    $('#tecnicas-background').css('background-color', '#4856ff');
    var lastTab   = sessionStorage.getItem('lastTecnicasTab')   || 'todo';
    var lastColor = sessionStorage.getItem('lastTecnicasColor') || '#ff6600';
    showBlock(lastTab, lastColor);

    // 11. Estilos de combate (slots 1-4)
    var estiloSlots = [
      { id: '#estilo1', v: estilo1, n: '1' },
      { id: '#estilo2', v: estilo2, n: '2' },
      { id: '#estilo3', v: estilo3, n: '3' },
      { id: '#estilo4', v: estilo4, n: '4' }
    ];
    for (var si = 0; si < estiloSlots.length; si++) {
      var ss = estiloSlots[si];
      if (ss.v === 'bloqueado')    { $(ss.id).html(getBloqueadoHtml(ss.n)); }
      else if (ss.v === 'no_bloqueado') { $(ss.id).html(getNoBloqueadoHtml(ss.n)); }
      else                         { $(ss.id).html(getEstiloHtml(ss.v)); }
    }

    // 12. Belicas fallback
    if (!belicas || typeof belicas !== 'object') {
      console.error('El objeto belicas no está definido o es null');
      belicas = {};
    }

    // 13. updateBelicaUI para cada disciplina activa
    for (var bui = 0; bui < belicasArray.length; bui++) {
      var bv2 = belicasArray[bui];
      if (bv2) updateBelicaUI(bv2);
    }

    // 14. Inventario: equipar bolsa/ropa iniciales
    $(w).click(function (event) {
      var mse = document.getElementById('modalSelectorEquipamiento');
      if (event.target === mse) cerrarSelectorEquipamiento();
    });

    if (objetosEquipados && objetosEquipados.bolsa) {
      var bolsaObj = objetos_json[objetosEquipados.bolsa];
      if (bolsaObj && bolsaObj[0]) equiparItemBolsa(bolsaObj[0].objeto_id);
    }
    if (objetosEquipados && objetosEquipados.ropa) {
      var ropaObj = objetos_json[objetosEquipados.ropa];
      if (ropaObj && ropaObj[0]) equiparItemRopa(ropaObj[0].objeto_id);
    }
    actualizarContadorEspacios();

    // 15. NPCs acompañantes
    loadNpcsAcompanantesBio();

    // 16. addEfectos / addEfectoStats en técnicas del DOM
    $('.tecnica_descripcion').each(function () {
      $(this).html(addEfectos($(this).html()));
    });
    $('.tecnica_efecto').each(function () {
      $(this).html(addEfectoStats($(this).html()));
    });

    // 17. Avatar hover
    $('.avatar_biografia').mouseover(function () {
      var time = Math.round(Date.now() / 500) % 360;
      $(this).css('transition', 'all 10s ease-out').css('filter', 'hue-rotate(' + time + 'deg)');
    }).mouseout(function () {
      $(this).css('transition', 'all 5s ease-out').css('filter', 'none');
    });

    // 18. Puntos de virtudes y defectos
    var puntosVirtud  = 0;
    for (var vi = 0; vi < virtudes_array_json.length; vi++) {
      puntosVirtud += parseInt(virtudes_json[virtudes_array_json[vi]][0].puntos);
    }
    var puntosDefecto = 0;
    for (var di = 0; di < defectos_array_json.length; di++) {
      puntosDefecto += parseInt(defectos_json[defectos_array_json[di]][0].puntos);
    }
    $('#virtudes_puntos').html(puntosVirtud);
    $('#defectos_puntos').html(puntosDefecto);

    // 19. Akuma espacio HTML (una sola vez)
    if (hasAkuma) {
      $('#akuma_espacio').html(
        '<div style="width:100%;background:url(/images/op/uploads/Libro%20Akuma%20_One_Piece_Gaiden_Foro_Rol.webp);background-repeat:no-repeat;background-size:cover;border:2px solid black;padding:10px 0;height:280px;border-radius:8px;" class="bbox">' +
        '<div style="text-align:center;font-size:17px;color:Black;font-family:moonGetHeavy;margin-top:0px;margin-bottom:5px;text-shadow:2px 2px 1px white;">' + akumaNombre + '</div>' +
        '<div style="text-align:center;font-size:13px;color:white;font-family:moonGetHeavy;font-style:italic;margin-bottom:5px;text-shadow:1px 1px 1px black;">' + akumaSubnombre + '</div>' +
        '<div id="frutaTipoTier" style="text-align:center;background:linear-gradient(90deg,rgba(0,116,143,0) 0%,rgb(81,25,106) 20%,rgb(35,5,46) 50%,rgb(81,25,106) 80%,rgba(0,116,143,0) 100%);margin-top:8px;">' +
        '<div style="font-family:moonGetHeavy;color:white;font-size:12px;margin:auto;text-shadow:2px 2px 1px black;letter-spacing:2px;"><span id="frutaTipo">' + akumaCategoria + '</span> | Tier <span id="frutaTier">' + akumaTier + '</span></div></div>' +
        '<div style="text-align:center;margin-top:-8px;"><img src="' + akumaImagen + '" style="width:200px;height:200px;box-shadow:0px 0px 0px rgba(0,0,0,0);border-radius:0px;"></div></div>'
      );
    } else if (hasFullHaki) {
      $('#akuma_espacio').html(
        '<div style="width:100%;background:url(/images/op/uploads/FullHaki_One_Piece_Gaiden_Foro_Rol.webp);background-repeat:no-repeat;background-size:cover;border:2px solid black;padding:10px 0;height:280px;display:flex;justify-content:center;align-items:center;border-radius:5px;" class="bbox">' +
        '<span style="font-family:\'moonGetHeavy\';font-size:24px;color:white;text-align:center;text-shadow:2px 2px 1px black;"></span></div>'
      );
    } else {
      $('#akuma_espacio').html(
        '<div style="width:100%;background:url(/images/op/uploads/BuscandoAkuma%20_One_Piece_Gaiden_Foro_Rol.webp);background-repeat:no-repeat;background-size:cover;border:2px solid black;padding:10px 0;height:280px;display:flex;justify-content:center;align-items:center;border-radius:5px;" class="bbox">' +
        '<span style="font-family:\'moonGetHeavy\';font-size:24px;color:white;text-align:center;text-shadow:2px 2px 1px black;"></span></div>'
      );
    }

    // akuma_espacio clickeable + hover
    if (hasAkuma) {
      var akumaEspacio = document.getElementById('akuma_espacio');
      if (akumaEspacio) {
        akumaEspacio.style.cursor = 'pointer';
        akumaEspacio.onclick = function (event) { event.stopPropagation(); openAkumaModal(); };
        akumaEspacio.addEventListener('mouseenter', function () {
          this.style.filter    = 'brightness(1.1) drop-shadow(0px 0px 8px rgba(255,126,0,0.8))';
          this.style.transform = 'scale(1.02)';
          this.style.transition = 'all 0.3s ease';
        });
        akumaEspacio.addEventListener('mouseleave', function () {
          this.style.filter    = '';
          this.style.transform = '';
        });
      }
    }

    // 20. clickPestana inicial (restaurar sessionStorage o variable PHP)
    if (pestana && pestana !== '') {
      clickPestana(pestana);
    } else {
      var lastFichaTab = sessionStorage.getItem('lastFichaTab') || 'portada';
      clickPestana(lastFichaTab);
    }

    // 21. Virtudes append
    for (var vvi = 0; vvi < virtudes_array_json.length; vvi++) {
      var virtud = virtudes_json[virtudes_array_json[vvi]][0];
      $('#rasgos_virtudes').append(
        '<div class="virtudCaja">' +
        '<div class="virtudPuntos virtudColor">' + virtud.puntos + '</div>' +
        '<div class="virtudNombre virtudColor">' + virtud.nombre + '</div>' +
        '<div class="virtudCajaTexto virtudBackground">' +
        '<div style="display:flex;flex-direction:row;">' +
        '<div style="width:40px;text-align:center;position:relative;top:17px;left:2px;">' +
        '<span style="color:#ffffff;font-family:moonGetHeavy;letter-spacing:0px;font-size:8px;">ID<br>' + virtud.virtud_id + '</span></div>' +
        '<div style="width:200px;margin-left:10px;font-family:notoKuro;color:white;font-size:12px;">' + virtud.descripcion + '</div>' +
        '</div></div></div>'
      );
    }

    // 22. Defectos append
    for (var ddi = 0; ddi < defectos_array_json.length; ddi++) {
      var defecto = defectos_json[defectos_array_json[ddi]][0];
      $('#rasgos_defectos').append(
        '<div class="virtudCaja">' +
        '<div class="virtudPuntos defectoColor">' + Math.abs(defecto.puntos) + '</div>' +
        '<div class="virtudNombre defectoColor">' + defecto.nombre + '</div>' +
        '<div class="virtudCajaTexto defectoBackground">' +
        '<div style="display:flex;flex-direction:row;">' +
        '<div style="width:40px;text-align:center;position:relative;top:17px;left:2px;">' +
        '<span style="color:#ffffff;font-family:moonGetHeavy;letter-spacing:0px;font-size:8px;">ID<br>' + defecto.virtud_id + '</span></div>' +
        '<div style="color:#ffffff;font-family:interRegular;letter-spacing:0px;font-size:11px;margin-top:6px;padding-right:5px;width:240px;">' + defecto.descripcion + '</div>' +
        '</div></div></div>'
      );
    }

    // 23. Subraza + espacios de peso
    var espacios = 0;
    if (altura > 0   && altura <= 50)  { espacios = 0.25; }
    if (altura > 50  && altura <= 100) { espacios = 0.5; }
    if (altura > 100 && altura <= 300) { espacios = 1; }
    if (altura > 300 && altura <= 500) { espacios = 2; }
    if (altura > 500) { espacios = 2 + Math.ceil((altura - 300) / 200); }

    var subrazaTxt = 'Raza Pura';
    var subraza = tec_aprendidas_json['Racial'] ? tec_aprendidas_json['Racial'].filter(function (i) { return i.tid.toLowerCase().includes('tri'); }) : [];
    if (subraza && subraza.length > 0) { subrazaTxt = subraza[0].nombre; }

    if (raza !== 'Humano' && raza !== 'Gyojin' && raza !== 'Ningyo' &&
        raza !== 'Gigante' && raza !== 'Tontatta' && raza !== 'Mink' &&
        raza !== 'Skypian' && raza !== 'Oni' && raza !== 'Lunarian') {
      subrazaTxt = 'Raza Híbrida';
    }
    $('#subraza').html(subrazaTxt);
    $('#espacios').html(espacios);

    // 24. Oficios fallback + protección
    if (typeof oficios === 'undefined') {
      console.error('El objeto oficios no está definido');
      oficios = {};
    }
    if (typeof w.oficiosInfo === 'undefined' || w.oficiosInfo === null) {
      w.oficiosInfo = { nivel: 'desconocido', sub: {}, espe1: null, espe2: null };
    }
    if (!w.getSafeOficio) {
      w.getSafeOficio = function (nombreOficio) {
        if (!nombreOficio || typeof oficios === 'undefined') return { nivel: 'desconocido', sub: {}, espe1: null, espe2: null };
        var o = oficios[nombreOficio];
        if (!o) return { nivel: 'desconocido', sub: {}, espe1: null, espe2: null };
        return o;
      };
    }

    // 25. has_sin_oficio
    if (has_sin_oficio) {
      $('#oficios').html('<div style="width:280px;height:280px;background:url(/images/op/uploads/sin_oficio.webp);filter:drop-shadow(0px 0px 10px black);"></div>');
    }

    // 26. Oficio1 render
    if (oficio1 !== '') {
      var auxOficio1 = (oficio1 !== 'Médico') ? oficio1 : 'Medico';
      var oficio1Obj = oficios[oficio1];
      var oficio1Nivel = oficio1Obj.nivel;
      var subKeys1  = Object.keys(oficio1Obj.sub);
      var firstSub1 = subKeys1[0];
      var secondSub1 = subKeys1[1];
      var auxOf1Sub0 = (firstSub1  === 'Recolector') ? 'Contrabandista' : firstSub1;
      var auxOf1Sub1 = (secondSub1 === 'Recolector') ? 'Contrabandista' : secondSub1;
      var of1NivelTag    = oficio1 + 'Nivel';
      var of1SubNivel1   = firstSub1  + 'Nivel';
      var of1SubNivel2   = secondSub1 + 'Nivel';
      var of1NivelId     = '#' + of1NivelTag;
      var of1SubNivelId1 = '#' + of1SubNivel1;
      var of1SubNivelId2 = '#' + of1SubNivel2;

      $('#oficios').append(
        '<div style="width:280px;margin:0px -2px;position:relative;box-sizing:border-box;">' +
        '<div style="position:relative;height:30px;background-color:' + borderColor + ';padding:0 5px;margin:auto;text-align:center;border-radius:15px 15px 0px 0px;border-top:2px solid black;border-left:2px solid black;border-right:2px solid black;">' +
        '<span style="font-family:\'moonGetHeavy\';color:white;font-size:20px;text-shadow:1px 1px 0px black;">' + oficio1 + '</span>' +
        '<span id="' + of1NivelTag + '" style="position:absolute;color:white;font-family:\'moonGetHeavy\';left:232px;top:5px;background-color:#9d57b4;width:40px;border-radius:20px;border:1px solid black;">' + oficio1Nivel + '</span></div>' +
        '<div style="height:100px;background:url(/images/op/uploads/OficioFicha' + auxOficio1 + '_One_Piece_Gaiden_Foro_Rol.webp);">' +
        '<div style="position:absolute;bottom:7px;left:6px;right:6px;display:flex;">' +
        '<div style="background-color:orange;width:130px;height:14px;border-radius:10px;display:flex;margin:auto;">' +
        '<span style="font-family:\'moonGetHeavy\';color:white;font-size:8px;margin:auto;text-shadow:1px 1px 0px black;">' + auxOf1Sub0 + '</span>' +
        '<span id="' + of1SubNivel1 + '" style="position:absolute;color:white;font-family:\'moonGetHeavy\';left:109px;top:2px;background-color:#9d57b4;width:20px;border-radius:20px;border:1px solid black;height:9px;font-size:6px;text-align:center;">' + oficio1Obj.sub[subKeys1[0]] + '</span></div>' +
        '<div style="background-color:orange;width:130px;height:14px;border-radius:10px;display:flex;margin:auto;">' +
        '<span style="font-family:\'moonGetHeavy\';color:white;font-size:8px;margin:auto;text-shadow:1px 1px 0px black;">' + auxOf1Sub1 + '</span>' +
        '<span id="' + of1SubNivel2 + '" style="position:absolute;color:white;font-family:\'moonGetHeavy\';left:243px;top:2px;background-color:#9d57b4;width:20px;border-radius:20px;border:1px solid black;height:9px;font-size:6px;text-align:center;">' + oficio1Obj.sub[subKeys1[1]] + '</span></div>' +
        '</div></div></div>'
      );

      if (is_owner && oficio1Obj.nivel < 2) { $(of1NivelId).css('cursor','pointer').on('click', function() { chooseOficio(oficio1, true); }); }
      else { $(of1NivelId).css('cursor','default').css('background-color','#28ce26'); }

      if (is_owner && oficio1Obj.nivel == 2 && (oficio1Obj.sub[firstSub1] > 0 || !(oficio1Obj.sub[secondSub1] >= 1) || has_estudioso || has_erudito)) {
        if (oficio1Obj.sub[firstSub1] < 3) { $(of1SubNivelId1).css('cursor','pointer').on('click', function() { chooseEspeOficio(oficio1, firstSub1); }); }
        else { $(of1SubNivelId1).css('background-color','#25ba23'); }
      } else { $(of1SubNivelId1).css('cursor','default').css('background-color','#8d888f'); }

      if (is_owner && oficio1Obj.nivel == 2 && (oficio1Obj.sub[secondSub1] > 0 || !(oficio1Obj.sub[firstSub1] >= 1) || has_estudioso || has_erudito)) {
        if (oficio1Obj.sub[secondSub1] < 3) { $(of1SubNivelId2).css('cursor','pointer').on('click', function() { chooseEspeOficio(oficio1, secondSub1); }); }
        else { $(of1SubNivelId2).css('background-color','#25ba23'); }
      } else { $(of1SubNivelId2).css('cursor','default').css('background-color','#8d888f'); }
    }

    // Slot libre / bloqueado oficio2
    if (oficio2 === '' && (has_polivalente || has_erudito)) {
      if (is_owner) {
        $('#oficios').append('<div style="width:280px;margin:9px -2px;position:relative;box-sizing:border-box;" onclick="chooseOficioTest()"><img style="cursor:pointer;width:280px;height:130px;" src="/images/op/uploads/FichaSlotLibre_One_Piece_Gaiden_Foro_Rol.webp" /></div>');
      } else {
        $('#oficios').append('<div style="width:280px;margin:9px -2px;position:relative;box-sizing:border-box;"><img style="width:280px;height:130px;" src="/images/op/uploads/FichaSlotLibre_One_Piece_Gaiden_Foro_Rol.webp" /></div>');
      }
    }
    if (oficio2 === '' && !(has_polivalente || has_erudito) && !has_sin_oficio) {
      $('#oficios').append('<div style="width:280px;margin:9px -2px;position:relative;box-sizing:border-box;"><img style="width:280px;height:130px;" src="/images/op/uploads/FichaSlotBloqueado_One_Piece_Gaiden_Foro_Rol.webp" /></div>');
    }

    // 27. Oficio2 render
    if (oficio2 !== '') {
      var auxOficio2 = (oficio2 !== 'Médico') ? oficio2 : 'Medico';
      var oficio2Obj = oficios[oficio2];
      var oficio2Nivel = oficio2Obj.nivel;
      var subKeys2  = Object.keys(oficio2Obj.sub);
      var firstSub2 = subKeys2[0];
      var secondSub2 = subKeys2[1];
      var auxOf2Sub0 = (firstSub2  === 'Recolector') ? 'Contrabandista' : firstSub2;
      var auxOf2Sub1 = (secondSub2 === 'Recolector') ? 'Contrabandista' : secondSub2;
      var of2NivelTag    = oficio2 + 'Nivel2';
      var of2SubNivel1   = firstSub2  + 'Nivel2';
      var of2SubNivel2   = secondSub2 + 'Nivel2';
      var of2NivelId     = '#' + of2NivelTag;
      var of2SubNivelId1 = '#' + of2SubNivel1;
      var of2SubNivelId2 = '#' + of2SubNivel2;

      $('#oficios').append(
        '<div style="width:280px;margin:9px -2px;position:relative;box-sizing:border-box;">' +
        '<div style="position:relative;height:30px;background-color:' + borderColor + ';padding:0 5px;margin:auto;text-align:center;border-radius:15px 15px 0px 0px;border-top:2px solid black;border-left:2px solid black;border-right:2px solid black;">' +
        '<span style="font-family:\'moonGetHeavy\';color:white;font-size:20px;text-shadow:1px 1px 0px black;">' + oficio2 + '</span>' +
        '<span id="' + of2NivelTag + '" style="position:absolute;color:white;font-family:\'moonGetHeavy\';left:232px;top:5px;background-color:#9d57b4;width:40px;border-radius:20px;border:1px solid black;">' + oficio2Nivel + '</span></div>' +
        '<div style="height:100px;background:url(/images/op/uploads/OficioFicha' + auxOficio2 + '_One_Piece_Gaiden_Foro_Rol.webp);">' +
        '<div style="position:absolute;bottom:7px;left:6px;right:6px;display:flex;">' +
        '<div style="background-color:orange;width:130px;height:14px;border-radius:10px;display:flex;margin:auto;">' +
        '<span style="font-family:\'moonGetHeavy\';color:white;font-size:8px;margin:auto;text-shadow:1px 1px 0px black;">' + auxOf2Sub0 + '</span>' +
        '<span id="' + of2SubNivel1 + '" style="position:absolute;color:white;font-family:\'moonGetHeavy\';left:109px;top:2px;background-color:#9d57b4;width:20px;border-radius:20px;border:1px solid black;height:9px;font-size:6px;text-align:center;">' + oficio2Obj.sub[subKeys2[0]] + '</span></div>' +
        '<div style="background-color:orange;width:130px;height:14px;border-radius:10px;display:flex;margin:auto;">' +
        '<span style="font-family:\'moonGetHeavy\';color:white;font-size:8px;margin:auto;text-shadow:1px 1px 0px black;">' + auxOf2Sub1 + '</span>' +
        '<span id="' + of2SubNivel2 + '" style="position:absolute;color:white;font-family:\'moonGetHeavy\';left:243px;top:2px;background-color:#9d57b4;width:20px;border-radius:20px;border:1px solid black;height:9px;font-size:6px;text-align:center;">' + oficio2Obj.sub[subKeys2[1]] + '</span></div>' +
        '</div></div></div>'
      );

      if (is_owner && oficio2Obj.nivel < 2) { $(of2NivelId).css('cursor','pointer').on('click', function() { chooseOficio(oficio2, true); }); }
      else { $(of2NivelId).css('cursor','default').css('background-color','#28ce26'); }

      if (is_owner && oficio2Obj.nivel == 2 && (oficio2Obj.sub[firstSub2] > 0 || !(oficio2Obj.sub[secondSub2] >= 1) || has_estudioso || has_erudito)) {
        if (oficio2Obj.sub[firstSub2] < 3) { $(of2SubNivelId1).css('cursor','pointer').on('click', function() { chooseEspeOficio(oficio2, firstSub2); }); }
        else { $(of2SubNivelId1).css('background-color','#25ba23'); }
      } else { $(of2SubNivelId1).css('cursor','default').css('background-color','#8d888f'); }

      if (is_owner && oficio2Obj.nivel == 2 && (oficio2Obj.sub[secondSub2] > 0 || !(oficio2Obj.sub[firstSub2] >= 1) || has_estudioso || has_erudito)) {
        if (oficio2Obj.sub[secondSub2] < 3) { $(of2SubNivelId2).css('cursor','pointer').on('click', function() { chooseEspeOficio(oficio2, secondSub2); }); }
        else { $(of2SubNivelId2).css('background-color','#25ba23'); }
      } else { $(of2SubNivelId2).css('cursor','default').css('background-color','#8d888f'); }
    }

    // 28. Berries
    $('#berries').text(berries.toLocaleString('es-es'));

    // 29. Modal antiguo (myModal) cerrar con click fuera
    $(w).click(function (event) {
      var myModal = document.getElementById('myModal');
      if (myModal && event.target === myModal) myModal.style.display = 'none';
    });

    // 30. Límite de nivel
    $('#limite_nivel').on('click', function () {
      var nikasCosto = 50;
      if (limite_nivel >= 20) nikasCosto = 5;
      if (limite_nivel >= 25) nikasCosto = 10;
      if (limite_nivel >= 30) nikasCosto = 15;
      if (limite_nivel >= 35) nikasCosto = 20;
      if (limite_nivel >= 40) nikasCosto = 25;
      if (FICHA.PendingQueue.availableNikas() < nikasCosto) { alert('No tienes suficientes nikas para aumentar el límite de nivel. Necesitas ' + nikasCosto + ' nikas.'); return; }
      if (confirm('Aumentar tu límite de nivel tendrá un costo de ' + nikasCosto + ' nikas. ¿Estás de acuerdo?')) {
        FICHA.PendingQueue.addPersonaje({ accion: 'limite_nivel' });
        FICHA.PendingQueue.reserve({ nikas: nikasCosto });
        limite_nivel += 5;
        $('#limite_nivel').text('Nivel Máximo: ' + limite_nivel + '. ¡SUBIR LÍMITE DE NIVEL!');
      }
    });

    // 31. Haki event handlers (solo is_owner)
    // Recalcula canGet/costo/nivelReq desde los globals actuales en cada click,
    // para que el segundo click refleje el tier ya encolado (no el tier inicial).
    function computeHakiVars(hakiName) {
      var val  = hakiName === 'buso' ? buso : hakiName === 'kenbun' ? kenbun : hao;
      var tbl  = hakiName === 'kenbun'
        ? [[1,10,5],[2,20,15],[3,25,20],[4,30,25],[5,35,30],[6,40,35]]
        : [[1,15,10],[2,20,15],[3,25,20],[4,30,25],[5,35,30],[6,40,35]];
      var costs = [hasFullHaki ? 0 : 10, 15, 25, 40, 60, 150];
      var canGet = false, nikasCosto = 10000, nivelHaki = 500;
      for (var i = 0; i < tbl.length; i++) {
        if (val == tbl[i][0]) {
          nivelHaki  = tbl[i][1];
          canGet     = nivel >= tbl[i][1] || (hasFullHaki && nivel >= tbl[i][2]);
          nikasCosto = costs[i];
          break;
        }
      }
      return { canGet: canGet, nikasCosto: nikasCosto, nivelHaki: nivelHaki };
    }

    if (is_owner) {
      $('#kenbun_img').css('cursor', 'pointer').on('click', function () {
        var hv = computeHakiVars('kenbun');
        if (hv.canGet && FICHA.PendingQueue.availableNikas() >= hv.nikasCosto) {
          if (confirm('Aumentar el nivel de tu Kenbunshoku Haki tendrá un costo de ' + hv.nikasCosto + ' nikas. ¿Estás de acuerdo?')) subirHaki('kenbun', hv.nikasCosto);
        } else {
          alert('No tienes nivel o nikas suficiente para entrenar este haki. Para subir Kenbun necesitas ' + hv.nikasCosto + ' nikas y nivel ' + hv.nivelHaki + ' (o 5 niveles menos si tienes Camino de la Voluntad).');
        }
      });
      $('#buso_img').css('cursor', 'pointer').on('click', function () {
        var hv = computeHakiVars('buso');
        if (hv.canGet && FICHA.PendingQueue.availableNikas() >= hv.nikasCosto) {
          if (confirm('Aumentar el nivel de tu Busoshoku Haki tendrá un costo de ' + hv.nikasCosto + ' nikas. ¿Estás de acuerdo?')) subirHaki('buso', hv.nikasCosto);
        } else {
          alert('No tienes nivel o nikas suficiente para entrenar este haki. Para subir Buso necesitas ' + hv.nikasCosto + ' nikas y nivel ' + hv.nivelHaki + ' (o 5 niveles menos si tienes Camino de la Voluntad).');
        }
      });
      $('#hao_img').css('cursor', 'pointer').on('click', function () {
        var hv = computeHakiVars('hao');
        if (hv.canGet && FICHA.PendingQueue.availableNikas() >= hv.nikasCosto) {
          if (confirm('Aumentar el nivel de tu Haoshoku Haki tendrá un costo de ' + hv.nikasCosto + ' nikas. ¿Estás de acuerdo?')) subirHaki('hao', hv.nikasCosto);
        } else {
          alert('No tienes nivel o nikas suficiente para entrenar este haki. Para subir Hao necesitas ' + hv.nikasCosto + ' nikas y nivel ' + hv.nivelHaki + ' (o 5 niveles menos si tienes Camino de la Voluntad).');
        }
      });
    }

    // 32. Disciplinas: handlers para las no desbloqueadas
    var disciplinas = ['Escudero','Artista Marcial','Combatiente','Artista','Asesino','Guerrero','Espadachín','Tecnicista','Artillero','Arquero','Tirador','Pícaro'];
    disciplinas.forEach(function (disc) {
      var discUnder = disc.replace(/ /g, '_');
      var isUnlocked = belicasArray.some(function (b) {
        return b === disc || b === discUnder || b === disc.replace(/ /g, '_') || b === disc.replace(/_/g, ' ');
      });
      if (!isUnlocked) {
        $('#' + discUnder + '_nivel_body').on('click', function () { chooseBelica(disc, false); });
      }
    });

    // 33. insertarDisciplinaTecs (24 pares)
    insertarDisciplinaTecs('Escudero',       'Vanguardia');
    insertarDisciplinaTecs('Escudero',       'Bastión');
    insertarDisciplinaTecs('Artista Marcial','Acróbata');
    insertarDisciplinaTecs('Artista Marcial','Monje');
    insertarDisciplinaTecs('Combatiente',    'Berserker');
    insertarDisciplinaTecs('Combatiente',    'Campeón');
    insertarDisciplinaTecs('Artista',        'Bardo');
    insertarDisciplinaTecs('Artista',        'Trovador');
    insertarDisciplinaTecs('Asesino',        'Sombra');
    insertarDisciplinaTecs('Asesino',        'Verdugo');
    insertarDisciplinaTecs('Guerrero',       'Castigador');
    insertarDisciplinaTecs('Guerrero',       'Warhammer');
    insertarDisciplinaTecs('Espadachín',     'Samurái');
    insertarDisciplinaTecs('Espadachín',     'Mosquetero');
    insertarDisciplinaTecs('Tecnicista',     'Diletante');
    insertarDisciplinaTecs('Tecnicista',     'WeaponMaster');
    insertarDisciplinaTecs('Artillero',      'Destructor');
    insertarDisciplinaTecs('Artillero',      'Juggernaut');
    insertarDisciplinaTecs('Arquero',        'Ballestero');
    insertarDisciplinaTecs('Arquero',        'Cazador');
    insertarDisciplinaTecs('Tirador',        'Duelista');
    insertarDisciplinaTecs('Tirador',        'Francotirador');
    insertarDisciplinaTecs('Pícaro',         'Gambito');
    insertarDisciplinaTecs('Pícaro',         'Trickster');

    // 34. Merge Haki tecs
    if (tec_aprendidas_json['Haoshoku'] || tec_aprendidas_json['Kenbunshoku'] || tec_aprendidas_json['Busoshoku']) {
      tec_aprendidas_json['Haki'] = [];
    }
    ['Haoshoku','Kenbunshoku','Busoshoku'].forEach(function (hk) {
      if (tec_aprendidas_json[hk]) {
        for (var hi = 0; hi < tec_aprendidas_json[hk].length; hi++) {
          tec_aprendidas_json['Haki'].push(tec_aprendidas_json[hk][hi]);
        }
      }
    });

  }); // end $(document).ready

})(window.FICHA, window);
