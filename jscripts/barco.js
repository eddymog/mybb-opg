/**
 * jscripts/barco.js — Lógica de la página de barco (cofre + salas)
 * BC (objeto de datos) es inyectado por el template antes de cargar este archivo.
 */
(function () {

  /* ── Utilidades ─────────────────────────────────────────────── */
  function esc(str) {
    return String(str || '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function formatBerries(n) {
    return Number(n).toLocaleString('es-ES') + ' &#x1F4B0;';
  }

  function ajax(url, data, cb) {
    var params = [];
    Object.keys(data).forEach(function (k) {
      params.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
    });
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function () {
      try { cb(JSON.parse(xhr.responseText)); }
      catch (e) { cb({ error: 'Error de respuesta del servidor' }); }
    };
    xhr.onerror = function () { cb({ error: 'Error de red' }); };
    xhr.send(params.join('&'));
  }

  /* ── Tab switching ───────────────────────────────────────────── */
  var _tabPanels = ['cofre', 'salas', 'trip', 'viajes', 'historial'];
  var _ausentes   = {};   // keys: 'owner' | 'trip_<id>' | 'npc_<id>'

  // Inicializar desde datos cargados en BD
  (function initAusentes() {
    if (typeof BC === 'undefined') return;
    if (BC.ownerAusente) { _ausentes['owner'] = true; }
    if (BC.tripulacion) { BC.tripulacion.forEach(function (m) { if (m.ausente) { _ausentes['trip_' + m.id] = true; } }); }
    if (BC.npcs)        { BC.npcs.forEach(function (n)        { if (n.ausente) { _ausentes['npc_'  + n.id] = true; } }); }
  }());

  window.baiSwitchTab = function (tab) {
    document.querySelectorAll('.bai-tab-btn').forEach(function (b) {
      b.classList.remove('bai-tab-active');
      if (b.dataset && b.dataset.tab === tab) b.classList.add('bai-tab-active');
    });
    _tabPanels.forEach(function (t) {
      var panel = document.getElementById('bai-' + t + '-panel');
      if (panel) panel.style.display = (t === tab) ? '' : 'none';
    });
  };

  /* ── Render cofre ────────────────────────────────────────────── */
  function renderCofre() {
    var panel = document.getElementById('bai-cofre-panel');
    if (!panel) return;

    var berriesHtml =
      '<div class="bai-berries-box">' +
        '<span class="bai-berries-label">Berries en el cofre</span>' +
        '<span class="bai-berries-cant" id="bai-berries-cant">' + formatBerries(BC.berries) + '</span>' +
        '<div class="bai-berries-actions">' +
          '<input type="number" id="bai-berries-input" class="bai-input-num" min="1" placeholder="Cantidad&hellip;" />' +
          '<button type="button" class="bai-btn bai-btn-deposit" onclick="baiBerriesAction(\'depositar\')">+ Depositar</button>' +
          '<button type="button" class="bai-btn bai-btn-withdraw" onclick="baiBerriesAction(\'retirar\')">&minus; Retirar</button>' +
        '</div>' +
      '</div>';

    var depositBtn = BC.isOwner
      ? '<button type="button" class="bai-btn bai-btn-deposit" onclick="baiAbrirModalDepositar()">+ Depositar &iacute;tem</button>'
      : '';

    var sectionHeader =
      '<div class="bai-section-header">' +
        '<span class="bai-section-title">&#x1F4E6; &Iacute;tems del cofre</span>' +
        depositBtn +
      '</div>';

    var itemsHtml;
    if (!BC.cofre || !BC.cofre.length) {
      itemsHtml = '<div class="bai-empty-small">El cofre est&aacute; vac&iacute;o.</div>';
    } else {
      itemsHtml = '<div class="bai-items-grid">';
      BC.cofre.forEach(function (item) {
        var nombre = item.apodo || item.obj_nombre || item.objeto_id;
        var img    = item.imagen || item.obj_imagen || '';
        itemsHtml +=
          '<div class="bai-item-card">' +
            (img
              ? '<img class="bai-item-img" src="' + esc(img) + '" alt="" />'
              : '<div class="bai-item-img--empty">&#x1F4E6;</div>') +
            '<div class="bai-item-nombre">' + esc(nombre) + '</div>' +
            '<div class="bai-item-cant">x' + esc(item.cantidad) + '</div>' +
            '<button type="button" class="bai-btn bai-btn-sm bai-btn-withdraw" ' +
              'onclick="baiAbrirModalRetirar(' + item.id + ',' + item.cantidad + ',\'' + esc(nombre).replace(/'/g, '&#39;') + '\')">Retirar</button>' +
          '</div>';
      });
      itemsHtml += '</div>';
    }

    var histWrap =
      '<div class="bai-section-header" style="margin-top:22px;">' +
        '<span class="bai-section-title">&#x1F4CB; &Uacute;ltimas transacciones</span>' +
        '<button type="button" class="bai-btn bai-btn-sm bai-btn-neutral" onclick="baiCargarHistorialCofre()">&#x21BB; Actualizar</button>' +
      '</div>' +
      '<div id="bai-cofre-hist-wrap"><div class="bai-empty-small" style="padding:12px 0">Cargando&hellip;</div></div>';

    panel.innerHTML = berriesHtml + sectionHeader + itemsHtml + histWrap;
    setTimeout(baiCargarHistorialCofre, 0);
  }

  /* ── Historial del cofre ─────────────────────────────────────── */
  function baiCargarHistorialCofre() {
    var wrap = document.getElementById('bai-cofre-hist-wrap');
    if (!wrap) return;
    wrap.innerHTML = '<div class="bai-empty-small" style="padding:12px 0">Cargando&hellip;</div>';
    ajax('/op/barco_cofre.php', {
      action:    'historial',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      post_code: BC.postCode
    }, function (resp) {
      if (!resp.ok || !resp.historial || !resp.historial.length) {
        wrap.innerHTML = '<div class="bai-empty-small" style="padding:12px 0">Sin transacciones a&uacute;n.</div>';
        return;
      }
      var html = '<div style="overflow-x:auto;">' +
        '<table style="width:100%;border-collapse:collapse;font-size:11px;font-family:InterRegular;">' +
        '<thead><tr style="background:#ff8900;color:#fff;font-family:moonGetHeavy;">' +
          '<th style="padding:6px 8px;text-align:left;font-size:10px;letter-spacing:1px;">FECHA</th>' +
          '<th style="padding:6px 8px;text-align:left;font-size:10px;letter-spacing:1px;">USUARIO</th>' +
          '<th style="padding:6px 8px;text-align:left;font-size:10px;letter-spacing:1px;">TIPO</th>' +
          '<th style="padding:6px 8px;text-align:left;font-size:10px;letter-spacing:1px;">DESCRIPCI&Oacute;N</th>' +
          '<th style="padding:6px 8px;text-align:right;font-size:10px;letter-spacing:1px;">CANTIDAD</th>' +
        '</tr></thead><tbody>';
      resp.historial.forEach(function (row, i) {
        var esEntrada = row.tipo === 'entrada';
        var bg = i % 2 === 0 ? '#fff3e0' : '#ffe8c2';
        var indicador = esEntrada
          ? '<span style="color:#1b5e20;font-family:moonGetHeavy;font-size:11px;">&#x25B2; Entrada</span>'
          : '<span style="color:#7f1818;font-family:moonGetHeavy;font-size:11px;">&#x25BC; Salida</span>';
        var fecha = new Date(parseInt(row.timestamp, 10) * 1000).toLocaleString('es-ES', {
          day: '2-digit', month: '2-digit', year: '2-digit',
          hour: '2-digit', minute: '2-digit'
        });
        var desc = row.clase === 'berries'
          ? '&#x1F4B0; Berries'
          : (row.nombre ? esc(row.nombre) : (row.objeto_id ? esc(row.objeto_id) : '&mdash;'));
        var cant = row.clase === 'berries'
          ? Number(row.cantidad).toLocaleString('es-ES')
          : 'x' + row.cantidad;
        html += '<tr style="background:' + bg + ';">' +
          '<td style="padding:5px 8px;white-space:nowrap;color:#555;">' + esc(fecha) + '</td>' +
          '<td style="padding:5px 8px;color:#2c1810;">' + (row.username ? esc(row.username) : '&mdash;') + '</td>' +
          '<td style="padding:5px 8px;">' + indicador + '</td>' +
          '<td style="padding:5px 8px;color:#2c1810;">' + desc + '</td>' +
          '<td style="padding:5px 8px;text-align:right;font-family:moonGetHeavy;color:#d26500;">' + cant + '</td>' +
        '</tr>';
      });
      html += '</tbody></table></div>';
      wrap.innerHTML = html;
    });
  }
  window.baiCargarHistorialCofre = baiCargarHistorialCofre;

  /* ── Catálogo de tipos de sala ──────────────────────────────── */
  var TIPOS_SALA = {
    mapa:        {nombre:'Sala de Mapas', mejora:1, trip:5,  berries:200000000, bonus:'Cartógrafo: crafteo -25% · Timonel: viaje -12h',                                          icono:'🗺️'},
    cocina:      {nombre:'Cocina',         mejora:1, trip:5,  berries:100000000, bonus:'Chef: +25% efectividad en platos',                                                         icono:'🍳'},
    taller:      {nombre:'Taller',         mejora:2, trip:10, berries:400000000, bonus:'Modista / Astillero / Constructor / Ingeniero: crafteo -25%',                              icono:'🔧'},
    enfermeria:  {nombre:'Enfermería',     mejora:1, trip:5,  berries:100000000, bonus:'Farmacólogo: crafteos de fármacos x2',                                                    icono:'💊'},
    quirofano:   {nombre:'Quirófano',      mejora:1, trip:5,  berries:200000000, bonus:'Doctor: crafteos x2 eficacia · Biólogo: implantes -1 Espacio',                            icono:'🏥'},
    archivos:    {nombre:'Archivos',       mejora:2, trip:10, berries:400000000, bonus:'Arqueólogo / Periodista / Contrabandista: crafteo -25% · Comerciante: +10% NPC',          icono:'📚'},
    invernadero: {nombre:'Invernadero',    mejora:1, trip:5,  berries:300000000, bonus:'Mayorista / Agreste: Pop Green x2 · Aprovisionador: +1 persona alimentada',              icono:'🌿'},
    forja:       {nombre:'Forja',          mejora:1, trip:5,  berries:100000000, bonus:'Herrero: crafteo -25%',                                                                    icono:'⚒️'},
    corral:      {nombre:'Corral',         mejora:1, trip:5,  berries:200000000, bonus:'Cazador: mascota +5 atributos/Tier · Domador: entrenar +2 por temporada',                 icono:'🐾'},
  };

  function _salasTiposExistentes() {
    var tipos = {};
    if (BC.salas) {
      Object.keys(BC.salas).forEach(function (slot) {
        var s = BC.salas[slot];
        if (s && s.tipo) tipos[s.tipo] = true;
      });
    }
    return tipos;
  }

  /* ── Render salas ────────────────────────────────────────────── */
  function renderSalas() {
    var panel = document.getElementById('bai-salas-panel');
    if (!panel) return;

    var mejoraUsada = BC.mejoraUsada || 0;
    var mejoraMax   = BC.maxSalas   || 0;
    var mejoraLibre = mejoraMax - mejoraUsada;

    if (!mejoraMax) {
      panel.innerHTML = '<div class="bai-empty-small">Este barco no tiene espacios para mejoras.</div>';
      return;
    }

    var salasArr = [];
    if (BC.salas) {
      Object.keys(BC.salas).sort(function (a, b) { return a - b; }).forEach(function (slot) {
        if (BC.salas[slot]) salasArr.push(BC.salas[slot]);
      });
    }

    var html = '<div class="bai-section-header">' +
      '<span class="bai-section-title">&#x1F3E0; Salas del barco</span>' +
      (BC.isCarpintero && mejoraLibre > 0
        ? '<button type="button" class="bai-btn bai-btn-deposit" onclick="baiAbrirModalSala()">+ Construir sala</button>'
        : '') +
      '</div>' +
      '<div class="bai-sala-budget">Espacios de mejora: <b>' + mejoraUsada + ' / ' + mejoraMax + '</b></div>';

    if (!salasArr.length) {
      html += '<div class="bai-empty-small">No hay salas construidas.</div>';
    } else {
      html += '<div class="bai-salas-grid">';
      salasArr.forEach(function (sala) {
        var cat = TIPOS_SALA[sala.tipo] || {};
        var icono = cat.icono || '🏠';
        var nombre = cat.nombre || sala.nombre || sala.tipo;
        var bonus  = cat.bonus  || sala.descripcion || '';
        var mejoraCoste = cat.mejora || 1;
        var tripCoste   = cat.trip   || 5;
        html +=
          '<div class="bai-sala-card bai-sala-filled">' +
            '<div class="bai-sala-img--empty">' + icono + '</div>' +
            '<div class="bai-sala-slot">Sala ' + sala.slot + '</div>' +
            '<div class="bai-sala-nombre">' + esc(nombre) + '</div>' +
            '<div class="bai-sala-desc">' + esc(bonus) + '</div>' +
            '<div class="bai-sala-desc" style="display:flex;gap:5px;padding:4px 10px 2px;flex-wrap:wrap">' +
              '<span class="bai-sala-tipo-coste bai-sala-tipo-coste--mejora">' + mejoraCoste + ' mejora</span>' +
              '<span class="bai-sala-tipo-coste bai-sala-tipo-coste--trip">' + tripCoste + ' trip.</span>' +
            '</div>' +
            (BC.isCarpintero
              ? '<div class="bai-sala-actions">' +
                  '<button type="button" class="bai-btn bai-btn-sm bai-btn-danger" onclick="baiDemolerSala(\'' + esc(sala.tipo) + '\',' + sala.slot + ')">Demoler</button>' +
                '</div>'
              : '') +
          '</div>';
      });
      html += '</div>';
    }
    // ── Sección de mejoras de barco ──
    html += _renderMejorasBarco();
    panel.innerHTML = html;
  }

  var _MEJORAS_INFO = {
    tripulacion: { label: 'Tripulación',      icono: '👥', desc: 'Aumenta los espacios de tripulación del barco.' },
    vitalidad:   { label: 'Vitalidad',        icono: '❤️', desc: 'Aumenta la vitalidad del barco en un 40%.' },
    resistencia: { label: 'Resistencia',      icono: '🛡️', desc: 'Aumenta la resistencia del barco en 0.1×RES+50.' },
    ruptura:     { label: 'Puntos de Ruptura',icono: '⚓', desc: 'Aumenta los puntos de ruptura. Requiere Vitalidad y Resistencia.' },
  };

  var _COSTES_MEJORA = {
    tripulacion: [50000, 2500000, 16000000, 70000000, 220000000],
    vitalidad:   [75000, 4200000, 24000000, 110000000, 330000000],
    resistencia: [60000, 3500000, 20000000, 90000000, 275000000],
    ruptura:     [100000, 5500000, 35000000, 140000000, 450000000],
  };

  var _BONUS_MEJORA = {
    tripulacion: [2, 4, 6, 8, 12],
    ruptura:     [1, 1, 2, 2, 3],
  };

  function _calcBonusMejora(tipo) {
    var tier = (BC.tierBarco || 1);
    var idx  = Math.max(0, Math.min(4, tier - 1));
    switch (tipo) {
      case 'tripulacion': return '+' + _BONUS_MEJORA.tripulacion[idx] + ' espacios';
      case 'vitalidad':   return '+' + Math.floor(0.4 * (BC.vitalidadBarco || 0)) + ' vitalidad';
      case 'resistencia': return '+' + Math.floor(0.1 * (BC.resistenciaBarco || 0) + 50) + ' resistencia';
      case 'ruptura':     return '+' + _BONUS_MEJORA.ruptura[idx] + ' ruptura';
    }
    return '';
  }

  function _renderMejorasBarco() {
    var tier = BC.tierBarco || 0;
    if (!tier) return '';
    var ma   = BC.mejorasAplicadas || {};
    var idx  = Math.max(0, Math.min(4, tier - 1));

    var html = '<div class="bai-section-header" style="margin-top:22px;">' +
      '<span class="bai-section-title">🔧 Mejoras del barco</span>' +
    '</div>' +
    '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;margin-top:8px;">';

    ['tripulacion', 'vitalidad', 'resistencia', 'ruptura'].forEach(function (tipo) {
      var info    = _MEJORAS_INFO[tipo];
      var aplicada = !!ma[tipo];
      var coste   = _COSTES_MEJORA[tipo][idx];
      var bonus   = _calcBonusMejora(tipo);
      var bloqueada = tipo === 'ruptura' && (!ma.vitalidad || !ma.resistencia);
      var puedeAplicar = BC.isCarpinteroMejora && !aplicada && !bloqueada;

      var bgColor  = aplicada ? '#d4edda' : (bloqueada ? '#f0f0f0' : '#fff8ee');
      var bdColor  = aplicada ? '#28a745' : (bloqueada ? '#ccc'    : '#ff8900');
      var txtColor = aplicada ? '#155724' : (bloqueada ? '#999'    : '#2c1810');

      html += '<div style="background:' + bgColor + ';border:2px solid ' + bdColor + ';border-radius:8px;padding:12px 14px;">' +
        '<div style="font-size:18px;margin-bottom:4px;">' + info.icono + ' <b>' + info.label + '</b></div>' +
        '<div style="font-size:11px;color:' + txtColor + ';margin-bottom:6px;">' + info.desc + '</div>' +
        '<div style="font-size:12px;font-weight:bold;color:#6c10ab;margin-bottom:4px;">' + bonus + '</div>' +
        '<div style="font-size:11px;color:#555;margin-bottom:8px;">Coste: ' + coste.toLocaleString() + ' berries</div>';

      if (aplicada) {
        html += '<div style="font-size:12px;color:#28a745;font-weight:bold;">✓ Aplicada</div>';
      } else if (bloqueada) {
        html += '<div style="font-size:11px;color:#999;">🔒 Requiere Vitalidad y Resistencia</div>';
      } else if (BC.isCarpinteroMejora) {
        html += '<button type="button" class="bai-btn bai-btn-deposit" style="font-size:11px;padding:5px 12px;" ' +
          'onclick="baiAplicarMejora(\'' + tipo + '\')">' +
          'Aplicar mejora' +
        '</button>';
      }

      html += '</div>';
    });

    html += '</div>';
    return html;
  }

  window.baiAplicarMejora = function (tipo) {
    var info  = _MEJORAS_INFO[tipo] || {};
    var tier  = BC.tierBarco || 1;
    var idx   = Math.max(0, Math.min(4, tier - 1));
    var coste = _COSTES_MEJORA[tipo][idx];
    if (!confirm('¿Aplicar Mejora de ' + (info.label || tipo) + '?\nCoste: ' + coste.toLocaleString() + ' berries del cofre del barco.')) return;
    ajax('/op/barco_mejora.php', {
      action:    'aplicar',
      tipo:      tipo,
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      post_code: BC.postCode,
    }, function (resp) {
      if (resp.ok) {
        if (!BC.mejorasAplicadas) BC.mejorasAplicadas = {};
        BC.mejorasAplicadas[tipo] = true;
        BC.berries = resp.berries_cofre;
        if (tipo === 'vitalidad')   BC.vitalidadBarco   = resp.valor_nuevo;
        if (tipo === 'resistencia') BC.resistenciaBarco = resp.valor_nuevo;
        if (tipo === 'tripulacion') BC.maxEspaciosTrip  = resp.valor_nuevo;
        if (tipo === 'ruptura')     BC.ruputura         = resp.valor_nuevo;
        renderSalas();
        renderCofre();
      } else {
        alert(resp.error || 'Error al aplicar la mejora.');
      }
    });
  };

  /* ── Berries ─────────────────────────────────────────────────── */
  window.baiBerriesAction = function (tipo) {
    var input = document.getElementById('bai-berries-input');
    var cant  = parseInt(input.value, 10);
    if (!cant || cant <= 0) { alert('Ingresa una cantidad válida.'); return; }

    ajax('/op/barco_cofre.php', {
      action:    'berries',
      tipo:      tipo,
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      cantidad:  cant,
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok) {
        BC.berries = resp.berries_cofre;
        var el = document.getElementById('bai-berries-cant');
        if (el) el.innerHTML = formatBerries(BC.berries);
        input.value = '';
        baiCargarHistorialCofre();
      } else {
        alert(resp.error || 'Error desconocido.');
      }
    });
  };

  /* ── Modal: depositar ítem ───────────────────────────────────── */
  window.baiAbrirModalDepositar = function () {
    var modal = document.getElementById('bai-modal-depositar');
    var list  = document.getElementById('bai-modal-inv-list');
    if (!modal || !list) return;

    list.innerHTML = '';
    var invComerciable = (BC.inventario || []).filter(function (i) { return parseInt(i.comerciable, 10) === 0; });
    if (!invComerciable.length) {
      list.innerHTML = '<div class="bai-empty-small">No tienes &iacute;tems comerciables para depositar.</div>';
    } else {
      invComerciable.forEach(function (item) {
        var nombre = item.apodo || item.obj_nombre || item.objeto_id;
        var div    = document.createElement('div');
        div.className = 'bai-inv-item';
        div.innerHTML =
          '<span class="bai-inv-nombre">' + esc(nombre) + '</span>' +
          '<span class="bai-inv-cant">x' + esc(item.cantidad) + '</span>' +
          '<input type="number" class="bai-input-num" min="1" max="' + item.cantidad + '" value="1" style="width:60px;" />' +
          '<button type="button" class="bai-btn bai-btn-sm bai-btn-deposit" ' +
            'data-obj="' + esc(item.objeto_id) + '">Depositar</button>';
        var btn = div.querySelector('button');
        var cantInput = div.querySelector('input');
        btn.addEventListener('click', function () {
          var cant = parseInt(cantInput.value, 10);
          baiDepositarItem(item.objeto_id, cant, item);
        });
        list.appendChild(div);
      });
    }
    modal.style.display = 'flex';
  };

  window.baiCerrarModalDepositar = function () {
    var m = document.getElementById('bai-modal-depositar');
    if (m) m.style.display = 'none';
  };

  function baiDepositarItem(objetoId, cant, itemRef) {
    if (!cant || cant <= 0) { alert('Cantidad inválida.'); return; }

    ajax('/op/barco_cofre.php', {
      action:    'agregar',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      objeto_id: objetoId,
      cantidad:  cant,
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok && resp.cofre_item) {
        // Update personal inventory locally
        for (var i = 0; i < BC.inventario.length; i++) {
          if (BC.inventario[i].objeto_id === objetoId) {
            BC.inventario[i].cantidad -= cant;
            if (BC.inventario[i].cantidad <= 0) BC.inventario.splice(i, 1);
            break;
          }
        }
        // Update or add to cofre
        var found = false;
        for (var j = 0; j < BC.cofre.length; j++) {
          if (BC.cofre[j].objeto_id === objetoId) {
            BC.cofre[j] = resp.cofre_item;
            found = true; break;
          }
        }
        if (!found) BC.cofre.push(resp.cofre_item);
        baiCerrarModalDepositar();
        renderCofre();
      } else {
        alert(resp.error || 'Error al depositar.');
      }
    });
  }

  /* ── Modal: retirar ítem ─────────────────────────────────────── */
  window.baiAbrirModalRetirar = function (cofreId, maxCant, nombre) {
    var input = document.getElementById('bai-modal-ret-input');
    document.getElementById('bai-modal-ret-nombre').textContent = nombre;
    input.max   = maxCant;
    input.value = 1;
    input.setAttribute('data-cofre-id', cofreId);
    document.getElementById('bai-modal-retirar').style.display = 'flex';
  };

  window.baiCerrarModalRetirar = function () {
    var m = document.getElementById('bai-modal-retirar');
    if (m) m.style.display = 'none';
  };

  window.baiConfirmarRetirar = function () {
    var input   = document.getElementById('bai-modal-ret-input');
    var cofreId = parseInt(input.getAttribute('data-cofre-id'), 10);
    var cant    = parseInt(input.value, 10);
    if (!cant || cant <= 0) { alert('Cantidad inválida.'); return; }

    ajax('/op/barco_cofre.php', {
      action:    'retirar',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      cofre_id:  cofreId,
      cantidad:  cant,
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok) {
        for (var i = 0; i < BC.cofre.length; i++) {
          if (parseInt(BC.cofre[i].id) === cofreId) {
            BC.cofre[i].cantidad -= cant;
            if (BC.cofre[i].cantidad <= 0) BC.cofre.splice(i, 1);
            break;
          }
        }
        baiCerrarModalRetirar();
        renderCofre();
      } else {
        alert(resp.error || 'Error al retirar.');
      }
    });
  };

  /* ── Modal: construir sala ───────────────────────────────────── */
  var _salaTipoSeleccionado = null;

  window.baiAbrirModalSala = function () {
    _salaTipoSeleccionado = null;
    var mejoraUsada = BC.mejoraUsada || 0;
    var mejoraLibre = (BC.maxSalas || 0) - mejoraUsada;
    var existentes  = _salasTiposExistentes();

    var budgetEl = document.getElementById('bai-sala-budget-info');
    if (budgetEl) {
      budgetEl.innerHTML = 'Espacios de mejora disponibles: <b>' + mejoraLibre + ' / ' + (BC.maxSalas || 0) + '</b>' +
        ' &nbsp;·&nbsp; Berries en cofre: <b>' + formatBerries(BC.berries) + '</b>';
    }

    var list = document.getElementById('bai-sala-tipo-list');
    if (list) {
      var html = '';
      Object.keys(TIPOS_SALA).forEach(function (key) {
        var t = TIPOS_SALA[key];
        var yaExiste  = !!existentes[key];
        var sinBudget = t.mejora > mejoraLibre;
        var disabled  = yaExiste || sinBudget;
        var razon = yaExiste ? ' (ya construida)' : (sinBudget ? ' (espacios insuficientes)' : '');
        html +=
          '<div class="bai-sala-tipo-item' + (disabled ? ' bai-sala-tipo-disabled' : '') + '" ' +
            (disabled ? '' : 'onclick="baiSeleccionarTipoSala(\'' + key + '\')"') +
            ' id="bai-sala-tipo-' + key + '">' +
            '<input type="radio" name="bai-sala-tipo" value="' + key + '" class="bai-sala-tipo-radio" ' +
              (disabled ? 'disabled' : '') + ' />' +
            '<div class="bai-sala-tipo-info">' +
              '<div class="bai-sala-tipo-nombre">' + t.icono + ' ' + esc(t.nombre) + esc(razon) + '</div>' +
              '<div class="bai-sala-tipo-bonus">' + esc(t.bonus) + '</div>' +
              '<div class="bai-sala-tipo-costes">' +
                '<span class="bai-sala-tipo-coste bai-sala-tipo-coste--berries">' + formatBerries(t.berries) + '</span>' +
                '<span class="bai-sala-tipo-coste bai-sala-tipo-coste--mejora">' + t.mejora + ' espacio' + (t.mejora > 1 ? 's' : '') + ' mejora</span>' +
                '<span class="bai-sala-tipo-coste bai-sala-tipo-coste--trip">' + t.trip + ' trip.</span>' +
              '</div>' +
            '</div>' +
          '</div>';
      });
      list.innerHTML = html;
    }

    var confirmBtn = document.getElementById('bai-sala-confirmar-btn');
    if (confirmBtn) confirmBtn.style.display = 'none';

    document.getElementById('bai-modal-sala').style.display = 'flex';
  };

  window.baiSeleccionarTipoSala = function (key) {
    _salaTipoSeleccionado = key;
    document.querySelectorAll('.bai-sala-tipo-item:not(.bai-sala-tipo-disabled)').forEach(function (el) {
      el.classList.remove('bai-sala-tipo-selected');
    });
    var el = document.getElementById('bai-sala-tipo-' + key);
    if (el) {
      el.classList.add('bai-sala-tipo-selected');
      var radio = el.querySelector('input[type=radio]');
      if (radio) radio.checked = true;
    }
    var confirmBtn = document.getElementById('bai-sala-confirmar-btn');
    if (confirmBtn) confirmBtn.style.display = '';
  };

  window.baiCerrarModalSala = function () {
    var m = document.getElementById('bai-modal-sala');
    if (m) m.style.display = 'none';
    _salaTipoSeleccionado = null;
  };

  window.baiConstruirSala = function () {
    if (!_salaTipoSeleccionado) return;
    var t = TIPOS_SALA[_salaTipoSeleccionado];
    if (!t) return;
    if (!confirm('¿Construir ' + t.nombre + ' por ' + formatBerries(t.berries) + '? Los berries se descontarán del cofre del barco.')) return;

    ajax('/op/barco_sala.php', {
      action:    'construir',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      tipo:      _salaTipoSeleccionado,
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok) {
        if (!BC.salas) BC.salas = {};
        BC.salas[resp.sala.slot] = resp.sala;
        BC.mejoraUsada = (BC.mejoraUsada || 0) + t.mejora;
        BC.berries     = resp.berries_cofre;
        baiCerrarModalSala();
        renderSalas();
        renderCofre();
      } else {
        alert(resp.error || 'Error al construir la sala.');
      }
    });
  };

  window.baiDemolerSala = function (tipo, slot) {
    var t = TIPOS_SALA[tipo];
    var nombre = t ? t.nombre : tipo;
    if (!confirm('¿Demoler ' + nombre + '? Esta acción no reembolsa los berries.')) return;

    ajax('/op/barco_sala.php', {
      action:    'demoler',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      tipo:      tipo,
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok) {
        if (BC.salas && BC.salas[slot]) {
          delete BC.salas[slot];
          var mejoraCoste = t ? t.mejora : 1;
          BC.mejoraUsada = Math.max(0, (BC.mejoraUsada || 0) - mejoraCoste);
        }
        renderSalas();
      } else {
        alert(resp.error || 'Error al demoler la sala.');
      }
    });
  };

  /* ── Espacios helpers ─────────────────────────────────────────── */
  function calcEspacios(altCm) {
    var h = parseFloat(altCm) || 0;
    if (h <= 0)   return 1;      // altura desconocida → 1 espacio por defecto
    if (h <= 50)  return 0.25;
    if (h <= 100) return 0.5;
    if (h <= 300) return 1;
    return 2 + Math.ceil((h - 300) / 200);
  }

  function formatEspaciosFrac(v) {
    var q = Math.round(v * 4);
    if (q === 0) return '0';
    var whole = Math.floor(q / 4);
    var rem   = q % 4;
    var fracs = ['', '\u00bc', '\u00bd', '\u00be']; // ¼ ½ ¾
    if (whole && rem) return whole + fracs[rem];
    if (whole)        return String(whole);
    return fracs[rem];
  }

  /* ── Tripulación ─────────────────────────────────────────────── */
  function renderTripulacion() {
    var panel = document.getElementById('bai-trip-panel');
    if (!panel) return;

    var isOwner   = BC.isOwner;
    var viewerUid = BC.viewerUid;
    var miembros  = BC.tripulacion || [];
    var npcs      = BC.npcs || [];
    var isVice    = !isOwner && miembros.some(function (m) {
      return m.miembro_uid == viewerUid && m.rango && m.rango.split(',').indexOf('Vicecapitan') !== -1;
    });
    var isSuperAdmin = (viewerUid == 850);

    // ── Presupuesto de espacios ──
    var salasTripCoste = 0;
    if (BC.salas) {
      Object.keys(BC.salas).forEach(function (slot) {
        var s = BC.salas[slot];
        if (s && s.tipo && TIPOS_SALA[s.tipo]) salasTripCoste += TIPOS_SALA[s.tipo].trip;
      });
    }
    var espaciosMax    = (BC.maxEspaciosTrip || 0) - salasTripCoste;
    var espaciosUsados = _ausentes['owner'] ? 0 : calcEspacios(BC.ownerAltura || 0);
    miembros.forEach(function (m) { if (!_ausentes['trip_' + m.id]) { espaciosUsados += calcEspacios(m.altura || 0); } });
    npcs.forEach(function (n)     { if (!_ausentes['npc_'  + n.id]) { espaciosUsados += calcEspacios(n.altura || 0); } });
    var espacioClass = (espaciosMax > 0 && espaciosUsados > espaciosMax) ? ' bai-trip-budget--over' : '';

    // ── Miembros ──
    var html = '<div class="bai-section-header">'
      + '<span class="bai-section-title">&#x1F9ED; Tripulaci&oacute;n</span>'
      + ((isOwner || isVice) ? '<button type="button" class="bai-btn bai-btn-deposit" onclick="baiAbrirInvitar()">+ Invitar</button>' : '')
      + '</div>';
    if (espaciosMax > 0) {
      html += '<div class="bai-trip-budget' + espacioClass + '">'
        + 'Espacios: <b>' + formatEspaciosFrac(espaciosUsados) + ' / ' + espaciosMax + '</b>'
        + (salasTripCoste ? ' <small style="color:#888">(salas ocupan ' + salasTripCoste + ')</small>' : '')
        + '</div>';
    }
    html += '<div class="bai-trip-list">';

    var RANGOS = {
      'Capitan':     {label: 'Capit\u00e1n',     cls: 'bai-trip-rango--capitan'},
      'Vicecapitan': {label: 'Vicecapit\u00e1n', cls: 'bai-trip-rango--vice'},
      'Tesorero':    {label: 'Tesorero',         cls: 'bai-trip-rango--tesorero'},
      'Navegante':   {label: 'Navegante',        cls: 'bai-trip-rango--navegante'},
      'Carpintero':  {label: 'Carpintero',       cls: 'bai-trip-rango--carpintero'}
    };

    // ── Capit\u00e1n ──
    var ownerRangos  = BC.ownerRangos ? BC.ownerRangos.split(',').filter(function (r) { return r; }) : [];
    var isViewerCap  = (isOwner && ownerRangos.indexOf('Capitan') !== -1)
      || miembros.some(function (m) { return m.miembro_uid == viewerUid && m.rango && m.rango.split(',').indexOf('Capitan') !== -1; });
    var hasCapitan   = ownerRangos.indexOf('Capitan') !== -1
      || miembros.some(function (m) { return m.rango && m.rango.split(',').indexOf('Capitan') !== -1; });
    var canAssignCap = isSuperAdmin || isViewerCap || (!hasCapitan && isOwner);
    var ownerBadges = ownerRangos.map(function (r) {
      return RANGOS[r] ? '<span class="bai-trip-rango ' + RANGOS[r].cls + '">' + RANGOS[r].label + '</span>' : '';
    }).join('');
    var capDropdown = '';
    if (isOwner || isSuperAdmin || isViewerCap) {
      var capKeys = ['Navegante', 'Carpintero'];
      if (isOwner || isSuperAdmin || isViewerCap) capKeys.unshift('Vicecapitan');
      if (canAssignCap) capKeys.unshift('Capitan');
      var capToggleLabel = ownerRangos.length
        ? ownerRangos.map(function (r) { return RANGOS[r] ? RANGOS[r].label : r; }).join(', ')
        : 'Roles \u25be';
      capDropdown = '<div class="bai-rango-dropdown" id="bai-rdrop-capitan">';
      capDropdown += '<button type="button" class="bai-rango-toggle" onclick="baiToggleRangoDrop(\'capitan\')">'
        + capToggleLabel + '</button>';
      capDropdown += '<div class="bai-rango-dropmenu" style="display:none">';
      capKeys.forEach(function (key) {
        var checked = ownerRangos.indexOf(key) !== -1 ? ' checked' : '';
        capDropdown += '<label class="bai-trip-rango-check">';
        capDropdown += '<input type="checkbox" value="' + key + '"' + checked + ' data-trip="capitan" />';
        capDropdown += RANGOS[key].label + '</label>';
      });
      capDropdown += '<div class="bai-rango-dropfooter">';
      capDropdown += '<button type="button" class="bai-btn bai-btn-sm bai-btn-primary" onclick="baiAsignarRangoCapitan()">' + '\u2713 Guardar</button>';
      capDropdown += '</div></div></div>';
    }
    // ── Lista unificada ordenada: Capitán > Vicecapitán > Dueño > resto ──
    function _sortPriority(rangos, isOwnerEntry) {
      if (rangos.indexOf('Capitan') !== -1)     return 1;
      if (rangos.indexOf('Vicecapitan') !== -1) return 2;
      if (isOwnerEntry)                         return 3;
      return 4;
    }
    var ownerIsCapitan = ownerRangos.indexOf('Capitan') !== -1;
    var entries = [{ type: 'owner', rangos: ownerRangos, priority: _sortPriority(ownerRangos, true) }];
    miembros.forEach(function (m) {
      var r = m.rango ? m.rango.split(',').filter(function (x) { return x; }) : [];
      entries.push({ type: 'trip', m: m, rangos: r, priority: _sortPriority(r, false) });
    });
    entries.sort(function (a, b) { return a.priority - b.priority; });

    if (ownerIsCapitan) { delete _ausentes['owner']; }

    entries.forEach(function (entry) {
      if (entry.type === 'owner') {
        var ownerAusenteHtml = ownerIsCapitan ? '' : '<label class="bai-ausente-label" title="Marcar como ausente">'
          + '<input type="checkbox" class="bai-ausente-cb"' + (_ausentes['owner'] ? ' checked' : '') + ' data-key="owner" onchange="baiToggleAusente(this)">'
          + ' Ausente</label>';
        html += '<div class="bai-trip-item' + (_ausentes['owner'] ? ' bai-trip-item--ausente' : '') + '">'
          + '<span class="bai-trip-role bai-trip-role--dueno">Due&ntilde;o</span>'
          + (ownerBadges ? '<div class="bai-trip-rangos">' + ownerBadges + '</div>' : '')
          + '<span class="bai-trip-nombre">' + esc(BC.ownerUsername) + '</span>'
          + '<span class="bai-trip-espacio">' + (_ausentes['owner'] ? '<s>' : '') + formatEspaciosFrac(calcEspacios(BC.ownerAltura || 0)) + ' esp.' + (_ausentes['owner'] ? '</s>' : '') + '</span>'
          + ownerAusenteHtml
          + capDropdown
          + '</div>';
      } else {
        var m = entry.m;
        var mRangos = entry.rangos;
        var memberIsVice = mRangos.indexOf('Vicecapitan') !== -1;
        var canManage = isOwner || isSuperAdmin || isViewerCap || (isVice && !memberIsVice);
        var displayRangos = memberIsVice ? mRangos.filter(function (r) { return r !== 'Tesorero'; }) : mRangos;
        var rangoBadges = displayRangos.map(function (r) {
          return RANGOS[r] ? '<span class="bai-trip-rango ' + RANGOS[r].cls + '">' + RANGOS[r].label + '</span>' : '';
        }).join('');
        var rangoDropdown = '';
        if (canManage) {
          var assignableKeys = Object.keys(RANGOS).filter(function (k) {
            if (k === 'Capitan')     return canAssignCap;
            if (k === 'Vicecapitan') return (isOwner || isSuperAdmin || isViewerCap) && !(isViewerCap && m.miembro_uid == viewerUid);
            if (k === 'Tesorero')    return !memberIsVice;
            return true;
          });
          var toggleLabel = mRangos.length
            ? mRangos.map(function (r) { return RANGOS[r] ? RANGOS[r].label : r; }).join(', ')
            : 'Rangos ▾';
          rangoDropdown = '<div class="bai-rango-dropdown" id="bai-rdrop-' + m.id + '">';
          rangoDropdown += '<button type="button" class="bai-rango-toggle" onclick="baiToggleRangoDrop(' + m.id + ')">' + toggleLabel + '</button>';
          rangoDropdown += '<div class="bai-rango-dropmenu" style="display:none">';
          assignableKeys.forEach(function (key) {
            var checked = mRangos.indexOf(key) !== -1 ? ' checked' : '';
            rangoDropdown += '<label class="bai-trip-rango-check">';
            rangoDropdown += '<input type="checkbox" value="' + key + '"' + checked + ' data-trip="' + m.id + '" />';
            rangoDropdown += RANGOS[key].label + '</label>';
          });
          rangoDropdown += '<div class="bai-rango-dropfooter">';
          rangoDropdown += '<button type="button" class="bai-btn bai-btn-sm bai-btn-primary" onclick="baiAsignarRango(' + m.id + ')">✓ Guardar</button>';
          rangoDropdown += '</div></div></div>';
        }
        var actionBtn = '';
        if (viewerUid && viewerUid == m.miembro_uid) {
          actionBtn = '<button type="button" class="bai-btn bai-btn-sm bai-btn-danger" onclick="baiAbandonarBarco()">Abandonar</button>';
        } else if (canManage) {
          actionBtn = '<button type="button" class="bai-btn bai-btn-sm bai-btn-danger" onclick="baiExpulsarTripulante(' + m.id + ',\'' + esc(m.username).replace(/'/g, '&#39;') + '\')">Expulsar</button>';
        }
        var ausenteKeyTrip = 'trip_' + m.id;
        var ausenteCheckedTrip = _ausentes[ausenteKeyTrip] ? ' checked' : '';
        var memberIsCapitan = mRangos.indexOf('Capitan') !== -1;
        if (memberIsCapitan) { delete _ausentes[ausenteKeyTrip]; }
        var ausenteLabelTrip = memberIsCapitan ? '' : '<label class="bai-ausente-label" title="Marcar como ausente">'
          + '<input type="checkbox" class="bai-ausente-cb"' + ausenteCheckedTrip + ' data-key="' + ausenteKeyTrip + '" onchange="baiToggleAusente(this)">'
          + ' Ausente</label>';
        html += '<div class="bai-trip-item' + (_ausentes[ausenteKeyTrip] ? ' bai-trip-item--ausente' : '') + '">'
          + '<span class="bai-trip-role">Tripulante</span>'
          + (rangoBadges ? '<div class="bai-trip-rangos">' + rangoBadges + '</div>' : '')
          + '<span class="bai-trip-nombre">' + esc(m.username) + ' <small style="color:#555">(UID ' + m.miembro_uid + ')</small></span>'
          + '<span class="bai-trip-espacio">' + (_ausentes[ausenteKeyTrip] ? '<s>' : '') + formatEspaciosFrac(calcEspacios(m.altura || 0)) + ' esp.' + (_ausentes[ausenteKeyTrip] ? '</s>' : '') + '</span>'
          + ausenteLabelTrip
          + rangoDropdown
          + actionBtn
          + '</div>';
      }
    });

    if (!miembros.length) {
      html += '<div class="bai-empty-small" style="padding:16px 0">Sin tripulantes a&uacute;n.</div>';
    }
    html += '</div>';

    // ── NPCs y Mascotas ──
    html += '<div class="bai-section-header" style="margin-top:20px">'
      + '<span class="bai-section-title">&#x1F43E; NPCs &amp; Mascotas</span>'
      + '<button type="button" class="bai-btn bai-btn-deposit" onclick="baiAbrirAddNpc()">+ A&ntilde;adir</button>'
      + '</div>'
      + '<div class="bai-trip-list">';

    npcs.forEach(function (n) {
      var npcOwnerUid = parseInt((n.ref_id.match(/^(\d+)-/) || [])[1], 10) || 0;
      var isNpcOwner  = viewerUid && viewerUid === npcOwnerUid;
      var canRemove   = isOwner || isNpcOwner;
      var baseLabel   = n.tipo === 'pet' ? 'Mascota' : 'NPC';
      var rolLabel    = n.rol ? ' · ' + n.rol : '';
      var ausenteKeyNpc     = 'npc_' + n.id;
      var ausenteCheckedNpc = _ausentes[ausenteKeyNpc] ? ' checked' : '';
      var ausenteLabelNpc   = '<label class="bai-ausente-label" title="Marcar como ausente">'
        + '<input type="checkbox" class="bai-ausente-cb"' + ausenteCheckedNpc + ' data-key="' + ausenteKeyNpc + '" onchange="baiToggleAusente(this)">'
        + ' Ausente</label>';

      // Dropdown de rol (solo dueño del NPC, solo para NPCs no mascotas)
      var rolDropdown = '';
      if (isNpcOwner && n.tipo === 'npc') {
        rolDropdown = '<div class="bai-npc-rol-wrap">'
          + '<select class="bai-npc-rol-sel" id="bai-npc-rol-sel-' + n.id + '">'
          + '<option value=""' + (!n.rol ? ' selected' : '') + '>Sin rol</option>'
          + '<option value="Carpintero"' + (n.rol === 'Carpintero' ? ' selected' : '') + '>&#x1F528; Carpintero</option>'
          + '<option value="Navegante"' + (n.rol === 'Navegante'  ? ' selected' : '') + '>&#x2388; Navegante</option>'
          + '</select>'
          + '<button type="button" class="bai-btn bai-btn-sm bai-btn-primary" onclick="baiAsignarRolNpc(' + n.id + ')">Asignar</button>'
          + '</div>';
      }

      html += '<div class="bai-trip-item' + (_ausentes[ausenteKeyNpc] ? ' bai-trip-item--ausente' : '') + '">'
        + '<span class="bai-trip-role bai-trip-role--npc">' + esc(baseLabel) + esc(rolLabel) + '</span>'
        + '<span class="bai-trip-nombre">' + esc(n.nombre) + ' <small style="color:#555">(' + esc(n.ref_id) + ')</small></span>'
        + '<span class="bai-trip-espacio">' + (_ausentes[ausenteKeyNpc] ? '<s>' : '') + formatEspaciosFrac(calcEspacios(n.altura || 0)) + ' esp.' + (_ausentes[ausenteKeyNpc] ? '</s>' : '') + '</span>'
        + ausenteLabelNpc
        + rolDropdown
        + (canRemove
          ? '<button type="button" class="bai-btn bai-btn-sm bai-btn-danger" onclick="baiRemoveNpc(' + n.id + ',\'' + esc(n.nombre).replace(/'/g, '&#39;') + '\')">Retirar</button>'
          : '')
        + '</div>';
    });

    if (!npcs.length) {
      html += '<div class="bai-empty-small" style="padding:16px 0">Sin NPCs ni mascotas a&uacute;n.</div>';
    }
    html += '</div>';

    panel.innerHTML = html;
  }

  window.baiToggleAusente = function (cb) {
    var key     = cb.getAttribute('data-key');
    var ausente = cb.checked;
    if (ausente) { _ausentes[key] = true; } else { delete _ausentes[key]; }
    renderTripulacion();

    // Determinar tipo y entry_id para persistir en BD
    var tipo, entryId;
    if (key === 'owner') {
      tipo = 'owner'; entryId = 0;
    } else if (key.indexOf('trip_') === 0) {
      tipo = 'trip'; entryId = parseInt(key.replace('trip_', ''), 10);
    } else if (key.indexOf('npc_') === 0) {
      tipo = 'npc'; entryId = parseInt(key.replace('npc_', ''), 10);
    } else { return; }

    ajax('/op/barco_tripulacion.php', {
      action:    'toggle_ausente',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      tipo:      tipo,
      entry_id:  entryId,
      ausente:   ausente ? 'true' : 'false',
      post_code: BC.postCode
    }, function (resp) {
      if (!resp.ok) {
        if (ausente) { delete _ausentes[key]; } else { _ausentes[key] = true; }
        renderTripulacion();
        alert(resp.error || 'Error al guardar ausencia');
      }
    });
  };

  window.baiAbrirInvitar = function () {
    var m = document.getElementById('bai-modal-invitar');
    if (m) { document.getElementById('bai-invitar-uid').value = ''; m.style.display = 'flex'; }
  };

  window.baiCerrarInvitar = function () {
    var m = document.getElementById('bai-modal-invitar');
    if (m) m.style.display = 'none';
  };

  window.baiConfirmarInvitar = function () {
    var input = document.getElementById('bai-invitar-uid');
    var uid   = parseInt(input.value, 10);
    if (!uid || uid <= 0) { alert('Introduce un UID válido.'); return; }

    ajax('/op/barco_tripulacion.php', {
      action:      'invitar',
      barco_id:    BC.barcoId,
      owner_uid:   BC.ownerUid,
      miembro_uid: uid,
      post_code:   BC.postCode
    }, function (resp) {
      if (resp.ok) {
        baiCerrarInvitar();
        var panel = document.getElementById('bai-trip-panel');
        var msg   = document.createElement('div');
        msg.style.cssText = 'background:#1b3a5e;border:1px solid #2a5a8e;border-radius:6px;padding:8px 12px;font-family:InterRegular;font-size:13px;color:#90caf9;margin-bottom:10px';
        msg.textContent = 'Invitación enviada a ' + (resp.username || 'UID ' + uid) + '.';
        if (panel) panel.insertBefore(msg, panel.firstChild);
        setTimeout(function () { if (msg.parentNode) msg.parentNode.removeChild(msg); }, 5000);
      } else {
        alert(resp.error || 'Error al invitar.');
      }
    });
  };

  window.baiAbrirAddNpc = function () {
    var m = document.getElementById('bai-modal-add-npc');
    if (m) { document.getElementById('bai-add-npc-id').value = ''; m.style.display = 'flex'; }
  };

  window.baiCerrarAddNpc = function () {
    var m = document.getElementById('bai-modal-add-npc');
    if (m) m.style.display = 'none';
  };

  window.baiConfirmarAddNpc = function () {
    var input  = document.getElementById('bai-add-npc-id');
    var ref_id = input.value.trim();
    if (!ref_id) { alert('Introduce el ID del NPC o Mascota.'); return; }

    ajax('/op/barco_tripulacion.php', {
      action:    'add_npc',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      ref_id:    ref_id,
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok) {
        BC.npcs.push(resp.npc);
        baiCerrarAddNpc();
        renderTripulacion();
      } else {
        alert(resp.error || 'Error al añadir.');
      }
    });
  };

  window.baiRemoveNpc = function (entryId, nombre) {
    if (!confirm('¿Retirar a ' + nombre + ' del barco?')) return;

    ajax('/op/barco_tripulacion.php', {
      action:    'remove_npc',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      entry_id:  entryId,
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok) {
        BC.npcs = BC.npcs.filter(function (n) { return n.id !== entryId; });
        renderTripulacion();
      } else {
        alert(resp.error || 'Error al retirar.');
      }
    });
  };

  window.baiAsignarRolNpc = function (entryId) {
    var sel = document.getElementById('bai-npc-rol-sel-' + entryId);
    if (!sel) return;
    var rol = sel.value;

    ajax('/op/barco_tripulacion.php', {
      action:    'set_npc_rol',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      entry_id:  entryId,
      rol:       rol,
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok) {
        var npc = BC.npcs.filter(function (n) { return n.id === entryId; })[0];
        if (npc) npc.rol = resp.rol;
        renderTripulacion();
      } else {
        alert(resp.error || 'Error al asignar rol.');
      }
    });
  };

  window.baiToggleRangoDrop = function (tripId) {
    var drop = document.getElementById('bai-rdrop-' + tripId);
    if (!drop) return;
    var menu = drop.querySelector('.bai-rango-dropmenu');
    if (!menu) return;
    var willOpen = menu.style.display === 'none';
    document.querySelectorAll('.bai-rango-dropmenu').forEach(function (m) { m.style.display = 'none'; });
    if (willOpen) menu.style.display = 'flex';
  };

  window.baiAsignarRango = function (tripId) {
    var checkboxes = document.querySelectorAll('[data-trip="' + tripId + '"]');
    var rangos = [];
    checkboxes.forEach(function (cb) { if (cb.checked) rangos.push(cb.value); });
    ajax('/op/barco_tripulacion.php', {
      action:    'asignar_rango',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      trip_id:   tripId,
      rangos:    rangos.join(','),
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok) {
        var m = BC.tripulacion.filter(function (x) { return x.id == tripId; })[0];
        if (m) m.rango = resp.rango;
        renderTripulacion();
      } else {
        alert(resp.error || 'Error al asignar rango.');
      }
    });
  };

  window.baiAsignarRangoCapitan = function () {
    var checkboxes = document.querySelectorAll('[data-trip="capitan"]');
    var rangos = [];
    checkboxes.forEach(function (cb) { if (cb.checked) rangos.push(cb.value); });
    ajax('/op/barco_tripulacion.php', {
      action:    'asignar_rango_capitan',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      rangos:    rangos.join(','),
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok) {
        BC.ownerRangos = resp.rango;
        renderTripulacion();
      } else {
        alert(resp.error || 'Error al asignar rango.');
      }
    });
  };

  window.baiAbandonarBarco = function () {
    if (!confirm('¿Abandonar este barco?')) return;

    ajax('/op/barco_tripulacion.php', {
      action:    'abandonar',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok) {
        BC.tripulacion = BC.tripulacion.filter(function (m) { return m.miembro_uid !== BC.viewerUid; });
        renderTripulacion();
      } else {
        alert(resp.error || 'Error al abandonar.');
      }
    });
  };

  window.baiExpulsarTripulante = function (tripId, nombre) {
    if (!confirm('¿Expulsar a ' + nombre + ' de la tripulación?')) return;

    ajax('/op/barco_tripulacion.php', {
      action:    'expulsar',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      trip_id:   tripId,
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok) {
        BC.tripulacion = BC.tripulacion.filter(function (m) { return m.id !== tripId; });
        renderTripulacion();
      } else {
        alert(resp.error || 'Error al expulsar.');
      }
    });
  };

  /* ── Selección: tab switching (Mis Barcos / Muelle / Historial / Viaje / Staff) ── */
  window.baSwitchMainTab = function (tab) {
    var panels = ['barcos', 'muelle', 'historial', 'viaje', 'staff'];
    panels.forEach(function (t) {
      var panel = document.getElementById('ba-' + t + '-panel');
      if (panel) panel.style.display = (t === tab) ? '' : 'none';
    });
    var btns = document.querySelectorAll('.ba-tab-btn');
    btns.forEach(function (b) {
      var m = (b.getAttribute('onclick') || '').match(/baSwitchMainTab\('([^']+)'\)/);
      var active = m && m[1] === tab;
      b.classList[active ? 'add' : 'remove']('ba-tab-active');
      b.style.border      = '2px solid #000';
      b.style.background  = active ? '#ff8900' : '';
      b.style.color       = active ? '#fff' : '';
    });
    if (tab === 'staff') renderBarcoStaff();
  };

  /* ── Staff: gestión de barcos únicos ────────────────────────────────────── */
  var _buEditMode = false;
  var _buLista    = [];

  function renderBarcoStaff() {
    var panel = document.getElementById('ba-staff-panel');
    if (!panel || typeof BS === 'undefined') return;
    if (panel.dataset.loaded) { _buRenderLista(); return; }
    panel.dataset.loaded = '1';
    panel.innerHTML = '<div class="ba-empty">Cargando&hellip;</div>';
    ajax('/op/barco_unico.php', { action: 'listar' }, function (resp) {
      if (!resp.ok) { panel.innerHTML = '<div class="ba-empty">Error: ' + esc(resp.error || 'Error al cargar.') + '</div>'; return; }
      _buLista = resp.barcos || [];
      _buRenderPanel();
    });
  }

  function _buRenderPanel() {
    var panel = document.getElementById('ba-staff-panel');
    if (!panel) return;
    panel.innerHTML =
      '<div class="ba-section-title" style="margin-bottom:14px;">&#x1F527; Taller de Barcos</div>' +
      '<div id="bu-form-wrap"></div>' +
      '<div id="bu-lista-wrap" style="margin-top:18px;"></div>';
    _buRenderForm(null);
    _buRenderLista();
  }

  function _buRenderForm(barco) {
    var wrap = document.getElementById('bu-form-wrap');
    if (!wrap) return;
    var editing = !!barco;
    var v = barco || { barco_id:'', nombre_barco:'', vitalidad:0, espacios:0, velocidad:0, tiempo_viaje:0, resistencia:0, espacios_mejora:0, ruputura:0, imagen:'', descripcion:'', oficio:'Carpintero', nivel:1, berriesCrafteo:0, crafteo_usuarios:'' };

    wrap.innerHTML =
      '<div style="background:linear-gradient(135deg,#ffe8c2,#ffdca3);border:2px solid rgba(143,89,247,.3);border-radius:6px;padding:16px 18px;">' +
        '<div style="font-weight:bold;color:#ff8900;margin-bottom:12px;font-size:13px;">' +
          (editing ? '&#x270F; Editando: ' + esc(v.barco_id) : '&#x2795; Nuevo barco') +
        '</div>' +
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">' +
          _buField('ID del barco', 'bu-id', v.barco_id, editing ? 'readonly' : '', 'text') +
          _buField('Nombre', 'bu-nombre', v.nombre_barco, '', 'text') +
          _buField('Vitalidad', 'bu-vitalidad', v.vitalidad, '', 'number') +
          _buField('Espacios de carga', 'bu-espacios', v.espacios, '', 'number') +
          _buField('Velocidad', 'bu-velocidad', v.velocidad, '', 'number') +
          _buField('Tiempo viaje (h)', 'bu-tiempo_viaje', v.tiempo_viaje, '', 'number') +
          _buField('Resistencia', 'bu-resistencia', v.resistencia, '', 'number') +
          _buField('Espacios de mejora', 'bu-espacios_mejora', v.espacios_mejora, '', 'number') +
          _buField('Ruptura', 'bu-ruputura', v.ruputura, '', 'number') +
          _buField('Imagen (URL)', 'bu-imagen', v.imagen, '', 'text') +
          _buField('Oficio requerido', 'bu-oficio', v.oficio, '', 'text') +
          _buField('Nivel requerido', 'bu-nivel', v.nivel, '', 'number') +
          _buField('Coste crafteo (berries)', 'bu-berriesCrafteo', v.berriesCrafteo, '', 'number') +
          _buField('UIDs exclusivos (CSV)', 'bu-crafteo_usuarios', v.crafteo_usuarios, 'placeholder="Ej: 61,310 — vacío = cualquiera"', 'text') +
        '</div>' +
        '<div style="margin-bottom:10px;">' +
          '<label style="display:block;font-size:11px;color:#6c10ab;margin-bottom:4px;">Descripción</label>' +
          '<textarea id="bu-descripcion" rows="3" style="width:100%;background:#e0d2fd;border:2px solid #8f59f7;border-radius:4px;padding:6px 8px;color:#2c1810;font-size:12px;resize:vertical;box-sizing:border-box;">' + esc(v.descripcion) + '</textarea>' +
        '</div>' +
        '<div style="display:flex;gap:10px;">' +
          '<button type="button" class="ba-viaje-btn" style="font-size:12px;padding:8px 18px;" onclick="buGuardar(\'' + (editing ? 'editar' : 'crear') + '\')">' +
            (editing ? '&#x1F4BE; Guardar cambios' : '&#x2795; Crear barco') +
          '</button>' +
          (editing ? '<button type="button" class="ba-viaje-btn ba-viaje-btn--nuevo" style="font-size:12px;padding:8px 18px;" onclick="buNuevo()">&#x2715; Cancelar</button>' : '') +
        '</div>' +
        '<div id="bu-form-msg" style="margin-top:8px;font-size:12px;"></div>' +
      '</div>';
  }

  function _buField(label, id, val, extra, type) {
    return '<div>' +
      '<label style="display:block;font-size:11px;color:#6c10ab;margin-bottom:4px;">' + label + '</label>' +
      '<input type="' + type + '" id="' + id + '" value="' + esc(String(val)) + '" ' + extra + ' ' +
        'style="width:100%;background:#e0d2fd;border:2px solid #8f59f7;border-radius:4px;padding:5px 8px;color:#2c1810;font-size:12px;box-sizing:border-box;" />' +
      '</div>';
  }

  function _buRenderLista() {
    var wrap = document.getElementById('bu-lista-wrap');
    if (!wrap) return;
    if (!_buLista.length) {
      wrap.innerHTML = '<div class="ba-empty">No hay barcos registrados.</div>';
      return;
    }
    var html = '<div style="font-weight:bold;color:#6c10ab;font-size:12px;margin-bottom:8px;">Barcos existentes (' + _buLista.length + ')</div>' +
      '<div style="overflow-x:auto;">' +
      '<table style="width:100%;border-collapse:collapse;font-size:11px;">' +
      '<thead><tr style="background:#ff8900;color:#fff;">' +
        '<th style="padding:6px 8px;text-align:left;">ID</th>' +
        '<th style="padding:6px 8px;text-align:left;">Nombre</th>' +
        '<th style="padding:6px 8px;text-align:center;">Vit</th>' +
        '<th style="padding:6px 8px;text-align:center;">Carga</th>' +
        '<th style="padding:6px 8px;text-align:center;">Vel</th>' +
        '<th style="padding:6px 8px;text-align:center;">T.Viaje</th>' +
        '<th style="padding:6px 8px;text-align:center;">Res</th>' +
        '<th style="padding:6px 8px;text-align:center;">Mejoras</th>' +
        '<th style="padding:6px 8px;text-align:center;">Rupt</th>' +
        '<th style="padding:6px 8px;text-align:left;">Oficio</th>' +
        '<th style="padding:6px 8px;text-align:center;">Nv</th>' +
        '<th style="padding:6px 8px;text-align:left;">UIDs</th>' +
        '<th style="padding:6px 8px;"></th>' +
      '</tr></thead><tbody>';
    _buLista.forEach(function (b, i) {
      var bg = i % 2 === 0 ? '#fff3e0' : '#ffe8c2';
      html += '<tr style="background:' + bg + ';">' +
        '<td style="padding:5px 8px;font-family:monospace;">' + esc(b.barco_id) + '</td>' +
        '<td style="padding:5px 8px;">' + esc(b.nombre_barco) + '</td>' +
        '<td style="padding:5px 8px;text-align:center;">' + b.vitalidad + '</td>' +
        '<td style="padding:5px 8px;text-align:center;">' + b.espacios + '</td>' +
        '<td style="padding:5px 8px;text-align:center;">' + b.velocidad + '</td>' +
        '<td style="padding:5px 8px;text-align:center;">' + b.tiempo_viaje + '</td>' +
        '<td style="padding:5px 8px;text-align:center;">' + b.resistencia + '</td>' +
        '<td style="padding:5px 8px;text-align:center;">' + b.espacios_mejora + '</td>' +
        '<td style="padding:5px 8px;text-align:center;">' + b.ruputura + '</td>' +
        '<td style="padding:5px 8px;">' + esc(b.oficio || '') + '</td>' +
        '<td style="padding:5px 8px;text-align:center;">' + (b.nivel || 0) + '</td>' +
        '<td style="padding:5px 8px;font-size:10px;color:#6c10ab;">' + esc(b.crafteo_usuarios || '') + '</td>' +
        '<td style="padding:5px 8px;text-align:right;">' +
          '<button type="button" class="ba-viaje-btn" style="font-size:11px;padding:4px 12px;" onclick="buEditar(' + i + ')">&#x270F; Editar</button>' +
        '</td>' +
      '</tr>';
    });
    html += '</tbody></table></div>';
    wrap.innerHTML = html;
  }

  window.buEditar = function (i) {
    _buRenderForm(_buLista[i]);
    document.getElementById('bu-form-wrap').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };

  window.buNuevo = function () { _buRenderForm(null); };

  window.buGuardar = function (action) {
    var id    = (document.getElementById('bu-id')    || {}).value || '';
    var nom   = (document.getElementById('bu-nombre') || {}).value || '';
    if (!id || !nom) { alert('ID y nombre son obligatorios.'); return; }
    var payload = {
      action:          action,
      post_code:       BS.postCode,
      barco_id:        id,
      nombre:          nom,
      vitalidad:       (document.getElementById('bu-vitalidad')       || {}).value || 0,
      espacios:        (document.getElementById('bu-espacios')        || {}).value || 0,
      velocidad:       (document.getElementById('bu-velocidad')       || {}).value || 0,
      tiempo_viaje:    (document.getElementById('bu-tiempo_viaje')    || {}).value || 0,
      resistencia:     (document.getElementById('bu-resistencia')     || {}).value || 0,
      espacios_mejora: (document.getElementById('bu-espacios_mejora') || {}).value || 0,
      ruputura:        (document.getElementById('bu-ruputura')        || {}).value || 0,
      imagen:            (document.getElementById('bu-imagen')            || {}).value || '',
      descripcion:       (document.getElementById('bu-descripcion')       || {}).value || '',
      oficio:            (document.getElementById('bu-oficio')            || {}).value || '',
      nivel:             (document.getElementById('bu-nivel')             || {}).value || 0,
      berriesCrafteo:    (document.getElementById('bu-berriesCrafteo')    || {}).value || 0,
      crafteo_usuarios:  (document.getElementById('bu-crafteo_usuarios')  || {}).value || '',
    };
    var msg = document.getElementById('bu-form-msg');
    if (msg) msg.textContent = 'Guardando…';
    ajax('/op/barco_unico.php', payload, function (resp) {
      if (resp.ok) {
        if (msg) { msg.style.color = '#2a7a2a'; msg.textContent = action === 'crear' ? '✓ Barco creado: ' + resp.barco_id : '✓ Guardado correctamente.'; }
        // Reload list
        var panel = document.getElementById('ba-staff-panel');
        if (panel) delete panel.dataset.loaded;
        ajax('/op/barco_unico.php', { action: 'listar' }, function (r2) {
          _buLista = (r2.ok && r2.barcos) ? r2.barcos : _buLista;
          if (action === 'crear') buNuevo();
          _buRenderLista();
        });
      } else {
        if (msg) { msg.style.color = '#a00'; msg.textContent = resp.error || 'Error desconocido.'; }
      }
    });
  };

  /* ── Viaje solitario ─────────────────────────────────────────── */
  var _SOLO_ISLAS = {
    'East Blue':  ['Conomi Islands','Isla de Rudra','Isla de Dawn','Refugio de Goat','Islas Organ','Isla Momobami','DemonTooth','Tequila Wolf','Isla Kilombo','Islas Gecko','Loguetown','Sabana de Cozia','Reino de Oykot'],
    'North Blue': ['Isla Tortuga','Isla Swallow','Isla de Kuen','Reino de Lvneel','Flevance','Isla de Rakesh','Isla de Ivansk','Skjodheilm','Polo Norte'],
    'South Blue': ['Cliff','Bawic','Reino Black Drum','Isla Kutsukku','Reino de Sorbet','Korinaru','Rubeck','Briss','Libertalia'],
    'West Blue':  ['Iruburu','Las Camps','Ohara','God Valley','Archipielago Tako','Ballywood','Eviland','Daiblum','Kano','Baratie'],
  };
  var _soloPhase  = 1;
  var _soloHoras  = 0;

  function _soloRebuildIslas(mar, selP, selL, valP, valL) {
    var islas = _SOLO_ISLAS[mar] || [];
    [selP, selL].forEach(function (sel, idx) {
      var other = idx === 0 ? valL : valP;
      sel.innerHTML = '<option value="">-- Isla --</option>';
      islas.forEach(function (isla) {
        var opt = document.createElement('option');
        opt.value = isla;
        opt.textContent = isla;
        if (isla === (idx === 0 ? valP : valL)) opt.selected = true;
        if (isla === other) opt.disabled = true;
        sel.appendChild(opt);
      });
    });
  }

  function _soloRecalcLlegada() {
    var elSal = document.getElementById('ba-solo-fecha-sal');
    var elLle = document.getElementById('ba-solo-fecha-lle');
    if (!elSal || !elLle || !_soloHoras) return;
    var diaSalida  = parseInt(elSal.value, 10) || 1;
    var diaLlegada = diaSalida + Math.ceil(_soloHoras / 24);
    if (diaLlegada > 90) diaLlegada = 90;
    elLle.value = diaLlegada;
  }
  window.baSoloRecalcLlegada = _soloRecalcLlegada;

  function _soloFetchHoras(partida, llegada) {
    var el = document.getElementById('ba-solo-horas');
    if (!el) return;
    if (!partida || !llegada || partida === llegada) { el.textContent = '--'; _soloHoras = 0; return; }
    ajax('/op/barco_viajes.php', { action: 'get_horas_solo', partida: partida, llegada: llegada },
      function (r) {
        if (r.horas) {
          _soloHoras = r.horas;
          el.textContent = r.horas + ' h';
          _soloRecalcLlegada();
        }
      });
  }

  function renderViajesSolo() {
    var panel = document.getElementById('ba-viaje-panel');
    if (!panel) return;
    _soloPhase = 1;
    var postCode = (typeof BI !== 'undefined') ? BI.postCode : '';

    panel.innerHTML =
      '<div class="ba-viaje-section-title">&#x2388; Viaje en Solitario</div>' +
      '<div style="background:linear-gradient(135deg,#ffe8c2,#ffdca3);border:2px solid #8f59f7;border-top:0;border-radius:0 0 6px 6px;padding:16px 18px;">' +
        '<div class="ba-viaje-grid">' +
          '<div class="ba-viaje-form-group">' +
            '<label class="ba-viaje-form-label">Mar</label>' +
            '<select id="ba-solo-mar" class="ba-viaje-select">' +
              '<option value="East Blue">East Blue</option>' +
              '<option value="North Blue">North Blue</option>' +
              '<option value="South Blue">South Blue</option>' +
              '<option value="West Blue">West Blue</option>' +
            '</select>' +
          '</div>' +
          '<div class="ba-viaje-form-group">' +
            '<label class="ba-viaje-form-label">Isla de Partida &nbsp;<span id="ba-solo-horas" class="ba-viaje-badge ba-viaje-badge--horas">--</span></label>' +
            '<select id="ba-solo-partida" class="ba-viaje-select"><option value="">-- Partida --</option></select>' +
          '</div>' +
          '<div class="ba-viaje-form-group">' +
            '<label class="ba-viaje-form-label">Isla de Llegada</label>' +
            '<select id="ba-solo-llegada" class="ba-viaje-select"><option value="">-- Llegada --</option></select>' +
          '</div>' +
          '<div class="ba-viaje-form-group">' +
            '<label class="ba-viaje-form-label">Temporada</label>' +
            '<select id="ba-solo-temporada" class="ba-viaje-select">' +
              '<option>Primavera</option><option selected>Verano</option><option>Oto&ntilde;o</option><option>Invierno</option>' +
            '</select>' +
          '</div>' +
          '<div class="ba-viaje-form-group">' +
            '<label class="ba-viaje-form-label">D&iacute;a de salida (IC)</label>' +
            '<input type="number" id="ba-solo-fecha-sal" class="ba-viaje-form-input" min="1" max="90" value="1" oninput="baSoloRecalcLlegada()" />' +
          '</div>' +
          '<div class="ba-viaje-form-group">' +
            '<label class="ba-viaje-form-label">D&iacute;a de llegada (calculado)</label>' +
            '<input type="text" id="ba-solo-fecha-lle" class="ba-viaje-form-input" readonly placeholder="— Elige ruta primero —" style="color:#888;cursor:default;" />' +
          '</div>' +
        '</div>' +
        '<div class="ba-viaje-form-group" style="margin-bottom:16px;">' +
          '<label class="ba-viaje-form-label">Link del post de inicio del viaje</label>' +
          '<input type="url" id="ba-solo-post" class="ba-viaje-form-input" placeholder="https://..." />' +
        '</div>' +
        '<div id="ba-solo-result" style="display:none;margin-bottom:14px;"></div>' +
        '<div style="display:flex;gap:10px;align-items:center;">' +
          '<button type="button" class="ba-viaje-btn" id="ba-solo-btn-roll" onclick="baSoloRoll()">&#x1F3B2; TIRADA DE VIAJE SOLITARIO</button>' +
          '<button type="button" class="ba-viaje-btn ba-viaje-btn--nuevo" style="display:none;" id="ba-solo-btn-nuevo" onclick="renderViajesSolo()">&#x2B; Nuevo Viaje</button>' +
        '</div>' +
      '</div>';

    var selMar = document.getElementById('ba-solo-mar');
    var selP   = document.getElementById('ba-solo-partida');
    var selL   = document.getElementById('ba-solo-llegada');
    _soloRebuildIslas(selMar.value, selP, selL, '', '');

    selMar.onchange = function () {
      _soloRebuildIslas(this.value, selP, selL, '', '');
      _soloHoras = 0;
      var el = document.getElementById('ba-solo-horas'); if (el) el.textContent = '--';
    };
    selP.onchange = function () {
      var mar = selMar.value;
      _soloRebuildIslas(mar, selP, selL, this.value, selL.value);
      _soloFetchHoras(this.value, selL.value);
    };
    selL.onchange = function () {
      var mar = selMar.value;
      _soloRebuildIslas(mar, selP, selL, selP.value, this.value);
      _soloFetchHoras(selP.value, this.value);
    };
  }
  window.renderViajesSolo = renderViajesSolo;

  window.baSoloRoll = function () {
    var mar       = (document.getElementById('ba-solo-mar')        || {}).value || '';
    var partida   = (document.getElementById('ba-solo-partida')    || {}).value || '';
    var llegada   = (document.getElementById('ba-solo-llegada')    || {}).value || '';
    var temporada = (document.getElementById('ba-solo-temporada')  || {}).value || 'Verano';
    var fSal      = (document.getElementById('ba-solo-fecha-sal')  || {}).value || '1';
    var fLle      = (document.getElementById('ba-solo-fecha-lle')  || {}).value || '1';
    var post      = (document.getElementById('ba-solo-post')       || {}).value || '';
    var postCode  = (typeof BI !== 'undefined') ? BI.postCode : '';

    if (!mar || !partida || !llegada) { alert('Selecciona mar, partida y llegada.'); return; }
    if (!fLle) { alert('Selecciona partida y llegada para calcular el día de llegada.'); return; }
    if (!post) { alert('Debes indicar el link del post de inicio del viaje.'); return; }

    var btn = document.getElementById('ba-solo-btn-roll');
    if (btn) { btn.disabled = true; btn.textContent = 'Realizando...'; }

    ajax('/op/barco_viajes.php', {
      action:            'viajar_solo',
      post_code:         postCode,
      mar:               mar,
      partida:           partida,
      llegada:           llegada,
      tiempo:            _soloHoras || 48,
      fecha_salida:      fSal,
      fecha_llegada:     fLle,
      temporada:         temporada,
      post_inicio_viaje: post,
    }, function (r) {
      if (btn) { btn.disabled = false; btn.textContent = '&#x1F3B2; Realizar Viaje'; }
      if (r.error) { alert(r.error); return; }
      var resDiv = document.getElementById('ba-solo-result');
      if (resDiv) {
        resDiv.style.display = '';
        resDiv.innerHTML =
          '<div class="ba-viaje-result-box">' +
            '<div class="ba-viaje-result-row"><span>D20</span><b>' + r.tirada + '</b></div>' +
            '<div class="ba-viaje-result-row"><span>D100 (suceso)</span><b>' + r.dado_naval + '</b></div>' +
            '<div class="ba-viaje-result-row"><span>Resultado</span><b>' + r.resultado + '</b></div>' +
            '<div class="ba-viaje-result-row"><span>Suceso</span><b>' + r.suceso + '</b></div>' +
          '</div>' +
          '<div style="font-family:moonGetHeavy;font-size:11px;color:#fff;background:#ff8900;padding:4px 10px;letter-spacing:1px;text-transform:uppercase;border:2px solid #000;border-bottom:none;">Log del Viaje</div>' +
          '<div class="ba-viaje-log">' + r.log + '</div>';
      }
      var btnN = document.getElementById('ba-solo-btn-nuevo'); if (btnN) btnN.style.display = '';
      if (btn) btn.style.display = 'none';
    });
  };

  /* ── Invitaciones pendientes (selección) ────────────────────── */
  window.baAceptarInvitacion = function (invId, barcoId, ownerUid) {
    var postCode = (typeof BI !== 'undefined') ? BI.postCode : '';
    ajax('/op/barco_tripulacion.php', {
      action:    'aceptar',
      inv_id:    invId,
      barco_id:  barcoId,
      owner_uid: ownerUid,
      post_code: postCode
    }, function (resp) {
      if (resp.ok) {
        window.location.reload();
      } else {
        alert(resp.error || 'Error al aceptar.');
      }
    });
  };

  window.baRechazarInvitacion = function (invId, barcoId, ownerUid) {
    var postCode = (typeof BI !== 'undefined') ? BI.postCode : '';
    ajax('/op/barco_tripulacion.php', {
      action:    'rechazar',
      inv_id:    invId,
      barco_id:  barcoId,
      owner_uid: ownerUid,
      post_code: postCode
    }, function (resp) {
      if (resp.ok) {
        var card = document.getElementById('ba-inv-' + invId);
        if (card) card.remove();
      } else {
        alert(resp.error || 'Error al rechazar.');
      }
    });
  };

  /* ── Editar imagen – interior ──────────────────────────────────── */
  window.baiUpdateImgPreview = function (v) {
    var prev = document.getElementById('bai-img-preview');
    var wrap = document.getElementById('bai-img-preview-wrap');
    if (!prev) return;
    if (v.trim()) { prev.src = v.trim(); wrap.style.display = 'block'; }
    else          { wrap.style.display = 'none'; }
  };

  window.baiAbrirEditarImagen = function () {
    var imgEl   = document.getElementById('bai-header-img-el');
    var current = (imgEl && imgEl.tagName === 'IMG') ? imgEl.getAttribute('src') : '';
    var input   = document.getElementById('bai-img-url-input');
    input.value = current;
    baiUpdateImgPreview(current);
    document.getElementById('bai-modal-img').style.display = 'flex';
  };

  window.baiCerrarEditarImagen = function () {
    document.getElementById('bai-modal-img').style.display = 'none';
  };

  window.baiGuardarImagen = function () {
    var url = document.getElementById('bai-img-url-input').value.trim();
    ajax('/op/barco_cofre.php', {
      action:    'set_imagen',
      barco_id:  BC.barcoId,
      owner_uid: BC.ownerUid,
      imagen:    url,
      post_code: BC.postCode
    }, function (resp) {
      if (resp.ok) {
        var imgEl = document.getElementById('bai-header-img-el');
        if (imgEl) {
          var parent = imgEl.parentNode;
          if (url) {
            var newImg = document.createElement('img');
            newImg.className = 'bai-header-img';
            newImg.id        = 'bai-header-img-el';
            newImg.src       = url;
            parent.replaceChild(newImg, imgEl);
          } else {
            var ph = document.createElement('div');
            ph.className = 'bai-header-img--ph';
            ph.id        = 'bai-header-img-el';
            ph.innerHTML = '&#x2693;';
            parent.replaceChild(ph, imgEl);
          }
        }
        baiCerrarEditarImagen();
      } else {
        alert(resp.error || 'Error al guardar imagen.');
      }
    });
  };

  /* ── Editar imagen – selección ──────────────────────────────────── */
  var _baEditImg = { barcoId: null, ownerUid: null };

  window.baUpdateImgPreview = function (v) {
    var prev = document.getElementById('ba-img-preview');
    var wrap = document.getElementById('ba-img-preview-wrap');
    if (!prev) return;
    if (v.trim()) { prev.src = v.trim(); wrap.style.display = 'block'; }
    else          { wrap.style.display = 'none'; }
  };

  window.baAbrirEditarImagen = function (barcoId, ownerUid) {
    _baEditImg.barcoId  = barcoId;
    _baEditImg.ownerUid = ownerUid;
    var wrap    = document.querySelector('.ba-card-img-wrap[data-barco="' + barcoId + '"]');
    var imgEl   = wrap ? wrap.querySelector('img.ba-card-img') : null;
    var current = imgEl ? imgEl.getAttribute('src') : '';
    var input   = document.getElementById('ba-img-url-input');
    input.value = current;
    baUpdateImgPreview(current);
    document.getElementById('ba-modal-img').style.display = 'flex';
  };

  window.baCerrarEditarImagen = function () {
    document.getElementById('ba-modal-img').style.display = 'none';
  };

  window.baGuardarImagen = function () {
    var url      = document.getElementById('ba-img-url-input').value.trim();
    var postCode = (typeof BI !== 'undefined') ? BI.postCode : '';
    ajax('/op/barco_cofre.php', {
      action:    'set_imagen',
      barco_id:  _baEditImg.barcoId,
      owner_uid: _baEditImg.ownerUid,
      imagen:    url,
      post_code: postCode
    }, function (resp) {
      if (resp.ok) {
        var wrap = document.querySelector('.ba-card-img-wrap[data-barco="' + _baEditImg.barcoId + '"]');
        if (wrap) {
          var old = wrap.querySelector('img.ba-card-img, .ba-card-img--ph');
          if (old) old.remove();
          if (url) {
            var newImg = document.createElement('img');
            newImg.className = 'ba-card-img';
            newImg.src       = url;
            wrap.insertBefore(newImg, wrap.firstChild);
          } else {
            var ph = document.createElement('div');
            ph.className = 'ba-card-img ba-card-img--ph';
            ph.innerHTML = '&#x2693;';
            wrap.insertBefore(ph, wrap.firstChild);
          }
        }
        baCerrarEditarImagen();
      } else {
        alert(resp.error || 'Error al guardar imagen.');
      }
    });
  };

  /* ── Render muelle ───────────────────────────────────────────── */
  function renderMuelle() {
    var panel = document.getElementById('ba-muelle-panel');
    if (!panel || typeof BM === 'undefined') return;

    if (!BM.barcos || !BM.barcos.length) {
      panel.innerHTML = '<div class="ba-muelle-empty">No tienes barcos gen&#xE9;ricos en tu inventario para bautizar.</div>';
      return;
    }

    var html = '<div class="ba-muelle-grid">';
    BM.barcos.forEach(function (b) {
      var imgHtml = b.imagen
        ? '<img class="ba-muelle-img" src="' + esc(b.imagen) + '" alt="" />'
        : '<div class="ba-muelle-img--ph">&#x2693;</div>';
      html +=
        '<div class="ba-muelle-card">' +
          imgHtml +
          '<div class="ba-muelle-info">' +
            '<div class="ba-muelle-tipo">' + esc(b.nombre) + '</div>' +
            '<div class="ba-muelle-cant">x' + esc(b.cantidad) + ' en inventario</div>' +
            '<div class="ba-muelle-form">' +
              '<input type="text" class="ba-muelle-input" maxlength="100" placeholder="Nombre del barco…" />' +
              '<button type="button" class="ba-btn-bautizar"' +
                ' onclick="baBautizarBarco(\'' + esc(b.objeto_id) + '\', this)">' +
                '&#x2693; Bautizar' +
              '</button>' +
            '</div>' +
          '</div>' +
        '</div>';
    });
    html += '</div>';
    panel.innerHTML = html;
  }

  /* ── Bautizar barco ──────────────────────────────────────────── */
  window.baBautizarBarco = function (barcoId, btn) {
    var card   = btn.closest('.ba-muelle-card');
    var input  = card.querySelector('.ba-muelle-input');
    var nombre = input.value.trim();
    if (!nombre) { alert('Escribe un nombre para el barco.'); input.focus(); return; }

    btn.disabled = true;
    btn.textContent = '...';

    ajax('/op/barco_bautizar.php', {
      barco_base_id: barcoId,
      nombre:        nombre,
      post_code:     BM.postCode
    }, function (resp) {
      if (resp.ok) {
        for (var i = 0; i < BM.barcos.length; i++) {
          if (BM.barcos[i].objeto_id === barcoId) {
            BM.barcos[i].cantidad -= 1;
            if (BM.barcos[i].cantidad <= 0) { BM.barcos.splice(i, 1); }
            break;
          }
        }
        renderMuelle();
        var panel = document.getElementById('ba-muelle-panel');
        var msg   = document.createElement('div');
        msg.className = 'ba-bautismo-ok';
        msg.innerHTML = '&#x2693; <strong>' + esc(resp.nombre) + '</strong> bautizado. ID: <code>' + esc(resp.nuevo_id) + '</code>';
        panel.insertBefore(msg, panel.firstChild);
        setTimeout(function () { if (msg.parentNode) msg.parentNode.removeChild(msg); }, 6000);
      } else {
        alert(resp.error || 'Error al bautizar.');
        btn.disabled = false;
        btn.textContent = '⚓ Bautizar';
      }
    });
  };

  /* ── Cerrar modales al hacer clic fuera ──────────────────────── */
  document.addEventListener('click', function (e) {
    if (e.target.classList.contains('bai-modal-overlay') || e.target.classList.contains('ba-modal-overlay')) {
      e.target.style.display = 'none';
    }
  });

  /* ══════════════════════════════════════════════════════════════
     Viajes Tab
     ══════════════════════════════════════════════════════════════ */

  var _MARES_ISLAS = {
    'East Blue':  ['Conomi Islands','Isla de Rudra','Isla de Dawn','Refugio de Goat','Islas Organ','Isla Momobami','DemonTooth','Tequila Wolf','Isla Kilombo','Islas Gecko','Loguetown','Sabana de Cozia','Reino de Oykot'],
    'North Blue': ['Isla Tortuga','Isla Swallow','Isla de Kuen','Reino de Lvneel','Flevance','Isla de Rakesh','Isla de Ivansk','Skjodheilm','Polo Norte'],
    'South Blue': ['Cliff','Bawic','Reino Black Drum','Isla Kutsukku','Reino de Sorbet','Korinaru','Rubeck','Briss','Libertalia'],
    'West Blue':  ['Iruburu','Las Camps','Ohara','God Valley','Archipielago Tako','Ballywood','Eviland','Daiblum','Kano','Baratie']
  };

  // State kept across render phases
  var _viajeData   = null; // last get_data response
  var _viajePhase  = 1;    // 1=form, 2=calcular results, 3=after roll

  function renderViajes() {
    var panel = document.getElementById('bai-viajes-panel');
    if (!panel) return;
    _viajeData  = null;
    _viajePhase = 1;
    panel.innerHTML = _bvFormHtml();
    _bvBindFormEvents();
  }

  /* ── Phase-1 HTML: sea / islands / travelers form ── */
  function _bvFormHtml() {
    var crewStr = Array.isArray(BC.crewUids) ? BC.crewUids.join(',') : String(BC.crewUids || '');
    var maresOpts = Object.keys(_MARES_ISLAS).map(function (m) {
      return '<option value="' + esc(m) + '">' + esc(m) + '</option>';
    }).join('');

    return '<div class="bai-viaje-section">' +
             '<div class="bai-viaje-section-title">&#x2388; Planificar Viaje</div>' +
             '<div class="bai-viaje-grid">' +
               '<div class="bai-form-group">' +
                 '<label class="bai-form-label">Mar</label>' +
                 '<select id="bvi-mar" class="bai-viaje-select" onchange="baiViajeMarChanged()">' +
                   '<option value="">— Selecciona —</option>' + maresOpts +
                 '</select>' +
               '</div>' +
               '<div class="bai-form-group">' +
                 '<label class="bai-form-label">Temporada</label>' +
                 '<select id="bvi-temporada" class="bai-viaje-select">' +
                   '<option>Verano</option><option>Oto\u00f1o</option><option>Invierno</option><option>Primavera</option>' +
                 '</select>' +
               '</div>' +
             '</div>' +
             '<div class="bai-viaje-grid">' +
               '<div class="bai-form-group">' +
                 '<label class="bai-form-label">Isla de Partida</label>' +
                 '<select id="bvi-partida" class="bai-viaje-select"><option value="">— Elige mar primero —</option></select>' +
               '</div>' +
               '<div class="bai-form-group">' +
                 '<label class="bai-form-label">Isla de Llegada</label>' +
                 '<select id="bvi-llegada" class="bai-viaje-select"><option value="">— Elige mar primero —</option></select>' +
               '</div>' +
             '</div>' +
             '<div class="bai-viaje-grid">' +
               '<div class="bai-form-group">' +
                 '<label class="bai-form-label">D\u00eda de Salida</label>' +
                 '<input type="number" id="bvi-dia-salida" class="bai-form-input" min="1" max="90" value="1" oninput="baiRecalcViaje()" />' +
               '</div>' +
               '<div class="bai-form-group">' +
                 '<label class="bai-form-label">D\u00eda de Llegada (calculado)</label>' +
                 '<input type="text" id="bvi-dia-llegada" class="bai-form-input" readonly placeholder="\u2014 Calcula la ruta primero \u2014" style="color:#666;cursor:default;" />' +
               '</div>' +
             '</div>' +
             '<div class="bai-form-group" style="margin-bottom:10px;">' +
               '<label class="bai-form-label">Viajeros (UIDs separados por comas)</label>' +
               '<input type="text" id="bvi-viajeros" class="bai-form-input" value="' + esc(crewStr) + '" />' +
             '</div>' +
             '<div class="bai-viaje-checkbox-row">' +
               '<input type="checkbox" id="bvi-npc" />' +
               '<label for="bvi-npc">Hay un Navegante NPC a bordo</label>' +
             '</div>' +
             '<div style="margin-top:12px;">' +
               '<button type="button" class="bai-btn bai-btn-deposit" onclick="baiCalcularViaje()">&#x1F9ED; Calcular Ruta</button>' +
             '</div>' +
           '</div>' +
           '<div id="bvi-results"></div>' +
           '<div class="bai-viaje-section" id="bvi-hist-section" style="display:none;">' +
             '<div class="bai-viaje-section-title">&#x1F4DC; Historial de Viajes</div>' +
             '<div id="bvi-historial"></div>' +
           '</div>';
  }

  function _bvBindFormEvents() { /* events already wired via inline onclick */ }

  /* ── Populate island dropdowns when sea changes ── */
  window.baiViajeMarChanged = function () {
    var mar     = document.getElementById('bvi-mar');
    var partida = document.getElementById('bvi-partida');
    var llegada = document.getElementById('bvi-llegada');
    if (!mar || !partida || !llegada) return;
    var islas = _MARES_ISLAS[mar.value] || [];
    var opts = islas.map(function (i) { return '<option value="' + esc(i) + '">' + esc(i) + '</option>'; }).join('');
    var empty = '<option value="">— Selecciona —</option>';
    partida.innerHTML = empty + opts;
    llegada.innerHTML = empty + opts;
  };

  /* ── Calcular Ruta: AJAX to get_data ── */
  window.baiCalcularViaje = function () {
    var mar      = (document.getElementById('bvi-mar')      || {}).value || '';
    var partida  = (document.getElementById('bvi-partida')  || {}).value || '';
    var llegada  = (document.getElementById('bvi-llegada')  || {}).value || '';
    var viajeros = (document.getElementById('bvi-viajeros') || {}).value || '';

    if (!mar)     { alert('Selecciona el mar.'); return; }
    if (!partida) { alert('Selecciona la isla de partida.'); return; }
    if (!llegada) { alert('Selecciona la isla de llegada.'); return; }
    if (partida === llegada) { alert('\u00a1No puedes viajar a la misma isla!'); return; }
    if (!viajeros.trim()) { alert('La lista de viajeros no puede estar vac\u00eda.'); return; }

    var resultsDiv = document.getElementById('bvi-results');
    if (resultsDiv) { resultsDiv.innerHTML = '<div class="bai-empty-small">Calculando\u2026</div>'; }

    ajax('/op/barco_viajes.php', {
      action: 'get_data', barco_id: BC.barcoId, owner_uid: BC.ownerUid,
      mar: mar, partida: partida, llegada: llegada
    }, function (resp) {
      if (resp.error) { alert(resp.error); if (resultsDiv) resultsDiv.innerHTML = ''; return; }
      _viajeData = resp;
      _bvRenderResults(resp, mar, partida, llegada, viajeros);
    });
  };

  /* ── Compute brujula bonus client-side ── */
  function _bvBrujulaBonus(brujula, mar) {
    if (!brujula) return 0;
    var nombre = (brujula.nombre || '').toLowerCase();
    var bonus;
    if (brujula.efectoBonus > 0) {
      bonus = brujula.efectoBonus;
    } else if (nombre.indexOf('eternal') !== -1) {
      bonus = 4;
    } else if (nombre.indexOf('log pose') !== -1) {
      bonus = 2;
    } else {
      bonus = 1;
    }
    if (mar && nombre.indexOf(mar.toLowerCase()) !== -1) { bonus += 1; }
    return bonus;
  }

  /* ── Recompute modifier display ── */
  window.baiRecalcViaje = function () {
    if (!_viajeData) return;
    var resp     = _viajeData;
    var npc      = !!(document.getElementById('bvi-npc') && document.getElementById('bvi-npc').checked);
    var mapaEl   = document.getElementById('bvi-mapa');
    var brujulaEl= document.getElementById('bvi-brujula');
    var mar      = (document.getElementById('bvi-mar') || {}).value || '';

    // mapa bonus (roll) and hora reduction — stored in data attributes
    var mapaBonus = 0;
    var mapaHorasReduccion = 0;
    if (mapaEl && mapaEl.value) {
      mapaBonus         = parseInt(mapaEl.options[mapaEl.selectedIndex].getAttribute('data-bonus')  || '0', 10);
      mapaHorasReduccion = parseInt(mapaEl.options[mapaEl.selectedIndex].getAttribute('data-horas') || '0', 10);
    }

    // brujula bonus and hour reduction
    var brujulaBonus = 0;
    var brujulaHorasReduccion = 0;
    if (brujulaEl && brujulaEl.value) {
      var selBrujula = null;
      for (var b = 0; b < resp.brujulas.length; b++) {
        if (resp.brujulas[b].objeto_id === brujulaEl.value) { selBrujula = resp.brujulas[b]; break; }
      }
      brujulaBonus          = _bvBrujulaBonus(selBrujula, mar);
      brujulaHorasReduccion = (selBrujula && selBrujula.horasReduccion) ? selBrujula.horasReduccion : 0;
    }

    var boniOficio = npc ? 0 : resp.bonificadorOficio;
    var modifier   = boniOficio + mapaBonus + brujulaBonus + (npc ? 6 : 0) + (resp.bonusFruta || 0);

    var modEl = document.getElementById('bvi-mod-display');
    if (modEl) { modEl.textContent = '+' + modifier; }

    // Time reduction from nav dropdown then flat map reduction
    var navRedEl = document.getElementById('bvi-nav-pct');
    var navPct   = navRedEl ? parseInt(navRedEl.value || '0', 10) : 0;
    var horas    = resp.horasConBarco;
    if (navPct > 0) { horas = Math.max(1, Math.round(horas * (1 - navPct / 100))); }
    if (mapaHorasReduccion    > 0) { horas = Math.max(1, horas - mapaHorasReduccion); }
    if (brujulaHorasReduccion > 0) { horas = Math.max(1, horas - brujulaHorasReduccion); }

    var horasEl = document.getElementById('bvi-horas-display');
    if (horasEl) { horasEl.textContent = horas + ' h'; }

    // Store computed modifier for the travel submit
    var modHidden = document.getElementById('bvi-mod-final');
    if (modHidden) { modHidden.value = modifier; }
    var horasHidden = document.getElementById('bvi-horas-final');
    if (horasHidden) { horasHidden.value = horas; }

    // Auto-calculate arrival day: ceil(horas / 24) days after departure
    var diaSalidaEl  = document.getElementById('bvi-dia-salida');
    var diaLlegadaEl = document.getElementById('bvi-dia-llegada');
    if (diaSalidaEl && diaLlegadaEl) {
      var diasViaje  = Math.ceil(horas / 24);
      var diaSalida  = parseInt(diaSalidaEl.value, 10) || 1;
      var diaLlegada = diaSalida + diasViaje;
      if (diaLlegada > 90) diaLlegada = 90;
      diaLlegadaEl.value = diaLlegada;
    }
  };

  /* ── Render phase-2 results after Calcular ── */
  function _bvRenderResults(resp, mar, partida, llegada, viajeros) {
    var resultsDiv = document.getElementById('bvi-results');
    if (!resultsDiv) return;

    // Navigator level description
    var navDesc = 'Sin navegante';
    if (resp.maxNavegante == 1)     navDesc = 'Navegante Nv 1';
    else if (resp.maxNavegante == 2) navDesc = 'Navegante Nv 2';
    else if (resp.maxNavegante == 3) navDesc = 'Navegante Nv 3';
    else if (resp.maxNavegante == 4) navDesc = 'Navegante Nv 4';
    else if (resp.maxNavegante >= 5) navDesc = 'Navegante M\u00e1ximo';
    if (resp.maxTimonel) { navDesc += ' / Timonel Nv ' + resp.maxTimonel; }
    if (resp.maxCartografo) { navDesc += ' / Cart\u00f3grafo Nv ' + resp.maxCartografo; }

    // Nav reduction dropdown options
    var navOptions = '<option value="0">Sin reducci\u00f3n</option>';
    if (resp.maxNavegante >= 2)  navOptions += '<option value="5">Navegante Nv 2 (\u22125%)</option>';
    if (resp.maxTimonel >= 1)    navOptions += '<option value="20">Timonel Nv 1 (\u221220%)</option>';
    if (resp.maxTimonel >= 2)    navOptions += '<option value="30">Timonel Nv 2 (\u221230%)</option>';
    if (resp.maxTimonel >= 3)    navOptions += '<option value="40">Timonel Nv 3 (\u221240%)</option>';
    if (resp.maxNavegante >= 5)  navOptions += '<option value="50">Navegante M\u00e1ximo (\u221250%)</option>';
    var selNav = (resp.maxNavegante >= 5) ? 50 : (resp.maxTimonel >= 3) ? 40 : (resp.maxTimonel >= 2) ? 30 : (resp.maxTimonel >= 1) ? 20 : (resp.maxNavegante >= 2) ? 5 : 0;

    // Maps options
    var mapaOpts = '<option value="" data-bonus="0" data-horas="0">Sin mapa</option>';
    (resp.mapas || []).forEach(function (m) {
      var label = esc(m.nombre);
      if (m.mapaBonus)       label += ' (+' + m.mapaBonus + ')';
      if (m.horasReduccion)  label += ' (−' + m.horasReduccion + 'h)';
      mapaOpts += '<option value="' + esc(m.objeto_id) + '" data-bonus="' + (m.mapaBonus || 0) + '" data-horas="' + (m.horasReduccion || 0) + '">' + label + '</option>';
    });

    // Compasses options
    var brujulaOpts = '<option value="">Sin br\u00fajula</option>';
    (resp.brujulas || []).forEach(function (b) {
      var label = esc(b.nombre);
      if (b.efectoBonus)    label += ' (+' + b.efectoBonus + ')';
      if (b.horasReduccion) label += ' (\u2212' + b.horasReduccion + 'h)';
      brujulaOpts += '<option value="' + esc(b.objeto_id) + '">' + label + '</option>';
    });

    var initialMod = resp.bonificadorOficio + (resp.bonusFruta || 0);
    var initialHoras = resp.horasConBarco;

    resultsDiv.innerHTML =
      '<hr style="border-color:#2a2a4a;margin:16px 0;" />' +
      '<div class="bai-viaje-section">' +
        '<div class="bai-viaje-section-title">&#x1F4CD; Resultados del C\u00e1lculo</div>' +
        '<div class="bai-viaje-result-box">' +
          '<div class="bai-viaje-result-row"><span>Ruta</span><b>' + esc(partida) + ' \u2192 ' + esc(llegada) + '</b></div>' +
          '<div class="bai-viaje-result-row"><span>Horas base</span><b>' + resp.horasBase + ' h</b></div>' +
          '<div class="bai-viaje-result-row"><span>Horas con barco</span><b>' + resp.horasConBarco + ' h</b></div>' +
          '<div class="bai-viaje-result-row"><span>Navegante</span><b>' + esc(navDesc) + '</b></div>' +
          '<div class="bai-viaje-result-row"><span>Bonif. oficio</span><b>+' + resp.bonificadorOficio + '</b></div>' +
          (resp.bonusFruta ? '<div class="bai-viaje-result-row"><span>Shipu Shipu no Mi</span><b>+' + resp.bonusFruta + '</b></div>' : '') +
        '</div>' +
        '<div class="bai-viaje-grid">' +
          '<div class="bai-form-group">' +
            '<label class="bai-form-label">Reducci\u00f3n de tiempo</label>' +
            '<select id="bvi-nav-pct" class="bai-viaje-select" onchange="baiRecalcViaje()">' + navOptions + '</select>' +
          '</div>' +
          '<div class="bai-form-group">' +
            '<label class="bai-form-label">Mapa</label>' +
            '<select id="bvi-mapa" class="bai-viaje-select" onchange="baiRecalcViaje()">' + mapaOpts + '</select>' +
          '</div>' +
        '</div>' +
        '<div class="bai-viaje-grid">' +
          '<div class="bai-form-group">' +
            '<label class="bai-form-label">Br\u00fajula</label>' +
            '<select id="bvi-brujula" class="bai-viaje-select" onchange="baiRecalcViaje()">' + brujulaOpts + '</select>' +
          '</div>' +
          '<div class="bai-form-group">' +
            '<label class="bai-form-label">Post de inicio del viaje (URL)</label>' +
            '<input type="text" id="bvi-post-inicio" class="bai-form-input" placeholder="https://..." />' +
          '</div>' +
        '</div>' +
        '<div class="bai-viaje-result-box" style="margin-top:10px;display:flex;gap:20px;align-items:center;flex-wrap:wrap;">' +
          '<span class="bai-form-label">Modificador total</span>' +
          '<span class="bai-viaje-badge bai-viaje-badge--mod" id="bvi-mod-display">+' + initialMod + '</span>' +
          '<span class="bai-form-label" style="margin-left:10px;">Tiempo de viaje</span>' +
          '<span class="bai-viaje-badge bai-viaje-badge--horas" id="bvi-horas-display">' + initialHoras + ' h</span>' +
        '</div>' +
        '<input type="hidden" id="bvi-mod-final" value="' + initialMod + '" />' +
        '<input type="hidden" id="bvi-horas-final" value="' + initialHoras + '" />' +
        '<div style="margin-top:14px;">' +
          '<button type="button" class="bai-btn bai-btn-deposit" onclick="baiRealizarViaje()">&#x1F3B2; Tirada de Viaje</button>' +
        '</div>' +
        '<div id="bvi-roll-result" style="margin-top:10px;"></div>' +
      '</div>';

    // Set nav reduction to best available and recalc
    var navPctEl = document.getElementById('bvi-nav-pct');
    if (navPctEl) { navPctEl.value = selNav; }
    baiRecalcViaje();

    // Render history
    _bvRenderHistorial(resp.historial || []);
  }

  /* ── Historial ── */
  function _bvRenderHistorial(historial) {
    var histSec = document.getElementById('bvi-hist-section');
    var histDiv = document.getElementById('bvi-historial');
    if (!histSec || !histDiv) return;

    if (!historial.length) {
      histDiv.innerHTML = '<div class="bai-empty-small">No hay viajes registrados.</div>';
    } else {
      var html = '';
      historial.forEach(function (v) {
        var fecha = v.fecha_salida ? 'D\u00eda ' + esc(v.fecha_salida) + ' de ' + esc(v.temporada || '') : '';
        html += '<div class="bai-viaje-hist-item">' +
                  '<div class="bai-viaje-hist-route">' +
                    esc(v.partida || '?') + ' \u2192 ' + esc(v.llegada || '?') +
                    ' <span class="bai-viaje-badge" style="font-size:10px;">' + esc(v.mar || '') + '</span>' +
                  '</div>' +
                  (fecha ? '<div>' + fecha + '</div>' : '') +
                  '<div style="margin-top:4px;">' + (v.log ? v.log.substring(0, 200).replace(/\n/g, ' ') + (v.log.length > 200 ? '&hellip;' : '') : '') + '</div>' +
                '</div>';
      });
      histDiv.innerHTML = html;
    }
    histSec.style.display = '';
  }

  /* ── Tirada de Viaje: POST viajar ── */
  window.baiRealizarViaje = function () {
    if (!_viajeData) { alert('Primero calcula la ruta.'); return; }

    var mar         = (document.getElementById('bvi-mar')         || {}).value || '';
    var partida     = (document.getElementById('bvi-partida')     || {}).value || '';
    var llegada     = (document.getElementById('bvi-llegada')     || {}).value || '';
    var viajeros    = (document.getElementById('bvi-viajeros')    || {}).value || '';
    var diaSalida   = (document.getElementById('bvi-dia-salida')  || {}).value || '1';
    var diaLlegada  = (document.getElementById('bvi-dia-llegada') || {}).value || '1';
    var temporada   = (document.getElementById('bvi-temporada')   || {}).value || 'Verano';
    var npc         = !!(document.getElementById('bvi-npc') && document.getElementById('bvi-npc').checked);
    var postInicio  = (document.getElementById('bvi-post-inicio') || {}).value || '';
    var mod         = (document.getElementById('bvi-mod-final')   || {}).value || '0';
    var horas       = (document.getElementById('bvi-horas-final') || {}).value || '48';
    var mapaEl      = document.getElementById('bvi-mapa');
    var mapaNombre  = mapaEl && mapaEl.value ? mapaEl.options[mapaEl.selectedIndex].text : '';
    // Strip the bonus annotation like " (+4)" from the option text
    mapaNombre = mapaNombre.replace(/\s*\(\+\d+\)\s*$/, '');

    if (!postInicio.trim()) { alert('Debes introducir el link del post de inicio del viaje.'); return; }

    var rollBtn = document.querySelector('[onclick="baiRealizarViaje()"]');
    if (rollBtn) { rollBtn.disabled = true; rollBtn.textContent = 'Realizando tirada\u2026'; }

    ajax('/op/barco_viajes.php', {
      action:           'viajar',
      barco_id:         BC.barcoId,
      owner_uid:        BC.ownerUid,
      post_code:        BC.postCode,
      mar:              mar,
      partida:          partida,
      llegada:          llegada,
      dificultad:       '10',
      modificador:      mod,
      tiempo:           horas,
      id_viajeros:      viajeros,
      barco_nombre:     _viajeData.barcoNombre || BC.barcoId,
      navegante_npc:    npc ? 'Si' : 'No',
      fecha_salida:     diaSalida,
      fecha_llegada:    diaLlegada,
      temporada:        temporada,
      mapa_nombre:      mapaNombre,
      post_inicio_viaje: postInicio
    }, function (resp) {
      if (rollBtn) { rollBtn.disabled = false; rollBtn.textContent = '\u1F3B2 Tirada de Viaje'; }
      var resultDiv = document.getElementById('bvi-roll-result');
      if (resp.error) {
        if (resultDiv) { resultDiv.innerHTML = '<div class="bai-error">' + esc(resp.error) + '</div>'; }
        return;
      }
      var html =
        '<div class="bai-viaje-result-box">' +
          '<div class="bai-viaje-result-row"><span>D20</span><b>' + resp.tirada + '</b></div>' +
          '<div class="bai-viaje-result-row"><span>Suceso naval</span><b>' + esc(resp.suceso || '') + '</b></div>' +
          '<div class="bai-viaje-result-row"><span>Resultado</span><b>' + (resp.resultado || '') + '</b></div>' +
        '</div>' +
        '<div class="bai-viaje-log">' + (resp.log || '') + '</div>';
      if (resultDiv) { resultDiv.innerHTML = html; }
    });
  };

  /* ── Init ────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof BC !== 'undefined') {
      if (!BC.canCofre) {
        // Ocultar pestaña y panel del cofre; activar salas por defecto
        var cofrePanel = document.getElementById('bai-cofre-panel');
        if (cofrePanel) cofrePanel.style.display = 'none';
        var btns = document.querySelectorAll('.bai-tab-btn');
        if (btns[0]) { btns[0].style.display = 'none'; }
        baiSwitchTab('salas');
      } else {
        renderCofre();
      }
      renderSalas();
      renderTripulacion();
      if (BC.isNavegante) { renderViajes(); }
    }
    if (typeof BM !== 'undefined') {
      renderMuelle();
    }
    if (typeof BI !== 'undefined') {
      renderViajesSolo();
    }
  });

})();
