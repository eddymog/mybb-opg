(function(OPG, w){
  if (!document.querySelector('.tabPanel[data-tab="solicitud"]')) return;

  // Estado local de la pestaña
  var jugadores = [];
  var enemigos = [];
  var modoEdicionNarrador = false;
  var peticionEnEdicionId = null;
  var ultimoResultadoEncuentro = null;
  var jugadorSeleccionadoUid = null;
  var jugadorSeleccionadoNombre = '';
  
  // Contadores de aventuras activas
  var aventurasIndividualesActivas = 0;
  var aventurasGrupoActivas = 0;
  var limiteIndividual = 5;
  var limiteGrupo = 1;

	function esSeleccionValidaJugador(){
	  var input = document.getElementById('nombreJugador');
	  if (!input) return false;
	  var ds = input.dataset && input.dataset.fichaUid ? parseInt(input.dataset.fichaUid,10) || 0 : 0;
	  var at = parseInt(input.getAttribute('data-ficha-uid')||'0',10) || 0;
	  var uid = jugadorSeleccionadoUid || ds || at;
	  return uid > 0;
	}
	
	function actualizarContadoresAventurasDesdeServidor(){
	  fetch('Calculadora_Tiers.php?action=list&limit=1', {
		method: 'GET',
		headers: {'X-Requested-With': 'XMLHttpRequest'}
	  })
	  .then(function(r){ return r.json(); })
	  .then(function(data){
		if (data && data.ok){
		  aventurasIndividualesActivas = parseInt(data.mis_aventuras_individuales, 10) || 0;
		  aventurasGrupoActivas = parseInt(data.mis_aventuras_grupo, 10) || 0;
		  limiteIndividual = parseInt(data.limite_individual, 10) || 5;
		  limiteGrupo = parseInt(data.limite_grupo, 10) || 1;
		  
		  // Actualizar indicador visual
		  actualizarIndicadorAventurasActivas();
		}
	  })
	  .catch(function(err){
		console.error('Error al obtener contadores de aventuras:', err);
	  });
	}
	
	function actualizarIndicadorAventurasActivas(){
	  var indicador = document.getElementById('indicadorAventurasActivas');
	  var contIndiv = document.getElementById('contadorIndividual');
	  var contGrupo = document.getElementById('contadorGrupo');
	  
	  if (!indicador || !contIndiv || !contGrupo) return;
	  
	  // Mostrar el indicador solo si hay aventuras activas
	  var hayAventuras = aventurasIndividualesActivas > 0 || aventurasGrupoActivas > 0;
	  indicador.style.display = hayAventuras ? 'block' : 'none';
	  
	  // Actualizar contadores
	  contIndiv.textContent = aventurasIndividualesActivas;
	  contGrupo.textContent = aventurasGrupoActivas;
	  
	  // Colorear en rojo si alcanzó el límite
	  contIndiv.style.color = aventurasIndividualesActivas >= limiteIndividual ? '#ff4444' : '#fff';
	  contGrupo.style.color = aventurasGrupoActivas >= limiteGrupo ? '#ff4444' : '#fff';
	  
	  // Actualizar tooltips para claridad
	  if (contIndiv.parentElement) contIndiv.parentElement.title = 'Aventuras totales (límite 5, cualquier número de jugadores)';
	  if (contGrupo.parentElement) contGrupo.parentElement.title = 'Aventuras grupales adicionales (límite 1, mínimo 2 jugadores)';
	}
	function actualizarEstadoBotonAgregarJugador(){
	  var btn = document.querySelector('button[onclick="agregarJugador()"]');
	  if (!btn) return;
	  var ok = esSeleccionValidaJugador();
	  btn.disabled = !ok || modoEdicionNarrador;
	  btn.style.opacity = btn.disabled ? '0.6' : '1';
	  btn.style.cursor = btn.disabled ? 'not-allowed' : 'pointer';
	}
	
  // --- UI bloqueo/edición narrador ---
  function mostrarAvisoEdicionNarrador(peticionId){
    var panel = document.querySelector('.tabPanel[data-tab="solicitud"]');
    if (!panel) return;
    var aviso = document.getElementById('avisoEdicionNarrador');
    if (!aviso){
      aviso = document.createElement('div');
      aviso.id = 'avisoEdicionNarrador';
      aviso.style.margin='15px 0'; aviso.style.padding='12px 18px';
      aviso.style.border='2px dashed #ff7b00'; aviso.style.borderRadius='12px';
      aviso.style.background='rgba(255,123,0,0.08)'; aviso.style.color='#ffb347';
      aviso.style.fontFamily='moonGetHeavy';
      panel.insertBefore(aviso, panel.firstChild);
    }
    aviso.textContent = 'Modo narrador activo · Editando petición #' + peticionId + '. Puedes ajustar el tier, los enemigos y el comentario público.';
  }
  function bloquearCamposFueraDeAlcance(){
    var buscador = document.getElementById('buscadorNarrador');
    var sinCheck = document.getElementById('sinNarradorCheck');
    var tierSelect = document.getElementById('tierEncuentro');
    var inframundoCheck = document.getElementById('inframundoCheck');
    var btnAgregarJugador = document.querySelector('button[onclick="agregarJugador()"]');
    var entradasJugador = [document.getElementById('nombreJugador'), document.getElementById('nivelJugador')];
    if (modoEdicionNarrador){
      if (buscador){ buscador.value=''; buscador.disabled=true; buscador.style.opacity='0.6'; }
      if (sinCheck){ sinCheck.checked=false; sinCheck.disabled=true; }
      // Permitir cambiar tier en modo edición narrador
      if (tierSelect){ tierSelect.disabled=false; tierSelect.style.opacity='1'; }
      if (inframundoCheck){ inframundoCheck.disabled=false; inframundoCheck.style.opacity='1'; }
      entradasJugador.forEach(function(i){ if(i){ i.disabled=true; i.style.opacity='0.6'; } });
      if (btnAgregarJugador){ btnAgregarJugador.disabled=true; btnAgregarJugador.style.opacity='0.6'; }
    } else {
      if (buscador){ buscador.disabled=false; buscador.style.opacity='1'; }
      if (sinCheck){ sinCheck.disabled=false; }
      if (tierSelect){ tierSelect.disabled=false; tierSelect.style.opacity='1'; }
      if (inframundoCheck){ inframundoCheck.disabled=false; inframundoCheck.style.opacity='1'; }
      entradasJugador.forEach(function(i){ if(i){ i.disabled=false; i.style.opacity='1'; } });
      if (btnAgregarJugador){ btnAgregarJugador.disabled=false; btnAgregarJugador.style.opacity='1'; }
    }
  }
  function prepararJugadoresDesdeServidor(lista){
    var base = Date.now();
    return (Array.isArray(lista)?lista:[]).map(function(item, idx){
      var uid = 0;
      if (item && typeof item.uid !== 'undefined'){
        uid = parseInt(item.uid,10) || 0;
      }
      return {
        id: base+idx+1,
        nombre: item && item.nombre ? item.nombre : 'Sin nombre',
        nivel: parseInt(item && item.nivel ? item.nivel : 1,10) || 1,
        uid: uid>0 ? uid : null
      };
    });
  }

  function prepararEnemigosDesdeServidor(lista){
    var base = Date.now()+1000;
    return (Array.isArray(lista)?lista:[]).map(function(item, idx){
      return { id: base+idx+1, nombre: item && item.nombre ? item.nombre : 'Sin nombre', nivel: parseInt(item && item.nivel ? item.nivel : 1,10) || 1, cantidad: Math.max(1, parseInt(item && item.cantidad ? item.cantidad : 1,10) || 1) };
    });
  }

  function activarModoEdicionNarrador(item){
    if (!item) return;
    modoEdicionNarrador = true;
    peticionEnEdicionId = parseInt(item.id,10) || null;
    jugadores = prepararJugadoresDesdeServidor(item.jugadores);
    enemigos  = prepararEnemigosDesdeServidor(item.enemigos);
    var c = document.getElementById('comentarioPublicoSolicitud'); if (c){ c.value = item.public_comment || ''; }
    var t = document.getElementById('tierEncuentro'); if (t && item.tier_seleccionado){ t.value = String(item.tier_seleccionado); }
    var sinCheck = document.getElementById('sinNarradorCheck'); if (sinCheck){ sinCheck.checked=false; }
    var infra = document.getElementById('inframundoCheck'); if (infra){ infra.checked = !!item.inframundo; }
    var hf = document.getElementById('narrador_fid');    if (hf) hf.value   = item.narrador_fid ? String(item.narrador_fid) : '';
    var hn = document.getElementById('narrador_nombre'); if (hn) hn.value  = item.narrador_nombre || '';
    var hu = document.getElementById('narrador_uid');    if (hu) hu.value  = item.narrador_uid ? String(item.narrador_uid) : '';
    var chip = document.getElementById('narradorSeleccionado');
    if (chip && item.narrador_nombre){
        chip.textContent = 'Narrador asignado: ' + item.narrador_nombre + (item.narrador_fid ? (' · fid ' + item.narrador_fid) : '');
        chip.style.display = 'inline-block';
    }
    actualizarListaJugadores();
    actualizarListaEnemigos();
    calcularEncuentro();
    mostrarAvisoEdicionNarrador(item.id);
    bloquearCamposFueraDeAlcance();
    var btn = document.getElementById('btnPeticion');
    if (btn){ btn.textContent='GUARDAR CAMBIOS'; btn.disabled=false; btn.style.cursor='pointer'; btn.style.opacity='1'; }
    validarPermisoPeticion();
  }

  function cargarPeticionParaEdicion(peticionId){
    if (!peticionId) return;
    fetch('Calculadora_Tiers.php?action=get&peticion_id='+encodeURIComponent(peticionId), {
      method:'GET', headers:{'X-Requested-With':'XMLHttpRequest'}
    }).then(function(r){return r.json();}).then(function(data){
      if (!data || !data.ok){ alert((data&&data.error)||'No se pudo cargar la petición solicitada.'); return; }
      if (!data.puede_editar){ alert('No estás autorizado para editar esta petición.'); return; }
      activarModoEdicionNarrador(data.item||{});
    }).catch(function(e){ alert('No se pudo abrir la petición: '+ e.message); });
  }

  // --- Jugadores ---
  function actualizarListaJugadores(){
    var lista = document.getElementById('listaJugadores');
    if (!lista) return;
    if (jugadores.length===0){
      lista.innerHTML = '<div class="lista-vacia" style="width:100%;">No hay jugadores agregados</div>'; return;
    }
    var puedeEditar = !modoEdicionNarrador;
    lista.innerHTML = jugadores.map(function(j){
      var del = puedeEditar ? ('<button class="jugador-eliminar" onclick="eliminarJugador('+j.id+')">ELIMINAR</button>') : '<button class="jugador-eliminar" disabled title="Solo puedes editar enemigos en esta etapa">ELIMINAR</button>';
      return '<div class="jugador-item"><div class="jugador-info"><span class="jugador-nombre">'+OPG.escapeHtml(j.nombre)+'</span><span class="jugador-nivel">Nv.'+j.nivel+'</span></div>'+del+'</div>';
    }).join('');
  }
  function agregarJugador(){
	  if (modoEdicionNarrador){ alert('En modo narrador solo puedes modificar los enemigos.'); return; }

	  var nombreInput = document.getElementById('nombreJugador');
	  var nivelInput  = document.getElementById('nivelJugador');
	  var nombre = (nombreInput.value||'').trim();
	  var nivel  = parseInt(nivelInput.value,10);

	  // Bloqueo duro: exigir selección válida desde el desplegable
	  if (!esSeleccionValidaJugador()){
		alert('Debes seleccionar un jugador del desplegable (autocompletado) antes de añadir.');
		// Feedback visual breve
		var prev = nombreInput.style.borderColor;
		nombreInput.style.borderColor = '#ff3b30';
		setTimeout(function(){ nombreInput.style.borderColor = prev || ''; }, 1000);
		nombreInput.focus();
		actualizarEstadoBotonAgregarJugador();
		return;
	  }

	  if (!nombre){ alert('Por favor, ingresa el nombre del jugador'); return; }
	  if (nivel<1 || nivel>100){ alert('El nivel debe estar entre 1 y 100'); return; }

	  // Determinar uid final desde estado/atributos
	  var jugadorUid = 0;
	  if (jugadorSeleccionadoUid && jugadorSeleccionadoUid>0){
		jugadorUid = jugadorSeleccionadoUid;
	  } else if (nombreInput){
		var atributoUid = (nombreInput.dataset && nombreInput.dataset.fichaUid)
		  ? nombreInput.dataset.fichaUid
		  : nombreInput.getAttribute('data-ficha-uid');
		if (atributoUid){ jugadorUid = parseInt(atributoUid,10) || 0; }
	  }

	  jugadores.push({ id: Date.now(), nombre: nombre, nivel: nivel, uid: jugadorUid>0 ? jugadorUid : null });

	  // Reset inputs/estado
	  if (nombreInput){
		nombreInput.value='';
		if (nombreInput.dataset){ nombreInput.dataset.fichaUid=''; }
		nombreInput.removeAttribute('data-ficha-uid');
	  }
	  if (nivelInput){ nivelInput.value='1'; }
	  jugadorSeleccionadoUid = null;
	  jugadorSeleccionadoNombre = '';

	  actualizarListaJugadores();
	  calcularEncuentro();
	  actualizarEstadoBotonAgregarJugador();
	}

  function eliminarJugador(id){
    if (modoEdicionNarrador){ alert('En modo narrador solo puedes modificar los enemigos.'); return; }
    jugadores = jugadores.filter(function(j){ return j.id !== id; });
    actualizarListaJugadores(); calcularEncuentro();
  }

  // --- Enemigos ---
  function obtenerTierPorNivel(n){
    if (n>=100) return 16; if (n>=90) return 15; if (n>=80) return 14; if (n>=70) return 13; if (n>=60) return 12; if (n>=50) return 11; if (n>=40) return 10; if (n>=35) return 9; if (n>=30) return 8; if (n>=25) return 7; if (n>=20) return 6; if (n>=16) return 5; if (n>=12) return 4; if (n>=8) return 3; if (n>=4) return 2; return 1;
  }
  function actualizarListaEnemigos(){
    var lista = document.getElementById('listaEnemigos');
    if (!lista) return;
    if (enemigos.length===0){ lista.innerHTML = '<div class="lista-vacia" style="width:100%;">No hay enemigos agregados</div>'; return; }
    lista.innerHTML = enemigos.map(function(e){
      var tier = obtenerTierPorNivel(e.nivel);
      var cantidadHTML = e.cantidad>1 ? '<span class="enemigo-cantidad">x'+e.cantidad+'</span>' : '';
      return '<div class="enemigo-item"><div class="enemigo-info"><span class="enemigo-nombre">'
        + OPG.escapeHtml(e.nombre) + '</span><span class="enemigo-nivel">Nv.'+e.nivel+'</span>'+cantidadHTML+'<span class="enemigo-xp">Tier '+tier+'</span></div>'
        + '<button class="enemigo-eliminar" onclick="eliminarEnemigo('+e.id+')">ELIMINAR</button></div>';
    }).join('');
  }
  function agregarEnemigo(){
    var nombreInput = document.getElementById('nombreEnemigo');
    var nivelInput  = document.getElementById('nivelEnemigo');
    var cantidadInput = document.getElementById('cantidadEnemigo');
    var nombre = (nombreInput.value||'').trim();
    var nivel  = parseInt(nivelInput.value,10);
    var cantidad = parseInt(cantidadInput.value,10);
    if (!nombre){ alert('Por favor, ingresa el nombre del enemigo'); return; }
    if (nivel<1 || nivel>50){ alert('El nivel debe estar entre 1 y 50'); return; }
    if (cantidad<1 || cantidad>100){ alert('La cantidad debe estar entre 1 y 100'); return; }
    enemigos.push({ id: Date.now(), nombre: nombre, nivel: nivel, cantidad: cantidad });
    nombreInput.value=''; nivelInput.value='1'; cantidadInput.value='1';
    actualizarListaEnemigos(); calcularEncuentro();
  }
  function eliminarEnemigo(id){
    enemigos = enemigos.filter(function(e){ return e.id !== id; });
    actualizarListaEnemigos(); calcularEncuentro();
  }
  function expandirEnemigos(){
    var out = [];
    enemigos.forEach(function(e){
      for (var i=0;i<e.cantidad;i++){
        out.push({ nombre: e.nombre + (e.cantidad>1 ? (' #'+(i+1)) : ''), nivel: e.nivel });
      }
    });
    return out;
  }

  // --- Cálculo ---
  function calcularDificultad(nivelPromedioJugadores, numJugadores){
    if (numJugadores===0 || enemigos.length===0) return { texto:'-', color:'#888888', detalles:[], ratioPoder:1 };
    var exp = expandirEnemigos();
    var total = exp.length;
    if (total===0) return { texto:'-', color:'#888888', detalles:[], ratioPoder:1 };

    var poderJugadores = nivelPromedioJugadores * numJugadores;
    var poderEnemigos = 0; var detalles = [];
    exp.forEach(function(enemigo, index){
      var dif = enemigo.nivel - nivelPromedioJugadores;
      var factorNivel = dif>=0 ? Math.pow(1.2, dif) : Math.pow(0.7, Math.abs(dif));
      var ratioEnemigos = total / numJugadores;
      var factorCantidad = 1.0;
      if (ratioEnemigos > 1){
        var baseExp = dif>=0 ? 1.15 : 1.08;
        var posRel = (index+1)/numJugadores;
        if (posRel>1){ factorCantidad = Math.pow(baseExp, posRel-1); }
      } else if (ratioEnemigos < 1){
        factorCantidad = Math.pow(0.85, (1/ratioEnemigos)-1);
      }
      var contribBase = nivelPromedioJugadores * factorNivel;
      var contribTotal = contribBase * factorCantidad;
      poderEnemigos += contribTotal;
      detalles.push({ nombre: enemigo.nombre, nivel: enemigo.nivel, diferenciaNivel: dif, factorNivel: factorNivel, factorCantidad: factorCantidad, contribucion: contribTotal });
    });

    var ratio = poderEnemigos / poderJugadores;
    var difEq = 0;
    if (ratio>1){ difEq = Math.log(ratio)/Math.log(1.2); }
    else if (ratio<1){ difEq = -Math.log(1/ratio)/Math.log(1.2); }

    var res = { texto:'', color:'', detalles:detalles, ratioPoder:ratio };
    if (difEq <= -2){ res.texto='FÁCIL'; res.color='#00ff00'; }
    else if (difEq > -2 && difEq <= 1){ res.texto='AJUSTADO'; res.color='#ffff00'; }
    else if (difEq > 1 && difEq <= 3){ res.texto='DIFÍCIL'; res.color='#ff8800'; }
    else if (difEq > 3 && difEq <= 5){ res.texto='MUY DIFÍCIL'; res.color='#ff4400'; }
    else if (difEq > 5){ res.texto='HARDCORE'; res.color='#ff0000'; }
    else { res.texto='TRIVIAL'; res.color='#00ffff'; }
    return res;
  }
  function calcularEncuentro(){
    var nivelProm = 0;
    if (jugadores.length>0){
      var sum = jugadores.reduce(function(a,j){ return a + j.nivel; }, 0);
      nivelProm = Math.round(sum / jugadores.length);
    }
    var tierEncuentro = parseInt(document.getElementById('tierEncuentro').value, 10);
    var exp = expandirEnemigos();
    var totalEnemigos = exp.length;

    var dif = calcularDificultad(nivelProm, jugadores.length);

    // cabecera
    var nj = jugadores.length;
    var elProm = document.getElementById('nivelPromedio'); if (elProm) elProm.textContent = nivelProm;
    var elNum  = document.getElementById('numJugadores'); if (elNum)  elNum.textContent = nj;

    // dificultad
    var dEl = document.getElementById('dificultadEncuentro');
    if (dEl){ dEl.textContent = dif.texto; dEl.style.color = dif.color; }

    // info tier
    var info = OPG.tiersInfo[tierEncuentro];
    var infoTierDiv = document.getElementById('infoTier');
    var textoAdicional = '';
    if (jugadores.length>0 && enemigos.length>0){
      var sumaP = 0, sumaW=0;
      exp.forEach(function(e){
        var d = e.nivel - nivelProm;
        var peso = Math.pow(1.2, Math.abs(d));
        sumaP += e.nivel * peso; sumaW += peso;
      });
      var nPromEne = (totalEnemigos>0 && sumaW>0) ? Math.round(sumaP/sumaW) : 0;
      var detHTML = '';
      if (dif.detalles && dif.detalles.length>0){
        detHTML = '<div style="margin-top:10px;max-height:200px;overflow-y:auto;">' +
          dif.detalles.map(function(d){
            var dt = d.diferenciaNivel>=0?('+'+d.diferenciaNivel):d.diferenciaNivel;
            var fN = 'x'+d.factorNivel.toFixed(2);
            var fC = d.factorCantidad!==1.0 ? (' · x'+d.factorCantidad.toFixed(2)+' cantidad') : '';
            return '<p style="margin:3px 0;font-size:12px;opacity:.8;">'+OPG.escapeHtml(d.nombre)+' Nv.'+d.nivel+' ('+dt+' niveles) '+fN+fC+' = '+d.contribucion.toFixed(1)+' poder</p>';
          }).join('') + '</div>';
      }
      textoAdicional =
        '<div style="margin-top:15px;padding:15px;background:rgba(0,0,0,0.3);border-radius:10px;">' +
        '<p style="margin:5px 0;font-family:moonGetHeavy;color:#ff7b00;">ANÁLISIS DEL ENCUENTRO</p>' +
        '<p style="margin:5px 0;">Jugadores: '+jugadores.length+' (Nivel Promedio: '+nivelProm+') = Poder Total: '+(nivelProm*jugadores.length).toFixed(1)+'</p>'+
        '<p style="margin:5px 0;">Enemigos: '+totalEnemigos+' (Nivel Promedio: '+nPromEne+')</p>'+ detHTML +
        '<p style="margin:8px 0;padding-top:8px;border-top:1px solid rgba(255,255,255,0.2);">Ratio de Poder: '+(dif.ratioPoder?dif.ratioPoder.toFixed(2):'1.00')+'</p>'+
        '<p style="margin:5px 0;font-family:moonGetHeavy;color:'+dif.color+';">Dificultad: '+dif.texto+'</p></div>';
    }
    if (infoTierDiv){
      infoTierDiv.innerHTML =
        '<div style="margin-bottom:15px;">' +
        '<span style="font-family:moonGetHeavy;font-size:24px;color:#ff7b00;">TIER '+tierEncuentro+'</span>'+
        '<span style="font-family:moonGetHeavy;font-size:18px;color:#fff;margin-left:15px;">Nivel Mínimo: '+info.nivelMin+'</span>'+
        '<span style="font-family:moonGetHeavy;font-size:18px;color:#fff;margin-left:15px;">Posts: '+info.posts+'</span>'+
        '</div>'+
        '<p style="margin:10px 0;line-height:1.6;text-align:justify;max-width:900px;margin-left:auto;margin-right:auto;">'+OPG.escapeHtml(info.descripcion)+'</p>'+
        textoAdicional;
    }

    ultimoResultadoEncuentro = { resultado: dif, detalles: dif.detalles||[], ratio: (typeof dif.ratioPoder==='number'? dif.ratioPoder:0) };
    return dif;
  }

  // --- Limpieza / payload / envío ---
  function limpiarCalculadora(){
    if (modoEdicionNarrador){
      if (!confirm('Esta acción solo limpiará los enemigos registrados. ¿Deseas continuar?')) return;
      enemigos = []; actualizarListaEnemigos(); calcularEncuentro(); return;
    }
    if (!confirm('¿Estás seguro de que quieres limpiar todos los datos?')) return;
    jugadores=[]; enemigos=[];
    jugadorSeleccionadoUid = null;
    jugadorSeleccionadoNombre = '';
    actualizarListaJugadores(); actualizarListaEnemigos();
    var c = document.getElementById('comentarioPublicoSolicitud'); if (c){ c.value=''; }    var infra = document.getElementById('inframundoCheck'); if (infra){ infra.checked = false; }    calcularEncuentro();
  }
  function recogerDescripcionTierSeleccionado(){
    var tier = parseInt(document.getElementById('tierEncuentro').value,10);
    var info = OPG.tiersInfo[tier]; return { tier: tier, descripcion: info ? info.descripcion : '' };
  }
  function construirPayloadCalculo(){
    var difActual = calcularEncuentro();
    var tierSel = parseInt(document.getElementById('tierEncuentro').value,10);
    var info = recogerDescripcionTierSeleccionado();
    var nivelPromedio = parseInt((document.getElementById('nivelPromedio').textContent||'0'),10)||0;
    var numJug      = parseInt((document.getElementById('numJugadores').textContent||'0'),10)||0;
    var difTxt = (document.getElementById('dificultadEncuentro').textContent||'-');
    var difColor = (document.getElementById('dificultadEncuentro').style.color||'#888888');

    var jugadoresPayload = jugadores.map(function(j){
      var valor = { nombre:j.nombre, nivel:j.nivel };
      var uid = (typeof j.uid!=='undefined' && j.uid!==null) ? parseInt(j.uid,10) : 0;
      if (!isNaN(uid) && uid>0){ valor.uid = uid; }
      return valor;
    });
    var enemigosPayload  = enemigos.map(function(e){ return { nombre:e.nombre, nivel:e.nivel, cantidad:e.cantidad }; });

    var sinNarrador = document.getElementById('sinNarradorCheck').checked ? 1 : 0;
    var inframundo = document.getElementById('inframundoCheck').checked ? 1 : 0;
    if (modoEdicionNarrador) sinNarrador = 0;
    var narrador_fid = (document.getElementById('narrador_fid').value||'');
    var narrador_nombre = (document.getElementById('narrador_nombre').value||'');
    var narrador_uid = (document.getElementById('narrador_uid').value||'');
    var c = document.getElementById('comentarioPublicoSolicitud');
    var comentarioPublico = c ? (c.value||'').trim() : '';

    var ratio = (difActual && typeof difActual.ratioPoder==='number') ? difActual.ratioPoder : 0;
    var detalles = (difActual && Array.isArray(difActual.detalles)) ? difActual.detalles : [];

    if (sinNarrador){ enemigosPayload = []; }

    return {
      tier: tierSel,
      descripcion_tier: info.descripcion,
      jugadores: jugadoresPayload,
      enemigos: enemigosPayload,
      nivel_promedio: nivelPromedio,
      num_jugadores: numJug,
      dificultad_texto: difTxt,
      dificultad_color: difColor,
      ratio_poder: ratio,
      detalles: detalles,
      sin_narrador: !!sinNarrador,
      inframundo: !!inframundo,
      narrador_fid: narrador_fid ? parseInt(narrador_fid,10) : 0,
      narrador_nombre: narrador_nombre,
      narrador_uid: narrador_uid ? parseInt(narrador_uid,10) : 0,
      public_comment: comentarioPublico
    };
  }
  function enviarPeticion(){
    if (document.getElementById('btnPeticion').disabled){
      alert('No cumples los requisitos para enviar la petición.'); return;
    }
    var sinNarradorActivo = document.getElementById('sinNarradorCheck').checked;
    if (jugadores.length===0){ alert('Debes añadir al menos 1 jugador antes de enviar.'); return; }
    
    // Validación de límites de aventuras activas (solo si no es modo edición)
    // Sistema: 5 aventuras base + 1 adicional si tiene al menos 1 grupal activa
    if (!modoEdicionNarrador){
      var numJugadores = jugadores.length;
      var esGrupo = numJugadores >= 2;
      
      // Límite absoluto: 6 aventuras (nunca puede superarse)
      if (aventurasIndividualesActivas >= 6){
        alert('Has alcanzado el máximo absoluto de 6 aventuras activas. Finaliza alguna antes de solicitar otra.');
        return;
      }
      
      // Si tiene 5 aventuras
      if (aventurasIndividualesActivas >= 5){
        // Si no tiene ninguna grupal, solo puede agregar una grupal para desbloquear el slot
        if (aventurasGrupoActivas === 0){
          if (!esGrupo){
            alert('Tienes 5 aventuras activas pero ninguna es grupal. Solo puedes agregar una aventura GRUPAL (2+ jugadores) para desbloquear el slot adicional.');
            return;
          }
          // Si es grupal, puede agregarla para desbloquear el sexto slot
        }
        // Si ya tiene al menos 1 grupal, el slot adicional está desbloqueado y puede agregar cualquier aventura
      }
      // Si tiene menos de 5, puede agregar cualquier tipo de aventura
    }
    
    if (modoEdicionNarrador){
      if (enemigos.length===0){ alert('Debes registrar al menos un enemigo antes de guardar los cambios.'); return; }
    } else if (!sinNarradorActivo && enemigos.length===0){
      alert('Debes añadir al menos 1 enemigo antes de enviar.'); return;
    }

    var payload = construirPayloadCalculo();
    console.log('PAYLOAD COMPLETO:', JSON.stringify(payload, null, 2));
    if (modoEdicionNarrador){
      if (!window.confirm('¿Deseas guardar los cambios de la solicitud?')) {
        return;
      }
      payload.peticion_id = peticionEnEdicionId;
      fetch('Calculadora_Tiers.php?action=update_enemigos',{
        method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify(payload)
      }).then(function(r){return r.json();}).then(function(data){
        if (data && data.ok){
          alert(data.message || 'Cambios guardados');
		  debugger;
          w.location.href = 'Calculadora_Tiers.php';
        }
        else { throw new Error((data&&data.error)||'No se pudo guardar la actualización'); }
      }).catch(function(err){ alert('Error al guardar cambios: '+err.message); });
      return;
    }

    fetch('Calculadora_Tiers.php?action=save', {
      method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify(payload)
    }).then(function(r){return r.json();}).then(function(data){
      if (data && data.ok){
        alert(data.message);
		debugger;
        w.location.href = 'Calculadora_Tiers.php';
      }
      else { alert('No se pudo guardar: '+ ((data&&data.error)?data.error:'error desconocido')); }
    }).catch(function(err){ alert('Error de red o formato: '+err); });
  }

  // --- Autocompletado Narrador (Solicitud) ---
  var narradorTimeout = null;
  var jugadorTimeout = null;
  function pintarResultadosNarrador(items){
    var cont = document.getElementById('resultadosNarrador');
    if (!cont) return;
    if (!items || items.length===0){ cont.style.display='none'; cont.innerHTML=''; return; }
    var html = '';
    for (var i=0;i<items.length;i++){
      var it = items[i];
      var titulo = it.nombre;
      if (it.apodo && it.apodo !== '') titulo += ' ('+it.apodo+')';
      html += '<div style="padding:8px 10px; cursor:pointer; color:#fff; border-bottom:1px solid rgba(255,255,255,0.08);" '+
              'onclick="seleccionarNarrador('+it.fid+', \''+titulo.replace(/'/g,"\\'")+'\')">'+OPG.escapeHtml(titulo)+'</div>';
    }
    cont.innerHTML = html; cont.style.display='block';
  }
  function buscarNarradores(q){
    if (!q || q.length<2){ pintarResultadosNarrador([]); return; }
    var xhr = new XMLHttpRequest();
    xhr.open('GET','Calculadora_Tiers.php?action=search_fichas&q='+encodeURIComponent(q), true);
    xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
    xhr.onreadystatechange = function(){
      if (xhr.readyState===4){
        try{
          var resp = JSON.parse(xhr.responseText);
          if (resp && resp.ok){ pintarResultadosNarrador(resp.items||[]); }
          else { pintarResultadosNarrador([]); }
        } catch(e){ pintarResultadosNarrador([]); }
      }
    };
    xhr.send();
  }

  function seleccionarNarrador(fid, titulo){
    if (modoEdicionNarrador) return;
    var hf = document.getElementById('narrador_fid'); if (hf) hf.value = String(fid||0);
    var hn = document.getElementById('narrador_nombre'); if (hn) hn.value = titulo || '';
    var hu = document.getElementById('narrador_uid'); if (hu) hu.value = String(fid||0); // asumes fid==uid
    var chip = document.getElementById('narradorSeleccionado');
    if (chip){
        chip.textContent = 'Seleccionado: ' + titulo + ' · fid ' + String(fid||0);
        chip.style.display = 'inline-block';
    }
    var box = document.getElementById('resultadosNarrador'); if (box){ box.style.display='none'; box.innerHTML=''; }
    validarPermisoPeticion();
  }

  // --- Autocompletado Jugadores ---
  function pintarResultadosJugadores(items){
    var cont = document.getElementById('resultadosJugadores');
    if (!cont) return;
    if (!items || !items.length){ cont.style.display='none'; cont.innerHTML=''; return; }
    var html = items.map(function(it){
      var titulo = it.nombre;
      if (it.apodo && it.apodo !== ''){ titulo += ' ('+it.apodo+')'; }
      var safe = titulo.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n').replace(/\r/g, '\\r');
      var fid = parseInt(it.fid,10) || 0;
      return '<div style="padding:8px 10px; cursor:pointer; color:#fff; border-bottom:1px solid rgba(255,255,255,0.08);" onclick="seleccionarJugadorBusqueda('+fid+', \''+safe+'\')">'+OPG.escapeHtml(titulo)+'</div>';
    }).join('');
    cont.innerHTML = html; cont.style.display='block';
  }
  function buscarJugadores(q){
    if (!q || q.length<2){ pintarResultadosJugadores([]); return; }
    fetch('Calculadora_Tiers.php?action=search_fichas&q='+encodeURIComponent(q), { method:'GET', headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(function(r){ return r.json(); })
      .then(function(data){ if (data && data.ok){ pintarResultadosJugadores(data.items||[]); } else { pintarResultadosJugadores([]); } })
      .catch(function(){ pintarResultadosJugadores([]); });
  }
  function seleccionarJugadorBusqueda(fid, nombre){
    var input = document.getElementById('nombreJugador');
    if (input){
      input.value = nombre || '';
      var valor = (fid && fid>0) ? String(fid) : '';
      if (input.dataset){ input.dataset.fichaUid = valor; }
      if (valor){ input.setAttribute('data-ficha-uid', valor); }
      else { input.removeAttribute('data-ficha-uid'); }
      jugadorSeleccionadoUid = fid && fid>0 ? fid : null;
      jugadorSeleccionadoNombre = nombre || '';
      input.focus();
    }
    pintarResultadosJugadores([]);
  }

  // --- Validación botón PETICIÓN ---
  function validarPermisoPeticion(){
    var btn = document.getElementById('btnPeticion'); if (!btn) return;
    var sinCheck = document.getElementById('sinNarradorCheck').checked;
    var fid = parseInt(document.getElementById('narrador_fid').value||'0',10);
    var uid = parseInt(document.getElementById('narrador_uid').value||'0',10);
    var habilitado = false;
    if (modoEdicionNarrador) habilitado = true;
    else if (sinCheck) habilitado = true;
    else if (fid>0 && uid>0 && fid===uid) habilitado = true;
    btn.disabled = !habilitado;
    btn.style.cursor = habilitado ? 'pointer':'not-allowed';
    btn.style.opacity = habilitado ? '1' : '0.6';
  }
  function toggleSinNarrador(){
    if (modoEdicionNarrador){
      var c = document.getElementById('sinNarradorCheck'); if (c){ c.checked=false; }
      return;
    }
    var checked = document.getElementById('sinNarradorCheck').checked;
    // si quieres limpiar selección al marcar, hazlo aquí
    validarPermisoPeticion();
  }

  // --- Init ---
  document.addEventListener('DOMContentLoaded', function(){
	// Al iniciar, ajustar estado del botón
	actualizarEstadoBotonAgregarJugador();
	
	// Obtener contadores de aventuras activas
	actualizarContadoresAventurasDesdeServidor();

	// Reaccionar a cambios del input para (des)habilitar el botón
	var inputJugador = document.getElementById('nombreJugador');
	if (inputJugador){
	  inputJugador.addEventListener('input', actualizarEstadoBotonAgregarJugador);
	  inputJugador.addEventListener('focus', actualizarEstadoBotonAgregarJugador);
	}

	// Cuando se selecciona desde el autocompletado, vuelve a habilitar el botón
	var _origSelJugadorBusqueda = seleccionarJugadorBusqueda;
	seleccionarJugadorBusqueda = function(fid, nombre){
	  _origSelJugadorBusqueda(fid, nombre);
	  actualizarEstadoBotonAgregarJugador();
	};

    // Enter para añadir
    var bindEnter = function(id, fn){ var el = document.getElementById(id); if (!el) return; el.addEventListener('keypress', function(e){ if (e.key==='Enter') fn(); }); };
    bindEnter('nombreJugador', agregarJugador);
    bindEnter('nivelJugador', agregarJugador);
    bindEnter('nombreEnemigo', agregarEnemigo);
    bindEnter('nivelEnemigo', agregarEnemigo);
    bindEnter('cantidadEnemigo', agregarEnemigo);

    actualizarListaJugadores();
    actualizarListaEnemigos();
    calcularEncuentro();
    bloquearCamposFueraDeAlcance();

    if (OPG.shareId){ cargarPeticionParaEdicion(OPG.shareId); }
    else {
      // Si la pestaña Registro está activa de inicio, registro.js se encarga de cargar.
    }

    // buscador narrador (input live)
    var busc = document.getElementById('buscadorNarrador');
    if (busc){
      busc.addEventListener('input', OPG.utils.debounce(function(){ buscarNarradores(busc.value||''); }, 220));
    }

    var inputJugador = document.getElementById('nombreJugador');
    if (inputJugador){
      inputJugador.addEventListener('input', function(){
        var val = inputJugador.value || '';
        if (inputJugador.dataset){ inputJugador.dataset.fichaUid=''; }
        inputJugador.removeAttribute('data-ficha-uid');
        if (val !== jugadorSeleccionadoNombre){
          jugadorSeleccionadoUid = null;
          jugadorSeleccionadoNombre = '';
        }
        if (jugadorTimeout) clearTimeout(jugadorTimeout);
        jugadorTimeout = setTimeout(function(){ buscarJugadores(val); }, 220);
      });
      inputJugador.addEventListener('focus', function(){
        var val = inputJugador.value || '';
        if (val.trim().length>=2){ buscarJugadores(val); }
      });
    }
    document.addEventListener('click', function(e){
      if (!e.target.closest('#resultadosJugadores') && e.target.id !== 'nombreJugador'){
        var cont = document.getElementById('resultadosJugadores');
        if (cont){ cont.style.display='none'; cont.innerHTML=''; }
      }
    });
  });

  // --- Exponer funciones globales usadas por onclicks del HTML ---
  w.agregarJugador = agregarJugador;
  w.eliminarJugador = eliminarJugador;
  w.agregarEnemigo  = agregarEnemigo;
  w.eliminarEnemigo = eliminarEnemigo;
  w.calcularEncuentro = calcularEncuentro;
  w.limpiarCalculadora = limpiarCalculadora;
  w.enviarPeticion = enviarPeticion;
  w.toggleSinNarrador = toggleSinNarrador;
  w.seleccionarNarrador = seleccionarNarrador;
  w.seleccionarJugadorBusqueda = seleccionarJugadorBusqueda;

})(OPG, window);
