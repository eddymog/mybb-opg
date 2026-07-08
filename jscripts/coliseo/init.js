/**
 * coliseo/init.js — Inicialización de la página Coliseo
 * Requiere: core.js, pestanas.js
 */
(function (COLISEO, w) {

  // ── Modal: Desafiar a un amigo ────────────────────────────────

  var desafioModal = null;

  function abrirModalDesafio() {
    desafioModal = document.getElementById('coliseo-modal-desafio');
    if (desafioModal) desafioModal.style.display = 'block';
    document.getElementById('coliseo-desafio-username').value = '';
    document.getElementById('coliseo-desafio-mensaje').value  = '';
    document.getElementById('coliseo-desafio-feedback').innerHTML = '';
    document.getElementById('coliseo-desafio-sugerencias').style.display = 'none';
  }

  function cerrarModalDesafio() {
    if (desafioModal) desafioModal.style.display = 'none';
  }

  function enviarDesafio() {
    var username = $('#coliseo-desafio-username').val().trim();
    var mensaje  = $('#coliseo-desafio-mensaje').val().trim();
    var feedback = $('#coliseo-desafio-feedback');

    if (!username) {
      feedback.css('color', '#ff6666').html('Debes indicar el nombre de usuario del retado.');
      return;
    }

    feedback.css('color', '#aaa').html('Enviando desafío…');

    // TODO: cuando exista el endpoint, reemplazar con COLISEO.post()
    // COLISEO.post({ accion: 'desafiar', username: username, mensaje: mensaje }, function (res) {
    //   var data = JSON.parse(res);
    //   if (data.success) { feedback.css('color','#66ff66').html(data.message); }
    //   else              { feedback.css('color','#ff6666').html(data.message); }
    // });

    // Placeholder hasta que exista la lógica de desafíos
    setTimeout(function () {
      feedback.css('color', '#ffaa44').html('(Sistema de desafíos en construcción)');
    }, 600);
  }

  // Cerrar modal al clicar fuera del contenido
  $(w).on('click', function (e) {
    if (desafioModal && e.target === desafioModal) cerrarModalDesafio();
  });

  // Exponer al scope global (los onclick del template los necesitan)
  w.abrirModalDesafio  = abrirModalDesafio;
  w.cerrarModalDesafio = cerrarModalDesafio;
  w.enviarDesafio      = enviarDesafio;

  // ── Inicialización ────────────────────────────────────────────

  $(document).ready(function () {

    // Restaurar pestaña desde PHP param o sessionStorage
    if (typeof coliseo_pestana !== 'undefined' && coliseo_pestana !== '') {
      clickPestanaColiseo(coliseo_pestana);
    } else {
      var lastTab = sessionStorage.getItem('lastColiseoTab') || 'portada';
      clickPestanaColiseo(lastTab);
    }

  });

})(window.COLISEO, window);
