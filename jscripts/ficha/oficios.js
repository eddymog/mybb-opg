/**
 * ficha/oficios.js — Oficios y especializaciones
 * Requiere: core.js
 */
(function (FICHA, w) {

  function subirEspeOficio(espeNumber, oficioNumber, espe, nikasCost, puntosOficioCost) {
    FICHA.PendingQueue.addPersonaje({ accion: 'oficio_espe', espeNumber: espeNumber, oficioNumber: oficioNumber, espe: espe });
    FICHA.PendingQueue.reserve({ nikas: nikasCost || 0, puntosOficio: puntosOficioCost || 0 });
    _previewEspeOficio(espeNumber, oficioNumber, espe);
  }

  function subirOficio(oficio, oficioNumber, puntosOficioCost) {
    FICHA.PendingQueue.addPersonaje({ accion: 'oficio', oficio: oficio, oficioNumber: oficioNumber });
    FICHA.PendingQueue.reserve({ puntosOficio: puntosOficioCost || 0 });
    _previewOficio(oficio, oficioNumber);
  }

  // ── Preview (actualización inmediata de UI sin guardar) ────────────

  function _previewOficio(oficio, oficioNumber) {
    if (!oficios[oficio]) return;
    oficios[oficio].nivel++;
    var suffix = oficioNumber === 'oficio1' ? 'Nivel' : 'Nivel2';
    $('#' + oficio + suffix).html(oficios[oficio].nivel).css('background-color', '#28ce26').css('cursor', 'default');
  }

  function _previewEspeOficio(espeSlot, oficioSlot, espe) {
    var oficioName = oficioSlot === 'oficio1' ? oficio1 : oficio2;
    if (!oficios[oficioName] || typeof oficios[oficioName].sub[espe] !== 'number') return;
    oficios[oficioName].sub[espe]++;
    var suffix = oficioSlot === 'oficio1' ? 'Nivel' : 'Nivel2';
    var newVal = oficios[oficioName].sub[espe];
    var $tag = $('#' + espe + suffix);
    $tag.html(newVal);
    if (newVal >= 3) $tag.css('background-color', '#25ba23').css('cursor', 'default');
  }

  function chooseEspeOficio(oficio, espe) {
    if (!oficios[oficio]) { console.warn(`Oficio '${oficio}' no encontrado en el objeto oficios`); return; }

    var isOficio1 = oficio1 == oficio;
    var isOficio2 = oficio2 == oficio;
    var espesCount = 0;
    var isEspe1 = oficios[oficio].espe1 == espe;
    var isEspe2 = oficios[oficio].espe2 == espe;
    if (oficios[oficio].espe1) espesCount++;
    if (oficios[oficio].espe2) espesCount++;

    var espeNivel = oficios[oficio].sub[espe];

    if (espeNivel == 2) {
      if (nivel < 50) { alert(`No cumples el requisito mínimo de nivel 50 para aprender la especialización ` + espe + `.`); return; }
      var _espeSlotA = isEspe1 ? 'espe1' : 'espe2';
      var _oficioSlotA = isOficio1 ? 'oficio1' : isOficio2 ? 'oficio2' : null;
      if (!_oficioSlotA) { alert(`No cumples los requisitos para mejorar la especialización ` + espe + `. Debes tener 50 nikas y 5000 puntos de oficio.`); return; }
      if (FICHA.PendingQueue.availableNikas() >= 50 && FICHA.PendingQueue.availablePuntosOficio() >= 5000) {
        if (confirm(`Subir a nivel 3 la especialización ` + espe + ` tiene un costo de 50 nikas y 5000 puntos de oficio. ¿Estás de acuerdo?`)) { subirEspeOficio(_espeSlotA, _oficioSlotA, espe, 50, 5000); }
      } else { alert(`No cumples los requisitos para mejorar la especialización ` + espe + `. Debes tener 50 nikas y 5000 puntos de oficio.`); }

    } else if (espeNivel == 1) {
      if (nivel < 30) { alert(`No cumples el requisito mínimo de nivel 30 para aprender la especialización ` + espe + `.`); return; }
      var _espeSlotB = isEspe1 ? 'espe1' : 'espe2';
      var _oficioSlotB = isOficio1 ? 'oficio1' : isOficio2 ? 'oficio2' : null;
      if (!_oficioSlotB) { alert(`No cumples los requisitos para mejorar la especialización ` + espe + `. Debes tener 25 nikas y 3500 puntos de oficio.`); return; }
      if (FICHA.PendingQueue.availableNikas() >= 25 && FICHA.PendingQueue.availablePuntosOficio() >= 3500) {
        if (confirm(`Subir a nivel 2 la especialización ` + espe + ` tiene un costo de 25 nikas y 3500 puntos de oficio. ¿Estás de acuerdo?`)) { subirEspeOficio(_espeSlotB, _oficioSlotB, espe, 25, 3500); }
      } else { alert(`No cumples los requisitos para mejorar la especialización ` + espe + `. Debes tener 25 nikas y 3500 puntos de oficio.`); }

    } else if (espeNivel == 0) {
      if (nivel < 20) { alert(`No cumples el requisito mínimo de nivel 20 para aprender la especialización ` + espe + `.`); return; }
      var _espeSlotC = espesCount === 0 ? 'espe1' : 'espe2';
      var _oficioSlotC = isOficio1 ? 'oficio1' : isOficio2 ? 'oficio2' : null;
      if (!_oficioSlotC) { alert(`No cumples los requisitos para aprender la especialización ` + espe + `. Debes tener 10 nikas y 2000 puntos de oficio.`); return; }
      if (FICHA.PendingQueue.availableNikas() >= 10 && FICHA.PendingQueue.availablePuntosOficio() >= 2000) {
        if (confirm(`Para aprender la especialización ` + espe + ` tiene un costo de 10 nikas y 2000 puntos de oficio. ¿Estás de acuerdo?`)) { subirEspeOficio(_espeSlotC, _oficioSlotC, espe, 10, 2000); }
      } else { alert(`No cumples los requisitos para aprender la especialización ` + espe + `. Debes tener 10 nikas y 2000 puntos de oficio.`); }
    }
  }

  function chooseOficio(oficio, hasOficio) {
    var isOficio1 = oficio1 == oficio;
    var isOficio2 = oficio2 == oficio;

    if (hasOficio) {
      var _ofSlot = isOficio1 ? 'oficio1' : isOficio2 ? 'oficio2' : null;
      if (!_ofSlot) { alert(`No cumples los 1000 puntos de oficio o de nivel 10 para mejorar el oficio de ` + oficio + `.`); return; }
      if (FICHA.PendingQueue.availablePuntosOficio() >= 1000 && nivel >= 10) {
        if (confirm(`Para subir el nivel al oficio de ` + oficio + ` tiene un costo de 1000 puntos de oficio. ¿Estás de acuerdo?`)) { subirOficio(oficio, _ofSlot, 1000); }
      } else { alert(`No cumples los 1000 puntos de oficio o de nivel 10 para mejorar el oficio de ` + oficio + `.`); }
    } else {
      if (has_polivalente || has_erudito) {
        if (confirm(`Aprender el segundo oficio ` + oficio + ` es gratis para Eruditos y Polivalentes. ¿Estás de acuerdo con aprender este oficio?`)) { subirOficio(oficio, 'oficio2', 0); }
      } else {
        alert('No tienes acceso a subir el segundo oficio');
      }
    }
  }

  function chooseOficioTest() { openOficiosModal(); }

  function openOficiosModal() {
    // Catálogo de oficios inyectado por el servidor desde BD — ver
    // OPG Catálogos en el admin. Copia de la misma construcción en
    // jscripts/ficha_script2.js.
    var oficiosDisponibles = (window.OPG_CATALOGO_OFICIOS || []).map(function(o) {
      return { nombre: o.nombre, imagen: o.imagen };
    });

    var modalId = 'oficiosModal';
    var modal = document.getElementById(modalId);
    if (!modal) {
      $('body').append('<div id="' + modalId + '" class="modal"></div>');
      modal = document.getElementById(modalId);
    }

    var modalContent = '<div class="modal-content oficios-modal-content">';
    modalContent += '<span class="close" onclick="closeOficiosModal()" style="position: absolute; right: 20px; top: 10px; font-size: 28px; cursor: pointer; z-index: 10001;">&times;</span>';
    modalContent += `<div class="modal-header" style="padding: 15px; background: ${borderColor}; text-align: center; border-radius: 8px 8px 0 0;">`;
    modalContent += '<h2 style="color: white; font-family: \'moonGetHeavy\'; margin: 0; text-shadow: 1px 1px 2px black;">SELECCIONAR SEGUNDO OFICIO</h2>';
    modalContent += '</div>';
    modalContent += '<div class="modal-body oficios-grid">';

    oficiosDisponibles.forEach(function (oficio) {
      var isDisabled    = (oficio1 === oficio.nombre);
      var disabledClass = isDisabled ? 'oficio-disabled' : '';
      var disabledText  = isDisabled ? '<div class="oficio-ya-aprendido">YA APRENDIDO</div>' : '';
      modalContent += '<div class="oficio-card ' + disabledClass + '" onclick="' + (isDisabled ? '' : 'seleccionarOficio(\'' + oficio.nombre + '\')') + '">';
      modalContent += '<div class="oficio-imagen-container">';
      modalContent += '<img src="' + oficio.imagen + '" alt="' + oficio.nombre + '" class="oficio-imagen">';
      modalContent += disabledText;
      modalContent += '</div>';
      modalContent += '<div class="oficio-nombre">' + oficio.nombre + '</div>';
      modalContent += '</div>';
    });

    modalContent += '</div></div>';
    modal.innerHTML = modalContent;
    modal.style.display = 'block';

    window.onclick = function (event) {
      if (event.target == modal) modal.style.display = 'none';
    };
  }

  function seleccionarOficio(nombreOficio) {
    if (has_polivalente || has_erudito) {
      if (confirm('¿Estás seguro de que quieres aprender el oficio de ' + nombreOficio + '? Esta acción es permanente.')) {
        subirOficio(nombreOficio, 'oficio2');
      }
    } else {
      alert('No tienes acceso a aprender un segundo oficio. Necesitas la virtud Polivalente o Erudito.');
    }
  }

  function closeOficiosModal() {
    var modal = document.getElementById('oficiosModal');
    if (modal) modal.style.display = 'none';
  }

  w.chooseEspeOficio  = chooseEspeOficio;
  w.chooseOficio      = chooseOficio;
  w.subirEspeOficio   = subirEspeOficio;
  w.subirOficio       = subirOficio;
  w.chooseOficioTest  = chooseOficioTest;
  w.openOficiosModal  = openOficiosModal;
  w.seleccionarOficio = seleccionarOficio;
  w.closeOficiosModal = closeOficiosModal;

})(window.FICHA, window);
