/**
 * coliseo/pestanas.js — Navegación de pestañas del Coliseo
 * Requiere: core.js (COLISEO namespace)
 */
(function (COLISEO, w) {

  var TABS = ['portada', 'clasificacion', 'torneos', 'historial', 'reglamento'];

  function clickPestanaColiseo(tipo) {
    $('.coliseo-pestana').removeClass('coliseo-pestana-active');
    sessionStorage.setItem('lastColiseoTab', tipo);

    TABS.forEach(function (t) {
      $('#coliseo_' + t).css('display', 'none');
    });

    $('#coliseo_' + tipo).css('display', 'block');
    $('#pestana_coliseo_' + tipo).addClass('coliseo-pestana-active');
  }

  w.clickPestanaColiseo = clickPestanaColiseo;

})(window.COLISEO, window);
