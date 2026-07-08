/**
 * ficha/core.js — Namespace FICHA + utilidades compartidas
 * Debe cargarse ANTES que cualquier otro módulo ficha/*.js
 */
(function (w) {
  if (w.FICHA) return;

  var FICHA = {
    version: '1.0.0'
  };

  // ── Utilidades generales ─────────────────────────────────────

  FICHA.getNumericPrefix = function (id) {
    var m = String(id || '').match(/^(\d+)/);
    return m ? m[1] : '';
  };

  FICHA.sleep = function (time) {
    return new Promise(function (resolve) { setTimeout(resolve, time); });
  };

  FICHA.capitalize = function (s) { return s && s[0].toUpperCase() + s.slice(1); };

  FICHA.safeGet = function (obj, property, defaultValue) {
    if (defaultValue === undefined) defaultValue = 'N/A';
    if (!obj || typeof obj !== 'object') {
      console.warn('safeGet: objeto es null/undefined:', obj);
      return defaultValue;
    }
    if (Object.prototype.hasOwnProperty.call(obj, property)) return obj[property];
    console.warn("safeGet: propiedad '" + property + "' no encontrada:", obj);
    return defaultValue;
  };

  FICHA.verificarObjetosInicializados = function () {
    var errores = [];
    if (typeof oficios === 'undefined') errores.push('oficios no está definido');
    if (typeof belicas === 'undefined') errores.push('belicas no está definido');
    if (errores.length > 0) { console.error('Objetos no inicializados:', errores); return false; }
    return true;
  };

  FICHA.createSafeOficiosInfo = function (oficioName) {
    if (!oficioName) {
      console.warn('createSafeOficiosInfo: oficioName es null/undefined');
      return { nivel: 'desconocido' };
    }
    if (typeof w.oficios === 'undefined') {
      console.warn('createSafeOficiosInfo: objeto oficios no está definido');
      return { nivel: 'desconocido' };
    }
    var info = w.oficios[oficioName];
    if (!info) {
      console.warn("createSafeOficiosInfo: oficio '" + oficioName + "' no encontrado");
      return { nivel: 'desconocido' };
    }
    return info;
  };

  // ── Catálogo de efectos de combate ───────────────────────────

  FICHA.efectosCodigo = {
    '[Daño contundente]': { nombre: 'Daño contundente', texto: 'Provoca golpes que pueden causar entumecimiento, fracturas, mareos y suelen tener buena destructividad.' },
    '[Daño cortante]': { nombre: 'Daño cortante', texto: 'Provoca cortes que pueden causar hemorragias o amputaciones.' },
    '[Daño perforante]': { nombre: 'Daño perforante', texto: 'Pueden causar hemorragias. Tienen poca destructividad pero ignoran 40 puntos de defensa pasiva del enemigo.' },
    '[Daño elemental]': { nombre: 'Daño elemental', texto: 'Inflige daño basado en elementos naturales como fuego o agua.' },
    '[Daño espiritual]': { nombre: 'Daño espiritual', texto: 'Afecta la energía o espíritu del objetivo.' },
    '[Defensa pasiva física]': { nombre: 'Defensa pasiva física', texto: 'A partir de nivel 5 reduce el daño físico recibido en un valor igual a tu Resistencia.' },
    '[Defensa pasiva mental]': { nombre: 'Defensa pasiva mental', texto: 'A partir de nivel 5 reduce el daño espiritual recibido en un valor igual a tu Voluntad.' },
    '[Umbral del dolor]': { nombre: 'Umbral del dolor', texto: '' },
    '[Agarre]': { nombre: 'Agarre', texto: 'Inmoviliza al objetivo mediante un agarre fuerte.' },
    '[Asfixia]': { nombre: 'Asfixia', texto: 'Impide la respiración, causando daño progresivo.' },
    '[Parálisis parcial]': { nombre: 'Parálisis parcial', texto: 'Inmoviliza parcialmente, limitando movimientos.' },
    '[Parálisis completa]': { nombre: 'Parálisis completa', texto: 'Inmoviliza totalmente, impidiendo cualquier movimiento.' },
    '[Canalizar]': { nombre: 'Canalizar', texto: 'Requiere permanecer concentrado en una tarea pero no limita el movimiento.' },
    '[Concentrar]': { nombre: 'Concentrar', texto: 'Requiere concentración absoluta, el usuario ha de mantenerse inmovil sin realizar técnica alguna.' },
    '[Ceguera]': { nombre: 'Ceguera', texto: 'Dificulta la visión temporalmente causando -12 Reflejos.' },
    '[Derribo]': { nombre: 'Derribo', texto: 'Lanza al objetivo haciéndole perder el equilibrio.' },
    '[Desarme]': { nombre: 'Desarme', texto: 'Fuerza al objetivo a soltar su arma o equipamiento.' },
    '[Desorientación]': { nombre: 'Desorientación', texto: 'Causa confusión y pérdida de dirección, -10 Reflejos.' },
    '[Confusión]': { nombre: 'Confusión', texto: 'Provoca alteración mental, dificultando la toma de decisiones, -10 Reflejos.' },
    '[Mareo]': { nombre: 'Mareo', texto: 'Desestabiliza al objetivo, dificultando su equilibrio, -15 Reflejos.' },
    '[Empuje]': { nombre: 'Empuje', texto: 'Mueve al objetivo hacia atrás con fuerza.' },
    '[Entumecimiento]': { nombre: 'Entumecimiento', texto: 'Reduce la movilidad debido a la rigidez corporal en la zona afectada, -15 Agilidad en dicha zona.' },
    '[Veneno leve]': { nombre: 'Veneno leve', texto: 'Inflige daño directo de 20 Puntos por turno durante 3 turnos.' },
    '[Veneno medio]': { nombre: 'Veneno medio', texto: 'Inflige daño directo de 30 Puntos por turno durante 4 turnos.' },
    '[Veneno grave]': { nombre: 'Veneno grave', texto: 'Inflige daño directo de 40 Puntos por turno, las curaciones serán un 50% menos efectivas mientras dure.' },
    '[Fractura]': { nombre: 'Fractura', texto: 'Rompe huesos, causando dolor y limitación de movimiento. -30 Agi en la zona.' },
    '[Frío]': { nombre: 'Frío', texto: 'En función de la diferencia entre el atributo que genera el efecto y el atributo resistencia del objetivo los efectos causados varían, revisar guía.' },
    '[Calor]': { nombre: 'Calor', texto: 'En función de la diferencia entre el atributo que genera el efecto y el atributo resistencia del objetivo los efectos causados varían, revisar guía.' },
    '[Hemorragia leve]': { nombre: 'Hemorragia leve', texto: 'Causa pérdida de sangre continua menor. Quita 10 puntos de energía cada turno durante 2 turnos.' },
    '[Hemorragia media]': { nombre: 'Hemorragia media', texto: 'Provoca pérdida de sangre continua moderada. Quita 20 puntos de energía cada turno durante 4 turnos.' },
    '[Hemorragia grave]': { nombre: 'Hemorragia grave', texto: 'Genera pérdida de sangre continua severa. Quita 40 puntos de energía cada turno de manera indefinida.' },
    '[Quemadura leve]': { nombre: 'Quemadura leve', texto: 'Produce daño superficial por calor. Hace 20 puntos de daño directo por turno durante 2 turnos.' },
    '[Quemadura media]': { nombre: 'Quemadura media', texto: 'Provoca daño significativo por calor. Hace 40 puntos de daño directo por turno durante 3 turnos.' },
    '[Quemadura grave]': { nombre: 'Quemadura grave', texto: 'Causa daño severo por calor. Hace 80 puntos de daño directo por turno.' },
    '[Miedo]': { nombre: 'Miedo', texto: 'Infunde temor, reduciendo la eficacia en combate, -10 Voluntad.' },
    '[Sordera]': { nombre: 'Sordera', texto: 'Dificulta la audición temporalmente, -8 Reflejos.' },
    '[Sueño]': { nombre: 'Sueño', texto: 'Induce un estado de sueño, el objetivo pierde 5 puntos en Fuerza, Agilidad, Destreza, Puntería, Control de Akuma y Reflejos.' },
    '[Terror]': { nombre: 'Terror', texto: 'Provoca un estado de pánico extremo, -15 Vol.' }
  };
  FICHA.efectosArray = Object.keys(FICHA.efectosCodigo);

  // ── Códigos de estadísticas ──────────────────────────────────

  FICHA.statCodigo = {
    RES: 'Resistencia', FUE: 'Fuerza', DES: 'Destreza', PUN: 'Puntería',
    AGI: 'Agilidad', REF: 'Reflejos', CAK: 'Control de Akuma', VOL: 'Voluntad', Nivel: 'Nivel'
  };

  // ── Helpers de efectos/stats ─────────────────────────────────

  FICHA.addEfectos = function (descripcion) {
    for (var i = 0; i < FICHA.efectosArray.length; i++) {
      var efecto = FICHA.efectosArray[i];
      descripcion = descripcion.replace(efecto,
        '<span class="tec-tooltip"><strong>' + FICHA.efectosCodigo[efecto].nombre +
        '</strong><span class="tec-tooltiptext">' + FICHA.efectosCodigo[efecto].texto + '</span></span>');
    }
    return descripcion;
  };

  FICHA.addEfectoStats = function (efectos) {
    console.log(efectos);
    var efectosRegex = /\[(\d+|\d+,\d+)x(RES|FUE|DES|PUN|AGI|REF|CAK|VOL|Nivel|NIVEL)]/g;
    var efectosMatch = Array.from(efectos.matchAll(efectosRegex));
    console.log(efectosMatch);
    for (var i = 0; i < efectosMatch.length; i++) {
      var codigo  = efectosMatch[i][0];
      var multi   = parseFloat(efectosMatch[i][1].replace(',', '.'));
      var statNum = (typeof w.statNums !== 'undefined') ? w.statNums[efectosMatch[i][2]] : 0;
      console.log(efectosMatch[i][2], multi, statNum);
      var htmlTag = '<span class="tec-tooltip"><strong>' + (statNum * multi).toFixed(1) +
        '</strong><span class="stat-tooltiptext">' + codigo + '</span></span>';
      efectos = efectos.replace(codigo, htmlTag);
    }
    for (var j = 0; j < FICHA.efectosArray.length; j++) {
      var ef = FICHA.efectosArray[j];
      efectos = efectos.replace(ef,
        '<span class="tec-tooltip"><strong>' + FICHA.efectosCodigo[ef].nombre +
        '</strong><span class="tec-tooltiptext">' + FICHA.efectosCodigo[ef].texto + '</span></span>');
    }
    return efectos;
  };

  FICHA.getStatCodigo = function (codigo) {
    console.log(codigo);
    return FICHA.statCodigo[codigo];
  };

  // ── Interceptor de errores ───────────────────────────────────

  w.originalError = w.onerror;
  w.onerror = function (message, source, lineno, colno, error) {
    if (message && message.indexOf('Cannot read properties of undefined') !== -1) {
      console.error('=== ERROR INTERCEPTADO ===');
      console.error('Mensaje:', message);
      console.error('Archivo:', source);
      console.error('Línea:', lineno);
      console.error('Columna:', colno);
      console.error('Error objeto:', error);
      FICHA.verificarObjetosInicializados();
      if (w.originalError) return w.originalError(message, source, lineno, colno, error);
      return true;
    }
    if (w.originalError) return w.originalError(message, source, lineno, colno, error);
  };

  // ── Fallback oficiosInfo ─────────────────────────────────────

  if (typeof w.oficiosInfo === 'undefined') {
    w.oficiosInfo = { nivel: 'desconocido' };
    console.warn('Variable oficiosInfo inicializada con valor de fallback');
  }

  // ── AJAX helper para mejoras (owner-only) ───────────────────

  FICHA.mejoraPOST = function (params, onSuccess, onError) {
    var uid = (typeof query_uid !== 'undefined') ? query_uid : '';
    var url = '/op/personaje.php?uid=' + uid;
    var body = Object.keys(params).map(function (k) {
      return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
    }).join('&');
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (data.success) { if (onSuccess) onSuccess(data); }
      else { alert(data.message || 'Error al realizar la mejora.'); if (onError) onError(data); }
    })
    .catch(function () {
      alert('Error de red al realizar la mejora.');
      if (onError) onError();
    });
  };

  // ── Cola de cambios pendientes ───────────────────────────────

  FICHA.PendingQueue = (function () {
    var _params    = {};  // params acumulados (campos cambiar_*) → POST /op/personaje.php
    var _personaje = [];  // array de mejoras (accion) → POST /op/personaje.php (secuencial)
    var _btn       = null;

    function _count() {
      return Object.keys(_params).length + _personaje.length;
    }

    function _updateBtn() {
      var n = _count();
      if (n === 0) { if (_btn) _btn.style.display = 'none'; return; }
      if (!_btn) {
        _btn = document.createElement('button');
        _btn.id = 'guardar-cambios-btn';
        _btn.style.cssText = [
          'position:fixed',
          'top:25vh',
          'left:50%',
          'transform:translateX(-50%)',
          'z-index:999999',
          'padding:12px 32px',
          'font-family:moonGetHeavy',
          'font-size:18px',
          'letter-spacing:1px',
          'color:white',
          'background:linear-gradient(135deg,#e85d04,#f48c06)',
          'border:3px solid rgba(255,255,255,0.3)',
          'border-radius:30px',
          'cursor:pointer',
          'box-shadow:0 6px 24px rgba(0,0,0,0.6)',
          'text-shadow:1px 1px 2px rgba(0,0,0,0.5)',
          'animation:pq-pulse 1.8s ease-in-out infinite'
        ].join(';');
        _btn.onclick = flush;
        var style = document.createElement('style');
        style.textContent = '@keyframes pq-pulse{0%,100%{box-shadow:0 6px 24px rgba(0,0,0,0.6)}50%{box-shadow:0 6px 36px rgba(232,93,4,0.8)}}';
        document.head.appendChild(style);
        document.body.appendChild(_btn);
      }
      _btn.textContent = '\uD83D\uDCBE Guardar Cambios (' + n + ')';
      _btn.style.display = 'block';
    }

    function _postFetch(url, params) {
      var body = Object.keys(params).map(function (k) {
        return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
      }).join('&');
      return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
      });
    }

    function _sendPersonajeSeq(queue, index, messages) {
      if (index >= queue.length) return Promise.resolve(messages);
      var uid = (typeof query_uid !== 'undefined') ? query_uid : '';
      return _postFetch('/op/personaje.php?uid=' + uid, queue[index])
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.message) messages.push(data.message);
          return _sendPersonajeSeq(queue, index + 1, messages);
        })
        .catch(function () {
          messages.push('Error de red en una operación de mejora.');
          return _sendPersonajeSeq(queue, index + 1, messages);
        });
    }

    var _reserved = { nikas: 0, puntosOficio: 0 };

    function reserve(costs) {
      if (costs.nikas)        _reserved.nikas        += costs.nikas;
      if (costs.puntosOficio) _reserved.puntosOficio += costs.puntosOficio;
    }

    function availableNikas()        { return (typeof nikas         !== 'undefined' ? nikas         : 0) - _reserved.nikas; }
    function availablePuntosOficio() { return (typeof puntos_oficio  !== 'undefined' ? puntos_oficio  : 0) - _reserved.puntosOficio; }

    function addParams(params) {
      Object.assign(_params, params);
      _updateBtn();
    }

    function addPersonaje(params) {
      _personaje.push(params);
      _updateBtn();
    }

    function flush() {
      if (_count() === 0) return;
      if (_btn) { _btn.disabled = true; _btn.textContent = 'Guardando\u2026'; }
      var uid = (typeof query_uid !== 'undefined') ? query_uid : '';
      var personajeCopy = _personaje.slice();
      var start = (Object.keys(_params).length > 0)
        ? _postFetch('/op/personaje.php?uid=' + uid, _params).then(function () { return []; })
        : Promise.resolve([]);
      start
        .then(function () { return _sendPersonajeSeq(personajeCopy, 0, []); })
        .then(function (msgs) {
          if (msgs.length > 0) alert(msgs.join('\n'));
          location.reload();
        })
        .catch(function () {
          if (_btn) { _btn.disabled = false; _btn.textContent = '\uD83D\uDCBE Guardar Cambios (' + _count() + ')'; }
          alert('Error al guardar los cambios. Inténtalo de nuevo.');
        });
    }

    return { addParams: addParams, addPersonaje: addPersonaje, reserve: reserve, availableNikas: availableNikas, availablePuntosOficio: availablePuntosOficio, flush: flush };
  })();

  // ── Exponer globalmente para compatibilidad ──────────────────

  w.addEfectos      = FICHA.addEfectos;
  w.addEfectoStats  = FICHA.addEfectoStats;
  w.getStatCodigo   = FICHA.getStatCodigo;
  w.sleep           = FICHA.sleep;
  w.capitalize      = FICHA.capitalize;
  w.getNumericPrefix = FICHA.getNumericPrefix;
  w.mejoraPOST      = FICHA.mejoraPOST;
  w.PendingQueue    = FICHA.PendingQueue;

  w.FICHA = FICHA;

})(window);
