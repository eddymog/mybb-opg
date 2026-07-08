/**
 * jscripts/tecnicas_buscar.js
 * Lógica del buscador de técnicas.
 * TB (objeto de datos) es inyectado por el template PHP antes de cargar este archivo.
 */
(function () {

  /* ── Etiquetas de daño (clave GET → texto a buscar en efectos) ── */
  var DANO_TAGS = {
    contundente: '[daño contundente]',
    cortante:    '[daño cortante]',
    perforante:  '[daño perforante]',
    sonico:      '[daño sónico]',
    espiritual:  '[daño espiritual]',
    fuego:       '[daño de fuego]',
    viento:      '[daño de viento]',
    agua:        '[daño de agua]',
    rayo:        '[daño de rayo]',
    hielo:       '[daño de hielo]',
    verdadero:   '[daño verdadero]',
    mitigado:    '[daño mitigado]'
  };

  /* ── Utilidades ─────────────────────────────────────────────── */
  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function resaltarDanos(texto, danosActivos) {
    var resultado = texto;
    danosActivos.forEach(function (clave) {
      var tag = DANO_TAGS[clave];
      if (!tag) return;
      var escaped = tag.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      var regex   = new RegExp('(' + escaped + ')', 'gi');
      resultado   = resultado.replace(regex, '<span class="tb-dano-match">$1</span>');
    });
    return resultado;
  }

  /* ── Renderizar tarjeta de técnica ──────────────────────────── */
  function renderizarTarjeta(tec, danosActivos) {
    var efectosTexto = tec.efectos
      ? resaltarDanos(escapeHtml(tec.efectos), danosActivos)
      : '<em style="color:#555">Sin efectos registrados</em>';

    var costes = '';
    if (tec.energia)       costes += '<span class="tb-coste tb-coste--energia">&#9889; ' + escapeHtml(tec.energia)       + '</span>';
    if (tec.energia_turno) costes += '<span class="tb-coste tb-coste--energia">&#9889;/T ' + escapeHtml(tec.energia_turno) + '</span>';
    if (tec.haki)          costes += '<span class="tb-coste tb-coste--haki">&#9670; ' + escapeHtml(tec.haki)          + '</span>';
    if (tec.haki_turno)    costes += '<span class="tb-coste tb-coste--haki">&#9670;/T ' + escapeHtml(tec.haki_turno)    + '</span>';
    if (tec.enfriamiento)  costes += '<span class="tb-coste tb-coste--enfr">&#8987; ' + escapeHtml(tec.enfriamiento)  + '</span>';

    var tags = '';
    if (tec.tier)   tags += '<span class="tb-tag tb-tag--tier">Tier '          + escapeHtml(tec.tier)   + '</span>';
    if (tec.clase)  tags += '<span class="tb-tag tb-tag--clase">'               + escapeHtml(tec.clase)  + '</span>';
    if (tec.tipo)   tags += '<span class="tb-tag tb-tag--tipo">'                + escapeHtml(tec.tipo)   + '</span>';
    if (tec.estilo) tags += '<span class="tb-tag tb-tag--estilo">'              + escapeHtml(tec.estilo) + '</span>';
    if (tec.rama && tec.rama !== tec.estilo)
                    tags += '<span class="tb-tag tb-tag--estilo">'              + escapeHtml(tec.rama)   + '</span>';

    var tidHtml = '';
    if (tec.tid) {
      var tidText = escapeHtml(tec.tid);
      tidHtml = TB.isStaff
        ? '<a href="/op/staff/tecnicas_modificar.php?tecnica_id=' + tidText + '" class="tb-tid tb-tid--link">' + tidText + '</a>'
        : '<span class="tb-tid">' + tidText + '</span>';
    }

    var editBtn = '';
    if (TB.isStaff && tec.tid) {
      var tidEsc = escapeHtml(tec.tid);
      editBtn = '<div style="margin-top:10px;text-align:center;">' +
        '<a href="/op/staff/tecnicas_modificar.php?tecnica_id=' + tidEsc + '" ' +
        'style="display:inline-block;background:linear-gradient(135deg,#e53935,#b71c1c);color:#fff;font-weight:bold;font-size:13px;padding:8px 20px;border-radius:20px;text-decoration:none;box-shadow:0 3px 10px rgba(0,0,0,.4);">' +
        '&#x270F; Editar t&#xE9;cnica' +
        '</a></div>';
    }

    return '<div class="tb-card">' +
      '<div class="tb-card-top">' +
        '<span class="tb-card-nombre">' + escapeHtml(tec.nombre) + '</span>' +
        '<div class="tb-card-tags">' + tags + tidHtml + '</div>' +
      '</div>' +
      (costes ? '<div class="tb-card-costes">' + costes + '</div>' : '') +
      (tec.descripcion ? '<div class="tb-card-descripcion">' + escapeHtml(tec.descripcion) + '</div>' : '') +
      '<div class="tb-card-efectos">' + efectosTexto + '</div>' +
      editBtn +
    '</div>';
  }

  /* ── Poblar grupos de checkboxes dinámicos ───────────────────── */
  function poblarCheckboxes(containerId, opciones, seleccionados, inputName, prefijo) {
    var container = document.getElementById(containerId);
    if (!container) return;
    opciones.forEach(function (val) {
      var label       = document.createElement('label');
      label.className = 'tb-check-label';
      var cb          = document.createElement('input');
      cb.type         = 'checkbox';
      cb.name         = inputName;
      cb.value        = val;
      if (seleccionados.indexOf(val) !== -1) cb.checked = true;
      label.appendChild(cb);
      label.appendChild(document.createTextNode(' ' + (prefijo || '') + val));
      container.appendChild(label);
    });
  }

  /* ── Restaurar checkboxes estáticos (clase, tipo, daño) ─────── */
  function restaurarCheckboxes() {
    var grupos = [
      { name: 'clase[]', values: TB.filtroClases },
      { name: 'tipo[]',  values: TB.filtroTipos  },
      { name: 'dano[]',  values: TB.danosSeleccionados }
    ];
    grupos.forEach(function (g) {
      g.values.forEach(function (v) {
        var cb = document.querySelector('input[name="' + g.name + '"][value="' + v + '"]');
        if (cb) cb.checked = true;
      });
    });
  }

  /* ── Multi-select dropdowns ──────────────────────────────────── */
  function actualizarTrigger(ms) {
    var labelEl = ms.querySelector('.tb-ms-label');
    var panel   = ms.querySelector('.tb-ms-panel');
    if (!labelEl || !panel) return;
    var placeholder = ms.querySelector('.tb-ms-trigger').dataset.placeholder || 'Todos';
    var checked     = panel.querySelectorAll('input[type=checkbox]:checked');
    if (checked.length === 0) {
      labelEl.textContent = placeholder;
    } else if (checked.length === 1) {
      labelEl.textContent = checked[0].closest('label').textContent.trim();
    } else {
      labelEl.textContent = checked.length + ' seleccionados';
    }
  }

  function cerrarTodosMultiselects() {
    document.querySelectorAll('.tb-ms-panel.open').forEach(function (p) { p.classList.remove('open'); });
    document.querySelectorAll('.tb-ms-trigger.open').forEach(function (t) { t.classList.remove('open'); });
  }

  function initMultiselects() {
    document.querySelectorAll('.tb-multiselect').forEach(function (ms) {
      var trigger = ms.querySelector('.tb-ms-trigger');
      var panel   = ms.querySelector('.tb-ms-panel');
      if (!trigger || !panel) return;

      trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = panel.classList.contains('open');
        cerrarTodosMultiselects();
        if (!isOpen) {
          panel.classList.add('open');
          trigger.classList.add('open');
        }
      });

      panel.addEventListener('click', function (e) { e.stopPropagation(); });

      panel.addEventListener('change', function () { actualizarTrigger(ms); });
    });

    document.addEventListener('click', cerrarTodosMultiselects);
    document.addEventListener('click', function (e) {
      var wrap = document.getElementById('tb-col-picker-wrap');
      if (wrap && !wrap.contains(e.target)) {
        var panel = document.getElementById('tb-col-picker-panel');
        if (panel) panel.classList.remove('open');
      }
    });
  }

  /* ── Renderizar resultados ───────────────────────────────────── */
  function renderizarResultados() {
    var lista   = document.getElementById('tb-lista');
    var countEl = document.getElementById('tb-count-label');
    var tabs    = document.getElementById('tb-tabs');
    if (!lista) return;

    if (!TB.hayBusqueda) {
      lista.innerHTML = '<div class="tb-empty">Utiliza los filtros para buscar técnicas.</div>';
      if (tabs) tabs.style.display = 'none';
      return;
    }
    if (!TB.totalResultados) {
      if (countEl) countEl.textContent = '0 resultados';
      if (tabs) tabs.style.display = 'none';
      lista.innerHTML = '<div class="tb-empty">No se encontraron técnicas con esos filtros.</div>';
      return;
    }

    var sufijo = TB.totalResultados >= 200 ? ' (mostrando primeras 200)' : '';
    if (countEl) countEl.textContent = TB.totalResultados + ' resultado' + (TB.totalResultados !== 1 ? 's' : '') + sufijo;
    if (tabs && TB.isStaff) tabs.style.display = '';

    var html = '';
    for (var i = 0; i < TB.resultados.length; i++) {
      html += renderizarTarjeta(TB.resultados[i], TB.danosSeleccionados);
    }
    lista.innerHTML = html;
  }

  /* ── Limpiar filtros ─────────────────────────────────────────── */
  function limpiarFiltros() {
    var tbNombre = document.getElementById('tb_nombre');
    if (tbNombre) tbNombre.value = '';
    document.querySelectorAll('input[name="clase[]"], input[name="tipo[]"], input[name="estilo[]"], input[name="tier[]"], input[name="dano[]"]').forEach(function (cb) {
      cb.checked = false;
    });
    document.querySelectorAll('.tb-multiselect').forEach(actualizarTrigger);
  }

  /* ── Init ────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    poblarCheckboxes('tb_estilo_panel', TB.optEstilos, TB.filtroEstilos, 'estilo[]', '');
    poblarCheckboxes('tb_tier_panel',   TB.optTiers,   TB.filtroTiers,   'tier[]',   'Tier ');
    restaurarCheckboxes();
    document.querySelectorAll('.tb-multiselect').forEach(actualizarTrigger);
    initMultiselects();
    renderizarResultados();

    // Evitar que el formulario de búsqueda se envíe cuando el panel Excel está activo
    var tbForm = document.getElementById('tb-form');
    if (tbForm) {
      tbForm.addEventListener('submit', function (e) {
        var panel = document.getElementById('tb-excel-panel');
        if (panel && panel.style.display !== 'none') {
          e.preventDefault();
        }
      });
    }
  });

  /* ── Staff Excel ─────────────────────────────────────────────── */

  var dirtyRows = {};
  var newRows   = {};

  var EXCEL_COLS = [
    { campo: 'tid',           label: 'TID',         w: 85,  tipo: 'readonly' },
    { campo: 'nombre',        label: 'Nombre',       w: 160, tipo: 'text' },
    { campo: 'estilo',        label: 'Estilo',       w: 120, tipo: 'text' },
    { campo: 'rama',          label: 'Rama',         w: 120, tipo: 'text' },
    { campo: 'clase',         label: 'Clase',        w: 90,  tipo: 'select', opts: ['', 'Activa', 'Pasiva', 'Mantenida', 'Conjunta'] },
    { campo: 'tipo',          label: 'Tipo',         w: 90,  tipo: 'select', opts: ['', 'Ofensiva', 'Defensiva', 'Ambiental', 'Elusiva', 'Utilidad'] },
    { campo: 'tier',          label: 'Tier',         w: 50,  tipo: 'number' },
    { campo: 'energia',       label: 'Energía',      w: 80,  tipo: 'text' },
    { campo: 'energia_turno', label: 'E/Turno',      w: 70,  tipo: 'text' },
    { campo: 'haki',          label: 'Haki',         w: 70,  tipo: 'text' },
    { campo: 'haki_turno',    label: 'H/Turno',      w: 70,  tipo: 'text' },
    { campo: 'enfriamiento',  label: 'Enfr.',        w: 70,  tipo: 'text' },
    { campo: 'descripcion',   label: 'Descripción',  w: 200, tipo: 'textarea' },
    { campo: 'efectos',       label: 'Efectos',      w: 260, tipo: 'textarea' },
    { campo: 'requisitos',    label: 'Requisitos',   w: 150, tipo: 'textarea' }
  ];

  /* columnas ocultas por defecto: las tres de texto largo */
  var _COLS_OCULTAS_DEFAULT = ['descripcion', 'efectos', 'requisitos'];
  var colVisible = EXCEL_COLS.map(function (col) {
    return _COLS_OCULTAS_DEFAULT.indexOf(col.campo) === -1;
  });

  function aplicarVisibilidadCols() {
    var table = document.querySelector('.tb-excel-tbl');
    if (!table) return;
    var rows = table.querySelectorAll('tr');
    for (var r = 0; r < rows.length; r++) {
      var cells = rows[r].children;
      for (var c = 0; c < cells.length && c < colVisible.length; c++) {
        cells[c].style.display = colVisible[c] ? '' : 'none';
      }
    }
  }

  function initColPicker() {
    var panel = document.getElementById('tb-col-picker-panel');
    if (!panel || panel.children.length) return;
    EXCEL_COLS.forEach(function (col, idx) {
      var label       = document.createElement('label');
      label.className = 'tb-col-check-label';
      var cb          = document.createElement('input');
      cb.type         = 'checkbox';
      cb.checked      = colVisible[idx];
      (function (i) {
        cb.addEventListener('change', function () {
          colVisible[i] = cb.checked;
          aplicarVisibilidadCols();
        });
      })(idx);
      label.appendChild(cb);
      label.appendChild(document.createTextNode(' ' + col.label));
      panel.appendChild(label);
    });
  }

  function toggleColPicker(e) {
    if (e) e.stopPropagation();
    var panel = document.getElementById('tb-col-picker-panel');
    if (panel) panel.classList.toggle('open');
  }

  function cambiarTabVista(vista) {
    var lista  = document.getElementById('tb-lista');
    var panel  = document.getElementById('tb-excel-panel');
    var btnTar = document.getElementById('tb-tab-tarjetas');
    var btnExc = document.getElementById('tb-tab-excel');
    if (vista === 'excel') {
      if (lista)  lista.style.display  = 'none';
      if (panel)  panel.style.display  = '';
      if (btnTar) btnTar.classList.remove('tb-tab-active');
      if (btnExc) btnExc.classList.add('tb-tab-active');
      var tbody = document.getElementById('tb-excel-tbody');
      if (tbody && tbody.children.length === 0) renderizarExcel();
    } else {
      if (lista)  lista.style.display  = '';
      if (panel)  panel.style.display  = 'none';
      if (btnTar) btnTar.classList.add('tb-tab-active');
      if (btnExc) btnExc.classList.remove('tb-tab-active');
    }
  }

  function marcarDirty(tid) {
    dirtyRows[tid] = true;
    var row = document.querySelector('#tb-excel-tbody tr[data-tid="' + tid + '"]');
    if (row) row.classList.add('tb-dirty');
  }

  function renderizarExcel() {
    var thead = document.getElementById('tb-excel-thead');
    var tbody = document.getElementById('tb-excel-tbody');
    if (!thead || !tbody) return;

    // Header
    var thHtml = '<tr>';
    for (var ci = 0; ci < EXCEL_COLS.length; ci++) {
      thHtml += '<th style="min-width:' + EXCEL_COLS[ci].w + 'px;">' + EXCEL_COLS[ci].label + '</th>';
    }
    thHtml += '</tr>';
    thead.innerHTML = thHtml;

    // Rows
    var rowsHtml = '';
    for (var ri = 0; ri < TB.resultados.length; ri++) {
      var tec = TB.resultados[ri];
      var tid = escapeHtml(tec.tid || '');
      rowsHtml += '<tr data-tid="' + tid + '">';

      for (var ci2 = 0; ci2 < EXCEL_COLS.length; ci2++) {
        var col = EXCEL_COLS[ci2];
        var val = tec[col.campo] !== undefined ? String(tec[col.campo]) : '';
        var valEsc = escapeHtml(val);
        var attrs  = ' data-tid="' + tid + '" data-campo="' + col.campo + '"';
        var onEvt  = ' oninput="marcarDirty(\'' + tid + '\')"';
        var onChg  = ' onchange="marcarDirty(\'' + tid + '\')"';

        rowsHtml += '<td style="min-width:' + col.w + 'px;">';
        if (col.tipo === 'readonly') {
          rowsHtml += '<span class="tb-excel-tid"><a href="/op/staff/tecnicas_modificar.php?tecnica_id=' + tid + '">' + tid + '</a></span>' +
            '<button type="button" class="tb-btn-dup" data-dup-tid="' + tid + '" onclick="duplicarTecnica(\'' + tid + '\')">&#x2398; Duplicar</button>';
        } else if (col.tipo === 'select') {
          rowsHtml += '<select' + attrs + onChg + '>';
          for (var oi = 0; oi < col.opts.length; oi++) {
            var optVal = col.opts[oi];
            var sel    = optVal === val ? ' selected' : '';
            rowsHtml += '<option value="' + escapeHtml(optVal) + '"' + sel + '>' + (optVal || '—') + '</option>';
          }
          rowsHtml += '</select>';
        } else if (col.tipo === 'number') {
          rowsHtml += '<input type="number"' + attrs + ' value="' + valEsc + '" min="1" max="20"' + onEvt + '>';
        } else if (col.tipo === 'textarea') {
          rowsHtml += '<textarea' + attrs + ' rows="2"' + onEvt + '>' + valEsc + '</textarea>';
        } else {
          rowsHtml += '<input type="text"' + attrs + ' value="' + valEsc + '"' + onEvt + '>';
        }
        rowsHtml += '</td>';
      }
      rowsHtml += '</tr>';
    }
    tbody.innerHTML = rowsHtml;
    initColPicker();
    aplicarVisibilidadCols();
  }

  function duplicarTecnica(refTid) {
    var ref = null;
    for (var i = 0; i < TB.resultados.length; i++) {
      if (TB.resultados[i].tid === refTid) { ref = TB.resultados[i]; break; }
    }
    if (!ref) return;

    var btn = document.querySelector('[data-dup-tid="' + refTid + '"]');
    if (btn) { btn.disabled = true; btn.textContent = '…'; }

    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/op/staff/tecnicas_next_aux_tid.php', true);
    xhr.onload = function () {
      if (btn) { btn.disabled = false; btn.innerHTML = '&#x2398; Duplicar'; }
      try {
        var resp = JSON.parse(xhr.responseText);
        if (resp.error) { alert(resp.error); return; }
        _insertarFilaNueva(resp.tid, ref, refTid);
      } catch (e) { alert('Error al obtener TID auxiliar'); }
    };
    xhr.onerror = function () {
      if (btn) { btn.disabled = false; btn.innerHTML = '&#x2398; Duplicar'; }
      alert('Error de red');
    };
    xhr.send();
  }

  function _insertarFilaNueva(nuevoTid, ref, refTid) {
    var tbody = document.getElementById('tb-excel-tbody');
    if (!tbody) return;

    newRows[nuevoTid]   = true;
    dirtyRows[nuevoTid] = true;

    var tidEsc = escapeHtml(nuevoTid);
    var html   = '<tr data-tid="' + tidEsc + '" data-new-row="1" class="tb-new-row tb-dirty">';

    for (var ci = 0; ci < EXCEL_COLS.length; ci++) {
      var col    = EXCEL_COLS[ci];
      var val    = col.campo === 'tid' ? nuevoTid : (ref[col.campo] !== undefined ? String(ref[col.campo]) : '');
      var valEsc = escapeHtml(val);
      var attrs  = ' data-tid="' + tidEsc + '" data-campo="' + col.campo + '"';
      var onEvt  = ' oninput="marcarDirty(\'' + tidEsc + '\')"';
      var onChg  = ' onchange="marcarDirty(\'' + tidEsc + '\')"';

      html += '<td style="min-width:' + col.w + 'px;">';
      if (col.campo === 'tid') {
        html += '<input type="text" class="tb-new-tid-input" data-tid="' + tidEsc + '" data-newtid="' + tidEsc + '" value="' + valEsc + '" oninput="_renombrarNuevoTid(this)">';
      } else if (col.tipo === 'select') {
        html += '<select' + attrs + onChg + '>';
        for (var oi = 0; oi < col.opts.length; oi++) {
          var optVal = col.opts[oi];
          var sel    = optVal === val ? ' selected' : '';
          html += '<option value="' + escapeHtml(optVal) + '"' + sel + '>' + (optVal || '—') + '</option>';
        }
        html += '</select>';
      } else if (col.tipo === 'number') {
        html += '<input type="number"' + attrs + ' value="' + valEsc + '" min="1" max="20"' + onEvt + '>';
      } else if (col.tipo === 'textarea') {
        html += '<textarea' + attrs + ' rows="2"' + onEvt + '>' + valEsc + '</textarea>';
      } else {
        html += '<input type="text"' + attrs + ' value="' + valEsc + '"' + onEvt + '>';
      }
      html += '</td>';
    }
    html += '</tr>';

    var refRow = refTid ? tbody.querySelector('tr[data-tid="' + escapeHtml(refTid) + '"]') : null;
    if (refRow) {
      refRow.insertAdjacentHTML('afterend', html);
    } else {
      tbody.insertAdjacentHTML('beforeend', html);
    }

    var newRow = tbody.querySelector('tr[data-tid="' + tidEsc + '"]');
    if (newRow) {
      var cells = newRow.children;
      for (var c = 0; c < cells.length && c < colVisible.length; c++) {
        cells[c].style.display = colVisible[c] ? '' : 'none';
      }
    }
  }

  function _renombrarNuevoTid(input) {
    var prevTid = input.getAttribute('data-newtid');
    var newTid  = input.value.trim();
    if (!newTid || newTid === prevTid) return;

    var row = input.parentNode;
    while (row && row.tagName !== 'TR') row = row.parentNode;
    if (!row) return;

    row.setAttribute('data-tid', newTid);
    var els = row.querySelectorAll('[data-tid]');
    for (var i = 0; i < els.length; i++) {
      els[i].setAttribute('data-tid', newTid);
    }

    if (newRows[prevTid])   { delete newRows[prevTid];   newRows[newTid]   = true; }
    if (dirtyRows[prevTid]) { delete dirtyRows[prevTid]; dirtyRows[newTid] = true; }

    input.setAttribute('data-newtid', newTid);
  }

  function guardarExcel() {
    var tids = Object.keys(dirtyRows);
    if (!tids.length) {
      alert('No hay cambios que guardar.');
      return;
    }

    var payload = [];
    for (var i = 0; i < tids.length; i++) {
      var tid = tids[i];
      var row = document.querySelector('#tb-excel-tbody tr[data-tid="' + tid + '"]');
      if (!row) continue;
      var entry  = { tid: tid };
      if (newRows[tid]) entry._new = true;
      var inputs = row.querySelectorAll('[data-campo]');
      for (var j = 0; j < inputs.length; j++) {
        var campo = inputs[j].getAttribute('data-campo');
        if (campo !== 'tid') entry[campo] = inputs[j].value;
      }
      payload.push(entry);
    }

    var btnGuardar = document.getElementById('tb-btn-guardar');
    var statusEl   = document.getElementById('tb-save-status');
    if (btnGuardar) { btnGuardar.disabled = true; btnGuardar.textContent = 'Guardando…'; }
    if (statusEl)   { statusEl.textContent = ''; statusEl.className = 'tb-save-status'; }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/op/staff/tecnicas_bulk_update.php?post_code=' + encodeURIComponent(TB.postCode), true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onload = function () {
      if (btnGuardar) { btnGuardar.disabled = false; btnGuardar.textContent = '💾 Guardar en BD'; }
      try {
        var resp = JSON.parse(xhr.responseText);
        if (resp.ok) {
          dirtyRows = {};
          newRows   = {};
          var rows = document.querySelectorAll('#tb-excel-tbody tr.tb-dirty');
          for (var k = 0; k < rows.length; k++) rows[k].classList.remove('tb-dirty');
          var msg = '✓ ' + resp.actualizadas + ' t\xE9cnica(s) guardadas correctamente';
          if (resp.errores && resp.errores.length) msg += ' • ' + resp.errores.length + ' error(es)';
          if (statusEl) { statusEl.textContent = msg; statusEl.className = 'tb-save-status ok'; }
        } else {
          if (statusEl) { statusEl.textContent = '✗ ' + (resp.error || 'Error desconocido'); statusEl.className = 'tb-save-status err'; }
        }
      } catch (e) {
        if (statusEl) { statusEl.textContent = '✗ Error al procesar respuesta'; statusEl.className = 'tb-save-status err'; }
      }
    };
    xhr.onerror = function () {
      if (btnGuardar) { btnGuardar.disabled = false; btnGuardar.textContent = '💾 Guardar en BD'; }
      if (statusEl)   { statusEl.textContent = '✗ Error de red'; statusEl.className = 'tb-save-status err'; }
    };
    xhr.send(JSON.stringify(payload));
  }

  /* Exponer limpiarFiltros al onclick del botón en el template */
  window.limpiarFiltros      = limpiarFiltros;
  window.toggleColPicker     = toggleColPicker;
  window.cambiarTabVista    = cambiarTabVista;
  window.marcarDirty        = marcarDirty;
  window.duplicarTecnica    = duplicarTecnica;
  window._renombrarNuevoTid = _renombrarNuevoTid;
  window.guardarExcel       = guardarExcel;

})();
