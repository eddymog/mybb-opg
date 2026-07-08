(function () {
  'use strict';

  var BC = {
    armasSeleccionadas: [],
    tecnicaSeleccionada: null,
    nivelPersonaje: 0,
    modoCombate: 'ataque',
    _data: null,
    _initialized: false,
    acciones: [],
    _accionCounter: 0,

    _nombreArma: function (i) {
      var nombres = ['PRINCIPAL', 'SECUNDARIA', 'TERCIARIA'];
      return i < nombres.length ? nombres[i] : (i + 1) + 'ª ARMA';
    },

    // ── HTML generation ────────────────────────────────────────────────────────

    _createHTML: function () {
      var stats = ['NIV', 'FUE', 'AGI', 'PUN', 'DES', 'RES', 'REF', 'VOL'];
      var statRows = '';
      for (var i = 0; i < stats.length; i++) {
        var s = stats[i];
        var border = i < stats.length - 1 ? 'border-bottom:1px solid #eee;' : '';
        statRows +=
          '<div style="display:flex;height:23px;' + border + '">' +
            '<div style="width:50px;text-align:center;line-height:23px;font-family:moonGetHeavy;font-size:12px;background:#f0f0f0;color:#111;">' + s + '</div>' +
            '<div id="bc-atributo-base-' + s + '" style="width:80px;text-align:center;line-height:23px;font-family:moonGetHeavy;font-size:14px;color:#111;">-</div>' +
            '<div id="bc-modificador-' + s + '" style="width:80px;text-align:center;line-height:23px;color:#111;">0</div>' +
            '<div id="bc-atributo-final-' + s + '" style="width:70px;text-align:center;line-height:23px;font-family:moonGetHeavy;font-size:14px;color:#111;">-</div>' +
          '</div>';
      }

      var col1 =
        '<div style="display:flex;flex-direction:column;">' +
          '<div style="width:200px;height:38px;text-align:center;background:#0055bb;border-radius:4px 4px 0 0;">' +
            '<span id="bc-label-golpe-basico" style="font-family:moonGetHeavy;color:#fff;font-size:16px;line-height:38px;letter-spacing:1px;">GOLPE BÁSICO</span>' +
          '</div>' +
          '<div style="width:200px;min-height:55px;background:rgba(255,255,255,.9);text-align:center;border:2px solid #555;border-top:none;margin-bottom:10px;padding:2px 0;">' +
            '<div><span id="bc-golpe-basico-dano" style="color:#111;font-size:24px;font-family:moonGetHeavy;">0</span></div>' +
            '<div><span id="bc-golpe-basico-tipo" style="color:#111;font-size:11px;font-family:moonGetHeavy;">DE DAÑO CONTUNDENTE</span></div>' +
          '</div>' +
          '<div style="width:200px;height:38px;text-align:center;background:#853dee;border-radius:4px 4px 0 0;">' +
            '<span id="bc-label-dano-tecnica" style="font-family:moonGetHeavy;color:#fff;font-size:14px;line-height:38px;letter-spacing:1px;">DAÑO TÉCNICA</span>' +
          '</div>' +
          '<div style="width:200px;min-height:55px;background:rgba(255,255,255,.9);text-align:center;border:2px solid #555;border-top:none;margin-bottom:10px;padding:2px 0;">' +
            '<div><span id="bc-dano-tecnica-valor" style="color:#111;font-size:24px;font-family:moonGetHeavy;">0</span></div>' +
            '<div><span id="bc-dano-tecnica-descripcion" style="color:#111;font-size:11px;font-family:moonGetHeavy;">SELECCIONE TÉCNICA</span></div>' +
          '</div>' +
          '<div style="width:200px;height:38px;text-align:center;background:#ff7e00;border-radius:4px 4px 0 0;">' +
            '<span style="font-family:moonGetHeavy;color:#fff;font-size:14px;line-height:38px;letter-spacing:1px;">DAÑO TOTAL</span>' +
          '</div>' +
          '<div style="width:200px;min-height:55px;background:rgba(255,255,255,.9);text-align:center;border:2px solid #555;border-top:none;padding:2px 0;">' +
            '<div><span id="bc-dano-total-valor" style="color:#111;font-size:24px;font-family:moonGetHeavy;">0</span></div>' +
            '<div><span id="bc-dano-total-descripcion" style="color:#111;font-size:10px;font-family:moonGetHeavy;">DAÑO COMBINADO</span></div>' +
          '</div>' +
        '</div>';

      var col2 =
        '<div style="display:flex;flex-direction:column;">' +
          '<div style="width:280px;height:38px;text-align:center;background:#0055bb;border-radius:4px 4px 0 0;">' +
            '<span style="font-family:moonGetHeavy;color:#fff;font-size:14px;line-height:38px;letter-spacing:1px;">AJUSTE DE ATRIBUTOS</span>' +
          '</div>' +
          '<div style="width:280px;background:rgba(255,255,255,.9);border:2px solid #555;border-top:none;">' +
            '<div style="display:flex;height:15px;background:#e0e0e0;border-bottom:1px solid #ccc;">' +
              '<div style="width:50px;text-align:center;font-family:moonGetHeavy;font-size:11px;line-height:15px;color:#111;">Attr</div>' +
              '<div style="width:80px;text-align:center;font-family:moonGetHeavy;font-size:11px;line-height:15px;color:#111;">Base</div>' +
              '<div style="width:80px;text-align:center;font-family:moonGetHeavy;font-size:11px;line-height:15px;color:#111;">Modif.</div>' +
              '<div style="width:70px;text-align:center;font-family:moonGetHeavy;font-size:11px;line-height:15px;color:#111;">Final</div>' +
            '</div>' +
            statRows +
          '</div>' +
        '</div>';

      var col3 =
        '<div style="display:flex;flex-direction:column;flex:0 0 30%;min-width:260px;">' +
          '<div style="background:rgba(0,0,0,.5);border-radius:4px;padding:6px;margin-bottom:8px;text-align:center;">' +
            '<label style="display:inline-flex;align-items:center;cursor:pointer;font-family:moonGetHeavy;font-size:14px;margin-right:20px;">' +
              '<input type="radio" id="bc-modo-ataque" name="bc-modo-combate" value="ataque" checked style="margin-right:5px;"> ' +
              '<span style="color:#d9534f;font-weight:bold;">ATAQUE</span>' +
            '</label>' +
            '<label style="display:inline-flex;align-items:center;cursor:pointer;font-family:moonGetHeavy;font-size:14px;">' +
              '<input type="radio" id="bc-modo-defensa" name="bc-modo-combate" value="defensa" style="margin-right:5px;"> ' +
              '<span style="color:#5bc0de;font-weight:bold;">DEFENSA</span>' +
            '</label>' +
          '</div>' +
          '<div style="height:38px;text-align:center;background:#0055bb;border-radius:4px 4px 0 0;">' +
            '<span style="font-family:moonGetHeavy;color:#fff;font-size:14px;line-height:38px;letter-spacing:1px;">SELECCIÓN DE ARMAS</span>' +
          '</div>' +
          '<div id="bc-armas-container" style="background:rgba(255,255,255,.9);border:2px solid #555;border-top:none;padding:8px;">' +
            '<div id="bc-armas-lista"></div>' +
            '<div style="text-align:center;margin-top:8px;">' +
              '<button id="bc-btn-agregar-arma" style="background:#28a745;color:#fff;border:none;padding:4px 10px;border-radius:3px;font-size:12px;cursor:pointer;font-family:moonGetHeavy;">+ AÑADIR ARMA</button>' +
            '</div>' +
          '</div>' +
          '<div style="height:38px;text-align:center;background:#0055bb;border-radius:4px 4px 0 0;margin-top:8px;">' +
            '<span style="font-family:moonGetHeavy;color:#fff;font-size:14px;line-height:38px;letter-spacing:1px;">SELECCIÓN DE TÉCNICAS</span>' +
          '</div>' +
          '<div style="background:rgba(255,255,255,.9);border:2px solid #555;border-top:none;padding:8px;">' +
            '<div style="display:flex;gap:8px;margin-bottom:6px;font-family:moonGetHeavy;font-size:11px;color:#111;">' +
              '<label style="cursor:pointer;"><input type="radio" id="bc-tec-filtro-todas" name="bc-tec-filtro" value="todas" checked> TODAS</label>' +
              '<label style="cursor:pointer;"><input type="radio" id="bc-tec-filtro-activa" name="bc-tec-filtro" value="activa"> ACTIVAS</label>' +
              '<label style="cursor:pointer;"><input type="radio" id="bc-tec-filtro-mantenida" name="bc-tec-filtro" value="mantenida"> MANTENIDAS</label>' +
            '</div>' +
            '<select id="bc-tecnica-seleccionada" style="width:100%;height:30px;font-size:13px;border-radius:4px;"><option value="">Sin técnica</option></select>' +
            '<div id="bc-tecnica-info" style="display:none;margin-top:4px;font-size:12px;color:#555;">' +
              '<div id="bc-tecnica-tipo-display"></div>' +
            '</div>' +
          '</div>' +
          '<div id="bc-btn-accion-wrap" style="display:none;margin-top:8px;">' +
            '<button id="bc-btn-agregar-accion" style="background:#ff7e00;color:#fff;border:none;padding:6px 16px;border-radius:4px;font-size:13px;cursor:pointer;font-family:moonGetHeavy;width:100%;">+ AÑADIR ACCIÓN</button>' +
          '</div>' +
        '</div>';

      var self = this;
      var barLeft = [
        ['DEFENSA PASIVA',       'bc-stat-defensa-pasiva'],
        ['FORTALEZA ESPIRITUAL', 'bc-stat-fortaleza-espiritual'],
        ['REGEN. ENERGÍA',       'bc-stat-regen-energia'],
        ['REGEN. HAKI',          'bc-stat-regen-haki'],
        ['CONCENTRACIÓN',        'bc-stat-concentracion'],
        ['UMBRAL DEL DOLOR',     'bc-stat-umbral-dolor']
      ];
      var barRight = [
        ['MOVIMIENTO',         'bc-stat-movimiento',        'm'],
        ['SALTO',              'bc-stat-salto',             'm'],
        ['TREPAR',             'bc-stat-trepar',            'm'],
        ['NADAR',              'bc-stat-nadar',             'm'],
        ['DIST. LANZAMIENTO',  'bc-stat-dist-lanzamiento',  'm'],
        ['LÍMITE CAÍDA',       'bc-stat-limite-caida',      'm']
      ];
      var leftBars = '', rightBars = '';
      for (var bi = 0; bi < barLeft.length; bi++) { leftBars  += self._statBar(barLeft[bi][0],  barLeft[bi][1]); }
      for (var bj = 0; bj < barRight.length; bj++) { rightBars += self._statBar(barRight[bj][0], barRight[bj][1]); }
      var col4 =
        '<div style="display:flex;flex-direction:column;flex:1;min-width:350px;">' +
          '<div style="height:38px;text-align:center;background:#a3180b;border-radius:4px 4px 0 0;">' +
            '<span style="font-family:moonGetHeavy;color:#fff;font-size:14px;line-height:38px;letter-spacing:1px;">ESTADÍSTICAS BÉLICAS</span>' +
          '</div>' +
          '<div style="background:rgba(20,5,5,0.7);border:2px solid #a3180b;border-top:none;padding:8px;flex:1;">' +
            '<div style="display:flex;gap:8px;">' +
              '<div style="flex:1;">' + leftBars  + '</div>' +
              '<div style="flex:1;">' + rightBars + '</div>' +
            '</div>' +
          '</div>' +
        '</div>';
      var recursosRow =
        '<div style="background:linear-gradient(135deg,#2c3e50 0%,#34495e 100%);padding:12px;border-radius:8px;">' +
          '<div style="display:flex;gap:16px;">' +
            '<div style="flex:1;">' +
              '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">' +
                '<span style="color:#e74c3c;font-weight:bold;font-size:13px;">❤️ VIDA</span>' +
                '<span id="bc-stat-vida" style="color:white;font-weight:bold;font-size:13px;">-</span>' +
              '</div>' +
              '<div style="background:#1a1a1a;border-radius:10px;height:16px;overflow:hidden;">' +
                '<div style="background:linear-gradient(90deg,#c0392b 0%,#e74c3c 100%);width:100%;height:100%;box-shadow:0 0 10px rgba(231,76,60,0.5);"></div>' +
              '</div>' +
            '</div>' +
            '<div style="flex:1;">' +
              '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">' +
                '<span style="color:#f39c12;font-weight:bold;font-size:13px;">⚡ ENERGÍA</span>' +
                '<span id="bc-stat-energia" style="color:white;font-weight:bold;font-size:13px;">-</span>' +
              '</div>' +
              '<div style="background:#1a1a1a;border-radius:10px;height:16px;overflow:hidden;">' +
                '<div style="background:linear-gradient(90deg,#f39c12 0%,#f1c40f 100%);width:100%;height:100%;box-shadow:0 0 10px rgba(243,156,18,0.5);"></div>' +
              '</div>' +
            '</div>' +
            '<div style="flex:1;">' +
              '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">' +
                '<span style="color:#9b59b6;font-weight:bold;font-size:13px;">🔮 HAKI</span>' +
                '<span id="bc-stat-haki" style="color:white;font-weight:bold;font-size:13px;">-</span>' +
              '</div>' +
              '<div style="background:#1a1a1a;border-radius:10px;height:16px;overflow:hidden;">' +
                '<div style="background:linear-gradient(90deg,#8e44ad 0%,#9b59b6 100%);width:100%;height:100%;box-shadow:0 0 10px rgba(155,89,182,0.5);"></div>' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>';
      var accionesSection =
        '<div id="bc-acciones-section" style="display:none;">' +
          '<div style="display:flex;flex-direction:row;gap:16px;">' +
            '<div id="bc-acciones-lista" style="flex:1;min-width:0;"></div>' +
            '<div id="bc-acciones-derecha" style="flex:1;min-width:0;"></div>' +
          '</div>' +
        '</div>';
      return '<div style="display:flex;flex-direction:column;gap:16px;">' +
        '<div style="display:flex;flex-direction:row;flex-wrap:wrap;gap:16px;">' + col1 + col2 + col3 + col4 + '</div>' +
        recursosRow +
        accionesSection +
      '</div>';
    },

    // ── Lifecycle ──────────────────────────────────────────────────────────────

    inject: function () {
      if (document.getElementById('bc-calculator')) return;
      var body = document.getElementById('belico-panel-body');
      if (!body) return;
      var wrap = document.createElement('div');
      wrap.id = 'bc-calculator';
      wrap.style.cssText = 'padding:12px;';
      wrap.innerHTML = this._createHTML();
      body.insertBefore(wrap, body.firstChild);
      this._attachEvents();
    },

    _attachEvents: function () {
      var self = this;
      var atq = document.getElementById('bc-modo-ataque');
      var def = document.getElementById('bc-modo-defensa');
      if (atq) atq.addEventListener('change', function () { self.cambiarModoCombate('ataque'); });
      if (def) def.addEventListener('change', function () { self.cambiarModoCombate('defensa'); });

      // Weapon events via delegation
      var armasContainer = document.getElementById('bc-armas-container');
      if (armasContainer) {
        armasContainer.addEventListener('change', function (e) {
          if (e.target && e.target.getAttribute('data-bc-arma-sel') !== null) {
            var indice = parseInt(e.target.getAttribute('data-bc-arma-sel'), 10);
            self.seleccionarArma(e.target.value, indice);
          }
        });
        armasContainer.addEventListener('click', function (e) {
          var target = e.target;
          while (target && target !== armasContainer) {
            if (target.hasAttribute && target.hasAttribute('data-eliminar-arma')) {
              var indice = parseInt(target.getAttribute('data-eliminar-arma'), 10);
              self._eliminarSlotArma(indice);
              return;
            }
            target = target.parentNode;
          }
        });
      }
      var btnAgregar = document.getElementById('bc-btn-agregar-arma');
      if (btnAgregar) btnAgregar.addEventListener('click', function () { self._agregarSlotArma(); });

      var tecSel = document.getElementById('bc-tecnica-seleccionada');
      if (tecSel) tecSel.addEventListener('change', function () { self.seleccionarTecnica(this.value); });
      var fTodas     = document.getElementById('bc-tec-filtro-todas');
      var fActiva    = document.getElementById('bc-tec-filtro-activa');
      var fMantenida = document.getElementById('bc-tec-filtro-mantenida');
      if (fTodas)     fTodas.addEventListener('change',     function () { self._filtrarTecnicas('todas'); });
      if (fActiva)    fActiva.addEventListener('change',    function () { self._filtrarTecnicas('activa'); });
      if (fMantenida) fMantenida.addEventListener('change', function () { self._filtrarTecnicas('mantenida'); });
      var btnAccion = document.getElementById('bc-btn-agregar-accion');
      if (btnAccion) btnAccion.addEventListener('click', function () { self._agregarAccion(); });
    },

    init: function () {
      if (this._initialized) return;
      this._initialized = true;
      var self = this;
      var xhr = new XMLHttpRequest();
      var _url = '/op/belico_data.php';
      if (typeof window.BC_TID !== 'undefined' && window.BC_TID > 0) { _url += '?tid=' + window.BC_TID; }
      xhr.open('GET', _url, true);
      xhr.onload = function () {
        if (xhr.status === 200) {
          try {
            var data = JSON.parse(xhr.responseText);
            if (data.error) {
              console.warn('[BC] belico_data error:', data.error);
            } else {
              self._data = data;
              self._setup();
            }
          } catch (e) {
            console.error('[BC] JSON parse error:', e.message, '| Raw (200 chars):', xhr.responseText.substring(0, 200));
          }
        } else {
          console.warn('[BC] HTTP error:', xhr.status, xhr.responseText.substring(0, 100));
        }
      };
      xhr.onerror = function () { console.error('[BC] XHR network error'); };
      xhr.send();
    },

    _setup: function () {
      var d = this._data;
      this.armasSeleccionadas = [null];
      this._initAtributos();
      this._cargarAtributosActuales(d);
      this._renderArmaSlots([]);
      this._cargarArmas(d);
      this._cargarTecnicas(d);
      this._mostrarBotonAccion();
    },

    // ── Attributes ─────────────────────────────────────────────────────────────

    _initAtributos: function () {
      var stats = ['NIV', 'FUE', 'AGI', 'PUN', 'DES', 'RES', 'REF', 'VOL'];
      var self = this;
      for (var i = 0; i < stats.length; i++) {
        var stat = stats[i];
        var modEl = document.getElementById('bc-modificador-' + stat);
        if (!modEl) continue;
        var input = document.createElement('input');
        input.type = 'text';
        input.id = 'bc-modificador-' + stat;
        input.value = '0';
        input.style.cssText = 'width:76px;height:19px;text-align:center;background:rgba(255,255,255,.8);border:1px solid #ccc;border-radius:3px;font-family:inherit;font-size:inherit;padding:0;margin:0;color:#111;';
        modEl.parentNode.replaceChild(input, modEl);
        (function (s) {
          input.addEventListener('input', function (e) {
            var val = e.target.value.replace(/[^0-9+\-]/g, '');
            var signo = '', num = val;
            if (val.charAt(0) === '+' || val.charAt(0) === '-') { signo = val.charAt(0); num = val.substring(1); }
            num = num.replace(/[+\-]/g, '');
            if (num.length > 5) num = num.substring(0, 5);
            val = signo + num;
            if (val === '+' || val === '-') val = signo + '0';
            e.target.value = val;
            self._actualizarAtributoFinal(s);
          });
          input.addEventListener('blur', function (e) {
            if (!e.target.value || e.target.value === '+' || e.target.value === '-') e.target.value = '0';
            self._actualizarAtributoFinal(s);
          });
        })(stat);
      }
      setTimeout(function () { self._actualizarGolpeBasico(); }, 100);
    },

    _actualizarAtributoFinal: function (stat) {
      var baseEl  = document.getElementById('bc-atributo-base-' + stat);
      var modEl   = document.getElementById('bc-modificador-' + stat);
      var finalEl = document.getElementById('bc-atributo-final-' + stat);
      if (!baseEl || !modEl || !finalEl) return;
      var base = parseInt(baseEl.textContent || 0);
      var mod  = parseInt(modEl.value || modEl.textContent || 0);
      finalEl.textContent = base + mod;
      this._actualizarGolpeBasico();
      this._actualizarDanoTecnica();
      this._actualizarEstadisticas();
      for (var i = 0; i < this.armasSeleccionadas.length; i++) {
        if (this.armasSeleccionadas[i]) this._actualizarInfoArma(i);
      }
    },

    _cargarAtributosActuales: function (d) {
      var attrs = {
        NIV: d.nivel,
        FUE: d.fuerza_completa,
        AGI: d.agilidad_completa,
        PUN: d.punteria_completa,
        DES: d.destreza_completa,
        RES: d.resistencia_completa,
        REF: d.reflejos_completa,
        VOL: d.voluntad_completa
      };
      this.nivelPersonaje = d.nivel;
      for (var stat in attrs) {
        var baseEl  = document.getElementById('bc-atributo-base-' + stat);
        var finalEl = document.getElementById('bc-atributo-final-' + stat);
        var modEl   = document.getElementById('bc-modificador-' + stat);
        if (baseEl && finalEl && modEl) {
          baseEl.textContent  = attrs[stat];
          var mod = parseInt(modEl.value || modEl.textContent || 0);
          finalEl.textContent = attrs[stat] + mod;
        }
      }
      this._actualizarGolpeBasico();
      this._actualizarEstadisticas();
    },

    _obtenerAtributoFinal: function (stat) {
      var el = document.getElementById('bc-atributo-final-' + stat);
      return el ? parseInt(el.textContent || 0) : 0;
    },

    _obtenerNivel: function () {
      var el = document.getElementById('bc-atributo-final-NIV');
      return parseInt(el ? el.textContent || '0' : '0', 10);
    },

    // ── Weapons ────────────────────────────────────────────────────────────────

    _crearSlotArma: function (i) {
      var label = this._nombreArma(i);
      var esPrincipal = i === 0;
      var slot = document.createElement('div');
      slot.id = 'bc-arma-slot-' + i;
      var borderStyle = i < this.armasSeleccionadas.length - 1
        ? 'margin-bottom:8px;border-bottom:1px solid #ccc;padding-bottom:8px;'
        : 'margin-bottom:8px;';
      slot.style.cssText = borderStyle;

      var headerHtml =
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">' +
          '<label style="font-family:moonGetHeavy;font-size:12px;color:#111;">' + label + '</label>';
      if (!esPrincipal) {
        headerHtml += '<button data-eliminar-arma="' + i + '" style="background:#dc3545;color:#fff;border:none;border-radius:3px;font-size:10px;cursor:pointer;padding:1px 6px;font-family:moonGetHeavy;line-height:16px;">&#x2715;</button>';
      }
      headerHtml += '</div>';

      slot.innerHTML = headerHtml +
        '<select id="bc-arma-sel-' + i + '" data-bc-arma-sel="' + i + '" style="width:100%;height:30px;font-size:13px;border-radius:4px;margin-bottom:6px;">' +
          '<option value="">Sin arma</option>' +
        '</select>' +
        '<div id="bc-arma-info-' + i + '" style="display:none;text-align:left;padding:6px;background:rgba(255,255,255,.8);border-radius:4px;">' +
          '<div id="bc-arma-nombre-' + i + '" style="font-weight:bold;font-size:14px;color:#111;"></div>' +
          '<div id="bc-arma-dano-' + i + '" style="font-size:13px;color:#111;"></div>' +
        '</div>';

      return slot;
    },

    _guardarSelecciones: function () {
      var selections = [];
      for (var i = 0; i < this.armasSeleccionadas.length; i++) {
        var sel = document.getElementById('bc-arma-sel-' + i);
        selections.push(sel ? sel.value : '');
      }
      return selections;
    },

    _renderArmaSlots: function (savedSelections) {
      var lista = document.getElementById('bc-armas-lista');
      if (!lista) return;
      lista.innerHTML = '';
      for (var i = 0; i < this.armasSeleccionadas.length; i++) {
        lista.appendChild(this._crearSlotArma(i));
      }
      for (var j = 0; j < this.armasSeleccionadas.length; j++) {
        if (this._data) this._cargarArmasEnSelector(j, this._data);
        var selEl = document.getElementById('bc-arma-sel-' + j);
        if (selEl && savedSelections && savedSelections[j]) {
          selEl.value = savedSelections[j];
          // Restore weapon object if the value is still valid
          if (selEl.value && this._data && this._data.objetos[selEl.value]) {
            this.armasSeleccionadas[j] = this._data.objetos[selEl.value][0];
            var infoEl = document.getElementById('bc-arma-info-' + j);
            if (infoEl) infoEl.style.display = 'block';
            this._actualizarInfoArma(j);
          }
        }
      }
    },

    _agregarSlotArma: function () {
      var selections = this._guardarSelecciones();
      this.armasSeleccionadas.push(null);
      this._renderArmaSlots(selections);
    },

    _eliminarSlotArma: function (indice) {
      var selections = this._guardarSelecciones();
      this.armasSeleccionadas.splice(indice, 1);
      selections.splice(indice, 1);
      this._renderArmaSlots(selections);
      this._actualizarGolpeBasico();
    },

    _cargarArmas: function (d) {
      for (var i = 0; i < this.armasSeleccionadas.length; i++) {
        this._cargarArmasEnSelector(i, d);
      }
    },

    _cargarArmasEnSelector: function (indice, d) {
      var sel = document.getElementById('bc-arma-sel-' + indice);
      if (!sel || !d) return;
      var currentVal = sel.value;
      sel.innerHTML = '<option value="">Sin arma</option>';
      var objetos = d.objetos;
      var objetos_array = d.objetos_array;
      var armas = [];
      for (var i = 0; i < objetos_array.length; i++) {
        var id = objetos_array[i];
        if (id === 'LLST001') continue;
        var obj = objetos[id] ? objetos[id][0] : null;
        if (obj && obj.categoria && obj.categoria.toLowerCase() === 'armas') {
          armas.push({ id: id, objeto: obj });
        }
      }
      for (var j = 0; j < armas.length; j++) {
        var opt = document.createElement('option');
        opt.value = armas[j].id;
        opt.textContent = (armas[j].objeto.editado === '1' ? armas[j].objeto.apodo : armas[j].objeto.nombre) + ' (ID: ' + armas[j].id + ')';
        sel.appendChild(opt);
      }
      if (currentVal) sel.value = currentVal;
    },

    seleccionarArma: function (id, indice) {
      var info = document.getElementById('bc-arma-info-' + indice);
      if (!id || !info) {
        if (info) info.style.display = 'none';
        this.armasSeleccionadas[indice] = null;
        this._actualizarGolpeBasico();
        this._mostrarBotonAccion();
        return;
      }
      if (!this._data || !this._data.objetos[id]) return;
      var obj = this._data.objetos[id][0];
      if (obj) {
        this.armasSeleccionadas[indice] = obj;
        this._actualizarInfoArma(indice);
        info.style.display = 'block';
        this._actualizarGolpeBasico();
        this._mostrarBotonAccion();
      }
    },

    _actualizarInfoArma: function (indice) {
      var obj = this.armasSeleccionadas[indice];
      if (!obj) return;
      var nomEl  = document.getElementById('bc-arma-nombre-' + indice);
      var danoEl = document.getElementById('bc-arma-dano-' + indice);
      if (nomEl) nomEl.textContent = obj.editado === '1' ? obj.apodo : obj.nombre;
      if (danoEl) {
        var calc = this._calcularDanoArma(obj);
        var etiqueta = this.modoCombate === 'defensa' ? 'Bloqueo:' : 'Daño:';
        var valorMostrar = this._obtenerValorMostrarArma(obj, calc);
        danoEl.innerHTML = '<strong>' + etiqueta + '</strong> ' + valorMostrar;
      }
    },

    _obtenerValorMostrarArma: function (obj, calc) {
      if (this.modoCombate === 'defensa' && obj.bloqueo) {
        return calc.calculado ? calc.valor + ' <span style="color:#5bc0de;">(' + calc.texto + ' = ' + calc.valor + ' total)</span>' : obj.bloqueo;
      } else if (obj.dano) {
        return calc.calculado ? calc.valor + ' <span style="color:#ff7e00;">(' + calc.texto + ' = ' + calc.valor + ' total)</span>' : obj.dano;
      }
      return 'No disponible';
    },

    _obtenerTipoDanoArma: function (arma) {
      if (!arma) return 'contundente';
      if (arma.dano) {
        var t = arma.dano.toLowerCase();
        if (t.indexOf('cortante') !== -1) return 'cortante';
        if (t.indexOf('perforante') !== -1) return 'perforante';
        if (t.indexOf('sónico') !== -1) return 'sónico';
      }
      return 'contundente';
    },

    _calcularDanoArma: function (arma) {
      if (!arma) return { texto: 'Sin arma', valor: 0, calculado: false };
      var texto = this.modoCombate === 'defensa' && arma.bloqueo ? arma.bloqueo : (arma.dano || '');
      if (!texto) return { texto: 'Sin valor', valor: 0, calculado: false };
      var attrs = {
        FUE: this._obtenerAtributoFinal('FUE'),
        RES: this._obtenerAtributoFinal('RES'),
        DES: this._obtenerAtributoFinal('DES'),
        PUN: this._obtenerAtributoFinal('PUN'),
        AGI: this._obtenerAtributoFinal('AGI'),
        REF: this._obtenerAtributoFinal('REF'),
        VOL: this._obtenerAtributoFinal('VOL'),
        NIV: this._obtenerNivel()
      };
      var patron = /\[([A-Z]{3})[x×](\d+(?:[,.]?\d+)?)\]/g;
      var calc = texto, match, total = 0, hasCalc = false;
      while ((match = patron.exec(texto)) !== null) {
        var stt = match[1], mult = parseFloat(match[2].replace(',', '.'));
        if (attrs[stt] !== undefined && !isNaN(mult)) {
          var val = Math.ceil(attrs[stt] * mult);
          total += val; hasCalc = true;
          calc = calc.replace(match[0], val.toString());
        }
      }
      var nums = calc.match(/\b\d+\b/g);
      if (nums) {
        total = 0;
        for (var k = 0; k < nums.length; k++) { var n = parseInt(nums[k], 10); if (!isNaN(n)) total += n; }
        hasCalc = true;
      }
      return { texto: calc, valor: total, calculado: hasCalc };
    },

    // ── Damage calculations ────────────────────────────────────────────────────

    _actualizarGolpeBasico: function () {
      var danoEl = document.getElementById('bc-golpe-basico-dano');
      var tipoEl = document.getElementById('bc-golpe-basico-tipo');
      if (!danoEl || !tipoEl) return;
      var activas = this.armasSeleccionadas.filter(function (a) { return a !== null; });
      var num = activas.length;
      if (num === 0) {
        var valorBase = this._obtenerAtributoFinal('FUE');
        danoEl.textContent = valorBase || '0';
        tipoEl.textContent = this.modoCombate === 'defensa' ? 'DAÑO MITIGADO' : 'DE DAÑO CONTUNDENTE';
      } else {
        // 1 arma: 100% | 2 armas: 75% c/u | 3+ armas: 60% c/u
        var mult = num === 1 ? 1.0 : num === 2 ? 0.75 : 0.60;
        var danoTotal = 0, tipos = [];
        for (var i = 0; i < activas.length; i++) {
          var arma = activas[i];
          var calc = this._calcularDanoArma(arma);
          var danoBase;
          if (this.modoCombate === 'defensa') {
            danoBase = calc.calculado && calc.valor > 0 ? calc.valor : this._obtenerAtributoFinal('RES');
          } else {
            danoBase = calc.calculado && calc.valor > 0 ? calc.valor : this._obtenerAtributoFinal('FUE');
          }
          danoTotal += danoBase * mult;
          var tipo = this._obtenerTipoDanoArma(arma);
          if (tipos.indexOf(tipo) === -1) tipos.push(tipo);
        }
        danoEl.textContent = Math.ceil(danoTotal);
        if (this.modoCombate === 'defensa') {
          tipoEl.textContent = 'DAÑO MITIGADO';
        } else {
          tipoEl.textContent = 'DE DAÑO ' + tipos.join('/').toUpperCase();
        }
      }
      this._actualizarDanoTotal();
    },

    // ── Techniques ─────────────────────────────────────────────────────────────

    _cargarTecnicas: function (d) {
      this._filtrarTecnicas('todas');
    },

    _filtrarTecnicas: function (filtro) {
      var sel = document.getElementById('bc-tecnica-seleccionada');
      if (!sel || !this._data || !this._data.tec_aprendidas || !this._data.tec_aprendidas.todo) return;
      var prevVal = sel.value;
      sel.innerHTML = '<option value="">Sin técnica</option>';
      var lista = this._data.tec_aprendidas.todo.slice().sort(function (a, b) { return a.nombre.localeCompare(b.nombre); });
      var modo = this.modoCombate;
      for (var j = 0; j < lista.length; j++) {
        var tec = lista[j];
        var clase = (tec.clase || '').toLowerCase();
        var tipo  = (tec.tipo  || '').toLowerCase();
        if (filtro === 'activa') {
          if (clase.indexOf('activa') === -1 || clase.indexOf('mantenida') !== -1) continue;
          if (modo === 'ataque') {
            if (tipo.indexOf('ofensiva') === -1 && tipo.indexOf('ambiental') === -1) continue;
          } else {
            if (tipo.indexOf('defensiva') === -1) continue;
          }
        }
        if (filtro === 'mantenida' && clase.indexOf('mantenida') === -1) continue;
        var opt = document.createElement('option');
        opt.value = tec.tid;
        opt.textContent = '[' + tec.tid + '] ' + tec.nombre + ' (Tier ' + tec.tier + ')';
        sel.appendChild(opt);
      }
      sel.value = prevVal;
      if (sel.value !== prevVal) { this.seleccionarTecnica(''); }
    },

    seleccionarTecnica: function (tid) {
      var info = document.getElementById('bc-tecnica-info');
      if (!tid || !info) {
        if (info) info.style.display = 'none';
        this.tecnicaSeleccionada = null;
        this._actualizarDanoTecnica();
        return;
      }
      var tec = null;
      if (this._data && this._data.tec_aprendidas && this._data.tec_aprendidas.todo) {
        var lista = this._data.tec_aprendidas.todo;
        for (var i = 0; i < lista.length; i++) {
          if (lista[i].tid === tid) { tec = lista[i]; break; }
        }
      }
      if (!tec) return;
      var claseStr = (tec.clase || '').toLowerCase();
      var tipoStr  = (tec.tipo  || '').toLowerCase();
      var tipoCompleto = [tec.clase, tec.tipo].filter(Boolean).join(' ').toLowerCase();
      var datos = {
        nombre: tec.nombre, tid: tec.tid, rama: tec.rama || '', tier: parseInt(tec.tier, 10) || 0,
        tipoCompleto: tipoCompleto,
        esTecnica: true,
        esActiva:   claseStr.indexOf('activa')   !== -1 && claseStr.indexOf('mantenida') === -1,
        esMantenida: claseStr.indexOf('mantenida') !== -1,
        esOfensiva:  tipoStr.indexOf('ofensiva')  !== -1,
        esDefensiva: tipoStr.indexOf('defensiva') !== -1,
        efectos: tec.efectos || ''
      };
      this.tecnicaSeleccionada = { datos: datos };
      var tipoEl = document.getElementById('bc-tecnica-tipo-display');
      if (tipoEl) {
        var texto = 'Tier ' + datos.tier;
        if (datos.rama) texto += ' - ' + datos.rama;
        if (datos.tipoCompleto) texto += ' (' + datos.tipoCompleto + ')';
        tipoEl.textContent = texto;
      }
      info.style.display = 'block';
      this._actualizarDanoTecnica();
    },

    _extraerDanoTecnica: function (el) {
      var efectoEl = el.querySelector ? el.querySelector('.tecnica_efecto') : (el.className === 'tecnica_efecto' ? el : null);
      if (!efectoEl) efectoEl = el;
      var info = { formula: null, tipo: 'especial', valorBase: 0 };
      var todoElTexto = efectoEl.textContent || '';
      this._procesarFormula(todoElTexto, info);
      var texto = (efectoEl.innerHTML || efectoEl.textContent || '').toLowerCase();
      if (texto.indexOf('daño cortante') !== -1) info.tipo = 'cortante';
      else if (texto.indexOf('daño perforante') !== -1) info.tipo = 'perforante';
      else if (texto.indexOf('daño contundente') !== -1) info.tipo = 'contundente';
      return info;
    },

    _procesarFormula: function (texto, info) {
      if (!texto) return false;
      if (/\[\d+(?:,\d+)?xNivel\]/.test(texto)) { info.formula = texto; return true; }
      if (/\[[A-Z]{3}x[\d,.]+\]/.test(texto)) { info.formula = texto; return true; }
      return false;
    },

    _calcularDanoTecnicaCompleto: function (el) {
      var info = this._extraerDanoTecnica(el);
      if (!info || !info.formula) return { texto: 'Sin daño', valor: 0, calculado: false, tipo: 'especial' };
      var nivel = this._obtenerNivel();
      var total = 0, calc = info.formula, hasCalc = false;
      var patronNivel = /\[(\d+(?:,\d+)?)xNivel\]/g, match;
      while ((match = patronNivel.exec(info.formula)) !== null) {
        var mult = parseFloat(match[1].replace(',', '.'));
        if (!isNaN(mult) && nivel > 0) {
          var val = Math.ceil(mult * nivel);
          total += val; hasCalc = true;
          calc = calc.replace(match[0], val.toString());
        }
      }
      if (!hasCalc) {
        var nums = calc.match(/\b\d+\b/g);
        if (nums) { for (var k = 0; k < nums.length; k++) { var n = parseInt(nums[k], 10); if (!isNaN(n)) total += n; } }
      }
      return { texto: calc, valor: hasCalc ? total : info.valorBase, calculado: hasCalc, tipo: info.tipo };
    },

    _actualizarDanoTecnica: function () {
      var valorEl = document.getElementById('bc-dano-tecnica-valor');
      var descEl  = document.getElementById('bc-dano-tecnica-descripcion');
      if (!valorEl || !descEl) return;
      if (!this.tecnicaSeleccionada) {
        valorEl.textContent = '0'; descEl.textContent = 'SELECCIONE TÉCNICA';
        this._actualizarDanoTotal(); return;
      }
      var el = document.createElement('div');
      el.className = 'tecnica_efecto';
      el.textContent = this.tecnicaSeleccionada.datos.efectos || '';
      var calc = this._calcularDanoTecnicaCompleto(el);
      if (this.tecnicaSeleccionada.datos.esDefensiva) {
        valorEl.textContent = calc.valor || 0; descEl.textContent = 'DE DAÑO MITIGADO';
      } else if (calc.calculado || calc.valor > 0) {
        valorEl.textContent = calc.valor; descEl.textContent = 'DE DAÑO ' + calc.tipo.toUpperCase();
      } else {
        valorEl.textContent = '0'; descEl.textContent = 'TÉCNICA SIN DAÑO';
      }
      this._actualizarDanoTotal();
    },

    _actualizarDanoTotal: function () {
      var valorEl = document.getElementById('bc-dano-total-valor');
      var descEl  = document.getElementById('bc-dano-total-descripcion');
      if (!valorEl || !descEl) return;
      var golpe   = parseInt(document.getElementById('bc-golpe-basico-dano').textContent || 0);
      var tecnica = parseInt(document.getElementById('bc-dano-tecnica-valor').textContent || 0);
      var total;
      if (this.modoCombate === 'defensa') {
        total = golpe;
        if (this.tecnicaSeleccionada && this.tecnicaSeleccionada.datos.esDefensiva) {
          total += tecnica; descEl.textContent = 'BLOQUEO + TÉCNICA';
        } else {
          descEl.textContent = tecnica > 0 ? 'BLOQUEO (TÉCNICA OFENSIVA)' : 'SOLO BLOQUEO';
        }
      } else {
        if (this.tecnicaSeleccionada && this.tecnicaSeleccionada.datos.esDefensiva) {
          total = golpe; descEl.textContent = '(TÉCNICA DEFENSIVA)';
        } else {
          total = golpe + tecnica;
          descEl.textContent = tecnica > 0 ? 'DAÑO COMBINADO' : 'SOLO GOLPE BÁSICO';
        }
      }
      valorEl.textContent = total;
    },

    // ── Mode ───────────────────────────────────────────────────────────────────

    cambiarModoCombate: function (modo) {
      this.modoCombate = modo;
      var labelGolpe = document.getElementById('bc-label-golpe-basico');
      var labelTec   = document.getElementById('bc-label-dano-tecnica');
      if (labelGolpe) labelGolpe.textContent = modo === 'defensa' ? 'BLOQUEO BÁSICO'  : 'GOLPE BÁSICO';
      if (labelTec)   labelTec.textContent   = modo === 'defensa' ? 'BLOQUEO TÉCNICA' : 'DAÑO TÉCNICA';
      for (var i = 0; i < this.armasSeleccionadas.length; i++) {
        if (this.armasSeleccionadas[i]) this._actualizarInfoArma(i);
      }
      this._actualizarGolpeBasico();
      this._actualizarDanoTecnica();
      this._actualizarDanoTotal();
      var fActiva = document.getElementById('bc-tec-filtro-activa');
      if (fActiva && fActiva.checked) { this._filtrarTecnicas('activa'); }
    },

    _statBar: function (label, id) {
      return (
        '<div style="width:100%;display:flex;margin-bottom:3px;position:relative;overflow:hidden;height:24px;border-radius:50px;border:1px solid #000;">' +
          '<div style="width:100%;height:100%;background-color:#a3180b;position:absolute;"></div>' +
          '<div style="position:absolute;width:35px;height:100%;right:58px;background-color:rgb(127,66,133);transform:skew(-45deg);transform-origin:top right;"></div>' +
          '<div style="position:absolute;width:65px;height:100%;right:0;background-color:rgb(127,66,133);"></div>' +
          '<div style="flex:1;position:relative;z-index:2;color:white;font-family:moonGetHeavy;line-height:24px;text-align:center;font-size:10px;padding:0 4px;">' + label + '</div>' +
          '<div id="' + id + '" style="width:65px;position:relative;z-index:2;text-align:center;font-family:moonGetHeavy;color:white;line-height:24px;font-size:12px;">-</div>' +
        '</div>'
      );
    },

    _actualizarEstadisticas: function () {
      if (this._data) {
        var vidaEl    = document.getElementById('bc-stat-vida');
        var energiaEl = document.getElementById('bc-stat-energia');
        var hakiEl    = document.getElementById('bc-stat-haki');
        if (vidaEl)    vidaEl.textContent    = this._data.vida_max    != null ? this._data.vida_max    : '-';
        if (energiaEl) energiaEl.textContent = this._data.energia_max != null ? this._data.energia_max : '-';
        if (hakiEl)    hakiEl.textContent    = this._data.haki_max    != null ? this._data.haki_max    : '-';
      }
      var FUE = this._obtenerAtributoFinal('FUE');
      var AGI = this._obtenerAtributoFinal('AGI');
      var RES = this._obtenerAtributoFinal('RES');
      var DES = this._obtenerAtributoFinal('DES');
      var VOL = this._obtenerAtributoFinal('VOL');
      var NIV = this._obtenerNivel();
      var mov = Math.floor(AGI / 2 + RES / 4);
      var vals = {
        'bc-stat-defensa-pasiva':       RES,
        'bc-stat-fortaleza-espiritual': VOL,
        'bc-stat-regen-energia':        NIV,
        'bc-stat-regen-haki':           Math.ceil(NIV / 2),
        'bc-stat-concentracion':        VOL + RES,
        'bc-stat-umbral-dolor':         (VOL + RES) * 5,
        'bc-stat-movimiento':           mov + ' m',
        'bc-stat-salto':                Math.round(1 + (FUE + AGI) / 5) + ' m',
        'bc-stat-trepar':               Math.round(1 + (AGI + DES) / 10) + ' m',
        'bc-stat-nadar':                Math.round(mov / 2) + ' m',
        'bc-stat-dist-lanzamiento':     Math.floor(FUE / 2) + ' m',
        'bc-stat-limite-caida':         Math.round(2 + (FUE + RES) / 4) + ' m'
      };
      for (var id in vals) {
        var el = document.getElementById(id);
        if (el) el.textContent = vals[id];
      }
    },

    _mostrarBotonAccion: function () {
      var wrap = document.getElementById('bc-btn-accion-wrap');
      if (wrap) wrap.style.display = 'block';
    },

    _agregarAccion: function () {
      this._accionCounter++;
      var armasNombres = [];
      for (var i = 0; i < this.armasSeleccionadas.length; i++) {
        if (this.armasSeleccionadas[i]) {
          var obj = this.armasSeleccionadas[i];
          armasNombres.push(this._nombreArma(i) + ': ' + (obj.editado === '1' ? obj.apodo : obj.nombre));
        }
      }
      var tecNombre = 'Sin técnica';
      var tecRama = '';
      if (this.tecnicaSeleccionada && this.tecnicaSeleccionada.datos) {
        var d = this.tecnicaSeleccionada.datos;
        tecNombre = '[' + d.tid + '] ' + d.nombre;
        tecRama = d.rama || '';
      }
      var golpeEl = document.getElementById('bc-golpe-basico-dano');
      var tecEl   = document.getElementById('bc-dano-tecnica-valor');
      var totalEl = document.getElementById('bc-dano-total-valor');
      var tipoEl  = document.getElementById('bc-golpe-basico-tipo');
      this.acciones.push({
        num:       this._accionCounter,
        modo:      this.modoCombate,
        armas:     armasNombres,
        tecnica:   tecNombre,
        tecRama:   tecRama,
        golpe:     golpeEl  ? golpeEl.textContent  : '0',
        danoTec:   tecEl    ? tecEl.textContent    : '0',
        total:     totalEl  ? totalEl.textContent  : '0',
        tipoGolpe: tipoEl   ? tipoEl.textContent   : ''
      });
      this._renderAcciones();
    },

    _eliminarAccion: function (idx) {
      this.acciones.splice(idx, 1);
      this._renderAcciones();
    },

    _renderAcciones: function () {
      var lista   = document.getElementById('bc-acciones-lista');
      var seccion = document.getElementById('bc-acciones-section');
      if (!lista) return;
      if (this.acciones.length === 0) {
        if (seccion) seccion.style.display = 'none';
        lista.innerHTML = '';
        return;
      }
      if (seccion) seccion.style.display = 'block';
      var self = this, html = '';
      for (var i = 0; i < this.acciones.length; i++) {
        var a = this.acciones[i];
        var modoColor = a.modo === 'defensa' ? '#5bc0de' : '#d9534f';
        var modoLabel = a.modo === 'defensa' ? 'DEFENSA' : 'ATAQUE';
        var armasHtml = a.armas.length > 0 ? a.armas.join(' &bull; ') : 'Sin arma';
        html +=
          '<div style="background:rgba(255,255,255,.9);border:2px solid #555;border-radius:4px;margin-bottom:8px;overflow:hidden;">' +
            '<div style="background:#0055bb;padding:4px 8px;display:flex;justify-content:space-between;align-items:center;">' +
              '<span style="font-family:moonGetHeavy;color:#fff;font-size:13px;">ACCIÓN ' + a.num + '</span>' +
              '<span style="background:' + modoColor + ';padding:2px 8px;border-radius:3px;font-size:11px;font-family:moonGetHeavy;color:#fff;">' + modoLabel + '</span>' +
              '<button data-eliminar-accion="' + i + '" style="background:#dc3545;color:#fff;border:none;border-radius:3px;font-size:11px;cursor:pointer;padding:2px 6px;font-family:moonGetHeavy;">&#x2715;</button>' +
            '</div>' +
            '<div style="padding:6px 8px;">' +
              '<div style="font-size:11px;color:#444;margin-bottom:2px;"><strong>Arma:</strong> ' + armasHtml + '</div>' +
              '<div style="font-size:11px;color:#444;margin-bottom:' + (a.tecRama ? '2px' : '6px') + ';"><strong>Técnica:</strong> ' + a.tecnica + '</div>' +
              (a.tecRama ? '<div style="font-size:10px;color:#666;font-style:italic;margin-bottom:6px;">' + a.tecRama + '</div>' : '') +
              '<div style="display:flex;gap:6px;text-align:center;">' +
                '<div style="flex:1;background:#e8f0ff;border-radius:4px;padding:4px 0;">' +
                  '<div style="font-family:moonGetHeavy;font-size:20px;color:#0055bb;">' + a.golpe + '</div>' +
                  '<div style="font-size:9px;color:#555;font-family:moonGetHeavy;">' + (a.modo === 'defensa' ? 'BLOQUEO' : 'GOLPE') + '</div>' +
                '</div>' +
                '<div style="flex:1;background:#f5eaff;border-radius:4px;padding:4px 0;">' +
                  '<div style="font-family:moonGetHeavy;font-size:20px;color:#853dee;">' + a.danoTec + '</div>' +
                  '<div style="font-size:9px;color:#555;font-family:moonGetHeavy;">TÉCNICA</div>' +
                '</div>' +
                '<div style="flex:1;background:#fff3e0;border-radius:4px;padding:4px 0;">' +
                  '<div style="font-family:moonGetHeavy;font-size:20px;color:#ff7e00;">' + a.total + '</div>' +
                  '<div style="font-size:9px;color:#555;font-family:moonGetHeavy;">TOTAL</div>' +
                '</div>' +
              '</div>' +
            '</div>' +
          '</div>';
      }
      lista.innerHTML = html;
      var btns = lista.querySelectorAll('[data-eliminar-accion]');
      for (var j = 0; j < btns.length; j++) {
        (function (btn, idx) {
          btn.addEventListener('click', function () { self._eliminarAccion(idx); });
        })(btns[j], parseInt(btns[j].getAttribute('data-eliminar-accion'), 10));
      }
    }
  };

  // Inject HTML as soon as the panel body exists (panel.js runs before calc.js)
  function tryInject() {
    if (document.getElementById('belico-panel-body')) {
      BC.inject();
    } else if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () { BC.inject(); });
    } else {
      BC.inject();
    }
  }
  tryInject();

  window.BC = BC;
})();
