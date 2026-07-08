/**
 * ficha/portada.js — NPCs, historial, Akuma modal, Wanted, Peso
 * Requiere: core.js
 */
(function (FICHA, w) {

  // ── Event listeners unificados ────────────────────────────────

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeHistorialModal();
      if (typeof closeEstilosModal === 'function')  closeEstilosModal();
      if (typeof closeWantedModal  === 'function')  closeWantedModal();
    }
  });

  window.addEventListener('click', function (event) {
    var wm = document.getElementById('wantedModal');
    if (wm && event.target === wm) closeWantedModal();
  });

  // ── NPCs acompañantes ─────────────────────────────────────────

  function loadNpcsAcompanantesBio() {
    $('#npcs_acompanantes').html('');
    if (typeof npcs_array_json !== 'undefined' && npcs_array_json.length > 0) {
      for (var i = 0; i < npcs_array_json.length; i++) {
        var npcs   = npcs_json[npcs_array_json[i]][0];
        var key    = npcs_array_json[i];
        var keyNum = FICHA.getNumericPrefix(key);
        if (keyNum !== String(query_uid)) continue;
        $('#npcs_acompanantes').append(`<a href="/op/compas.php?npc_id=${npcs.npc_id}" target="_blank"><div class="npcBox npcItemBio" style="position:relative;background-size:cover;background:url(${npcs.avatar1});border:3px solid #8062d6;margin-right:10px;display:inline-block;"><div class="clipBox" style="background-color:#8062d6;"></div><div style="text-align:center;transform:rotate(349deg);"><span class="npcName">${npcs.nombre}</span></div></div></a>`);
      }
    }
    for (var j = 0; j < mascotas_array_json.length; j++) {
      var mascotas = mascotas_json[mascotas_array_json[j]][0];
      var mkey     = mascotas_array_json[j];
      var mkeyNum  = FICHA.getNumericPrefix(mkey);
      if (mkeyNum !== String(query_uid)) continue;
      $('#npcs_acompanantes').append(`<a href="/op/compas.php?npc_id=${mascotas.npc_id}" target="_blank"><div class="npcBox npcItemBio" style="position:relative;background-size:cover;background:url(${mascotas.avatar1});border:3px solid #ff5900;margin-right:10px;display:inline-block;"><div class="clipBox" style="background-color:#ff5900;"></div><div style="text-align:center;transform:rotate(349deg);"><span class="npcName">${mascotas.nombre}</span></div></div></a>`);
    }
    if ((typeof npcs_array_json === 'undefined' || npcs_array_json.length === 0) &&
        (typeof mascotas_array_json === 'undefined' || mascotas_array_json.length === 0)) {
      $('#npcs_acompanantes').html('<div style="width:100%;text-align:center;font-family:InterRegular;margin-top:100px;">No tienes mascotas ni acompañantes</div>');
    }
  }

  function closeModalBio() {
    var modal = document.getElementById('npcModalBio');
    if (modal) modal.style.display = 'none';
  }

  // ── Peso / FUE ────────────────────────────────────────────────

  function alzarPeso(kg) {
    if (kg >= 0     && kg <= 5)      return 1;
    if (kg > 5      && kg <= 50)     return 5;
    if (kg > 50     && kg <= 100)    return 10;
    if (kg > 100    && kg <= 250)    return 20;
    if (kg > 250    && kg <= 500)    return 30;
    if (kg > 500    && kg <= 1000)   return 50;
    if (kg > 1000   && kg <= 5000)   return 75;
    if (kg > 5000   && kg <= 10000)  return 100;
    if (kg > 10000  && kg <= 20000)  return 125;
    if (kg > 20000  && kg <= 30000)  return 150;
    if (kg > 30000  && kg <= 50000)  return 175;
    if (kg > 50000  && kg <= 100000) return 200;
  }

  // ── Historial ─────────────────────────────────────────────────

  function openHistorialModal() {
    var modalId = 'historialModal';
    var modal   = document.getElementById(modalId);
    if (!modal) {
      $('body').append('<div id="' + modalId + '" class="modal"></div>');
      modal = document.getElementById(modalId);
    }

    var mc = '<div class="modal-content historial-modal-content">';
    mc += '<span class="close" onclick="closeHistorialModal()" style="position:absolute;right:20px;top:10px;font-size:28px;cursor:pointer;z-index:10001;color:#333;">&times;</span>';
    mc += '<div class="historial-header"><h2>HISTORIAL DE TRANSACCIONES</h2></div>';
    mc += '<div class="historial-grid" style="grid-template-columns:repeat(5,1fr);">';

    mc += '<div class="historial-column"><div class="historial-column-header kuros-header"><div style="display:flex;align-items:center;justify-content:center;gap:10px;"><img style="height:24px;" src="/images/op/uploads/KuroPoint_One_Piece_Gaiden_Foro_Rol.png">KUROS</div></div><div class="historial-column-content" id="historial-kuros">' + cargarHistorialKuros() + '</div></div>';
    mc += '<div class="historial-column"><div class="historial-column-header experiencia-header"><div style="display:flex;align-items:center;justify-content:center;gap:10px;"><img style="height:24px;" src="/images/op/ficha/SolNivel_One_Piece_Gaiden_Foro_Rol.png">EXPERIENCIA</div></div><div class="historial-column-content" id="historial-experiencia">' + cargarHistorialExperiencia() + '</div></div>';
    mc += '<div class="historial-column"><div class="historial-column-header nikas-header"><div style="display:flex;align-items:center;justify-content:center;gap:10px;"><img style="height:24px;" src="/images/op/indice/SolMenu_One_Piece_Gaiden_Foro_Rol.png">NIKAS</div></div><div class="historial-column-content" id="historial-nikas">' + cargarHistorialNikas() + '</div></div>';
    mc += '<div class="historial-column"><div class="historial-column-header berries-header"><div style="display:flex;align-items:center;justify-content:center;gap:10px;"><img style="height:24px;" src="https://64.media.tumblr.com/ef457fd69a4ae7fdfb7ce6d2daa27de1/tumblr_nkz8u0C2UL1up54j8o1_1280.png">BERRIES</div></div><div class="historial-column-content" id="historial-berries">' + cargarHistorialBerries() + '</div></div>';
    mc += '<div class="historial-column"><div class="historial-column-header puntos-oficio-header"><div style="display:flex;align-items:center;justify-content:center;gap:10px;"><img style="height:28px;" src="https://static.thenounproject.com/png/5421605-200.png">PUNTOS OFICIO</div></div><div class="historial-column-content" id="historial-puntos-oficio">' + cargarHistorialPuntosOficio() + '</div></div>';

    mc += '</div></div>';
    modal.innerHTML = mc;
    modal.style.display = 'block';
    modal.onclick = function (e) { if (e.target == modal) modal.style.display = 'none'; };
  }

  function extractDate(dateTimeStr) {
    if (!dateTimeStr) return '';
    return dateTimeStr.split(' ')[0];
  }

  function extractKuros(input) {
    var m = input.match(/Kuros:\s*(-?\d+)->(-?\d+)\s*\((-?\d+)\)/);
    return { kuros: m ? parseInt(m[3]) : 0, kurosBefore: m ? parseInt(m[1]) : 0, kurosAfter: m ? parseInt(m[2]) : 0 };
  }

  function extractKurosPost(input) {
    var m = input.match(/([\d.]+) Kuros \(([\d.]+) \+ [\d.]+ = ([\d.]+)\)/);
    return { kuros: m ? parseFloat(m[1]) : 0, kurosBefore: m ? parseFloat(m[2]) : 0, kurosAfter: m ? parseFloat(m[3]) : 0 };
  }

  function extractExperiencia(input) {
    var m = input.match(/Experiencia:\s*(-?\d+(?:\.\d+)?)->(-?\d+(?:\.\d+)?)\s*\((-?\d+)\)/);
    return { experiencia: m ? parseInt(m[3]) : 0, experienciaBefore: m ? parseInt(m[1]) : 0, experienciaAfter: m ? parseInt(m[2]) : 0 };
  }

  function extractExperienciaPost(input) {
    var m = input.match(/(-?\d+(?:\.\d+)?) de Experiencia \((\d+(?:\.\d+)?) \+ (-?\d+(?:\.\d+)?) = (\d+(?:\.\d+)?)\)/);
    if (!m) return { experiencia: 0, experienciaBefore: 0, experienciaAfter: 0 };
    return { experiencia: parseFloat(m[3]), experienciaBefore: parseFloat(m[2]), experienciaAfter: parseFloat(m[4]) };
  }

  function extractNikas(input) {
    var m = input.match(/Nikas:\s*(-?\d+)->(-?\d+)\s*\((-?\d+)\)/);
    return { nikas: m ? parseInt(m[3]) : 0, before: m ? parseInt(m[1]) : 0, after: m ? parseInt(m[2]) : 0 };
  }

  function extractBerries(input) {
    var m = input.match(/Berries:\s*(-?\d+)->(-?\d+)\s*\((-?\d+)\)/);
    return { berries: m ? parseInt(m[3]) : 0, before: m ? parseInt(m[1]) : 0, after: m ? parseInt(m[2]) : 0 };
  }

  function extractPuntosOficio(input) {
    var m = input.match(/Puntos de oficio:\s*(-?\d+)->(-?\d+)\s*\((-?\d+)\)/);
    return { puntos_oficio: m ? parseInt(m[3]) : 0, before: m ? parseInt(m[1]) : 0, after: m ? parseInt(m[2]) : 0 };
  }

  function parseHistorialKuros(input, categoria) {
    if (categoria == '[Post]') {
      var threadMatch = input.match(/\[\s*(\d*)\s*\|\|\|\s*(.*?)\s*\]/);
      var thread = null, title = '';
      if (threadMatch) {
        if (threadMatch[1]) thread = parseInt(threadMatch[1], 10);
        if (threadMatch[2]) title = threadMatch[2].trim();
      }
      var descripcion = (threadMatch && threadMatch[0] === '[ ||| ]')
        ? 'Creación de tema'
        : `Post en tema <a target="_blank" href="/showthread.php?tid=${thread}">${title}</a>.`;
      return Object.assign({ descripcion: descripcion }, extractKurosPost(input));
    }
    return Object.assign({ descripcion: categoria }, extractKuros(input));
  }

  function parseHistorialExperiencia(input, categoria) {
    if (categoria == '[Post]') {
      var threadMatch = input.match(/\[\s*(\d*)\s*\|\|\|\s*(.*?)\s*\]/);
      var thread = null, title = '';
      if (threadMatch) {
        if (threadMatch[1]) thread = parseInt(threadMatch[1], 10);
        if (threadMatch[2]) title = threadMatch[2].trim();
      }
      var descripcion = (threadMatch && threadMatch[0] === '[ ||| ]')
        ? 'Creación de tema'
        : `Post en tema <a target="_blank" href="/showthread.php?tid=${thread}">${title}</a>.`;
      return Object.assign({ descripcion: descripcion }, extractExperienciaPost(input));
    }
    if (categoria == '[Modificación de experiencia]') return Object.assign({ descripcion: 'Modificación de atributos' }, extractExperiencia(input));
    return Object.assign({ descripcion: categoria }, extractExperiencia(input));
  }

  function parseHistorialNikas(input, categoria)        { return Object.assign({ descripcion: categoria }, extractNikas(input)); }
  function parseHistorialBerries(input, categoria)      { return Object.assign({ descripcion: categoria }, extractBerries(input)); }
  function parseHistorialPuntosOficio(input, categoria) { return Object.assign({ descripcion: categoria }, extractPuntosOficio(input)); }

  function cargarHistorialKuros() {
    var historial = [];
    for (var i = 0; i < historial_kuro.length; i++) {
      var h = parseHistorialKuros(historial_kuro[i].log, historial_kuro[i].categoria);
      if (h.kuros == 0) continue;
      historial.push(Object.assign({ fecha: historial_kuro[i].tiempo }, h));
    }
    var html = historial.map(function (item) {
      var cls = item.kuros > 0 ? 'cantidad-positiva' : 'cantidad-negativa';
      var sig = item.kuros > 0 ? '+' : '';
      return `<div class="historial-item kuros-item"><div class="historial-item-fecha">${extractDate(item.fecha)}</div><div class="historial-item-descripcion">${item.descripcion}</div><div class="historial-item-cantidad ${cls}">${sig}${item.kuros.toFixed(2)} Kuros</div><div class="historial-item-cantidad">${item.kurosBefore} + ${item.kuros.toFixed(2)} = ${item.kurosAfter}</div></div>`;
    }).join('');
    return html || '<div style="text-align:center;color:#666;margin-top:50px;">Sin transacciones</div>';
  }

  function cargarHistorialExperiencia() {
    var historial = [];
    for (var i = 0; i < historial_experiencia.length; i++) {
      var h = parseHistorialExperiencia(historial_experiencia[i].log, historial_experiencia[i].categoria);
      if (h.experiencia == 0) continue;
      historial.push(Object.assign({ fecha: historial_experiencia[i].tiempo }, h));
    }
    var html = historial.map(function (item) {
      var cls = item.experiencia > 0 ? 'cantidad-positiva' : 'cantidad-negativa';
      var sig = item.experiencia > 0 ? '+' : '';
      return `<div class="historial-item kuros-item"><div class="historial-item-fecha">${extractDate(item.fecha)}</div><div class="historial-item-descripcion">${item.descripcion}</div><div class="historial-item-cantidad ${cls}">${sig}${item.experiencia.toFixed(2)} EXP</div><div class="historial-item-cantidad">${item.experienciaBefore} + ${item.experiencia.toFixed(2)} = ${item.experienciaAfter}</div></div>`;
    }).join('');
    return html || '<div style="text-align:center;color:#666;margin-top:50px;">Sin transacciones</div>';
  }

  function cargarHistorialNikas() {
    var historial = [];
    for (var i = 0; i < historial_nikas.length; i++) {
      var h = parseHistorialNikas(historial_nikas[i].log, historial_nikas[i].categoria);
      if (h.nikas == 0) continue;
      historial.push(Object.assign({ fecha: historial_nikas[i].tiempo }, h));
    }
    var html = historial.map(function (item) {
      var cls = item.nikas > 0 ? 'cantidad-positiva' : 'cantidad-negativa';
      var sig = item.nikas > 0 ? '+' : '';
      return `<div class="historial-item nikas-item"><div class="historial-item-fecha">${extractDate(item.fecha)}</div><div class="historial-item-descripcion">${item.descripcion}</div><div class="historial-item-cantidad ${cls}">${sig}${item.nikas.toFixed(2)} Nikas</div><div class="historial-item-cantidad">${item.before} + ${item.nikas.toFixed(2)} = ${item.after}</div></div>`;
    }).join('');
    return html || '<div style="text-align:center;color:#666;margin-top:50px;">Sin transacciones</div>';
  }

  function cargarHistorialBerries() {
    var historial = [];
    for (var i = 0; i < historial_berries.length; i++) {
      var h = parseHistorialBerries(historial_berries[i].log, historial_berries[i].categoria);
      if (h.berries == 0) continue;
      historial.push(Object.assign({ fecha: historial_berries[i].tiempo }, h));
    }
    var html = historial.map(function (item) {
      var cls = item.berries > 0 ? 'cantidad-positiva' : 'cantidad-negativa';
      var sig = item.berries > 0 ? '+' : '';
      return `<div class="historial-item berries-item"><div class="historial-item-fecha">${extractDate(item.fecha)}</div><div class="historial-item-descripcion">${item.descripcion}</div><div class="historial-item-cantidad ${cls}">${sig}${item.berries.toFixed(0)} Berries</div><div class="historial-item-cantidad">${item.before} + ${item.berries.toFixed(0)} = ${item.after}</div></div>`;
    }).join('');
    return html || '<div style="text-align:center;color:#666;margin-top:50px;">Sin transacciones</div>';
  }

  function cargarHistorialPuntosOficio() {
    var historial = [];
    for (var i = 0; i < historial_puntos_oficio.length; i++) {
      var h = parseHistorialPuntosOficio(historial_puntos_oficio[i].log, historial_puntos_oficio[i].categoria);
      if (h.puntos_oficio == 0) continue;
      historial.push(Object.assign({ fecha: historial_puntos_oficio[i].tiempo }, h));
    }
    var html = historial.map(function (item) {
      var cls = item.puntos_oficio > 0 ? 'cantidad-positiva' : 'cantidad-negativa';
      var sig = item.puntos_oficio > 0 ? '+' : '';
      return `<div class="historial-item puntos-oficio-item"><div class="historial-item-fecha">${extractDate(item.fecha)}</div><div class="historial-item-descripcion">${item.descripcion}</div><div class="historial-item-cantidad ${cls}">${sig}${item.puntos_oficio.toFixed(2)} Puntos de Oficio</div><div class="historial-item-cantidad">${item.before} + ${item.puntos_oficio.toFixed(2)} = ${item.after}</div></div>`;
    }).join('');
    return html || '<div style="text-align:center;color:#666;margin-top:50px;">Sin transacciones</div>';
  }

  function closeHistorialModal() {
    var modal = document.getElementById('historialModal');
    if (modal) modal.style.display = 'none';
  }

  // ── Akuma modal ───────────────────────────────────────────────

  function openAkumaModal() {
    if (!hasAkuma || !akumaNombre || akumaNombre === '') return;
    var modal = document.getElementById('akumaModal');
    if (!modal) {
      $('body').append('<div id="akumaModal" class="modal"></div>');
      modal = document.getElementById('akumaModal');
    }

    var frutaGradient = '';
    if (akumaCategoria == 'Paramecia') frutaGradient = 'linear-gradient(90deg, rgba(0,116,143,0) 0%, #ef3c3c 20%, #810b0b 50%, #ef3c3c 80%, rgba(0,116,143,0) 100%)';
    else if (akumaCategoria == 'Logia') frutaGradient = 'linear-gradient(90deg, rgba(0,116,143,0) 0%, #4CAF50 20%, #2E7D32 50%, #4CAF50 80%, rgba(0,116,143,0) 100%)';
    else if (akumaCategoria == 'Zoan')  frutaGradient = 'linear-gradient(90deg, rgba(0,116,143,0) 0%, #FF9800 20%, #E65100 50%, #FF9800 80%, rgba(0,116,143,0) 100%)';

    function getControlAkumaInfo(nv) {
      var rangos = (fichaCamino !== 'Akuma')
        ? [{ nombre: 'Dominio Básico', costo: 0 }, { nombre: 'Pasiva 1', costo: 5 }, { nombre: 'Dominio Intermedio', costo: 10 }, { nombre: 'Pasiva 2', costo: 20 }, { nombre: 'Dominio Maestro', costo: 25 }, { nombre: 'Pasiva 3', costo: 40 }]
        : [{ nombre: 'Dominio Básico', costo: 0 }, { nombre: 'Pasiva 1', costo: 0 }, { nombre: 'Dominio Intermedio', costo: 5 }, { nombre: 'Pasiva 2', costo: 15 }, { nombre: 'Dominio Maestro', costo: 20 }, { nombre: 'Pasiva 3', costo: 30 }, { nombre: 'Despertado', costo: 80 }];
      return rangos[nv] || rangos[0];
    }

    var controlAkumaActual = parseInt(control_akuma) || 0;
    var rangoActual   = getControlAkumaInfo(controlAkumaActual);
    var siguienteRango = getControlAkumaInfo(controlAkumaActual + 1);

    var nivelesDesbloqueados = '';
    for (var i = 0; i <= controlAkumaActual; i++) {
      var ri = getControlAkumaInfo(i);
      var esActual = (i === controlAkumaActual);
      nivelesDesbloqueados += `<div class="control-nivel-desbloqueado" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;padding:6px 10px;background:${esActual ? 'linear-gradient(90deg,#4CAF50 0%,#45a049 100%)' : 'linear-gradient(90deg,#81C784 0%,#66BB6A 100%)'};border-radius:5px;color:white;border-left:4px solid ${esActual ? '#2E7D32' : '#4CAF50'};"><div style="display:flex;align-items:center;"><span style="font-family:'moonGetHeavy';font-size:${esActual ? '16px' : '14px'};margin-right:10px;text-shadow:1px 1px 2px black;">${ri.nombre}</span>${esActual ? '<span style="background:rgba(255,255,255,0.3);padding:2px 8px;border-radius:12px;font-family:\'InterRegular\';font-size:10px;font-weight:bold;">ACTUAL</span>' : ''}</div></div>`;
    }

    var siguienteNivelHtml = '';
    if (controlAkumaActual < 6 && is_owner) {
      var puedeDesbloquear = nikas >= siguienteRango.costo;
      siguienteNivelHtml = `<div class="control-siguiente" style="display:flex;justify-content:space-between;align-items:center;padding:8px;background:linear-gradient(90deg,#2196F3 0%,#1976D2 100%);border-radius:5px;color:white;"><div style="display:flex;flex-direction:column;"><span style="font-family:'moonGetHeavy';font-size:12px;">Siguiente Nivel:</span><span style="font-family:'moonGetHeavy';font-size:14px;text-shadow:1px 1px 2px black;">${siguienteRango.nombre}</span></div><div style="display:flex;flex-direction:column;align-items:center;"><span style="font-family:'moonGetHeavy';font-size:10px;margin-bottom:5px;">Costo: ${siguienteRango.costo} Nikas</span>${(is_owner || g_is_staff) ? `<button onclick="desbloquearControlAkuma()" style="background-color:${puedeDesbloquear ? '#FF9800' : '#666666'};color:white;border:none;border-radius:3px;padding:5px 10px;font-family:'moonGetHeavy';font-size:10px;cursor:${puedeDesbloquear ? 'pointer' : 'not-allowed'};transition:all 0.3s;" ${puedeDesbloquear ? '' : 'disabled'}>DESBLOQUEAR</button>` : ''}</div></div>`;
    } else if (controlAkumaActual == 6) {
      siguienteNivelHtml = `<div class="control-maximo" style="text-align:center;padding:15px;background:linear-gradient(90deg,#9C27B0 0%,#7B1FA2 100%);border-radius:50px;color:white;"><span style="font-family:'moonGetHeavy';font-size:16px;text-shadow:1px 1px 2px black;">¡NIVEL MÁXIMO ALCANZADO!</span><div style="font-family:'InterRegular';font-size:12px;margin-top:5px;opacity:0.9;">Has dominado completamente tu Akuma no Mi</div></div>`;
    }

    var dominiosHtml = (akumaDominios && akumaDominios !== '') ? `<div class="akuma-descripcion-background-libro" style="background:${frutaGradient};margin-top:15px;"><div class="akuma-descripcion-titulo-libro">Dominios</div></div><div class="akuma-descripcion-libro">${akumaDominios}</div>` : '';
    var pasivasHtml  = (akumaPasivas  && akumaPasivas  !== '') ? `<div class="akuma-descripcion-background-libro" style="background:${frutaGradient};margin-top:15px;"><div class="akuma-descripcion-titulo-libro">Pasivas</div></div><div class="akuma-descripcion-libro">${akumaPasivas}</div>` : '';

    modal.innerHTML = `<div class="modal-content akuma-libro-modal"><span class="close" onclick="closeAkumaModal()" style="color:white;float:right;font-size:28px;font-weight:bold;position:absolute;right:20px;top:10px;z-index:1001;cursor:pointer;">&times;</span><div class="modal-body akuma-libro-body"><div class="akuma-libro-left"><div class="akuma-id-badge">ID: ${(akumaNombre ? akumaNombre.substring(0,3).toUpperCase() : 'N/A')}</div><div class="akuma-nombre-libro">${akumaNombre}</div><div class="akuma-subnombre-libro">${akumaSubnombre || ''}</div><div class="akuma-tipo-tier-libro" style="background:${frutaGradient};"><div class="akuma-tipo-tier-text"><span class="akuma-tipo">${akumaCategoria}</span> | Tier <span class="akuma-tier">${akumaTier}</span></div></div><div class="akuma-imagen-libro-container"><img class="akuma-imagen-libro" src="${akumaImagen}" alt="${akumaNombre}"></div></div><div class="akuma-libro-right"><div id="npc_info_akuma" class="npc-info-akuma" style="margin-top:-140px;"></div><div class="akuma-descripcion-background-libro" style="background:${frutaGradient};"><div class="akuma-descripcion-titulo-libro">Descripción</div></div><div class="akuma-descripcion-libro" style="max-height:107px;">${akumaDescripcion || 'Sin descripción disponible'}</div><div class="akuma-descripcion-background-libro" style="background:${frutaGradient};margin-top:0px;"><div class="akuma-descripcion-titulo-libro">Control de Akuma</div></div><div class="akuma-control-container" style="padding:0px 70px;border-radius:5px;margin-top:5px;">${nivelesDesbloqueados}${siguienteNivelHtml}</div>${dominiosHtml}${pasivasHtml}</div></div></div>`;
    modal.style.display = 'block';
    modal.onclick = function (e) { if (e.target === modal) modal.style.display = 'none'; };
  }

  function desbloquearControlAkuma() {
    var controlAkumaActual = parseInt(control_akuma) || 0;
    var costos = [], rangos = [], rangosDisponibles = [], nivelNecesarioSiguiente;

    if (fichaCamino === 'Voz') {
      costos = [0, 5, 10, 20, 25, 40];
      rangos = ['Dominio Básico', 'Pasiva 1', 'Dominio Intermedio', 'Pasiva 2', 'Dominio Maestro', 'Pasiva 3'];
    } else {
      costos = [0, 0, 5, 15, 20, 30, 80];
      rangos = ['Dominio Básico', 'Pasiva 1', 'Dominio Intermedio', 'Pasiva 2', 'Dominio Maestro', 'Pasiva 3', 'Despertado'];
    }

    if (controlAkumaActual >= 6) { alert('Ya has alcanzado el nivel máximo de control de Akuma.'); return; }

    var siguienteNivel = controlAkumaActual + 1;
    var costoRequerido = costos[siguienteNivel];
    var nombreSiguienteNivel = rangos[siguienteNivel];

    if (akumaTier > 1) {
      var SCase = Number(akumaTier);
      switch (SCase) {
        case 1:
          if (nivelUsuario < 6)  { rangosDisponibles = ['Dominio Básico']; nivelNecesarioSiguiente = 6; }
          else if (nivelUsuario < 10) { rangosDisponibles = ['Dominio Básico','Pasiva 1']; nivelNecesarioSiguiente = 10; }
          else if (nivelUsuario < 15) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio']; nivelNecesarioSiguiente = 15; }
          else if (nivelUsuario < 18) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2']; nivelNecesarioSiguiente = 18; }
          else if (nivelUsuario < 21) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro']; nivelNecesarioSiguiente = 21; }
          else if (nivelUsuario < 35) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro','Pasiva 3']; nivelNecesarioSiguiente = 35; }
          else { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro','Pasiva 3','Despertado']; }
          break;
        case 2:
          if (nivelUsuario < 6)  { rangosDisponibles = ['Dominio Básico']; nivelNecesarioSiguiente = 6; }
          else if (nivelUsuario < 11) { rangosDisponibles = ['Dominio Básico','Pasiva 1']; nivelNecesarioSiguiente = 11; }
          else if (nivelUsuario < 16) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio']; nivelNecesarioSiguiente = 16; }
          else if (nivelUsuario < 19) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2']; nivelNecesarioSiguiente = 19; }
          else if (nivelUsuario < 22) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro']; nivelNecesarioSiguiente = 22; }
          else if (nivelUsuario < 35) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro','Pasiva 3']; nivelNecesarioSiguiente = 35; }
          else { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro','Pasiva 3','Despertado']; }
          break;
        case 3:
          if (nivelUsuario < 7)  { rangosDisponibles = ['Dominio Básico']; nivelNecesarioSiguiente = 7; }
          else if (nivelUsuario < 12) { rangosDisponibles = ['Dominio Básico','Pasiva 1']; nivelNecesarioSiguiente = 12; }
          else if (nivelUsuario < 17) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio']; nivelNecesarioSiguiente = 17; }
          else if (nivelUsuario < 21) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2']; nivelNecesarioSiguiente = 21; }
          else if (nivelUsuario < 25) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro']; nivelNecesarioSiguiente = 25; }
          else if (nivelUsuario < 35) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro','Pasiva 3']; nivelNecesarioSiguiente = 35; }
          else { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro','Pasiva 3','Despertado']; }
          break;
        case 4:
          if (nivelUsuario < 7)  { rangosDisponibles = ['Dominio Básico']; nivelNecesarioSiguiente = 7; }
          else if (nivelUsuario < 13) { rangosDisponibles = ['Dominio Básico','Pasiva 1']; nivelNecesarioSiguiente = 13; }
          else if (nivelUsuario < 18) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio']; nivelNecesarioSiguiente = 18; }
          else if (nivelUsuario < 21) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2']; nivelNecesarioSiguiente = 21; }
          else if (nivelUsuario < 26) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro']; nivelNecesarioSiguiente = 26; }
          else if (nivelUsuario < 35) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro','Pasiva 3']; nivelNecesarioSiguiente = 35; }
          else { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro','Pasiva 3','Despertado']; }
          break;
        case 5:
          if (nivelUsuario < 8)  { rangosDisponibles = ['Dominio Básico']; nivelNecesarioSiguiente = 8; }
          else if (nivelUsuario < 14) { rangosDisponibles = ['Dominio Básico','Pasiva 1']; nivelNecesarioSiguiente = 14; }
          else if (nivelUsuario < 20) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio']; nivelNecesarioSiguiente = 20; }
          else if (nivelUsuario < 25) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2']; nivelNecesarioSiguiente = 25; }
          else if (nivelUsuario < 29) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro']; nivelNecesarioSiguiente = 29; }
          else if (nivelUsuario < 35) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro','Pasiva 3']; nivelNecesarioSiguiente = 35; }
          else { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro','Pasiva 3','Despertado']; }
          break;
        case 6:
          if (nivelUsuario < 8)  { rangosDisponibles = ['Dominio Básico']; nivelNecesarioSiguiente = 8; }
          else if (nivelUsuario < 15) { rangosDisponibles = ['Dominio Básico','Pasiva 1']; nivelNecesarioSiguiente = 15; }
          else if (nivelUsuario < 21) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio']; nivelNecesarioSiguiente = 21; }
          else if (nivelUsuario < 26) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2']; nivelNecesarioSiguiente = 26; }
          else if (nivelUsuario < 30) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro']; nivelNecesarioSiguiente = 30; }
          else if (nivelUsuario < 35) { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro','Pasiva 3']; nivelNecesarioSiguiente = 35; }
          else { rangosDisponibles = ['Dominio Básico','Pasiva 1','Dominio Intermedio','Pasiva 2','Dominio Maestro','Pasiva 3','Despertado']; }
          break;
      }
    }

    if (!rangosDisponibles.includes(nombreSiguienteNivel)) {
      alert('No tienes suficiente nivel. Necesitas mínimo nivel ' + nivelNecesarioSiguiente + '.');
      return;
    }
    if (nikas < costoRequerido) {
      alert('No tienes suficientes Nikas. Necesitas ' + costoRequerido + ' Nikas para desbloquear ' + nombreSiguienteNivel + '.');
      return;
    }
    if (confirm('¿Deseas desbloquear "' + nombreSiguienteNivel + '" por ' + costoRequerido + ' Nikas?')) {
      FICHA.PendingQueue.addPersonaje({ accion: 'control_akuma' });
      closeAkumaModal();
    }
  }

  function closeAkumaModal() {
    var modal = document.getElementById('akumaModal');
    if (modal) modal.style.display = 'none';
  }

  // ── Wanted modal ──────────────────────────────────────────────

  function openWantedModal() {
    var modal = document.getElementById('wantedModal');
    if (modal) {
      modal.style.display = 'block';
      var berriesEl = document.getElementById('berriesWanted');
      if (berriesEl) {
        var rep = parseInt(berriesEl.textContent);
        if (!isNaN(rep)) berriesEl.textContent = rep.toLocaleString('es-ES');
      }
    }
  }

  function closeWantedModal() {
    var modal = document.getElementById('wantedModal');
    if (modal) modal.style.display = 'none';
  }

  // ── Exposición global ─────────────────────────────────────────

  w.loadNpcsAcompanantesBio  = loadNpcsAcompanantesBio;
  w.closeModalBio            = closeModalBio;
  w.alzarPeso                = alzarPeso;
  w.openHistorialModal       = openHistorialModal;
  w.closeHistorialModal      = closeHistorialModal;
  w.openAkumaModal           = openAkumaModal;
  w.desbloquearControlAkuma  = desbloquearControlAkuma;
  w.closeAkumaModal          = closeAkumaModal;
  w.openWantedModal          = openWantedModal;
  w.closeWantedModal         = closeWantedModal;

})(window.FICHA, window);
