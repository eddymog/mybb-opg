(function () {
  'use strict';

  var PANEL_HEIGHT = 600;
  var btn, panel, belicoInited = false;

  function crearEstilos() {
    var style = document.createElement('style');
    style.textContent = [
      '#belico-btn-wrap {',
      '  display: flex;',
      '  justify-content: center;',
      '  margin: 8px 0 12px;',
      '}',
      '#belico-btn {',
      '  background: #8b0000;',
      '  color: #fff;',
      '  border: none;',
      '  padding: 10px 22px;',
      '  font-family: moonGetHeavy, sans-serif;',
      '  font-size: 15px;',
      '  letter-spacing: 2px;',
      '  cursor: pointer;',
      '  border-radius: 6px;',
      '  box-shadow: 2px 0 8px rgba(0,0,0,.5);',
      '  transition: background .2s, opacity .2s;',
      '  white-space: nowrap;',
      '}',
      '#belico-btn:hover { background: #a00000; }',
      '#belico-btn.oculto { opacity: 0; pointer-events: none; }',
      '#belico-panel {',
      '  position: fixed;',
      '  bottom: 0;',
      '  left: 0;',
      '  width: 100%;',
      '  height: ' + PANEL_HEIGHT + 'px;',
      '  background: #1a1a1a;',
      '  border-top: 3px solid #8b0000;',
      '  z-index: 8999;',
      '  box-shadow: 0 -4px 20px rgba(0,0,0,.7);',
      '  display: flex;',
      '  flex-direction: column;',
      '  transform: translateY(100%);',
      '  transition: transform .35s ease;',
      '}',
      '#belico-panel.abierto { transform: translateY(0); }',
      '#belico-panel-header {',
      '  display: flex;',
      '  align-items: center;',
      '  justify-content: space-between;',
      '  background: #8b0000;',
      '  padding: 8px 16px;',
      '  flex-shrink: 0;',
      '}',
      '#belico-panel-header span {',
      '  color: #fff;',
      '  font-family: moonGetHeavy, sans-serif;',
      '  font-size: 16px;',
      '  letter-spacing: 2px;',
      '}',
      '#belico-cerrar {',
      '  background: none;',
      '  border: none;',
      '  color: #fff;',
      '  font-size: 22px;',
      '  cursor: pointer;',
      '  line-height: 1;',
      '  padding: 0 4px;',
      '}',
      '#belico-panel-body {',
      '  flex: 1;',
      '  overflow-y: auto;',
      '  padding: 16px;',
      '  color: #ddd;',
      '  font-family: InterRegular, sans-serif;',
      '}',
    ].join('\n');
    document.head.appendChild(style);
  }

  function crearHTML() {
    var form = document.querySelector('form[name="input"]') ||
               document.querySelector('form[name="newreply"]');
    var tabla = form ? form.querySelector('table.tborder') : null;

    btn = document.createElement('button');
    btn.id = 'belico-btn';
    btn.type = 'button';
    btn.textContent = 'BÉLICO';

    var wrap = document.createElement('div');
    wrap.id = 'belico-btn-wrap';
    wrap.appendChild(btn);
    if (form) {
      form.parentNode.insertBefore(wrap, form.nextSibling);
    } else {
      document.body.appendChild(wrap);
    }

    panel = document.createElement('div');
    panel.id = 'belico-panel';
    panel.innerHTML = [
      '<div id="belico-panel-header">',
      '  <span>PANEL BÉLICO</span>',
      '  <button id="belico-cerrar" type="button" title="Cerrar">&times;</button>',
      '</div>',
      '<div id="belico-panel-body"></div>',
    ].join('');
    document.body.appendChild(panel);

    btn.addEventListener('click', function () { togglePanel(true); });
    document.getElementById('belico-cerrar').addEventListener('click', function () { togglePanel(false); });
  }

  function togglePanel(abrir) {
    if (!panel || !btn) return;
    panel.classList.toggle('abierto', abrir);
    btn.classList.toggle('oculto', abrir);
    if (abrir && !belicoInited && typeof BC !== 'undefined' && typeof BC.init === 'function') {
      belicoInited = true;
      try { BC.init(); } catch (e) {}
    }
  }

  function init() {
    crearEstilos();
    crearHTML();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
