(function(OPG, w){
  var esStaffActual = !!OPG.env.IS_STAFF;

  // Estados locales
  var registroPeticionesCargado = false;
  var registroPeticionesCargando = false;
  var listadoPeticionesCache = [];
  var registroNarradorTimers = {};
  var filtroEstadoRegistro = null;
  var filtroEstadoConsola  = null;
  var filtroUidRegistro = null;
  var filtroUidConsola  = null;

  // Devuelve true si el uid dado coincide con el narrador o con algún jugador participante del item
  function itemCoincideConUid(item, uid){
    if (!uid) return true;
    var narradorUid = (typeof item.narrador_uid==='number') ? item.narrador_uid : (parseInt(item.narrador_uid,10)||0);
    if (narradorUid === uid) return true;
    if (Array.isArray(item.jugadores)){
      return item.jugadores.some(function(j){
        var jugadorUid = (j && typeof j.uid !== 'undefined' && j.uid !== null) ? (parseInt(j.uid,10)||0) : 0;
        return jugadorUid > 0 && jugadorUid === uid;
      });
    }
    return false;
  }

  // Catálogo de estados
  var STATUS_CATALOG = {
    'creado': { key:'creado', label:'Creado', code:0 },
    'aprobado': { key:'aprobado', label:'Aprobado', code:1 },
    'denegado': { key:'denegado', label:'Denegado', code:2 },
    'finalizado': { key:'finalizado', label:'Finalizado', code:3 },
    'pendiente-narrador': { key:'pendiente-narrador', label:'Pendiente narrador', code:4 },
    'pendiente-borrado': { key:'pendiente-borrado', label:'Pendiente borrado', code:5 }
  };
  var STATUS_ALIAS = {
    '0':'creado','creado':'creado','creada':'creado',
    '1':'aprobado','aprobado':'aprobado','aprobada':'aprobado',
    '2':'denegado','denegado':'denegado','denegada':'denegado',
    '3':'finalizado','finalizado':'finalizado','finalizada':'finalizado',
    '4':'pendiente-narrador','pendiente':'pendiente-narrador','pendiente narrador':'pendiente-narrador','pendiente-narrador':'pendiente-narrador','pendiente_narrador':'pendiente-narrador',
    '5':'pendiente-borrado','pendiente borrado':'pendiente-borrado','pendiente-borrado':'pendiente-borrado','pendiente_borrado':'pendiente-borrado'
  };
  function resolverClaveEstado(v){
    if (v===null || typeof v==='undefined') return null;
    if (typeof v==='object'){
      if (typeof v.status_key==='string') return resolverClaveEstado(v.status_key);
      if (typeof v.status==='string') return resolverClaveEstado(v.status);
      if (typeof v.status_code!=='undefined') return resolverClaveEstado(v.status_code);
    }
    if (typeof v==='number' || typeof v==='boolean') return STATUS_ALIAS[String(v)] || null;
    var t = v.toString().trim().toLowerCase();
    return STATUS_ALIAS[t] || t || null;
  }
  function obtenerInfoEstado(origen){ var key = resolverClaveEstado(origen) || 'creado'; return STATUS_CATALOG[key] || STATUS_CATALOG.creado; }
  function obtenerCodigoEstado(v){ return obtenerInfoEstado(v).code; }

  // Helpers UI
  function prepararColecciones(item){
    var jugadores = Array.isArray(item.jugadores)?item.jugadores:[];
    var enemigos = Array.isArray(item.enemigos)?item.enemigos:[];
    var detalles = Array.isArray(item.detalles)?item.detalles:[];
    return {
      jugadores: jugadores, enemigos: enemigos, detalles: detalles,
      jugadoresHTML: jugadores.length ? jugadores.map(function(j){ return '<li>'+OPG.escapeHtml(j.nombre||'Sin nombre')+' · Nv.'+OPG.escapeHtml(String(j.nivel||'-'))+'</li>'; }).join('') : '<li>Sin jugadores registrados</li>',
      enemigosHTML: enemigos.length ? enemigos.map(function(e){ var et = (e.cantidad && e.cantidad>1) ? (' x'+e.cantidad) : ''; return '<li>'+OPG.escapeHtml(e.nombre||'Sin nombre')+' · Nv.'+OPG.escapeHtml(String(e.nivel||'-'))+et+'</li>'; }).join('') : '<li>Sin enemigos registrados</li>',
      detallesHTML: detalles.length ? detalles.slice(0,6).map(function(d){
        var dif = typeof d.diferenciaNivel==='number' ? d.diferenciaNivel : (parseFloat(d.diferenciaNivel)||0);
        var contrib = d.contribucion ? Number(d.contribucion).toFixed(1) : '0';
        return '<li>'+OPG.escapeHtml(d.nombre||'Enemigo')+' ('+(dif>=0?'+':'')+OPG.escapeHtml(String(dif))+' nv) = '+contrib+'</li>';
      }).join('') : '<li>Sin desglose de dificultad</li>'
    };
  }

	// helper: habilita/deshabilita el botón según haya fid
	function actualizarEstadoBotonClaim(id){
	  var fidInput = obtenerElementoClaimRegistro(id, 'Fid');
	  var btn = document.querySelector('.registroClaimBtn[data-id="'+id+'"]');
	  if (!btn || !fidInput) return;
	  var ok = parseInt(fidInput.value||'0',10) > 0;
	  btn.disabled = !ok;
	  btn.textContent = ok ? 'Postularme como narrador' : 'Selecciona tu ficha';
	}	

  // Claim narrador en Registro
  function generarFormularioNarradorRegistro(item){
    var id = item && item.id ? String(item.id) : '';
    if (!id) return '';
    var propietarioUid = (typeof item.uid==='number')? item.uid : (parseInt(item.uid,10)||0);
    var esPropietario = (typeof OPG.env.CURRENT_UID==='number' && OPG.env.CURRENT_UID>0 && OPG.env.CURRENT_UID===propietarioUid);
    if (esPropietario){
      return '<div class="registroClaimWrapper" data-peticion-id="'+OPG.escapeHtml(id)+'"><p class="registroClaimTitle">Esta petición es tuya, por lo que no puedes postularte como narrador.</p></div>';
    }
    var puedePostular = (typeof OPG.env.CURRENT_UID==='number' && OPG.env.CURRENT_UID>0);
    var dis = puedePostular ? '' : ' disabled';
    var texto = puedePostular ? 'Postularme como narrador' : 'Inicia sesión para postularte';
    return ''+
      '<div class="registroClaimWrapper" data-peticion-id="'+OPG.escapeHtml(id)+'">'+
      '<p class="registroClaimTitle">Esta petición espera narrador. Si eres narrador, busca tu ficha para asignarte.</p>'+
      '<div>'+
      '<input type="text" class="registroClaimInput" id="registroClaimInput-'+OPG.escapeHtml(id)+'" data-peticion-id="'+OPG.escapeHtml(id)+'" placeholder="Buscar ficha..." '+(puedePostular?'':'disabled')+' autocomplete="off">'+
      '<div class="registroClaimResults" id="registroClaimResults-'+OPG.escapeHtml(id)+'"></div>'+
      '<span class="registroClaimChip" id="registroClaimChip-'+OPG.escapeHtml(id)+'" style="display:none;"></span>'+
      '<input type="hidden" id="registroClaimFid-'+OPG.escapeHtml(id)+'" value="">'+
      '<input type="hidden" id="registroClaimNombre-'+OPG.escapeHtml(id)+'" value="">'+
      '</div>'+
      '<button type="button" class="registroClaimBtn" data-action="registro-claim" data-id="'+OPG.escapeHtml(id)+'"'+dis+'>'+texto+'</button>'+
      '</div>';
  }
  function obtenerElementoClaimRegistro(id, tipo){ return document.getElementById('registroClaim'+tipo+'-'+id); }
  function pintarResultadosNarradorRegistro(id, items){
    var cont = obtenerElementoClaimRegistro(id, 'Results'); if (!cont) return;
    if (!items || !items.length){ cont.innerHTML=''; cont.style.display='none'; return; }
    var html = items.map(function(it){
      var titulo = it.nombre; if (it.apodo && it.apodo!=='') titulo += ' ('+it.apodo+')';
      return '<div class="registroClaimResultItem" data-peticion-id="'+OPG.escapeHtml(String(id))+'" data-fid="'+OPG.escapeHtml(String(it.fid))+'" data-display="'+OPG.escapeHtml(titulo)+'">'+OPG.escapeHtml(titulo)+'</div>';
    }).join('');
    cont.innerHTML = html; cont.style.display='block';
  }
  function buscarNarradoresParaRegistro(id, q){
    var texto = (q||'').trim();
    if (texto.length<2){ pintarResultadosNarradorRegistro(id, []); return; }
    fetch('Calculadora_Tiers.php?action=search_fichas&q='+encodeURIComponent(texto), { method:'GET', headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(function(r){return r.json();})
      .then(function(data){ if (data && data.ok){ pintarResultadosNarradorRegistro(id, data.items||[]); } else { pintarResultadosNarradorRegistro(id, []); } })
      .catch(function(){ pintarResultadosNarradorRegistro(id, []); });
  }
  function seleccionarNarradorRegistro(id, fid, titulo){
	  var input = obtenerElementoClaimRegistro(id, 'Input');
	  var fidInput = obtenerElementoClaimRegistro(id, 'Fid');
	  var nombreInput = obtenerElementoClaimRegistro(id, 'Nombre');
	  var chip = obtenerElementoClaimRegistro(id, 'Chip');
	  if (!input || !fidInput || !nombreInput || !chip) return;
	  input.value = titulo;
	  fidInput.value = String(fid);
	  nombreInput.value = titulo;
	  chip.textContent = 'Seleccionado: '+titulo;
	  chip.style.display = 'inline-flex';
	  pintarResultadosNarradorRegistro(id, []);

	  // <- ahora sí: habilitar botón
	  actualizarEstadoBotonClaim(id);
  }

  function cerrarResultadosClaimRegistro(excluir){
    var boxes = document.querySelectorAll('.registroClaimResults');
    boxes.forEach(function(b){ if (excluir && b===excluir) return; b.style.display='none'; });
  }

  // Filtros
  function actualizarBotonesFiltro(tipo){
    var wrapId = (tipo==='consola') ? 'consolaFilterBar' : 'registroFilterBar';
    var wrap = document.getElementById(wrapId); if (!wrap) return;
    var filtroActivo = (tipo==='consola') ? filtroEstadoConsola : filtroEstadoRegistro;
    var botones = wrap.querySelectorAll('.registroFilterBtn');
    botones.forEach(function(btn){
      var valor = btn.getAttribute('data-filter');
      btn.classList.toggle('active', filtroActivo===valor);
    });
  }
  function manejarClickFiltroEstados(e){
    var btn = e.target.closest('.registroFilterBtn'); if (!btn) return;
    var rol = btn.getAttribute('data-rol'); var filtro = btn.getAttribute('data-filter'); if (!rol || !filtro) return;
    var actual = (rol==='consola') ? filtroEstadoConsola : filtroEstadoRegistro;
    var nuevo = (actual===filtro) ? null : filtro;
    if (rol==='consola'){ filtroEstadoConsola = nuevo; actualizarBotonesFiltro('consola'); if (esStaffActual){ renderConsolaPeticiones(listadoPeticionesCache); } }
    else { filtroEstadoRegistro = nuevo; actualizarBotonesFiltro('registro'); renderRegistroPeticiones(listadoPeticionesCache); }
  }
  function inicializarFiltrosEstado(){
    var reg = document.getElementById('registroFilterBar'); if (reg && reg.dataset.filterReady!=='1'){ reg.dataset.filterReady='1'; reg.addEventListener('click', manejarClickFiltroEstados); }
    var con = document.getElementById('consolaFilterBar'); if (con && con.dataset.filterReady!=='1'){ con.dataset.filterReady='1'; con.addEventListener('click', manejarClickFiltroEstados); }
    actualizarBotonesFiltro('registro'); if (esStaffActual) actualizarBotonesFiltro('consola');

    var regUidInput = document.getElementById('registroUidFiltro');
    if (regUidInput && regUidInput.dataset.filterReady!=='1'){
      regUidInput.dataset.filterReady='1';
      regUidInput.addEventListener('input', function(){
        filtroUidRegistro = parseInt(regUidInput.value,10) || null;
        renderRegistroPeticiones(listadoPeticionesCache);
      });
    }
    var conUidInput = document.getElementById('consolaUidFiltro');
    if (conUidInput && conUidInput.dataset.filterReady!=='1'){
      conUidInput.dataset.filterReady='1';
      conUidInput.addEventListener('input', function(){
        filtroUidConsola = parseInt(conUidInput.value,10) || null;
        if (esStaffActual){ renderConsolaPeticiones(listadoPeticionesCache); }
      });
    }
  }

  // Acciones usuario (registro)
  function postularseComoNarrador(id){
    if (!id) return;
    if (!OPG.env.CURRENT_UID){ alert('Debes iniciar sesión para postularte como narrador.'); return; }
    var fidInput = obtenerElementoClaimRegistro(id, 'Fid');
    var nombreInput = obtenerElementoClaimRegistro(id, 'Nombre');
    if (!fidInput || !nombreInput){ alert('No se pudo leer la selección.'); return; }
    var fid = parseInt(fidInput.value||'0',10);
    var nombre = (nombreInput.value||'').trim();
    if (!fid || !nombre){ alert('Selecciona tu ficha antes de enviar.'); return; }
    var boton = document.querySelector('.registroClaimBtn[data-id="'+id+'"]');
    if (boton){ boton.disabled=true; boton.textContent='Enviando...'; }
    fetch('Calculadora_Tiers.php?action=claim_narrador', {
      method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body: JSON.stringify({ peticion_id:id, narrador_fid:fid, narrador_nombre:nombre })
    }).then(function(r){return r.json();}).then(function(data){
      if (data && data.ok){
        if (data.redirect){ w.location.href=data.redirect; return; }
        alert('Te postulaste como narrador.');
        cargarRegistroPeticiones(true);
      } else { throw new Error((data&&data.error)||'No se pudo completar la acción'); }
    }).catch(function(err){ alert('No se pudo registrar la postulación: '+err.message); })
      .finally(function(){ if (boton){ boton.disabled=false; boton.textContent='Postularme como narrador'; } });
  }
  function marcarPeticionPendienteBorrado(id, boton){
    if (!id) return;
    if (!confirm('¿Deseas eliminar la petición #'+id+' del registro?')) return;
    if (boton){ boton.disabled=true; boton.textContent='Eliminando...'; }
    fetch('Calculadora_Tiers.php?action=hide_request', {
      method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body: JSON.stringify({ peticion_id:id })
    }).then(function(r){return r.json();}).then(function(data){
      if (data && data.ok){ alert('La petición se elimino del registro.'); cargarRegistroPeticiones(true); }
      else { throw new Error((data&&data.error)||'No se pudo procesar la solicitud'); }
    }).catch(function(err){ alert('No se pudo eliminar la petición: '+err.message); })
      .finally(function(){ if (boton){ boton.disabled=false; boton.textContent='Eliminar del registro'; } });
  }
  function construirEnlacePeticion(id){
    var origin = w.location.origin || (w.location.protocol+'//'+w.location.host);
    return origin + w.location.pathname + '?peticion=' + encodeURIComponent(String(id));
  }
  function compartirPeticion(id, boton){
    if (!id) return;
    var enlace = construirEnlacePeticion(id);
    var orig = boton ? boton.textContent : null;
    var restore = function(){ if (boton && orig!==null){ setTimeout(function(){ boton.textContent=orig; }, 2000); } };
    var copied = function(msg){ if (boton){ boton.textContent=msg; restore(); } };
    if (navigator.clipboard && navigator.clipboard.writeText){
      navigator.clipboard.writeText(enlace).then(function(){ copied('Link copiado'); })
      .catch(function(){ prompt('Copia y comparte este enlace:', enlace); copied('Copiar enlace'); });
    } else { prompt('Copia y comparte este enlace:', enlace); copied('Copiar enlace'); }
  }

  // Delegaciones (registro)
	function manejarInputClaimRegistro(e){
	  var input = e.target.closest('.registroClaimInput'); if (!input) return;
	  var id = parseInt(input.getAttribute('data-peticion-id')||'0',10); if (!id) return;

	  // Al teclear, limpiar selección previa y deshabilitar botón
	  var fidInput = obtenerElementoClaimRegistro(id, 'Fid');
	  var nombreInput = obtenerElementoClaimRegistro(id, 'Nombre');
	  var chip = obtenerElementoClaimRegistro(id, 'Chip');
	  if (fidInput) fidInput.value = '';
	  if (nombreInput) nombreInput.value = '';
	  if (chip) chip.style.display = 'none';
	  actualizarEstadoBotonClaim(id);

	  // Buscar
	  if (registroNarradorTimers[id]) clearTimeout(registroNarradorTimers[id]);
	  registroNarradorTimers[id] = setTimeout(function(){
		buscarNarradoresParaRegistro(id, input.value||'');
	  }, 220);
	}
  function manejarFocusClaimRegistro(e){
    var input = e.target.closest('.registroClaimInput'); if (!input) return;
    var id = parseInt(input.getAttribute('data-peticion-id')||'0',10);
    if (id && (input.value||'').trim().length>=2){ buscarNarradoresParaRegistro(id, input.value||''); }
  }
  function manejarClickClaimRegistro(e){
    var hideBtn = e.target.closest('.registroHideBtn');
    if (hideBtn){ var hid = parseInt(hideBtn.getAttribute('data-id')||'0',10); if (hid) marcarPeticionPendienteBorrado(hid, hideBtn); return; }
    var boton = e.target.closest('.registroClaimBtn');
    if (boton){ var id = parseInt(boton.getAttribute('data-id')||'0',10); if (id) postularseComoNarrador(id); return; }
    var shareBtn = e.target.closest('.registroShareBtn');
    if (shareBtn){ var sid = parseInt(shareBtn.getAttribute('data-id')||'0',10); if (sid) compartirPeticion(sid, shareBtn); return; }
    var reopenBtn = e.target.closest('.registroReopenBtn');
    if (reopenBtn){
      var rid = parseInt(reopenBtn.getAttribute('data-id')||'0',10);
      if (rid){
        var enlace = construirEnlacePeticion(rid);
        w.location.href = enlace;
      }
      return;
    }
    var opcion = e.target.closest('.registroClaimResultItem');
    if (opcion){
      var oid = parseInt(opcion.getAttribute('data-peticion-id')||'0',10);
      var fid = parseInt(opcion.getAttribute('data-fid')||'0',10);
      var titulo = opcion.getAttribute('data-display')||'';
      if (oid && fid) seleccionarNarradorRegistro(oid, fid, titulo);
      return;
    }
	var linkBtn = e.target.closest('.registroLinkBtn');
	if (linkBtn) {
	  var id = parseInt(linkBtn.getAttribute('data-id')||'0',10);
	  var input = document.getElementById('linkAventura-'+id);
	  if (!id || !input) return;
	  var url = input.value.trim();
	  if (!url) { alert('Introduce el enlace de la aventura'); return; }
	  linkBtn.disabled = true; linkBtn.textContent = 'Guardando...';
	  fetch('Calculadora_Tiers.php?action=vincular_aventura', {
		method:'POST',
		headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
		body: JSON.stringify({ peticion_id:id, aventura_url:url })
	  }).then(r=>r.json()).then(data=>{
		if (data && data.ok){
		  alert('Enlace vinculado correctamente.');
		  cargarRegistroPeticiones(true);
		} else { throw new Error((data && data.error)||'Error al guardar'); }
	  }).catch(err=>alert('No se pudo guardar: '+err.message))
	  .finally(()=>{ linkBtn.disabled=false; linkBtn.textContent='Guardar enlace'; });
	  return;
	}
  }
  function manejarClickFueraClaimRegistro(e){
    var wrapper = e.target.closest('.registroClaimWrapper'); if (wrapper) return;
    if (e.target.closest('.registroClaimResults')) return;
    cerrarResultadosClaimRegistro();
  }
  function inicializarRegistroNarradorInteracciones(){
    var cont = document.getElementById('registroPeticiones'); if (!cont || cont.dataset.claimReady==='1') return;
    cont.dataset.claimReady='1';
    cont.addEventListener('input', manejarInputClaimRegistro);
    cont.addEventListener('focusin', manejarFocusClaimRegistro);
    cont.addEventListener('click', manejarClickClaimRegistro);
    document.addEventListener('click', manejarClickFueraClaimRegistro);
  }

  // Render de listas
  function mostrarMensajeEnContenedor(cont, clase, msg){ if (!cont) return; cont.innerHTML = '<p class="'+clase+'">'+OPG.escapeHtml(msg)+'</p>'; }

	function pintarBadgeAventurasActivas(individual, grupo, limIndiv, limGrupo){
	  var btn = document.querySelector('.tabButton[data-tab="registro"]');
	  if (!btn) return;
	  // Prepara el botón para posicionar el badge
	  if (getComputedStyle(btn).position === 'static') {
		btn.style.position = 'relative';
	  }

	  individual = parseInt(individual, 10) || 0;
	  grupo = parseInt(grupo, 10) || 0;
	  limIndiv = parseInt(limIndiv, 10) || 3;
	  limGrupo = parseInt(limGrupo, 10) || 1;

	  var badge = btn.querySelector('.tabBadgeActivas');
	  var total = individual + grupo;

	  if (total > 0) {
		if (!badge) {
		  badge = document.createElement('span');
		  badge.className = 'tabBadgeActivas';
		  // estilos inline para la esquina superior derecha
		  badge.style.position = 'absolute';
		  badge.style.right = '-6px';
		  badge.style.top = '-6px';
		  badge.style.minWidth = '22px';
		  badge.style.height = 'auto';
		  badge.style.padding = '3px 6px';
		  badge.style.borderRadius = '10px';
		  badge.style.background = 'linear-gradient(135deg, #ff7b00, #ff9500)';
		  badge.style.color = '#fff';
		  badge.style.fontFamily = 'moonGetHeavy';
		  badge.style.fontSize = '10px';
		  badge.style.lineHeight = '12px';
		  badge.style.textAlign = 'center';
		  badge.style.boxShadow = '0 2px 6px rgba(0,0,0,0.35)';
		  badge.style.whiteSpace = 'nowrap';
		  btn.appendChild(badge);
		}
		// Mostrar formato: "Total: X/5 | Grupales adicionales: Y/1"
		var colorIndiv = individual >= limIndiv ? '#ff4444' : '#4CAF50';
		var colorGrupo = grupo >= limGrupo ? '#ff4444' : '#4CAF50';
		badge.innerHTML = '<span style="color:'+colorIndiv+';">'+individual+'/'+limIndiv+'</span> | <span style="color:'+colorGrupo+';" title="Aventuras con 2+ jugadores">+'+grupo+'/'+limGrupo+' grupales</span>';
		badge.title = 'Aventuras activas: ' + individual + '/' + limIndiv + ' totales (cualquier número de jugadores), ' + grupo + '/' + limGrupo + ' grupales adicionales (2+ jugadores)';
	  } else {
		if (badge) { badge.remove(); }
	  }
	}
	
  function renderRegistroPeticiones(items){
    var cont = document.getElementById('registroPeticiones'); if (!cont) return;
    cont.innerHTML = '';
    actualizarBotonesFiltro('registro');
    if (!items || !items.length){
      cont.innerHTML = '<p class="registroEmpty">No se encontraron solicitudes registradas.</p>'; return;
    }
    var visibles = 0;
    var filtro = filtroEstadoRegistro;
    items.forEach(function(item){
      var estadoFuente = (typeof item.status_code!=='undefined') ? item.status_code : (item.status_key || item.status);
      var info = obtenerInfoEstado(estadoFuente);
      var clave = info.key;
      var uidActual = (typeof OPG.env.CURRENT_UID==='number' && !isNaN(OPG.env.CURRENT_UID)) ? OPG.env.CURRENT_UID : parseInt(OPG.env.CURRENT_UID,10)||0;
      var narradorUid = (typeof item.narrador_uid==='number') ? item.narrador_uid : (parseInt(item.narrador_uid,10)||0);
      var propietarioUid = (typeof item.uid==='number') ? item.uid : (parseInt(item.uid,10)||0);
      var esNarradorAsignado = (uidActual>0 && narradorUid>0 && uidActual===narradorUid);
      var esPropietario = (uidActual>0 && uidActual===propietarioUid);
      var esJugadorParticipante = false;
      if (uidActual>0 && Array.isArray(item.jugadores) && item.jugadores.length){
        esJugadorParticipante = item.jugadores.some(function(j){
          var jugadorUid = null;
          if (j && typeof j.uid !== 'undefined' && j.uid !== null){
            jugadorUid = (typeof j.uid==='number') ? j.uid : (parseInt(j.uid,10)||0);
          }
          return jugadorUid && jugadorUid>0 && jugadorUid===uidActual;
        });
      }
      if (esJugadorParticipante && esPropietario){
        esJugadorParticipante = false;
      }
      if (clave==='pendiente-borrado') return;

      var pasaFiltro = true;
      if (filtro){
        if (filtro==='soy-narrador') pasaFiltro = esNarradorAsignado;
        else if (filtro==='soy-jugador') pasaFiltro = esJugadorParticipante;
        else pasaFiltro = (clave===filtro);
      }
      if (!pasaFiltro) return;
      if (!itemCoincideConUid(item, filtroUidRegistro)) return;
      if (OPG.shareId && item.id !== OPG.shareId) return;

      var cols = prepararColecciones(item);
      var color = item.dificultad_color || '#ffffff';
      var ratio = (typeof item.ratio_poder==='number') ? item.ratio_poder.toFixed(2) : (parseFloat(item.ratio_poder)||0).toFixed(2);
      var narradorLabel = item.narrador_nombre ? (item.narrador_nombre + ' · uid '+(item.narrador_uid||'-')+' · fid '+(item.narrador_fid||'-')) : 'Sin narrador asignado';
      var comentarioPublico = (item.public_comment||'').trim();
      var estadoBadge = '<span class="statusBadge '+clave+'">'+OPG.escapeHtml(info.label)+'</span>';
      var inframundoBadge = (item.inframundo == '1' || item.inframundo == 1 || item.inframundo == true) ? '<span class="registroInframundoBadge" title="Narración categoría Inframundo">Inframundo</span>' : '';
      var enemigosHTML = esNarradorAsignado ? cols.enemigosHTML : '<li>Solo el narrador asignado puede verlo.</li>';
      var detallesHTML = esNarradorAsignado ? cols.detallesHTML : '<li>Solo el narrador asignado puede verlo.</li>';
      var enemigosTitulo = esNarradorAsignado ? 'Enemigos ('+cols.enemigos.length+')' : 'Enemigos (privado)';
      var detallesTitulo = esNarradorAsignado ? 'Detalles' : 'Detalles (privado)';
	  var tituloTexto = 'Petición #'+OPG.escapeHtml(String(item.id))+' · Tier '+OPG.escapeHtml(String(item.tier_seleccionado));
		var tituloHtml = (item.aventura_url && item.aventura_url.trim()!=='')
		  ? '<a href="'+OPG.escapeHtml(item.aventura_url)+'" target="_blank" style="color:#ffb347;text-decoration:underline;">'+tituloTexto+'</a>'
		  : tituloTexto;
		
      var bloques = ''+
        '<h3>'+tituloHtml+'</h3>'+
        '<div class="registroMeta">'+
        '<span>Solicitante UID: '+OPG.escapeHtml(String(item.uid||'-'))+(item.username?(' ('+OPG.escapeHtml(item.username)+')'):'')+'</span>'+
        '<span>Creada: '+OPG.escapeHtml(OPG.formatearFechaRegistro(item.created_at))+'</span>'+
        '<span>Narrador: '+OPG.escapeHtml(narradorLabel)+'</span>'+
        '<span>Jugadores: '+OPG.escapeHtml(String(item.num_jugadores||0))+' (Nv. prom '+OPG.escapeHtml(String(item.nivel_promedio||0))+')</span>'+
        '<span>Ratio poder: '+ratio+'</span>'+
        '</div>'+
        '<div style="margin-top:10px; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">'+
        estadoBadge + inframundoBadge +
        '<span class="registroBadge" style="background:'+OPG.escapeHtml(color)+'20; color:'+OPG.escapeHtml(color)+';">'+OPG.escapeHtml(item.dificultad_texto||'-')+'</span>'+
        '<span style="font-size:13px; color:rgba(255,255,255,0.75); flex:1 1 auto;">'+OPG.escapeHtml(item.descripcion_tier||'')+'</span>'+
        '</div>'+
        '<div class="registroListas">'+
        '<div><span class="registroListTitle">Jugadores ('+cols.jugadores.length+')</span><ul>'+cols.jugadoresHTML+'</ul></div>'+
        '<div><span class="registroListTitle">'+enemigosTitulo+'</span><ul>'+enemigosHTML+'</ul></div>'+
        '<div><span class="registroListTitle">'+detallesTitulo+'</span><ul>'+detallesHTML+'</ul></div>'+
        '</div>';
      if (comentarioPublico){
        bloques += '<div class="registroComment"><strong>Comentario público:</strong><br>'+OPG.escapeHtml(comentarioPublico)+'</div>';
      }
	  
	  // Vincular/editar enlace de aventura (solo narrador asignado y estado aprobado)
		if (clave === 'aprobado' && esNarradorAsignado) {
		  var urlActual = (item.aventura_url || '').trim();
		  bloques += ''
			+ '<div class="registroNarradorActions" style="margin-top:10px;">'
			+   '<label style="display:block;font-family:\'moonGetHeavy\';color:#ffb347;font-size:13px;margin-bottom:6px;">Enlace de la aventura</label>'
			+   '<input type="url" class="registroLinkInput" id="linkAventura-'+OPG.escapeHtml(String(item.id))+'"'
			+          ' value="'+OPG.escapeHtml(urlActual)+'"'
			+          ' placeholder="https://tu-foro/tema/aventura..."'
			+          ' style="width:100%;padding:8px;border-radius:6px;border:1px solid #16a085;background:#0b1e1a;color:#fff;">'
			+   '<button type="button" class="registroLinkBtn" data-id="'+OPG.escapeHtml(String(item.id))+'"'
			+           ' style="margin-top:8px;padding:8px 14px;border:1px solid #16a085;background:#16a08530;color:#fff;border-radius:6px;cursor:pointer;">'
			+     (urlActual ? 'Actualizar enlace' : 'Guardar enlace')
			+   '</button>'
			+ '</div>';
		}
		
      if (clave==='pendiente-narrador' && (!item.narrador_uid || item.narrador_uid===0)){
        bloques += generarFormularioNarradorRegistro(item);
      }
      if (esPropietario){
        var btns = [];
        var tieneNarrador = narradorUid>0;
        if (!tieneNarrador) btns.push('<button type="button" class="registroShareBtn" data-id="'+OPG.escapeHtml(String(item.id))+'">Compartir solicitud</button>');
        btns.push('<button type="button" class="registroHideBtn" data-id="'+OPG.escapeHtml(String(item.id))+'">Eliminar del registro</button>');
        bloques += '<div class="registroOwnerActions">'+btns.join('')+'</div>';
      }
      if (esNarradorAsignado && clave==='denegado'){
        bloques += '<div class="registroNarradorActions"><button type="button" class="registroReopenBtn" data-id="'+OPG.escapeHtml(String(item.id))+'">Volver al editor</button></div>';
      }
      var card = document.createElement('div');
      card.className = 'registroItem status-'+clave;
      card.innerHTML = bloques;
      cont.appendChild(card);
      visibles++;
    });
    if (!visibles){
      cont.innerHTML = OPG.shareId ? '<p class="registroEmpty">No se encontró la solicitud compartida o fue eliminada.</p>' : '<p class="registroEmpty">No se encontraron solicitudes registradas.</p>';
    }
    inicializarRegistroNarradorInteracciones();
  }

  function renderConsolaPeticiones(items){
    if (!esStaffActual) return;
    var cont = document.getElementById('consolaPeticiones'); if (!cont) return;
    cont.innerHTML = '';
    actualizarBotonesFiltro('consola');
    if (!items || !items.length){ cont.innerHTML='<p class="registroEmpty">No se encontraron solicitudes registradas.</p>'; return; }
    var filtro = filtroEstadoConsola; var visibles=0;
    items.forEach(function(item){
      var info = obtenerInfoEstado((typeof item.status_code!=='undefined')?item.status_code:(item.status_key||item.status));
      var clave = info.key;
      if (filtro && clave!==filtro) return;
      if (!itemCoincideConUid(item, filtroUidConsola)) return;
      var cols = prepararColecciones(item);
      var color = item.dificultad_color || '#ffffff';
      var ratio = (typeof item.ratio_poder==='number') ? item.ratio_poder.toFixed(2) : (parseFloat(item.ratio_poder)||0).toFixed(2);
      var narradorLabel = item.narrador_nombre ? (item.narrador_nombre + ' · uid '+(item.narrador_uid||'-')+' · fid '+(item.narrador_fid||'-')) : 'Sin narrador asignado';
      var staffNote = (item.staff_note||'').trim();
      var publicComment = (item.public_comment||'').trim();
		var tituloTexto = 'Petición #'+OPG.escapeHtml(String(item.id))+' · Tier '+OPG.escapeHtml(String(item.tier_seleccionado));
		var tituloHtml = (item.aventura_url && item.aventura_url.trim()!=='')
		  ? '<a href="'+OPG.escapeHtml(item.aventura_url)+'" target="_blank" style="color:#ffb347;text-decoration:underline;">'+tituloTexto+'</a>'
		  : tituloTexto;

      var html = ''+
        '<h3>'+tituloHtml+'</h3>'+
        '<div class="registroMeta" style="margin-bottom:8px;">'+
        '<span>Solicitante UID: '+OPG.escapeHtml(String(item.uid||'-'))+(item.username?(' ('+OPG.escapeHtml(item.username)+')'):'')+'</span>'+
        '<span>Creada: '+OPG.escapeHtml(OPG.formatearFechaRegistro(item.created_at))+'</span>'+
        '<span>Narrador: '+OPG.escapeHtml(narradorLabel)+'</span>'+
        '<span>Ratio poder: '+ratio+'</span>'+
        '</div>'+
        '<div style="margin-bottom:12px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">'+
        '<span class="statusBadge '+clave+'">'+OPG.escapeHtml(info.label)+'</span>'+ ((item.inframundo == '1' || item.inframundo == 1 || item.inframundo == true) ? '<span class="registroInframundoBadge" title="Narración categoría Inframundo">Inframundo</span>' : '') +
        '<span class="registroBadge" style="background:'+OPG.escapeHtml(color)+'20; color:'+OPG.escapeHtml(color)+';">'+OPG.escapeHtml(item.dificultad_texto||'-')+'</span>'+
        '<span style="font-size:13px; color:rgba(255,255,255,0.75); flex:1 1 auto;">'+OPG.escapeHtml(item.descripcion_tier||'')+'</span>'+
        '</div>'+
        '<div class="registroListas">'+
        '<div><span class="registroListTitle">Jugadores ('+cols.jugadores.length+')</span><ul>'+cols.jugadoresHTML+'</ul></div>'+
        '<div><span class="registroListTitle">Enemigos ('+cols.enemigos.length+')</span><ul>'+cols.enemigosHTML+'</ul></div>'+
        '<div><span class="registroListTitle">Detalles</span><ul>'+cols.detallesHTML+'</ul></div>'+
        '</div>'+
        '<label class="consolaNoteLabel">Nota interna (solo staff)</label>'+
        '<textarea class="consolaTextarea consolaTextarea--nota" data-field="staff_note">'+OPG.escapeHtml(staffNote)+'</textarea>'+
        '<label class="consolaNoteLabel">Comentario público</label>'+
        '<textarea class="consolaTextarea consolaTextarea--comentario" data-field="public_comment">'+OPG.escapeHtml(publicComment)+'</textarea>'+
        '<div class="consolaButtonRow">'+
        '<button type="button" class="consolaBtn primary" data-action="guardar-meta" data-id="'+OPG.escapeHtml(String(item.id))+'">Guardar notas</button>'+
        '<button type="button" class="consolaBtn primary" data-action="set-status" data-status-code="1" data-id="'+OPG.escapeHtml(String(item.id))+'">Aprobar</button>'+
        '<button type="button" class="consolaBtn primary" data-action="set-status" data-status-code="3" data-id="'+OPG.escapeHtml(String(item.id))+'">Finalizar</button>'+
        '<button type="button" class="consolaBtn warning" data-action="set-status" data-status-code="4" data-id="'+OPG.escapeHtml(String(item.id))+'">Pendiente narrador</button>'+
        '<button type="button" class="consolaBtn danger" data-action="set-status" data-status-code="2" data-id="'+OPG.escapeHtml(String(item.id))+'">Denegar</button>'+
        '<button type="button" class="consolaBtn danger compact" data-action="eliminar" data-id="'+OPG.escapeHtml(String(item.id))+'">Eliminar</button>'+
        (function(){
          var tid=0;
          var hasUrl = item.aventura_url && item.aventura_url.trim();
          if (hasUrl){
            try{
              var match = item.aventura_url.match(/[?&]tid=(\d+)/);
              if(match) tid=parseInt(match[1],10)||0;
            }catch(e){}
          }
          var label = hasUrl && tid>0 ? 'PAGAR' : 'VINCULAR Y PAGAR';
          var disabled = !hasUrl || tid<=0 ? ' disabled' : '';
          return '<button type="button" class="consolaBtn primary" data-action="pagar" data-tid="'+tid+'" data-id="'+OPG.escapeHtml(String(item.id))+'"'+disabled+'>'+label+'</button>';
        })()+
        '</div>';
      var card = document.createElement('div');
      card.className='registroItem status-'+clave;
      card.setAttribute('data-peticion-id', String(item.id));
      card.innerHTML = html;
      cont.appendChild(card);
      visibles++;
    });
    if (!visibles){ cont.innerHTML='<p class="registroEmpty">No se encontraron solicitudes registradas.</p>'; }
  }

  // Acciones Staff
  function ejecutarAccionStaff(action, payload){
    return fetch('Calculadora_Tiers.php?action='+action, {
      method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify(payload)
    }).then(function(r){return r.json();}).then(function(data){
      if (data && data.ok){ cargarRegistroPeticiones(true); return data; }
      throw new Error((data&&data.error)||'Acción no completada');
    }).catch(function(err){ alert('No se pudo completar la acción: '+err.message); });
  }
  function guardarNotasPeticion(id, staffNote, publicComment){
    ejecutarAccionStaff('update_meta', { peticion_id:id, staff_note:staffNote, public_comment:publicComment });
  }
  function actualizarEstadoPeticion(id, estado){
    var val = estado;
    if (typeof val==='string'){
      var t = val.trim();
      if (t==='') val = obtenerCodigoEstado('creado');
      else if (/^-?\d+$/.test(t)) val = parseInt(t,10);
      else val = obtenerCodigoEstado(t);
    }
    if (typeof val!=='number' || isNaN(val)) val = obtenerCodigoEstado('creado');
    ejecutarAccionStaff('update_meta', { peticion_id:id, status: val });
  }
  function eliminarPeticionStaff(id){ ejecutarAccionStaff('delete', { peticion_id:id }); }
  function redirigirAPagoPeticion(tid){
    if (!tid || tid<=0){ alert('No se ha encontrado un TID válido para esta aventura.'); return; }
    window.location.href = 'staff/recompensasAventuras.php?tid='+tid;
  }
  function manejarClickConsola(e){
    var btn = e.target.closest('.consolaBtn'); if (!btn) return;
    var id = parseInt(btn.getAttribute('data-id'),10); if (!id && btn.getAttribute('data-action')!=='pagar') return;
    var act = btn.getAttribute('data-action');
    if (act==='guardar-meta'){
      var card = btn.closest('.registroItem'); if (!card) return;
      var nota = card.querySelector('.consolaTextarea--nota');
      var com  = card.querySelector('.consolaTextarea--comentario');
      guardarNotasPeticion(id, nota?nota.value:'', com?com.value:''); return;
    }
    if (act==='set-status'){
      var code = btn.getAttribute('data-status-code'); var texto = btn.getAttribute('data-status');
      var valor = null; if (code && code!==''){ var p = parseInt(code,10); if (!isNaN(p)) valor = p; }
      if (valor===null && texto) valor = texto;
      if (valor!==null) actualizarEstadoPeticion(id, valor);
      return;
    }
    if (act==='eliminar'){
      if (confirm('¿Seguro que deseas eliminar la petición #'+id+'?')) eliminarPeticionStaff(id);
    }
    if (act==='pagar'){
      var tid = parseInt(btn.getAttribute('data-tid'),10)||0;
      if (tid>0) {
        redirigirAPagoPeticion(tid);
      } else {
        alert('Primero debes vincular una URL de aventura con TID válido a esta petición.\n\nUsa la funcionalidad "Vincular aventura" en el registro o añade manualmente una URL con formato: ...showthread.php?tid=123');
      }
    }
  }

  // Carga de registro
  function cargarRegistroPeticiones(force){
    var contReg = document.getElementById('registroPeticiones');
    if (!contReg) return;

    if (registroPeticionesCargando) return;
    if (registroPeticionesCargado && !force) return;

    var contCon = esStaffActual ? document.getElementById('consolaPeticiones') : null;

    registroPeticionesCargando = true;
    mostrarMensajeEnContenedor(contReg, 'registroLoader', 'Consultando peticiones en la base de datos...');
    if (contCon){ mostrarMensajeEnContenedor(contCon, 'registroLoader', 'Consultando peticiones en la base de datos...'); }

    fetch('Calculadora_Tiers.php?action=list', { method:'GET', headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(function(r){return r.json();})
      .then(function(data){
        if (data && data.ok){
          listadoPeticionesCache = data.items || [];
          renderRegistroPeticiones(listadoPeticionesCache);
          if (esStaffActual) renderConsolaPeticiones(listadoPeticionesCache);
		  pintarBadgeAventurasActivas(
			data.mis_aventuras_individuales || 0,
			data.mis_aventuras_grupo || 0,
			data.limite_individual || 5,
			data.limite_grupo || 1
		  );
          registroPeticionesCargado = true;
        } else {
          var msg = 'No se pudo obtener el registro: ' + ((data&&data.error)||'Error desconocido');
          mostrarMensajeEnContenedor(contReg, 'registroError', msg);
          if (contCon) mostrarMensajeEnContenedor(contCon, 'registroError', msg);
        }
      }).catch(function(err){
        var msg = 'Error al conectar con el servidor: ' + String(err);
        mostrarMensajeEnContenedor(contReg, 'registroError', msg);
        if (contCon) mostrarMensajeEnContenedor(contCon, 'registroError', msg);
      }).finally(function(){ registroPeticionesCargando = false; });
  }

  // Init
  document.addEventListener('DOMContentLoaded', function(){
    inicializarFiltrosEstado();
    var tabRegistroActiva = document.querySelector('.tabButton[data-tab="registro"].active');
    if (OPG.shareId){ /* solicitud.js ya maneja edición */ }
    else if (tabRegistroActiva){ cargarRegistroPeticiones(false); }
    if (esStaffActual){
      var contCon = document.getElementById('consolaPeticiones'); if (contCon){ contCon.addEventListener('click', manejarClickConsola); }
    }
  });

  // Exponer global para onclick del botón ACTUALIZAR y para core-tabs
  w.cargarRegistroPeticiones = cargarRegistroPeticiones;

})(OPG, window);
