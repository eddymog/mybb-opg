(function () {
  'use strict';

  var PostEditor = {
    init: function (editor) {
      this._editor = editor;
      editor.css('@import url("/jscripts/sceditor/styles/opg-post-writing.css?ver=5");');
      this._initContador();
      this._initBorrador();
      this._initBBCodesExternos();
    },

    _initContador: function () {
      var span = document.getElementById('palabras');
      if (!span) { return; }
      var self = this;
      var UMBRAL = 300;
      var timer = null;

      function recalcular() {
        var texto = self._editor.inSourceMode()
          ? (self._editor.val() || '').replace(/\[[^\]]*\]/g, ' ')
          : self._editor.getBody().innerText || '';
        var palabras = texto.split(/\s+/).filter(function (w) { return w !== ''; }).length;
        span.textContent = palabras;
        span.classList.toggle('pe-cumple', palabras >= UMBRAL);
      }

      self._editor.bind('valuechanged', function () {
        clearTimeout(timer);
        timer = setTimeout(recalcular, 180);
      });
      recalcular();
    },

    _initBorrador: function () {
      var boton = document.getElementById('recuperar-post');
      if (!boton) { return; }
      var self = this;

      self._editor.bind('valuechanged', function () {
        var body = self._editor.getBody();
        var texto = body.innerText || '';
        var palabras = texto.split(/\s+/).filter(function (w) { return w !== ''; }).length;
        if (palabras >= 10) {
          try { localStorage.setItem('user-post', body.innerHTML); } catch (e) { /* storage bloqueado, no romper el editor */ }
        }
      });

      boton.addEventListener('click', function (e) {
        e.preventDefault();
        if (self._editor.inSourceMode && self._editor.inSourceMode()) {
          alert('Sólo puedes recuperar lo último escrito en el post si el botón de "Ver código de Fuente (<>)" no está apretado/activo.');
          return;
        }
        var guardado;
        try { guardado = localStorage.getItem('user-post'); } catch (e) { guardado = null; }
        if (guardado === null) { return; }
        var actual = self._editor.getBody().innerHTML;
        if (actual === guardado) {
          alert('El último texto guardado es idéntico al que está actual en la página. No hay nada que recuperar.');
        } else {
          self._editor.getBody().innerHTML = guardado;
        }
      });
    },

    _initBBCodesExternos: function () {
      var overlay = document.getElementById('pe-bbcode-overlay');
      if (!overlay) { return; }
      var self = this;
      var tipoActual = null;

      document.querySelectorAll('.newpost-bbcodes [data-bbcode]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          tipoActual = btn.dataset.bbcode;
          if (tipoActual === 'ficha' || tipoActual === 'hide') {
            overlay.hidden = true;
            self._insertarBBCode(tipoActual);
            return;
          }
          self._mostrarCamposBBCode(tipoActual);
          overlay.hidden = false;
          var campo = document.getElementById({
            dado: 'pe-dado-cantidad',
            tecnica: 'pe-tecnica-tid',
            spoiler: 'pe-spoiler-titulo',
            recursos: 'pe-recursos-vida',
            objeto: 'pe-objeto-id',
            consumir: 'pe-objeto-id'
          }[tipoActual]);
          if (campo) { campo.focus(); }
        });
      });

      var cerrar = document.getElementById('pe-bbcode-cerrar');
      if (cerrar) {
        cerrar.addEventListener('click', function () {
          overlay.hidden = true;
        });
      }
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) { overlay.hidden = true; }
      });

      var insertar = document.getElementById('pe-bbcode-insertar');
      if (insertar) {
        overlay.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
            e.preventDefault();
            insertar.click();
          }
        });
        insertar.addEventListener('click', function () {
          if (self._insertarBBCode(tipoActual)) {
            overlay.hidden = true;
          }
        });
      }
    },

    _mostrarCamposBBCode: function (tipo) {
      var titulos = {
        dado: 'Lanzar dados', tecnica: 'Insertar técnica', spoiler: 'Insertar spoiler',
        recursos: 'Insertar recursos', objeto: 'Insertar objeto', consumir: 'Consumir objeto'
      };
      var titulo = document.getElementById('pe-bbcode-titulo');
      if (titulo) { titulo.textContent = titulos[tipo] || ''; }
      document.querySelectorAll('.pe-campo-dado, .pe-campo-tecnica, .pe-campo-spoiler, .pe-campo-recursos, .pe-campo-objeto, .pe-aviso-consumir').forEach(function (el) {
        el.style.display = 'none';
      });
      var campoTipo = tipo === 'consumir' ? 'objeto' : tipo;
      document.querySelectorAll('.pe-campo-' + campoTipo).forEach(function (el) {
        el.style.display = '';
      });
      if (tipo === 'consumir') {
        document.querySelector('.pe-aviso-consumir').style.display = '';
      }
    },

    _insertarBBCode: function (tipo) {
      if (tipo === 'dado') {
        var cantEl = document.getElementById('pe-dado-cantidad');
        var carasEl = document.getElementById('pe-dado-caras');
        var c = Math.min(20, Math.max(1, parseInt(cantEl.value, 10) || 1));
        var k = Math.min(100000, Math.max(1, parseInt(carasEl.value, 10) || 1));
        this._editor.focus();
        this._editor.insert('[' + c + 'd' + k + ']');
      } else if (tipo === 'tecnica') {
        var tecnicaEl = document.getElementById('pe-tecnica-tid');
        var tid = tecnicaEl.value.trim();
        if (!tid) { tecnicaEl.focus(); return false; }
        this._editor.focus();
        this._editor.insert('[tecnica=' + tid + ']');
      } else if (tipo === 'spoiler') {
        var titulo = document.getElementById('pe-spoiler-titulo').value.trim();
        var apertura = titulo ? '[spoiler="' + titulo.replace(/"/g, '&quot;') + '"]' : '[spoiler]';
        this._editor.focus();
        this._editor.insert(apertura, '[/spoiler]');
      } else if (tipo === 'ficha') {
        this._editor.focus();
        this._editor.insert('[ficha]');
      } else if (tipo === 'hide') {
        this._editor.focus();
        this._editor.insert('[hide]', '[/hide]');
      } else if (tipo === 'recursos') {
        var nombres = ['vida', 'energia', 'haki'];
        var valores = [];
        for (var i = 0; i < nombres.length; i++) {
          var recursoEl = document.getElementById('pe-recursos-' + nombres[i]);
          var valor = Number(recursoEl.value.trim());
          if (!/^\d+$/.test(recursoEl.value.trim()) || !Number.isSafeInteger(valor)) {
            recursoEl.focus();
            return false;
          }
          valores.push(valor);
        }
        this._editor.focus();
        this._editor.insert('[recursos=' + valores.join('/') + ']');
      } else if (tipo === 'objeto' || tipo === 'consumir') {
        var objetoEl = document.getElementById('pe-objeto-id');
        var id = objetoEl.value.trim();
        if (!id || /[\[\]]/.test(id)) { objetoEl.focus(); return false; }
        this._editor.focus();
        this._editor.insert('[' + tipo + '=' + id + ']');
      } else {
        return false;
      }
      return true;
    }
  };

  window.PostEditor = PostEditor;

  $(function () {
    if (!window.MyBBEditor) { return; }
    PostEditor.init(MyBBEditor);
  });
})();
