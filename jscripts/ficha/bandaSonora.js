/**
 * Banda sonora de la ficha — reproductor real de YouTube (IFrame API).
 *
 * Dos reproductores distintos, nunca los dos sonando a la vez:
 *  - "de fondo" (oculto, 2x2px): el que suena solo al entrar a la ficha, si
 *    el visitante tiene la preferencia de autoplay activada. Vive siempre
 *    en #pr-banda-sonora-player mientras el modal está cerrado.
 *  - "del modal" (visible, con controles normales de YouTube): se crea recién
 *    al abrir el modal de "Banda Sonora" (para que el visitante vea el video
 *    que eligieron), y se destruye al cerrarlo. Mientras existe, el de fondo
 *    queda en pausa — así nunca suenan los dos juntos.
 * No se pasa el mismo <iframe> de uno a otro (moverlo de contenedor en el DOM
 * hace que varios navegadores lo recarguen desde cero) — son instancias
 * separadas de YT.Player con el mismo videoId.
 *
 * window.bandaSonora expone la API compartida que usa modales.js (el modal
 * de edición/reproducción) para no duplicar estado entre archivos.
 */
(function () {
  'use strict';

  var LS_VOLUMEN  = 'opg_banda_sonora_volumen';
  var LS_AUTOPLAY = 'opg_banda_sonora_autoplay';

  // Un ID de video de YouTube: 11 caracteres de [A-Za-z0-9_-].
  var ID_YOUTUBE = /^[A-Za-z0-9_-]{11}$/;

  function resultadoYoutube(ok, id, motivo) {
    return { ok: ok, vacio: false, id: id || '', canonica: ok ? 'https://www.youtube.com/watch?v=' + id : '', motivo: motivo || '' };
  }

  /**
   * Analiza lo que escribió el usuario y devuelve { ok, vacio, id, canonica, motivo }.
   * Acepta, con o sin https:// o http://, con o sin www., m. o music.:
   *   youtube.com/watch?v=ID (el parámetro v puede ir en cualquier posición)
   *   youtu.be/ID, youtube.com/embed/ID, /v/ID, /shorts/ID, /live/ID
   *   y youtube-nocookie.com/embed/ID.
   * "canonica" es la forma normalizada que se guarda: https://www.youtube.com/watch?v=ID.
   * Debe coincidir con opg_banda_sonora_normalizar() de inc/plugins/lib/banda_sonora.php.
   */
  function analizarEnlaceYoutube(texto) {
    var t = String(texto == null ? '' : texto).trim().replace(/^[<"'\s]+|[>"'\s]+$/g, '');
    if (t === '') {
      return { ok: false, vacio: true, id: '', canonica: '', motivo: 'Escribe un enlace de YouTube.' };
    }
    if (!/^[a-z][a-z0-9+.\-]*:\/\//i.test(t)) { t = 'https://' + t.replace(/^\/\//, ''); }
    var u;
    try { u = new URL(t); } catch (e) { return resultadoYoutube(false, '', 'No parece un enlace válido.'); }

    var host = u.hostname.toLowerCase().replace(/^(www|m|music)\./, '');
    var id = '';
    if (host === 'youtu.be') {
      id = u.pathname.split('/')[1] || '';
    } else if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
      if (/^\/watch\/?$/.test(u.pathname)) {
        id = u.searchParams.get('v') || '';
      } else {
        var m = u.pathname.match(/^\/(?:embed|v|shorts|live)\/([^\/]+)/);
        id = m ? m[1] : '';
      }
    } else {
      return resultadoYoutube(false, '', 'El enlace debe ser de YouTube (youtube.com o youtu.be).');
    }
    if (!id) {
      var esLista = u.searchParams.get('list') || /^\/(playlist|channel|c|user|@)/.test(u.pathname);
      return resultadoYoutube(false, '', esLista
        ? 'Ese enlace es de una lista o un canal. Usa el enlace de un video concreto.'
        : 'No se encontró el ID del video en el enlace.');
    }
    if (!ID_YOUTUBE.test(id)) {
      return resultadoYoutube(false, '', 'El ID del video no es válido (debe tener 11 letras, números, - o _).');
    }
    return resultadoYoutube(true, id, '');
  }
  window.analizarEnlaceYoutube = analizarEnlaceYoutube;

  function extraerYoutubeId(url) {
    var r = analizarEnlaceYoutube(url);
    return r.ok ? r.id : '';
  }
  window.extraerYoutubeId = extraerYoutubeId;

  /**
   * Comprueba con el oEmbed de YouTube si un video existe y se puede incrustar, sin
   * cargarlo. callback recibe: 'ok', 'no-existe', 'no-incrustable' o 'desconocido'
   * (sin red, bloqueado por el navegador o sin respuesta en 6 s: no se puede asegurar nada).
   */
  function verificarVideoYoutube(id, callback) {
    if (!window.fetch) { callback('desconocido'); return; }
    var terminado = false;
    function fin(resultado) {
      if (terminado) { return; }
      terminado = true;
      clearTimeout(reloj);
      callback(resultado);
    }
    var reloj = setTimeout(function () { fin('desconocido'); }, 6000);
    var url = 'https://www.youtube.com/oembed?format=json&url=' + encodeURIComponent('https://www.youtube.com/watch?v=' + id);
    fetch(url).then(function (r) {
      if (r.ok) { fin('ok'); }
      else if (r.status === 404 || r.status === 400) { fin('no-existe'); }
      else if (r.status === 401 || r.status === 403) { fin('no-incrustable'); }
      else { fin('desconocido'); }
    }).catch(function () { fin('desconocido'); });
  }

  // Mensaje de estado del modal de edición (#bandaSonoraEstado), si está abierto.
  function avisoModal(texto, tipo) {
    var el = document.getElementById('bandaSonoraEstado');
    if (!el) { return; }
    el.textContent = texto || '';
    el.style.color = tipo === 'ok' ? '#1d6b3a' : (tipo === 'err' ? '#a31621' : '#5e5e5e');
  }

  var VOLUMEN_POR_DEFECTO = 5;

  function leerVolumenGuardado() {
    try {
      var v = parseInt(localStorage.getItem(LS_VOLUMEN), 10);
      return (v >= 0 && v <= 100) ? v : VOLUMEN_POR_DEFECTO;
    } catch (e) {
      return VOLUMEN_POR_DEFECTO; // localStorage bloqueado (modo privado, etc.): valor por defecto, no rompe nada
    }
  }
  function guardarVolumenGuardado(v) {
    try { localStorage.setItem(LS_VOLUMEN, String(v)); } catch (e) { /* ídem */ }
  }

  // Preferencia GLOBAL del visitante (aplica a todas las fichas, no solo
  // esta) — por defecto activada, para mantener el efecto original de "suena
  // solo al entrar"; el visitante puede apagarla desde el modal y desde ahí
  // se respeta en cualquier otra ficha que visite después.
  function leerAutoplayGuardado() {
    try {
      var v = localStorage.getItem(LS_AUTOPLAY);
      return v === null ? true : v === '1';
    } catch (e) {
      return true;
    }
  }
  function guardarAutoplayGuardado(v) {
    try { localStorage.setItem(LS_AUTOPLAY, v ? '1' : '0'); } catch (e) { /* ídem */ }
  }

  var volumenActual = leerVolumenGuardado();
  var videoIdInicial = extraerYoutubeId(typeof banda_sonora !== 'undefined' ? banda_sonora : '');

  // El reproductor de fondo arranca muteado (mute:1 más abajo) porque los
  // navegadores no dejan autoplay con sonido de otra forma. Sin esto nunca
  // se des-mutea solo: hace falta una interacción REAL del visitante con la
  // página (cualquiera, no tiene que ser con la banda sonora) para que el
  // navegador permita reproducir con volumen. Se registra una sola vez,
  // cubre los dos órdenes posibles: que el visitante interactúe antes o
  // después de que el reproductor termine de crearse.
  var _yaInteractuo = false;
  function _marcarPrimeraInteraccion() {
    if (_yaInteractuo) { return; }
    _yaInteractuo = true;
    document.removeEventListener('click', _marcarPrimeraInteraccion, true);
    document.removeEventListener('keydown', _marcarPrimeraInteraccion, true);
    document.removeEventListener('touchstart', _marcarPrimeraInteraccion, true);
    if (window.player && typeof window.player.unMute === 'function') {
      window.player.unMute();
      window.player.setVolume(volumenActual);
    }
  }
  document.addEventListener('click', _marcarPrimeraInteraccion, true);
  document.addEventListener('keydown', _marcarPrimeraInteraccion, true);
  document.addEventListener('touchstart', _marcarPrimeraInteraccion, true);

  // ── Carga diferida de la API de YouTube ─────────────────────────
  // Antes se cargaba siempre que hubiera banda sonora. Ahora puede hacer
  // falta más tarde nomás (alguien abre el modal en una ficha sin banda
  // sonora guardada y le da "Probar"), así que cualquiera de los dos casos
  // pide la API por esta misma función — solo se inserta el <script> una vez.
  var _apiCargando = false;
  var _apiCallbacks = [];
  function asegurarApiYoutube(callback) {
    if (window.YT && window.YT.Player) { callback(); return; }
    _apiCallbacks.push(callback);
    if (_apiCargando) { return; }
    _apiCargando = true;
    window.onYouTubeIframeAPIReady = function () {
      var cbs = _apiCallbacks;
      _apiCallbacks = [];
      cbs.forEach(function (cb) { cb(); });
    };
    var tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    var primerScript = document.getElementsByTagName('script')[0];
    primerScript.parentNode.insertBefore(tag, primerScript);
  }

  // ── Reproductor de fondo (oculto, autoplay muteado) ─────────────
  function crearReproductorFondo() {
    if (!videoIdInicial || window.player) { return; }
    asegurarApiYoutube(function () {
      if (window.player) { return; } // pudo crearse mientras se cargaba la API
      window.player = new YT.Player('pr-banda-sonora-player', {
        videoId: videoIdInicial,
        // width/height chicos a propósito: YT.Player() reemplaza el div
        // objetivo por un <iframe> nuevo que no hereda su estilo inline — el
        // envoltorio de afuera (overflow:hidden) ya lo oculta, esto es
        // refuerzo por si hay un instante entre que se crea el iframe y el
        // navegador termina de aplicar ese overflow.
        width: '2',
        height: '2',
        playerVars: { autoplay: 1, mute: 1, controls: 0, enablejsapi: 1 },
        events: {
          onReady: function (e) {
            e.target.setVolume(volumenActual);
            e.target.playVideo();
            if (_yaInteractuo) { e.target.unMute(); } // el visitante ya había interactuado antes de que esto terminara de cargar
          },
        },
      });
    });
  }

  // ── Reproductor visible dentro del modal ────────────────────────
  var modalPlayer = null;
  var fondoEstabaSonando = false;

  function mostrarEnModal(containerId, videoIdOverride) {
    var idAMostrar = videoIdOverride || videoIdInicial;
    if (!idAMostrar) { return; }

    fondoEstabaSonando = !!(window.player && typeof window.player.getPlayerState === 'function'
      && window.player.getPlayerState() === YT.PlayerState.PLAYING);
    if (window.player && fondoEstabaSonando) { window.player.pauseVideo(); }

    asegurarApiYoutube(function () {
      var contenedor = document.getElementById(containerId);
      if (!contenedor) { return; } // el modal ya se cerró antes de que cargara la API
      if (modalPlayer) { try { modalPlayer.destroy(); } catch (e) { /* ya destruido */ } modalPlayer = null; }
      modalPlayer = new YT.Player(containerId, {
        videoId: idAMostrar,
        width: '100%',
        height: '100%',
        playerVars: { controls: 1, enablejsapi: 1 },
        events: {
          // Sin playVideo() acá a propósito: el visitante ve el video
          // cargado (con su miniatura y el botón de play nativo de
          // YouTube, o el ▶️/⏸ de acá al lado) pero no arranca solo — recién
          // suena si lo activa. Antes arrancaba directo al abrir el modal.
          onReady: function (e) {
            e.target.setVolume(volumenActual);
          },
          // 2: ID inválido; 5: error del reproductor; 100: no existe o es privado; 101 y 150: el dueño no permite reproducirlo fuera de YouTube.
          onError: function (e) {
            avisoModal('YouTube no pudo cargar este video (código ' + e.data + '). Puede no existir, ser privado o no permitir reproducirlo aquí.', 'err');
          },
        },
      });
    });
  }

  // Usado por "Probar" en el modal: carga una URL sin guardar todavía.
  function actualizarVideoModal(containerId, url) {
    var id = extraerYoutubeId(url);
    if (!id) { return false; }
    mostrarEnModal(containerId, id);
    return true;
  }

  function ocultarDeModal() {
    if (modalPlayer) {
      try { modalPlayer.destroy(); } catch (e) { /* ya destruido */ }
      modalPlayer = null;
    }
    if (window.player) {
      // El volumen pudo cambiar mientras el modal estaba abierto (se le
      // aplicaba al reproductor del modal, no a este) — sincronizarlo acá
      // para que se note al toque, sin esperar a un reload.
      if (typeof window.player.setVolume === 'function') { window.player.setVolume(volumenActual); }
      if (fondoEstabaSonando) { window.player.playVideo(); }
    }
  }

  function alternarModal() {
    if (!modalPlayer || typeof modalPlayer.getPlayerState !== 'function') { return; }
    if (modalPlayer.getPlayerState() === YT.PlayerState.PLAYING) { modalPlayer.pauseVideo(); } else { modalPlayer.playVideo(); }
  }

  function cambiarVolumen(v) {
    volumenActual = Math.max(0, Math.min(100, parseInt(v, 10) || 0));
    guardarVolumenGuardado(volumenActual);
    // Afecta al que esté sonando en ese momento: el del modal si está
    // abierto, si no, el de fondo.
    var objetivo = modalPlayer || window.player;
    if (objetivo && typeof objetivo.setVolume === 'function') { objetivo.setVolume(volumenActual); }
  }

  function setAutoplayHabilitado(valor) {
    guardarAutoplayGuardado(valor);
    if (valor) {
      // Si el reproductor de fondo ya existía (se creó al cargar la
      // página) crearReproductorFondo() no hace nada — hay que retomarlo a
      // mano; solo se crea de cero si esta ficha nunca lo tuvo.
      if (window.player) { window.player.playVideo(); } else { crearReproductorFondo(); }
    } else if (window.player) {
      window.player.pauseVideo();
    }
  }

  window.bandaSonora = {
    volumenActual: function () { return volumenActual; },
    cambiarVolumen: cambiarVolumen,
    autoplayHabilitado: leerAutoplayGuardado,
    setAutoplayHabilitado: setAutoplayHabilitado,
    mostrarEnModal: mostrarEnModal,
    actualizarVideoModal: actualizarVideoModal,
    verificarVideo: verificarVideoYoutube,
    aviso: avisoModal,
    ocultarDeModal: ocultarDeModal,
    alternarModal: alternarModal,
  };

  if (leerAutoplayGuardado()) {
    crearReproductorFondo();
  }

  // ── Detectar el cierre del modal compartido (#myModal) ──────────
  // Hay más de diez lugares en jscripts/ficha/*.js que hacen
  // `modal.style.display = 'none'` (uno por cada campo editable, más el
  // cierre por click afuera en init.js) y no hay un único punto de "se
  // cerró" para engancharse — en vez de tocar los diez, se observa el propio
  // atributo style del modal y se reacciona solo cuando de verdad importa
  // (hay un modalPlayer vivo).
  document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('myModal');
    if (!modalEl) { return; }
    new MutationObserver(function () {
      if (modalEl.style.display === 'none' && modalPlayer) {
        ocultarDeModal();
      }
    }).observe(modalEl, { attributes: true, attributeFilter: ['style'] });
  });
})();
