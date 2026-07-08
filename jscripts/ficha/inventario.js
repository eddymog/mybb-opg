/**
 * ficha/inventario.js — Sistema de inventario y equipamiento
 * Requiere: core.js
 */
(function (FICHA, w) {

  // ── Estado del módulo ────────────────────────────────────────
  var filtroInventario = { categorias: [], tiers: [], busqueda: '' };
  var tipoEquipamientoSeleccionado = '';
  var espaciosOcupados = 0;

  // ── Buscador (DOMContentLoaded) ──────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    var buscador = document.getElementById('buscador-inventario');
    if (buscador) {
      buscador.addEventListener('input', function () {
        filtroInventario.busqueda = this.value;
        aplicarFiltrosInventario();
      });
    }
  });

  // ── Filtros ──────────────────────────────────────────────────

  function desequiparTodo() {
    if (is_owner || g_is_staff) {
      objetosEquipados = JSON.parse('{"ropa": null, "bolsa": null, "espacios": {}}');
      cambiarEquipamiento();
    }
  }

  function filtrarCategoria(categoria) {
    var boton = document.getElementById('btn-' + categoria);
    var index = filtroInventario.categorias.indexOf(categoria);
    if (index > -1) {
      filtroInventario.categorias.splice(index, 1);
      boton.style.backgroundColor = '#666666';
    } else {
      filtroInventario.categorias.push(categoria);
      boton.style.backgroundColor = '#000000';
    }
    aplicarFiltrosInventario();
  }

  function filtrarTier(tier) {
    var botonId = tier === 'ESP' ? 'btn-tier-esp' : 'btn-tier' + tier;
    var boton = document.getElementById(botonId);
    var index = filtroInventario.tiers.indexOf(tier.toString());
    if (index > -1) {
      filtroInventario.tiers.splice(index, 1);
      boton.style.backgroundColor = '#666666';
    } else {
      filtroInventario.tiers.push(tier.toString());
      boton.style.backgroundColor = '#000000';
    }
    aplicarFiltrosInventario();
  }

  function aplicarFiltrosInventario() {
    console.log('Aplicando filtros de inventario:', filtroInventario);
    var categorias = ['especiales', 'consumibles', 'armas', 'utensilios', 'materiales', 'estructuras'];
    categorias.forEach(function (categoria) {
      var contenedor = document.getElementById('objetos_' + categoria);
      if (!contenedor) return;
      var mostrarCategoria = (filtroInventario.categorias.length > 0)
        ? filtroInventario.categorias.includes(categoria)
        : true;

      if (mostrarCategoria) {
        contenedor.style.display = 'block';
        var objetos = contenedor.querySelectorAll('.item-outer');
        objetos.forEach(function (objeto) {
          var mostrarObjeto = true;
          if (filtroInventario.tiers.length > 0) {
            var tooltip = objeto.querySelector('.tooltiptext');
            if (tooltip) {
              var tooltipClone = tooltip.cloneNode(true);
              var desc = tooltipClone.querySelector('.mydescripcion');
              if (desc) desc.remove();
              var textoTooltip = tooltipClone.textContent || tooltipClone.innerText;
              var tierEncontrado = false;
              filtroInventario.tiers.forEach(function (tierBuscado) {
                if (tierBuscado === 'ESP') {
                  if (textoTooltip.includes('Tier ESP') || textoTooltip.includes('Tier Especial') ||
                      textoTooltip.includes('Tier 6') || textoTooltip.includes('Tier 7') ||
                      textoTooltip.includes('Tier 8') || textoTooltip.includes('Tier 9') ||
                      textoTooltip.includes('Tier 10')) tierEncontrado = true;
                } else {
                  if (textoTooltip.includes('Tier ' + tierBuscado)) tierEncontrado = true;
                }
              });
              if (!tierEncontrado) mostrarObjeto = false;
            } else {
              mostrarObjeto = false;
            }
          }
          if (filtroInventario.busqueda) {
            var nombreObjeto = objeto.querySelector('.item-nombre').textContent.toLowerCase();
            if (!nombreObjeto.includes(filtroInventario.busqueda.toLowerCase())) mostrarObjeto = false;
          }
          objeto.style.display = mostrarObjeto ? 'block' : 'none';
        });
      } else {
        contenedor.style.display = 'none';
      }
    });
  }

  function limpiarFiltrosInventario() {
    filtroInventario = { categorias: [], tiers: [], busqueda: '' };
    var botones = document.querySelectorAll('[id^="btn-"]');
    botones.forEach(function (boton) {
      if (boton.id.includes('tier') || boton.id.includes('consumibles') ||
          boton.id.includes('armas') || boton.id.includes('utensilios') ||
          boton.id.includes('estructuras') || boton.id.includes('materiales') ||
          boton.id.includes('especiales')) {
        boton.style.backgroundColor = '#666666';
      }
    });
    var buscador = document.getElementById('buscador-inventario');
    if (buscador) buscador.value = '';
    aplicarFiltrosInventario();
  }

  // ── Selector de equipamiento (bolsa/ropa) ────────────────────

  function abrirSelectorEquipamiento(tipo) {
    if (!is_owner) return;
    tipoEquipamientoSeleccionado = tipo;
    var modal  = document.getElementById('modalSelectorEquipamiento');
    var titulo = document.getElementById('tituloSelectorEquipamiento');
    var lista  = document.getElementById('listaEquipamientoDisponible');

    titulo.innerHTML = (tipo === 'equipajes') ? 'SELECCIONAR BOLSA/EQUIPAJE' : 'SELECCIONAR ROPA/ARMADURA/ESPECIAL MT';
    lista.innerHTML  = '';

    var objetosDisponibles = [];
    for (var i = 0; i < objetos_array_json.length; i++) {
      var objetoId = objetos_array_json[i];
      if (objetoId === 'LLST001') continue;
      var objeto = objetos_json[objetoId][0];
      var subcategoriaLower = objeto.subcategoria.toLowerCase();
      if (tipo === 'ropa y armaduras') {
        if (subcategoriaLower === tipo || subcategoriaLower === 'especial mt') objetosDisponibles.push(objetoId);
      } else if (subcategoriaLower === tipo) {
        objetosDisponibles.push(objetoId);
      }
    }

    for (var j = 0; j < objetosDisponibles.length; j++) {
      var oid = objetosDisponibles[j];
      var obj = objetos_json[oid][0];
      var img = _resolverImagenObjeto(oid, obj);
      var el  = document.createElement('div');
      el.style.cssText = 'width: 120px; height: 140px; border: 2px solid #ccc; border-radius: 8px; padding: 10px; text-align: center; cursor: pointer; background-color: #f9f9f9; transition: all 0.3s; display: inline-block; margin: 5px; vertical-align: top;';
      el.innerHTML = '<img src="' + img + '" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;"><div style="font-family: moonGetHeavy; font-size: 11px; margin-top: 8px; color: #333; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; width: 100%;">' + (obj.apodo !== '' ? obj.apodo : obj.nombre) + '</div>';
      el.setAttribute('onmouseover', 'this.style.backgroundColor="#e3f2fd"; this.style.borderColor="#2196f3";');
      el.setAttribute('onmouseout',  'this.style.backgroundColor="#f9f9f9"; this.style.borderColor="#ccc";');
      el.setAttribute('onclick', 'equiparItem("' + oid + '")');
      lista.appendChild(el);
    }
    modal.style.display = 'block';
  }

  function cerrarSelectorEquipamiento() {
    var modal = document.getElementById('modalSelectorEquipamiento');
    modal.style.display = 'none';
    tipoEquipamientoSeleccionado = '';
  }

  // ── Helpers de imágenes ──────────────────────────────────────

  function _resolverImagenObjeto(objetoId, objeto) {
    var subcategoria = objeto.subcategoria.toLowerCase().split(' ').join('_');
    var imagen_id    = objeto.imagen_id;
    var ext = (subcategoria === 'cofres' || subcategoria === 'akuma_no_mi' || objetoId === 'EANM001' ||
               objetoId === 'LLST001' || objetoId === 'THR001' || subcategoria === 'materiales' ||
               subcategoria === 'tecnicas' || objetoId === 'EPA001' || objetoId === 'KMP001' ||
               objetoId === 'VCD001' || objetoId === 'VCZ001' || subcategoria === 'documentos' ||
               subcategoria === 'recetas') ? 'gif' : 'jpg';
    var imagen = '/images/op/iconos/' + subcategoria + '_' + imagen_id + '_One_Piece_Gaiden_Foro_Rol.' + ext;
    if (objeto.imagen_avatar !== '') imagen = objeto.imagen_avatar;
    return imagen;
  }

  // ── Reindexar objeto ─────────────────────────────────────────

  function reindexObject(obj) {
    var values = Object.keys(obj).sort(function (a, b) { return Number(a) - Number(b); }).map(function (k) { return obj[k]; });
    var newObj = {};
    values.forEach(function (value, index) { newObj[index] = value; });
    return newObj;
  }

  // ── Desequipar item ──────────────────────────────────────────

  function desequiparItem() {
    if (!is_owner) return;
    if (tipoEquipamientoSeleccionado === 'equipajes' && objetosEquipados.bolsa) {
      objetosEquipados.bolsa = null;
      var imagenBolsa = document.getElementById('imagen-bolsa-equipada');
      imagenBolsa.src = '/images/op/uploads/SinMochila_One_Piece_Gaiden_Foro_Rol.jpg';
      imagenBolsa.alt = '';
      document.getElementById('slot-bolsa').title = '';
      mostrarMensajeEquipamiento('Bolsa desequipada correctamente.');
    } else if (tipoEquipamientoSeleccionado === 'ropa y armaduras' && objetosEquipados.ropa) {
      objetosEquipados.ropa = null;
      var imagenRopa = document.getElementById('imagen-ropa-equipada');
      imagenRopa.src = '/images/op/uploads/SinRopa_One_Piece_Gaiden_Foro_Rol.jpg';
      imagenRopa.alt = '';
      document.getElementById('slot-ropa').title = '';
      mostrarMensajeEquipamiento('Ropa desequipada correctamente.');
    }
    cambiarEquipamiento();
    actualizarContadorEspacios();
    cerrarSelectorEquipamiento();
  }

  function mostrarMensajeEquipamiento(mensaje) {
    var mensajeDiv = document.createElement('div');
    mensajeDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background-color: #4caf50; color: white; padding: 15px 20px; border-radius: 5px; font-family: moonGetHeavy; font-size: 14px; z-index: 2000; box-shadow: 0 2px 5px rgba(0,0,0,0.3);';
    mensajeDiv.innerHTML = mensaje;
    document.body.appendChild(mensajeDiv);
    setTimeout(function () { if (mensajeDiv.parentNode) mensajeDiv.parentNode.removeChild(mensajeDiv); }, 3000);
  }

  // ── Espacios ─────────────────────────────────────────────────

  function extraerEspaciosDelEfecto(efecto, dano) {
    var espacios = 0;
    var patronEspacios = /\[otorga\s+(\d+)\s+espacios?\]/i;
    var patronSinCorchetes = /otorga\s+(\d+)\s+espacios?/i;
    var patronGeneral = /(\d+)\s+espacios?/i;
    if (efecto) {
      var m = efecto.match(patronEspacios) || efecto.match(patronSinCorchetes) || efecto.match(patronGeneral);
      if (m) espacios += parseInt(m[1]);
    }
    if (dano) {
      var md = dano.match(patronEspacios) || dano.match(patronSinCorchetes);
      if (md) espacios += parseInt(md[1]);
    }
    return espacios;
  }

  function extraerEspaciosRequeridos(objeto) {
    var n = parseInt(objeto.espacios, 10);
    return Number.isFinite(n) ? n : 1;
  }

  function calcularEspaciosDisponibles() {
    var espaciosBase       = 5;
    var espaciosAdicionales = 0;
    if (objetosEquipados.bolsa && objetos_json[objetosEquipados.bolsa]) {
      var ob = objetos_json[objetosEquipados.bolsa][0];
      espaciosAdicionales += extraerEspaciosDelEfecto(ob.efecto, ob.dano);
    }
    if (objetosEquipados.ropa && objetos_json[objetosEquipados.ropa]) {
      var or = objetos_json[objetosEquipados.ropa][0];
      espaciosAdicionales += extraerEspaciosDelEfecto(or.efecto, or.dano);
    }
    try {
      if ((typeof oficio1 !== 'undefined' && oficio1 === 'Aventurero') ||
          (typeof oficio2 !== 'undefined' && oficio2 === 'Aventurero')) {
        espaciosAdicionales += 4;
      }
    } catch (e) { /* ignora si no están definidas */ }
    actualizarEspaciosOcupados();
    var total = espaciosBase + espaciosAdicionales - espaciosOcupados;
    return total < 0 ? 0 : total;
  }

  function actualizarEspaciosOcupados() {
    if (!objetosEquipados || !objetosEquipados.espacios) { espaciosOcupados = 0; return; }
    var acum = 0;
    Object.keys(objetosEquipados.espacios).forEach(function (key) {
      var slot = objetosEquipados.espacios[key];
      if (slot && typeof slot.espacios !== 'undefined') acum += Number(slot.espacios) || 0;
    });
    espaciosOcupados = acum;
  }

  function equiparObjetoEnEspacio(objetoId, espacioIndex) {
    var objeto = objetos_json[objetoId][0];
    var espaciosRequeridos  = extraerEspaciosRequeridos(objeto);
    var espaciosDisponibles = calcularEspaciosDisponibles();
    if (espaciosRequeridos > espaciosDisponibles) {
      mostrarMensajeEquipamiento('No hay suficientes espacios disponibles. Se requieren ' + espaciosRequeridos + ' espacios.');
      return false;
    }
    if (objetosEquipados.espacios[espacioIndex]) {
      mostrarMensajeEquipamiento('Este espacio ya está ocupado.');
      return false;
    }
    objetosEquipados.espacios[espacioIndex] = { objetoId: objetoId, espacios: espaciosRequeridos };
    espaciosOcupados += espaciosRequeridos;
    actualizarContadorEspacios();
    mostrarMensajeEquipamiento(objeto.nombre + ' equipado correctamente.');
    cambiarEquipamiento();
    return true;
  }

  function desequiparObjetoDeEspacio(espacioIndex) {
    if (!objetosEquipados.espacios[espacioIndex]) {
      mostrarMensajeEquipamiento('No hay ningún objeto equipado en este espacio.');
      return false;
    }
    var objetoEquipado = objetosEquipados.espacios[espacioIndex];
    var objeto = objetos_json[objetoEquipado.objetoId][0];
    espaciosOcupados -= objetoEquipado.espacios;
    delete objetosEquipados.espacios[espacioIndex];
    objetosEquipados['espacios'] = reindexObject(objetosEquipados['espacios']);
    actualizarContadorEspacios();
    mostrarMensajeEquipamiento(objeto.nombre + ' desequipado correctamente.');
    cambiarEquipamiento();
    return true;
  }

  function generarEspaciosVisuales() {
    var contenedorEspacios = document.getElementById('espacios-equipamiento-visual');
    if (!contenedorEspacios) return;
    contenedorEspacios.innerHTML = '';

    var espaciosDisponibles = calcularEspaciosDisponibles() + Object.keys(objetosEquipados.espacios).length;
    var countObjetosEquipados = 0;

    for (var i = 0; i < espaciosDisponibles; i++) {
      var espacioDiv    = document.createElement('div');
      var objetoEquipado = objetosEquipados.espacios[i];
      espacioDiv.className = 'espacio-equipamiento';
      espacioDiv.id = 'espacio-' + i;

      if (objetoEquipado) {
        countObjetosEquipados += 1;
        if (!objetos_json[objetoEquipado.objetoId] || !objetos_json[objetoEquipado.objetoId][0]) {
          console.warn('generarEspaciosVisuales: objeto no encontrado:', objetoEquipado.objetoId);
          continue;
        }
        var obj  = objetos_json[objetoEquipado.objetoId][0];
        var img  = _resolverImagenObjeto(objetoEquipado.objetoId, obj);
        espacioDiv.style.cssText = 'width: 70px; height: 70px; min-width: 70px; min-height: 70px; border: 2px solid #4caf50; border-radius: 8px; background-color: rgba(76, 175, 80, 0.1); display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s; position: relative; box-sizing: border-box; background-image: url(' + img + '); background-size: 50px 50px; background-position: center; background-repeat: no-repeat; flex-shrink: 0;';
        espacioDiv.title = obj.nombre + ' (' + objetoEquipado.espacios + ' espacios)';
        espacioDiv.innerHTML = '<div style="position: absolute; bottom: 2px; left: 2px; right: 2px; background-color: rgba(0,0,0,0.7); color: white; font-family: moonGetHeavy; font-size: 7px; text-align: center; border-radius: 3px; padding: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + (obj.apodo !== '' ? obj.apodo.substring(0, 8) : obj.nombre.substring(0, 8)) + '</div>';
        espacioDiv.setAttribute('onmouseover', 'this.style.borderColor="#2e7d32"; this.style.backgroundColor="rgba(76, 175, 80, 0.2)";');
        espacioDiv.setAttribute('onmouseout',  'this.style.borderColor="#4caf50"; this.style.backgroundColor="rgba(76, 175, 80, 0.1)";');
        espacioDiv.setAttribute('onclick', 'mostrarOpcionesObjeto(' + i + ')');
      } else {
        espacioDiv.style.cssText = 'width: 70px; height: 70px; min-width: 70px; min-height: 70px; border: 2px dashed #ccc; border-radius: 8px; background-color: rgba(255, 255, 255, 0.3); display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s; position: relative; box-sizing: border-box; flex-shrink: 0;';
        espacioDiv.innerHTML = '<div style="font-family: moonGetHeavy; font-size: 9px; color: #666; text-align: center; line-height: 10px;">ESPACIO<br>' + (espaciosOcupados + i + 1 - countObjetosEquipados) + '</div>';
        espacioDiv.setAttribute('onmouseover', 'this.style.borderColor="#2196f3"; this.style.backgroundColor="rgba(33, 150, 243, 0.1)";');
        espacioDiv.setAttribute('onmouseout',  'this.style.borderColor="#ccc"; this.style.backgroundColor="rgba(255, 255, 255, 0.3)";');
        espacioDiv.setAttribute('onclick', 'abrirSelectorObjetoInventario(' + countObjetosEquipados + ')');
      }
      contenedorEspacios.appendChild(espacioDiv);
    }

    if (espaciosDisponibles === 0) {
      var mensajeDiv = document.createElement('div');
      mensajeDiv.style.cssText = 'width: 100%; text-align: center; color: #999; font-family: moonGetHeavy; font-size: 12px; padding: 20px 0;';
      mensajeDiv.innerHTML = 'No hay espacios adicionales<br><small>Equipa una bolsa o ropa para obtener más espacios</small>';
      contenedorEspacios.appendChild(mensajeDiv);
    }
  }

  function objetoEstaEquipado(objetoId) {
    if (objetosEquipados.bolsa === objetoId) return true;
    if (objetosEquipados.ropa  === objetoId) return true;
    var cuantasVeces = 0;
    for (var idx in objetosEquipados.espacios) {
      if (objetosEquipados.espacios[idx].objetoId === objetoId) cuantasVeces++;
    }
    if (cuantasVeces >= parseInt(objetos_json[objetoId][0].cantidad)) return true;
    return false;
  }

  function abrirSelectorObjetoInventario(espacioIndex) {
    if (!is_owner) return;
    var modal  = document.getElementById('modalSelectorEquipamiento');
    var titulo = document.getElementById('tituloSelectorEquipamiento');
    var lista  = document.getElementById('listaEquipamientoDisponible');
    titulo.innerHTML = 'SELECCIONAR OBJETO PARA EQUIPAR';
    lista.innerHTML  = '';

    var objetosDisponibles = [];
    for (var i = 0; i < objetos_array_json.length; i++) {
      var oid = objetos_array_json[i];
      if (oid === 'LLST001') continue;
      if (objetoEstaEquipado(oid)) continue;
      var sub = objetos_json[oid][0].subcategoria.toLowerCase();
      if (sub !== 'equipajes') objetosDisponibles.push(oid);
    }

    for (var j = 0; j < objetosDisponibles.length; j++) {
      var oid = objetosDisponibles[j];
      var obj = objetos_json[oid][0];
      var img = _resolverImagenObjeto(oid, obj);
      var espaciosRequeridos = extraerEspaciosRequeridos(obj);
      var puedeEquipar = espaciosRequeridos <= calcularEspaciosDisponibles();
      var colorFondo = puedeEquipar ? '#f9f9f9' : '#ffebee';
      var colorBorde = puedeEquipar ? '#ccc'    : '#f44336';
      var el = document.createElement('div');
      el.style.cssText = 'width: 120px; height: 140px; border: 2px solid ' + colorBorde + '; border-radius: 8px; padding: 10px; text-align: center; cursor: pointer; background-color: ' + colorFondo + '; transition: all 0.3s; display: inline-block; margin: 5px; vertical-align: top;';
      el.innerHTML = '<img src="' + img + '" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;"><div style="font-family: moonGetHeavy; font-size: 11px; margin-top: 8px; color: #333; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; width: 100%;">' + (obj.apodo !== '' ? obj.apodo : obj.nombre) + '</div><div style="font-family: moonGetHeavy; font-size: 9px; color: #666; margin-top: 4px;">' + espaciosRequeridos + ' Espacios</div>';
      if (puedeEquipar) {
        el.setAttribute('onmouseover', 'this.style.backgroundColor="#e3f2fd"; this.style.borderColor="#2196f3";');
        el.setAttribute('onmouseout',  'this.style.backgroundColor="#f9f9f9"; this.style.borderColor="#ccc";');
        el.setAttribute('onclick', 'equiparObjetoInventario("' + oid + '", ' + espacioIndex + ')');
      } else {
        el.title = 'No hay suficientes espacios disponibles';
      }
      lista.appendChild(el);
    }

    var botonesDiv = modal.querySelector('div[style*="margin-top: 20px"]');
    if (botonesDiv) botonesDiv.innerHTML = '<button onclick="cerrarSelectorEquipamiento()" style="background-color: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-family: \'moonGetHeavy\';">CANCELAR</button>';
    modal.style.display = 'block';
    w.espacioSeleccionado = espacioIndex;
  }

  function equiparObjetoInventario(objetoId, espacioIndex) {
    if (equiparObjetoEnEspacio(objetoId, espacioIndex)) {
      cerrarSelectorEquipamiento();
      generarEspaciosVisuales();
    }
  }

  function mostrarOpcionesObjeto(espacioIndex) {
    if (!is_owner) return;
    var objetoEquipado = objetosEquipados.espacios[espacioIndex];
    if (!objetoEquipado) return;
    var objeto = objetos_json[objetoEquipado.objetoId][0];
    if (confirm('¿Deseas desequipar ' + objeto.nombre + '?')) {
      desequiparObjetoDeEspacio(espacioIndex);
      generarEspaciosVisuales();
    }
  }

  function actualizarContadorEspacios() {
    var espaciosAdicionales = calcularEspaciosDisponibles();
    var contadorEspacios    = document.getElementById('espacios-equipamiento');
    if (!contadorEspacios) return;
    contadorEspacios.innerHTML = 'ESPACIOS: ' + espaciosOcupados + ' / ' + (espaciosAdicionales + espaciosOcupados);
    if (espaciosOcupados >= (espaciosAdicionales + espaciosOcupados)) {
      contadorEspacios.style.color = '#f44336';
    } else if (espaciosAdicionales > 0) {
      contadorEspacios.style.color = '#4caf50';
    } else {
      contadorEspacios.style.color = 'white';
    }
    generarEspaciosVisuales();
  }

  function equiparItem(objetoId) {
    var objeto = objetos_json[objetoId][0];
    var img    = _resolverImagenObjeto(objetoId, objeto);
    var espaciosOtorgados = extraerEspaciosDelEfecto(objeto.efecto, objeto.dano);
    if (tipoEquipamientoSeleccionado === 'equipajes') {
      objetosEquipados.bolsa = objetoId;
      var imgBolsa = document.getElementById('imagen-bolsa-equipada');
      imgBolsa.src = img; imgBolsa.alt = objeto.nombre;
      document.getElementById('slot-bolsa').title = objeto.nombre + ' - +' + espaciosOtorgados + ' Espacios';
    } else if (tipoEquipamientoSeleccionado === 'ropa y armaduras') {
      objetosEquipados.ropa = objetoId;
      var imgRopa = document.getElementById('imagen-ropa-equipada');
      imgRopa.src = img; imgRopa.alt = objeto.nombre;
      document.getElementById('slot-ropa').title = objeto.nombre + ' - +' + espaciosOtorgados + ' Espacios';
    }
    cambiarEquipamiento();
    actualizarContadorEspacios();
    cerrarSelectorEquipamiento();
    mostrarMensajeEquipamiento(objeto.nombre + ' equipado correctamente. +' + espaciosOtorgados + ' espacios añadidos.');
  }

  function equiparItemBolsa(objetoId) {
    var objeto = objetos_json[objetoId][0];
    var img    = _resolverImagenObjeto(objetoId, objeto);
    var espaciosOtorgados = extraerEspaciosDelEfecto(objeto.efecto, objeto.dano);
    objetosEquipados.bolsa = objetoId;
    var imgBolsa = document.getElementById('imagen-bolsa-equipada');
    imgBolsa.src = img; imgBolsa.alt = objeto.nombre;
    document.getElementById('slot-bolsa').title = objeto.nombre + ' - +' + espaciosOtorgados + ' Espacios';
  }

  function equiparItemRopa(objetoId) {
    var objeto = objetos_json[objetoId][0];
    var img    = _resolverImagenObjeto(objetoId, objeto);
    var espaciosOtorgados = extraerEspaciosDelEfecto(objeto.efecto, objeto.dano);
    objetosEquipados.ropa = objetoId;
    var imgRopa = document.getElementById('imagen-ropa-equipada');
    imgRopa.src = img; imgRopa.alt = objeto.nombre;
    document.getElementById('slot-ropa').title = objeto.nombre + ' - +' + espaciosOtorgados + ' Espacios';
  }

  // ── Guardar equipamiento ─────────────────────────────────────

  function cambiarEquipamiento() {
    FICHA.PendingQueue.addParams({ cambiar_equipamiento: JSON.stringify(objetosEquipados) });
  }

  // ── Modal de cantidad de venta ───────────────────────────────

  var _modalCantidadVenta = null;
  var _mcvObjetoId        = '';
  var _mcvPrecioUnit      = 0;
  var _mcvMax             = 1;

  function _crearModalCantidadVenta() {
    var overlay = document.createElement('div');
    overlay.id = 'modal-cantidad-venta';
    overlay.style.cssText = 'display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.75);z-index:1000000;align-items:center;justify-content:center;';
    var card = document.createElement('div');
    card.style.cssText = 'background:#1a1a1a;border:2px solid #555;border-radius:12px;padding:28px 36px;min-width:320px;text-align:center;color:white;font-family:moonGetHeavy;';
    card.innerHTML = [
      '<div style="font-size:16px;letter-spacing:1px;margin-bottom:12px;">CANTIDAD A VENDER</div>',
      '<div id="mcv-nombre" style="font-size:13px;color:#ccc;margin-bottom:6px;"></div>',
      '<div id="mcv-precio-unit" style="font-size:11px;color:#aaa;margin-bottom:18px;"></div>',
      '<div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:14px;">',
        '<button onclick="mcvCambiar(-1)" style="width:32px;height:32px;font-size:18px;border:none;border-radius:6px;background:#333;color:white;cursor:pointer;">-</button>',
        '<input id="mcv-input" type="number" min="1" value="1" style="width:60px;text-align:center;font-family:moonGetHeavy;font-size:16px;background:#222;color:white;border:1px solid #555;border-radius:6px;padding:4px;" oninput="mcvActualizarTotal()" />',
        '<button onclick="mcvCambiar(1)"  style="width:32px;height:32px;font-size:18px;border:none;border-radius:6px;background:#333;color:white;cursor:pointer;">+</button>',
        '<span id="mcv-max" style="font-size:11px;color:#888;"></span>',
      '</div>',
      '<div id="mcv-total" style="font-size:14px;color:#2ecc40;margin-bottom:20px;"></div>',
      '<div style="display:flex;gap:12px;justify-content:center;">',
        '<button onclick="cerrarModalCantidadVenta()" style="padding:8px 22px;border:none;border-radius:8px;background:#555;color:white;font-family:moonGetHeavy;cursor:pointer;font-size:12px;">CANCELAR</button>',
        '<button onclick="confirmarCantidadVenta()" style="padding:8px 22px;border:none;border-radius:8px;background:linear-gradient(135deg,#1a7a1a,#2ecc40);color:white;font-family:moonGetHeavy;cursor:pointer;font-size:12px;">AÑADIR A COLA</button>',
      '</div>'
    ].join('');
    overlay.appendChild(card);
    document.body.appendChild(overlay);
    return overlay;
  }

  function abrirModalCantidadVenta(objetoId, precioUnit, cantidadMax) {
    if (!_modalCantidadVenta) _modalCantidadVenta = _crearModalCantidadVenta();
    _mcvObjetoId   = objetoId;
    _mcvPrecioUnit = precioUnit;
    _mcvMax        = cantidadMax;
    var objeto = objetos_json[objetoId][0];
    document.getElementById('mcv-nombre').textContent     = objeto.nombre;
    document.getElementById('mcv-precio-unit').textContent = 'Precio unitario: ' + precioUnit.toLocaleString('es-es') + ' berries';
    document.getElementById('mcv-max').textContent         = '/ ' + cantidadMax;
    var input = document.getElementById('mcv-input');
    input.max   = cantidadMax;
    input.value = 1;
    mcvActualizarTotal();
    _modalCantidadVenta.style.display = 'flex';
  }

  function cerrarModalCantidadVenta() {
    if (_modalCantidadVenta) _modalCantidadVenta.style.display = 'none';
  }

  function mcvCambiar(delta) {
    var input = document.getElementById('mcv-input');
    input.value = Math.max(1, Math.min(_mcvMax, (parseInt(input.value) || 1) + delta));
    mcvActualizarTotal();
  }

  function mcvActualizarTotal() {
    var input = document.getElementById('mcv-input');
    var val   = Math.max(1, Math.min(_mcvMax, parseInt(input.value) || 1));
    input.value = val;
    document.getElementById('mcv-total').textContent = 'Total: ' + (val * _mcvPrecioUnit).toLocaleString('es-es') + ' berries';
  }

  function confirmarCantidadVenta() {
    var cant      = Math.max(1, Math.min(_mcvMax, parseInt(document.getElementById('mcv-input').value) || 1));
    var objetoId  = _mcvObjetoId;
    var precioUnit = _mcvPrecioUnit;
    cerrarModalCantidadVenta();
    queueVenta(objetoId, precioUnit, cant);
  }

  // ── Cola de ventas ────────────────────────────────────────────

  var _sellQueue = [];
  var _sellBtn   = null;

  function _updateSellBtn() {
    var n = _sellQueue.length;
    if (n === 0) { if (_sellBtn) _sellBtn.style.display = 'none'; return; }
    if (!_sellBtn) {
      _sellBtn = document.createElement('button');
      _sellBtn.id = 'vender-items-btn';
      _sellBtn.style.cssText = [
        'position:fixed',
        'top:calc(25vh + 65px)',
        'left:50%',
        'transform:translateX(-50%)',
        'z-index:999999',
        'padding:12px 32px',
        'font-family:moonGetHeavy',
        'font-size:18px',
        'letter-spacing:1px',
        'color:white',
        'background:linear-gradient(135deg,#1a7a1a,#2ecc40)',
        'border:3px solid rgba(255,255,255,0.3)',
        'border-radius:30px',
        'cursor:pointer',
        'box-shadow:0 6px 24px rgba(0,0,0,0.6)',
        'text-shadow:1px 1px 2px rgba(0,0,0,0.5)'
      ].join(';');
      _sellBtn.onclick = flushVentas;
      document.body.appendChild(_sellBtn);
    }
    _sellBtn.textContent = '\uD83D\uDCB0 Vender (' + n + ')';
    _sellBtn.style.display = 'block';
  }

  function _calcPrecioVentaPct() {
    var pct = 2.00000001;
    if (typeof oficios !== 'object' || !oficios || !oficios.Mercader) return pct;
    pct = 1.66666667;
    var sub = oficios.Mercader.sub;
    if (sub && sub.Comerciante) {
      var n = sub.Comerciante;
      if (n == 1) pct = 1.42857143;
      if (n == 2) pct = 1.25;
      if (n == 3) pct = 1.1111111117;
    }
    return pct;
  }

  function venderItem(objetoId) {
    var objeto     = objetos_json[objetoId][0];
    var pct        = _calcPrecioVentaPct();
    var precioUnit = Math.floor(parseInt(objeto.berries) / pct) + 1;
    var cantidad   = parseInt(objeto.cantidad) || 1;
    if (cantidad > 1) {
      abrirModalCantidadVenta(objetoId, precioUnit, cantidad);
    } else {
      if (confirm('¿Estás seguro que quieres vender ' + objeto.nombre + ' por ' + precioUnit.toLocaleString('es-es') + ' berries?')) {
        queueVenta(objetoId, precioUnit, 1);
      }
    }
  }

  function queueVenta(objetoId, precioUnit, cantidadVender) {
    cantidadVender = cantidadVender || 1;
    if (_sellQueue.some(function(e) { return e.id === objetoId; })) {
      alert('Este objeto ya está en la cola de venta.');
      return;
    }
    _sellQueue.push({ id: objetoId, cantidad: cantidadVender });
    var card = document.getElementById(objetoId);
    if (card) {
      card.style.position = 'relative';
      var badge = document.createElement('div');
      badge.id = 'venta-badge-' + objetoId;
      badge.title = 'En cola de venta: ' + cantidadVender + ' x ' + (precioUnit ? precioUnit.toLocaleString('es-es') : '?') + 'B';
      badge.style.cssText = [
        'position:absolute',
        'top:2px',
        'right:2px',
        'background:rgba(26,120,26,0.92)',
        'color:white',
        'font-family:moonGetHeavy',
        'font-size:7px',
        'padding:2px 4px',
        'border-radius:4px',
        'z-index:10',
        'pointer-events:none',
        'line-height:1.5',
        'text-align:center'
      ].join(';');
      badge.innerHTML = '\uD83D\uDCB0' + (cantidadVender > 1 ? '<br>x' + cantidadVender : '') + '<br>EN<br>VENTA';
      card.appendChild(badge);
    }
    _updateSellBtn();
  }

  function flushVentas() {
    if (_sellQueue.length === 0) return;
    if (_sellBtn) { _sellBtn.disabled = true; _sellBtn.textContent = 'Vendiendo\u2026'; }
    var queue = _sellQueue.slice();
    function next(i) {
      if (i >= queue.length) { location.reload(); return; }
      var item = queue[i];
      $.post('/op/vender.php', { objeto: item.id, cantidad_vender: item.cantidad }, function (data) {
          if (!data.ok) { alert('Error al vender: ' + (data.error || 'Error desconocido')); }
          next(i + 1);
        }, 'json').fail(function () {
          alert('Error de conexión al vender.');
          next(i + 1);
        });
    }
    next(0);
  }

  // ── Exposición global ────────────────────────────

  w.desequiparTodo              = desequiparTodo;
  w.filtrarCategoria            = filtrarCategoria;
  w.filtrarTier                 = filtrarTier;
  w.aplicarFiltrosInventario    = aplicarFiltrosInventario;
  w.limpiarFiltrosInventario    = limpiarFiltrosInventario;
  w.abrirSelectorEquipamiento   = abrirSelectorEquipamiento;
  w.cerrarSelectorEquipamiento  = cerrarSelectorEquipamiento;
  w.reindexObject               = reindexObject;
  w.desequiparItem              = desequiparItem;
  w.mostrarMensajeEquipamiento  = mostrarMensajeEquipamiento;
  w.extraerEspaciosDelEfecto    = extraerEspaciosDelEfecto;
  w.extraerEspaciosRequeridos   = extraerEspaciosRequeridos;
  w.calcularEspaciosDisponibles = calcularEspaciosDisponibles;
  w.actualizarEspaciosOcupados  = actualizarEspaciosOcupados;
  w.equiparObjetoEnEspacio      = equiparObjetoEnEspacio;
  w.desequiparObjetoDeEspacio   = desequiparObjetoDeEspacio;
  w.generarEspaciosVisuales     = generarEspaciosVisuales;
  w.objetoEstaEquipado          = objetoEstaEquipado;
  w.abrirSelectorObjetoInventario = abrirSelectorObjetoInventario;
  w.equiparObjetoInventario     = equiparObjetoInventario;
  w.mostrarOpcionesObjeto       = mostrarOpcionesObjeto;
  w.actualizarContadorEspacios  = actualizarContadorEspacios;
  w.equiparItem                 = equiparItem;
  w.equiparItemBolsa            = equiparItemBolsa;
  w.equiparItemRopa             = equiparItemRopa;
  w.cambiarEquipamiento         = cambiarEquipamiento;
  w.venderItem                  = venderItem;
  w.queueVenta                  = queueVenta;
  w.flushVentas                 = flushVentas;
  w.abrirModalCantidadVenta     = abrirModalCantidadVenta;
  w.cerrarModalCantidadVenta    = cerrarModalCantidadVenta;
  w.mcvCambiar                  = mcvCambiar;
  w.mcvActualizarTotal          = mcvActualizarTotal;
  w.confirmarCantidadVenta      = confirmarCantidadVenta;

})(window.FICHA, window);
