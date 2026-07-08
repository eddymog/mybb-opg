/**
 * coliseo/core.js — Namespace COLISEO + utilidades compartidas
 * Debe cargarse ANTES que cualquier otro módulo coliseo/*.js
 */
(function (w) {
  if (w.COLISEO) return;

  var COLISEO = {
    version: '1.0.0'
  };

  COLISEO.sleep = function (time) {
    return new Promise(function (resolve) { setTimeout(resolve, time); });
  };

  COLISEO.post = function (data, callback) {
    $.ajax({
      url: '/op/coliseo.php',
      type: 'POST',
      data: data,
      success: callback,
      error: function (xhr) {
        console.error('[COLISEO] Error AJAX:', xhr.status, xhr.responseText);
      }
    });
  };

  w.COLISEO = COLISEO;
})(window);
