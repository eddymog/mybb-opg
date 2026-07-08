/**
 * ficha/belicas.js — Disciplinas bélicas y estilos de combate
 * Requiere: core.js
 */
(function (FICHA, w) {

  // ── Estilos ──────────────────────────────────────────────────

  function openEstilosModal(estiloSlot) {
    console.log('Abriendo modal para slot:', estiloSlot);
    var existing = document.getElementById('estilosModal');
    if (existing) existing.remove();
    var modal = createEstilosModal(estiloSlot);
    document.body.appendChild(modal);
    modal.style.display = 'block';
    modal.onclick = function (event) {
      if (event.target === modal) closeEstilosModal();
    };
  }

  function createEstilosModal(estiloSlot) {
    var modal = document.createElement('div');
    modal.id = 'estilosModal';
    modal.className = 'modal';
    modal.style.cssText = 'position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); display: none;';

    var modalContent = document.createElement('div');
    modalContent.className = 'estilos-modal-content';

    var titulo = document.createElement('h2');
    titulo.style.cssText = 'text-align: center; margin-bottom: 20px; font-family: moonGetHeavy; color: #333; font-size: 24px;';
    titulo.textContent = 'Seleccionar Estilo de Combate';

    var closeBtn = document.createElement('span');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cssText = 'position: absolute; top: 10px; right: 25px; font-size: 35px; font-weight: bold; color: #999; cursor: pointer; transition: color 0.3s;';
    closeBtn.onclick = closeEstilosModal;
    closeBtn.onmouseover = function () { this.style.color = '#000'; };
    closeBtn.onmouseout  = function () { this.style.color = '#999'; };

    var estilosGrid = document.createElement('div');
    estilosGrid.className = 'estilos-grid';

    var estilosAprendidos = [estilo1, estilo2, estilo3, estilo4].filter(function (e) { return e && e !== ''; });
    var isCipherPol    = faccion == 'CipherPol';
    var isRevolucionario = faccion == 'Revolucionario';
    var isGyojin = raza == 'Gyojin' || raza == 'Ningyo' || raza == 'Woko' || raza == 'Hafugyo' || raza == 'Wotan';

    var estilosData = [
      { id: 'Gunkata',            nombre: 'Gunkata',            imagen: '/images/op/uploads/FichaGunkata_One_Piece_Gaiden_Foro_Rol.webp',            aprendido: estilosAprendidos.includes('Gunkata'),            disponible: true,           tipoColor: '#ff4444' },
      { id: 'Hasshoken',          nombre: 'Hasshoken',          imagen: '/images/op/uploads/FichaHasshoken_One_Piece_Gaiden_Foro_Rol.webp',          aprendido: estilosAprendidos.includes('Hasshoken'),          disponible: true,           tipoColor: '#ff4444' },
      { id: 'Santoryu',           nombre: 'Santoryu',           imagen: '/images/op/uploads/FichaSantoryu_One_Piece_Gaiden_Foro_Rol.webp',           aprendido: estilosAprendidos.includes('Santoryu'),           disponible: true,           tipoColor: '#44ff44' },
      { id: 'Kuroashi',           nombre: 'Kuroashi',           imagen: '/images/op/uploads/FichaKuroashi_One_Piece_Gaiden_Foro_Rol.webp',           aprendido: estilosAprendidos.includes('Kuroashi'),           disponible: true,           tipoColor: '#ff4444' },
      { id: 'Gyojin Karate',      nombre: 'Gyojin Karate',      imagen: '/images/op/uploads/FichaGyojin Karate_One_Piece_Gaiden_Foro_Rol.webp',      aprendido: estilosAprendidos.includes('Gyojin Karate'),      disponible: isGyojin,       tipoColor: '#ff4444' },
      { id: 'Gyojin Bukijutsu',   nombre: 'Gyojin Bukijutsu',   imagen: '/images/op/uploads/FichaGyojin Bukijutsu_One_Piece_Gaiden_Foro_Rol.webp',   aprendido: estilosAprendidos.includes('Gyojin Bukijutsu'),   disponible: isGyojin,       tipoColor: '#ff4444' },
      { id: 'Gyojin Jujutsu',     nombre: 'Gyojin Jujutsu',     imagen: '/images/op/uploads/FichaGyojin Jujutsu_One_Piece_Gaiden_Foro_Rol.webp',     aprendido: estilosAprendidos.includes('Gyojin Jujutsu'),     disponible: isGyojin,       tipoColor: '#ff4444' },
      { id: 'Rokushiki',          nombre: 'Rokushiki',          imagen: '/images/op/uploads/FichaRokushiki_One_Piece_Gaiden_Foro_Rol.webp',          aprendido: estilosAprendidos.includes('Rokushiki'),          disponible: isCipherPol,    tipoColor: '#ff4444' },
      { id: 'Okama Kempo',        nombre: 'Okama Kempo',        imagen: '/images/op/uploads/FichaOkama Kempo_One_Piece_Gaiden_Foro_Rol.webp',        aprendido: estilosAprendidos.includes('Okama Kempo'),        disponible: true,           tipoColor: '#ff4444' },
      { id: 'Sora Yokujin',       nombre: 'Sora Yokujin',       imagen: '/images/op/uploads/FichaSora Yokujin_One_Piece_Gaiden_Foro_Rol.webp',       aprendido: estilosAprendidos.includes('Sora Yokujin'),       disponible: true,           tipoColor: '#ff4444' },
      { id: 'Ninjutsu',           nombre: 'Ninjutsu',           imagen: '/images/op/uploads/FichaNinjutsu_One_Piece_Gaiden_Foro_Rol.webp',           aprendido: estilosAprendidos.includes('Ninjutsu'),           disponible: true,           tipoColor: '#ff4444' },
      { id: 'Ryusoken',           nombre: 'Ryusoken',           imagen: '/images/op/uploads/FichaRyusoken_One_Piece_Gaiden_Foro_Rol.webp',           aprendido: estilosAprendidos.includes('Ryusoken'),           disponible: true,           tipoColor: '#ff4444' },
      { id: 'Jiyuumura Kempo',    nombre: 'Jiyuumura Kempo',    imagen: '/images/op/uploads/FichaJiyuumura Kempo_One_Piece_Gaiden_Foro_Rol.webp',    aprendido: estilosAprendidos.includes('Jiyuumura Kempo'),    disponible: isRevolucionario, tipoColor: '#ff4444' },
      { id: 'Pop Green',          nombre: 'Pop Green',          imagen: '/images/op/uploads/FichaPop Green_One_Piece_Gaiden_Foro_Rol.webp',          aprendido: estilosAprendidos.includes('Pop Green'),          disponible: true,           tipoColor: '#ff4444' },
      { id: 'Clima Tact',         nombre: 'Clima Tact',         imagen: '/images/op/uploads/FichaClima Tact_One_Piece_Gaiden_Foro_Rol.webp',         aprendido: estilosAprendidos.includes('Clima Tact'),         disponible: true,           tipoColor: '#ff4444' },
      { id: 'Funekiri',           nombre: 'Funekiri',           imagen: '/images/op/uploads/FichaFunekiri_One_Piece_Gaiden_Foro_Rol.webp',           aprendido: estilosAprendidos.includes('Funekiri'),           disponible: true,           tipoColor: '#ff4444' },
      { id: 'Hakai Shin',         nombre: 'Hakai Shin',         imagen: '/images/op/uploads/FichaHakai Shin_One_Piece_Gaiden_Foro_Rol.webp',         aprendido: estilosAprendidos.includes('Hakai Shin'),         disponible: true,           tipoColor: '#ff4444' },
      { id: 'Railgun Style',      nombre: 'Railgun Style',      imagen: '/images/op/uploads/FichaRailgun Style_One_Piece_Gaiden_Foro_Rol.webp',      aprendido: estilosAprendidos.includes('Railgun Style'),      disponible: true,           tipoColor: '#ff4444' },
      { id: 'Shuron Hakke',       nombre: 'Shuron Hakke',       imagen: '/images/op/uploads/FichaShuron Hakke_One_Piece_Gaiden_Foro_Rol.webp',       aprendido: estilosAprendidos.includes('Shuron Hakke'),       disponible: true,           tipoColor: '#ff4444' },
      { id: 'Raqisat Alsahra',    nombre: 'Raqisat Alsahra',    imagen: '/images/op/uploads/FichaRaqisat Alsahra_One_Piece_Gaiden_Foro_Rol.webp',    aprendido: estilosAprendidos.includes('Raqisat Alsahra'),    disponible: true,           tipoColor: '#ff4444' },
      { id: 'Breeskjold',         nombre: 'Breeskjold',         imagen: '/images/op/uploads/FichaBreeskjold_One_Piece_Gaiden_Foro_Rol.webp',         aprendido: estilosAprendidos.includes('Breeskjold'),         disponible: true,           tipoColor: '#ff4444' },
      { id: 'Impacto Explosivo',  nombre: 'Impacto Explosivo',  imagen: '/images/op/uploads/FichaImpacto Explosivo_One_Piece_Gaiden_Foro_Rol.webp',  aprendido: estilosAprendidos.includes('Impacto Explosivo'),  disponible: true,           tipoColor: '#ff4444' },
      { id: 'Royal Guard',        nombre: 'Royal Guard',        imagen: '/images/op/uploads/FichaRoyal Guard_One_Piece_Gaiden_Foro_Rol.webp',        aprendido: estilosAprendidos.includes('Royal Guard'),        disponible: true,           tipoColor: '#ff4444' },
      { id: 'Shikaku Teikoku',    nombre: 'Shikaku Teikoku',    imagen: '/images/op/uploads/FichaShikaku Teikoku_One_Piece_Gaiden_Foro_Rol.webp',    aprendido: estilosAprendidos.includes('Shikaku Teikoku'),    disponible: true,           tipoColor: '#ff4444' },
      { id: 'Duelliste de Givre', nombre: 'Duelliste de Givre', imagen: '/images/op/uploads/FichaDuelliste de Givre_One_Piece_Gaiden_Foro_Rol.webp', aprendido: estilosAprendidos.includes('Duelliste de Givre'), disponible: true,           tipoColor: '#ff4444' },
      { id: 'Filo Della Vita',    nombre: 'Filo Della Vita',    imagen: '/images/op/uploads/FichaFilo Della Vita_One_Piece_Gaiden_Foro_Rol.webp',    aprendido: estilosAprendidos.includes('Filo Della Vita'),    disponible: true,           tipoColor: '#ff4444' },
      { id: 'Havets Symfoni',     nombre: 'Havets Symfoni',     imagen: '/images/op/uploads/FichaHavets Symfoni_One_Piece_Gaiden_Foro_Rol.webp',     aprendido: estilosAprendidos.includes('Havets Symfoni'),     disponible: true,           tipoColor: '#ff4444' },
      { id: 'Bakudai Karin',      nombre: 'Bakudai Karin',      imagen: '/images/op/uploads/FichaBakudai Karin_One_Piece_Gaiden_Foro_Rol.webp',      aprendido: estilosAprendidos.includes('Bakudai Karin'),      disponible: true,           tipoColor: '#ff4444' },
      { id: 'Yama Kurai',         nombre: 'Yama Kurai',         imagen: '/images/op/uploads/FichaYama Kurai_One_Piece_Gaiden_Foro_Rol.webp',         aprendido: estilosAprendidos.includes('Yama Kurai'),         disponible: true,           tipoColor: '#ff4444' },
      { id: 'Shiseiju',           nombre: 'Shiseiju',           imagen: '/images/op/uploads/FichaShiseiju_One_Piece_Gaiden_Foro_Rol.webp',           aprendido: estilosAprendidos.includes('Shiseiju'),           disponible: true,           tipoColor: '#ff4444' },
      { id: 'Sea Corsair',        nombre: 'Sea Corsair',        imagen: '/images/op/uploads/FichaSea Corsair_One_Piece_Gaiden_Foro_Rol.webp',        aprendido: estilosAprendidos.includes('Sea Corsair'),        disponible: true,           tipoColor: '#ff4444' },
      { id: 'Wano Nitoryu',       nombre: 'Wano Nitoryu',       imagen: '/images/op/uploads/FichaWano Nitoryu_One_Piece_Gaiden_Foro_Rol.webp',       aprendido: estilosAprendidos.includes('Wano Nitoryu'),       disponible: true,           tipoColor: '#ff4444' },
      { id: 'Mano de Tahur',      nombre: 'Mano de Tahur',      imagen: '/images/op/uploads/FichaMano de Tahur_One_Piece_Gaiden_Foro_Rol.webp',      aprendido: estilosAprendidos.includes('Mano de Tahur'),      disponible: true,           tipoColor: '#ff4444' },
      { id: 'Kokudan',            nombre: 'Kokudan',            imagen: '/images/op/uploads/FichaKokudan_One_Piece_Gaiden_Foro_Rol.webp',            aprendido: estilosAprendidos.includes('Kokudan'),            disponible: true,           tipoColor: '#ff4444' },
      { id: 'Kodai no Bushido',   nombre: 'Kodai no Bushido',   imagen: '/images/op/uploads/FichaKodai no Bushido_One_Piece_Gaiden_Foro_Rol.webp',   aprendido: estilosAprendidos.includes('Kodai no Bushido'),   disponible: true,           tipoColor: '#ff4444' },
      { id: 'Ittoryu Sekai',      nombre: 'Ittoryu Sekai',      imagen: '/images/op/uploads/FichaIttoryu Sekai_One_Piece_Gaiden_Foro_Rol.webp',      aprendido: estilosAprendidos.includes('Ittoryu Sekai'),      disponible: true,           tipoColor: '#ff4444' },
      { id: 'Global Performer',   nombre: 'Global Performer',   imagen: '/images/op/uploads/FichaGlobal Performer_One_Piece_Gaiden_Foro_Rol.webp',   aprendido: estilosAprendidos.includes('Global Performer'),   disponible: true,           tipoColor: '#ff4444' },
      { id: 'Cavalry Warrior',    nombre: 'Cavalry Warrior',    imagen: '/images/op/uploads/FichaCavalry Warrior_One_Piece_Gaiden_Foro_Rol.webp',    aprendido: estilosAprendidos.includes('Cavalry Warrior'),    disponible: true,           tipoColor: '#ff4444' },
      { id: 'Ashigara Dokoi',     nombre: 'Ashigara Dokoi',     imagen: '/images/op/uploads/FichaAshigara Dokoi_One_Piece_Gaiden_Foro_Rol.webp',     aprendido: estilosAprendidos.includes('Ashigara Dokoi'),     disponible: true,           tipoColor: '#ff4444' },
      { id: 'Kanpo Kenpo',        nombre: 'Kanpo Kenpo',        imagen: '/images/op/uploads/FichaKanpo Kenpo_One_Piece_Gaiden_Foro_Rol.webp',        aprendido: estilosAprendidos.includes('Kanpo Kenpo'),        disponible: true,           tipoColor: '#ff4444' }
    ];

    for (var i = 0; i < estilosData.length; i++) {
      estilosGrid.appendChild(createEstiloCard(estilosData[i], estiloSlot));
    }

    modalContent.appendChild(closeBtn);
    modalContent.appendChild(titulo);
    modalContent.appendChild(estilosGrid);
    modal.appendChild(modalContent);
    return modal;
  }

  function createEstiloCard(estilo, estiloSlot) {
    var card = document.createElement('div');
    card.className = 'estilo-card';
    if (!estilo.disponible || estilo.aprendido) card.classList.add('estilo-disabled');

    var imagenContainer = document.createElement('div');
    imagenContainer.className = 'estilo-imagen-container';
    var imagen = document.createElement('img');
    imagen.src = estilo.imagen;
    imagen.alt = estilo.nombre;
    imagen.className = 'estilo-imagen';
    imagen.style.cssText = 'width: 100%; height: 100%; object-fit: cover; object-position: center;';
    imagenContainer.appendChild(imagen);

    var info = document.createElement('div');
    info.className = 'estilo-info';

    if (estilo.aprendido) {
      var yaAprendido = document.createElement('div');
      yaAprendido.className = 'estilo-ya-aprendido';
      yaAprendido.textContent = 'Ya Aprendido';
      card.appendChild(yaAprendido);
    }

    if (estilo.disponible && !estilo.aprendido) {
      card.style.cursor = 'pointer';
      card.onclick = (function (id, slot) {
        return function () { seleccionarEstilo(id, slot); };
      })(estilo.id, estiloSlot);
    }

    card.appendChild(imagenContainer);
    card.appendChild(info);
    return card;
  }

  function seleccionarEstilo(estiloId, estiloSlot) {
    console.log('Seleccionado estilo ' + estiloId + ' para slot ' + estiloSlot);
    if (confirm('¿Estás seguro de que quieres aprender el estilo ' + estiloId + '?')) {
      FICHA.PendingQueue.addPersonaje({ accion: 'estilo', estilo: estiloId, slot: estiloSlot });
      w[estiloSlot] = estiloId;
      $('#' + estiloSlot).html(getEstiloHtml(estiloId));
      closeEstilosModal();
    }
  }

  function closeEstilosModal() {
    var modal = document.getElementById('estilosModal');
    if (modal) {
      modal.style.display = 'none';
      setTimeout(function () { if (modal.parentNode) modal.parentNode.removeChild(modal); }, 300);
    }
  }

  function cargarEstilosDisponibles() { /* stub — reservado para carga AJAX */ }

  function _desbloquearEstiloSlot(num, accion, costo) {
    FICHA.PendingQueue.addPersonaje({ accion: accion });
    FICHA.PendingQueue.reserve({ nikas: costo || 0 });
    var slotKey = 'estilo' + num;
    w[slotKey] = 'no_bloqueado';
    $('#' + slotKey).html(getNoBloqueadoHtml(num));
  }

  function openModalDesbloquearEstilo1() {
    if (nivel >= 8 && estilo1 == 'bloqueado') {
      if (confirm('Puedes desbloquear el primer slot de estilo. Será gratuito. ¿Estás de acuerdo?')) {
        _desbloquearEstiloSlot(1, 'estilo1_desbloquear', 0);
      }
    } else {
      alert('Aún no puedes desbloquear el primer estilo.\nDebes ser nivel 8 mínimo.');
    }
  }

  function openModalDesbloquearEstilo2() {
    if (nivel >= 16 && FICHA.PendingQueue.availableNikas() >= 25 && estilo2 == 'bloqueado' && estilo1 != 'bloqueado') {
      if (confirm('Puedes desbloquear el segundo slot de estilo. Deberás invertir 25 nikas. ¿Estás de acuerdo?')) {
        _desbloquearEstiloSlot(2, 'estilo2_desbloquear', 25);
      }
    } else {
      alert('Aún no puedes desbloquear el segundo estilo.\nDebes ser nivel 16 y tener más de 25 nikas.');
    }
  }

  function openModalDesbloquearEstilo3() {
    if (nivel >= 25 && FICHA.PendingQueue.availableNikas() >= 50 && estilo3 == 'bloqueado' && estilo2 != 'bloqueado') {
      if (confirm('Puedes desbloquear el tercer slot de estilo. Deberás invertir 50 nikas. ¿Estás de acuerdo?')) {
        _desbloquearEstiloSlot(3, 'estilo3_desbloquear', 50);
      }
    } else {
      alert('Aún no puedes desbloquear el tercer estilo.\nDebes ser nivel 25 y tener más de 50 nikas.');
    }
  }

  function openModalDesbloquearEstilo4() {
    if (nivel >= 35 && FICHA.PendingQueue.availableNikas() >= 75 && estilo4 == 'bloqueado' && estilo3 != 'bloqueado') {
      if (confirm('Puedes desbloquear el cuarto slot de estilo. Deberás invertir 75 nikas. ¿Estás de acuerdo?')) {
        _desbloquearEstiloSlot(4, 'estilo4_desbloquear', 75);
      }
    } else {
      alert('Aún no puedes desbloquear el cuarto estilo.\nDebes ser nivel 35 y tener más de 75 nikas.');
    }
  }

  function getNoBloqueadoHtml(estiloNumero) {
    if (is_owner) {
      return `<img style="width: 260px; height: 125px; border-radius: 8px; position: relative; overflow: hidden;cursor:pointer;" onclick="openEstilosModal('estilo` + estiloNumero + `');" src="/images/op/uploads/FichaSlotLibre_One_Piece_Gaiden_Foro_Rol.webp" />`;
    }
    return `<img style="width: 260px; height: 125px; border-radius: 8px; position: relative; overflow: hidden;" src="/images/op/uploads/FichaSlotLibre_One_Piece_Gaiden_Foro_Rol.webp" />`;
  }

  function getBloqueadoHtml(estiloNumero) {
    if (is_owner) {
      return `<img style="width: 260px; height: 125px; border-radius: 8px; position: relative; overflow: hidden;cursor:pointer;" onclick="openModalDesbloquearEstilo` + estiloNumero + `();" src="/images/op/uploads/FichaSlotBloqueado_One_Piece_Gaiden_Foro_Rol.webp" />`;
    }
    return `<img style="width: 260px; height: 125px; border-radius: 8px; position: relative; overflow: hidden;" src="/images/op/uploads/FichaSlotBloqueado_One_Piece_Gaiden_Foro_Rol.webp" />`;
  }

  function getEstiloHtml(estilo) {
    return `<img style="width: 260px; height: 125px; border-radius: 8px; position: relative; overflow: hidden;" src="/images/op/uploads/Ficha` + estilo + `_One_Piece_Gaiden_Foro_Rol.webp" />`;
  }

  function chooseEstilo(estilo) {
    if (confirm('¿Estás seguro que quieres seleccionar el estilo ' + estilo + '?')) {
      FICHA.PendingQueue.addPersonaje({ accion: 'estilo', estilo: estilo });
    }
  }

  // ── Haki ─────────────────────────────────────────────────────

  function subirHaki(haki, nikasCost) {
    FICHA.PendingQueue.addPersonaje({ accion: haki });
    FICHA.PendingQueue.reserve({ nikas: nikasCost || 0 });
    _previewHaki(haki);
  }

  // ── Disciplinas ───────────────────────────────────────────────

  function subirBelica(belica, belicaNumber, nikasCost) {
    FICHA.PendingQueue.addPersonaje({ accion: 'belica', belica: belica, belicaNumber: belicaNumber });
    FICHA.PendingQueue.reserve({ nikas: nikasCost || 0 });
    _previewBelica(belica, belicaNumber);
  }

  function subirCamino(espeNumber, belicaNumber, camino, nikasCost, previewNivel) {
    FICHA.PendingQueue.addPersonaje({ accion: 'belica_espe', espeNumber: espeNumber, belicaNumber: belicaNumber, espe: camino });
    FICHA.PendingQueue.reserve({ nikas: nikasCost || 0 });
    _previewCamino(camino, previewNivel);
  }

  // ── Preview (actualización inmediata de UI sin guardar) ────────────

  // Mapa de caminos por disciplina (mismos pares que el template PHP)
  var _CAMINOS = {
    'Escudero':        ['Vanguardia',  'Bastión'],
    'Artista Marcial': ['Acróbata',    'Monje'],
    'Combatiente':     ['Berserker',   'Campeón'],
    'Artista':         ['Bardo',       'Trovador'],
    'Asesino':         ['Sombra',      'Verdugo'],
    'Guerrero':        ['Castigador',  'Warhammer'],
    'Espadachín':      ['Samurái',     'Mosquetero'],
    'Tecnicista':      ['Diletante',   'WeaponMaster'],
    'Artillero':       ['Destructor',  'Juggernaut'],
    'Arquero':         ['Ballestero',  'Cazador'],
    'Tirador':         ['Duelista',    'Francotirador'],
    'Pícaro':          ['Gambito',     'Trickster']
  };

  function _previewHaki(haki) {
    if      (haki === 'buso')   { buso++;   $('#buso_nivel').html('Tier ' + (buso + 1) + ' de Poder');     $('#buso_img').css('filter', 'grayscale(0)'); }
    else if (haki === 'kenbun') { kenbun++; $('#kenbun_nivel').html('Tier ' + (kenbun + 1) + ' de Poder'); $('#kenbun_img').css('filter', 'grayscale(0)'); }
    else if (haki === 'hao')    { hao++;    $('#hao_nivel').html('Tier ' + (hao + 1) + ' de Poder');       $('#hao_img').css('filter', 'grayscale(0)'); }
  }

  function _previewBelica(belica, belicaNumber) {
    if (!belicas[belica]) {
      var sub = {};
      (_CAMINOS[belica] || []).forEach(function (c) { sub[c] = 0; });
      belicas[belica] = { nivel: 1, espe1: '', espe2: '', sub: sub };
    } else {
      belicas[belica].nivel++;
    }
    if (belicaNumber) w[belicaNumber] = belica;
    updateBelicaUI(belica);
  }

  function _previewCamino(camino, newNivel) {
    var id = normBelicaId(camino);
    var $body = $('#' + id + '_nivel_body');
    var $text = $('#' + id + '_nivel');
    if (newNivel === 1) {
      $body.css({ 'background-color': '#e1740a', 'cursor': 'pointer' });
      $text.html('C');
    } else if (newNivel >= 2) {
      $body.css({ 'background-color': '#28ce26', 'cursor': 'default' }).off('click');
      $text.html('E');
    }
  }

  function resolveBelica(belicaName) {
    return belicas[belicaName]
        || belicas[belicaName.replace(/ /g, '_')]
        || belicas[belicaName.replace(/_/g, ' ')];
  }

  function chooseCamino(belica, camino) {
    if (!is_owner) return;

    var belicaData = resolveBelica(belica);
    if (!belicaData) { alert(`Error: La disciplina ${belica} no es válida.`); return; }
    if (!belicaData.sub || !belicaData.sub.hasOwnProperty(camino)) { alert(`Error: El camino ${camino} no está disponible para la disciplina ${belica}.`); return; }
    if (typeof belicaData.sub[camino] !== 'number') { alert(`Error: El valor del camino ${camino} no es válido para la disciplina ${belica}.`); return; }
    // re-alias so remaining code can use belicas[belica]
    belicas[belica] = belicaData;

    var isBelica1  = belica1  == belica; var isBelica2  = belica2  == belica; var isBelica3  = belica3  == belica;
    var isBelica4  = belica4  == belica; var isBelica5  = belica5  == belica; var isBelica6  = belica6  == belica;
    var isBelica7  = belica7  == belica; var isBelica8  = belica8  == belica; var isBelica9  = belica9  == belica;
    var isBelica10 = belica10 == belica; var isBelica11 = belica11 == belica; var isBelica12 = belica12 == belica;

    var espesCount = 0;
    var isEspe1 = belicas[belica].espe1 == camino;
    var isEspe2 = belicas[belica].espe2 == camino;
    if (belicas[belica].espe1) espesCount++;
    if (belicas[belica].espe2) espesCount++;

    if (belicas[belica].sub[camino] == 1 && !isEspe1 && !isEspe2) { alert(`Error: La especialización ${camino} no está asignada correctamente en la disciplina ${belica}.`); return; }
    if (isEspe1 && belicas[belica].sub[belicas[belica].espe2] == 2) { alert(`No puedes tener más de una especialidad, y ya tienes especialidad en ${belicas[belica].espe2}.`); return; }
    else if (isEspe2 && belicas[belica].sub[belicas[belica].espe1] == 2) { alert(`No puedes tener más de una especialidad, y ya tienes especialidad en ${belicas[belica].espe1}.`); return; }

    var espeNivel = belicas[belica].sub[camino];
    var nikaReq = 0;

    if (espeNivel == 1) {
      if (nivel < 20) { alert(`No cumples el requisito mínimo de nivel 20 para aprender la especialidad de ` + camino + `.`); return; }

      if (isBelica1)  nikaReq = 45;  if (isBelica2)  nikaReq = 60;  if (isBelica3)  nikaReq = 75;
      if (isBelica4)  nikaReq = 100; if (isBelica5)  nikaReq = 125; if (isBelica6)  nikaReq = 150;
      if (isBelica7)  nikaReq = 175; if (isBelica8)  nikaReq = 200; if (isBelica9)  nikaReq = 225;
      if (isBelica10) nikaReq = 250; if (isBelica11) nikaReq = 275; if (isBelica12) nikaReq = 300;

      var espeSlot = isEspe1 ? 'espe1' : 'espe2';
      var _bk = isBelica1?'belica1':isBelica2?'belica2':isBelica3?'belica3':isBelica4?'belica4':isBelica5?'belica5':isBelica6?'belica6':isBelica7?'belica7':isBelica8?'belica8':isBelica9?'belica9':isBelica10?'belica10':isBelica11?'belica11':isBelica12?'belica12':null;
      if (!_bk) { alert(`Error: No se pudo determinar la disciplina.`); return; }
      if (FICHA.PendingQueue.availableNikas() >= nikaReq) {
        if (confirm(`Para aprender la especialización de ` + camino + ` tiene un costo de ` + nikaReq + ` nikas. ¿Estás de acuerdo?`)) {
          belicaData.sub[camino] = 2;
          subirCamino(espeSlot, _bk, camino, nikaReq, 2);
        }
      } else { alert(`No cumples los requisitos para aprender la especialización de ` + camino + `. Debes tener ` + nikaReq + ` nikas.`); }

    } else if (espeNivel == 0) {
      if (nivel < 8) { alert(`No cumples el requisito mínimo de nivel 8 para aprender el camino ` + camino + `.`); return; }

      var COSTES_BASE = {
        espe1: { belica1: 0,  belica2: 20,  belica3: 30,  belica4: 45,  belica5: 60,  belica6: 75,  belica7: 90,  belica8: 105, belica9: 120, belica10: 135, belica11: 150, belica12: 165 },
        espe2: { belica1: 15, belica2: 30,  belica3: 40,  belica4: 60,  belica5: 75,  belica6: 90,  belica7: 105, belica8: 120, belica9: 135, belica10: 150, belica11: 165, belica12: 180 }
      };

      nikaReq = 999;
      var belicaNum = 0;
      if (isBelica1) belicaNum=1; else if (isBelica2) belicaNum=2; else if (isBelica3) belicaNum=3;
      else if (isBelica4) belicaNum=4; else if (isBelica5) belicaNum=5; else if (isBelica6) belicaNum=6;
      else if (isBelica7) belicaNum=7; else if (isBelica8) belicaNum=8; else if (isBelica9) belicaNum=9;
      else if (isBelica10) belicaNum=10; else if (isBelica11) belicaNum=11; else if (isBelica12) belicaNum=12;

      if (belicaNum === 0) { alert(`Error: No se pudo determinar el número de disciplina.`); return; }
      if (espesCount === 0) nikaReq = COSTES_BASE.espe1[`belica${belicaNum}`];
      else if (espesCount === 1) nikaReq = COSTES_BASE.espe2[`belica${belicaNum}`];
      if (nikaReq === 999 || nikaReq === undefined) { alert(`Error: No se pudo determinar el coste para el camino ${camino} en la disciplina ${belica}.`); return; }

      var _espeLabel = espesCount === 0 ? 'primer' : 'segundo';
      var _espeSlot  = espesCount === 0 ? 'espe1'  : 'espe2';
      var _belicaKey = 'belica' + belicaNum;
      if (FICHA.PendingQueue.availableNikas() >= nikaReq) {
        if (confirm(`Para aprender el ${_espeLabel} camino ` + camino + ` tiene un costo de ` + nikaReq + ` nikas. ¿Estás de acuerdo?`)) {
          belicaData.sub[camino] = 1;
          belicaData[_espeSlot] = camino;
          subirCamino(_espeSlot, _belicaKey, camino, nikaReq, 1);
        }
      } else { alert(`No cumples los requisitos para aprender el camino ` + camino + `. Debes tener ` + nikaReq + ` nikas.`); }
    }
  }

  function chooseBelica(belica, hasBelica) {
    if (!is_owner) return;
    var COSTOS = [0, 10, 20, 35, 50, 65, 80, 95, 110, 125, 140, 155];
    var SLOTS  = ['', 'belica2', 'belica3', 'belica4', 'belica5', 'belica6', 'belica7', 'belica8', 'belica9', 'belica10', 'belica11', 'belica12'];
    var countBelicas = Object.keys(belicas).length;
    var nikaReq = COSTOS[countBelicas] !== undefined ? COSTOS[countBelicas] : -1;
    var slot    = SLOTS[countBelicas];
    if (nikaReq < 0 || !slot) { alert(`No cumples los requisitos para aprender más disciplinas.`); return; }
    if (FICHA.PendingQueue.availableNikas() < nikaReq) {
      alert(`No cumples los requisitos para aprender la disciplina ` + belica + `. Debes tener ` + nikaReq + ` nikas.`);
      return;
    }
    var msg = countBelicas === 1
      ? `Para aprender la disciplina ` + belica + ` tiene un costo de ` + nikaReq + ` nikas. ¿Estás de acuerdo?`
      : `Para aprender la nivel a la disciplina ` + belica + ` tiene un costo de ` + nikaReq + ` nikas. ¿Estás de acuerdo?`;
    if (confirm(msg)) { subirBelica(belica, slot, nikaReq); }
  }

  function normBelicaId(name) {
    return name.replace(/ /g, '_');
  }

  function updateBelicaUI(belicaName) {
    if (!belicaName || belicaName === '') { console.warn('Nombre de bélica vacío o undefined'); return; }
    if (!belicas || typeof belicas !== 'object') { console.error('El objeto belicas no está definido o es null'); return; }

    // Normalize: try exact match, then space→underscore, then underscore→space
    var belica = belicas[belicaName]
              || belicas[belicaName.replace(/ /g, '_')]
              || belicas[belicaName.replace(/_/g, ' ')];
    if (!belica) { console.warn(`Bélica '${belicaName}' no encontrada en el objeto belicas`); return; }

    var nivelBelica = belica.nivel;
    var idSafe = normBelicaId(belicaName);
    $(`#${idSafe}_img`).css('filter', 'grayscale(0)');
    var nivelBody = `#${idSafe}_nivel_body`;

    if (nivelBelica == 1) {
      $(nivelBody).css('cursor', 'auto').css('background-color', '#28ce26');
    } else {
      $(nivelBody).css('background-color', '#8d888f');
    }

    if (!belica.sub) return;
    var caminos = Object.keys(belica.sub);
    caminos.forEach(function (camino) {
      var caminoNivel     = belica.sub[camino];
      var caminoIdSafe    = normBelicaId(camino);
      var caminoNivelBody = `#${caminoIdSafe}_nivel_body`;
      var caminoNivelId   = `#${caminoIdSafe}_nivel`;

      if (caminoNivel == 2) {
        $(caminoNivelBody).css('background-color', '#28ce26');
        $(caminoNivelId).html('E');
      } else if (caminoNivel == 0 || caminoNivel == 1) {
        $(caminoNivelBody).css('cursor', 'pointer');
        if (caminoNivel == 0) $(caminoNivelBody).css('background-color', '#9d57b4');
        if (caminoNivel == 1) { $(caminoNivelBody).css('background-color', '#e1740a'); $(caminoNivelId).html('C'); }
        $(caminoNivelBody).off('click').on('click', (function (b, c) { return function () { chooseCamino(b, c); }; })(belicaName, camino));
      }
    });
  }

  // ── Exposición global ─────────────────────────────────────────

  w.openEstilosModal           = openEstilosModal;
  w.closeEstilosModal          = closeEstilosModal;
  w.seleccionarEstilo          = seleccionarEstilo;
  w.cargarEstilosDisponibles   = cargarEstilosDisponibles;
  w.openModalDesbloquearEstilo1 = openModalDesbloquearEstilo1;
  w.openModalDesbloquearEstilo2 = openModalDesbloquearEstilo2;
  w.openModalDesbloquearEstilo3 = openModalDesbloquearEstilo3;
  w.openModalDesbloquearEstilo4 = openModalDesbloquearEstilo4;
  w.getNoBloqueadoHtml         = getNoBloqueadoHtml;
  w.getBloqueadoHtml           = getBloqueadoHtml;
  w.getEstiloHtml              = getEstiloHtml;
  w.chooseEstilo               = chooseEstilo;
  w.subirHaki                  = subirHaki;
  w.subirBelica                = subirBelica;
  w.subirCamino                = subirCamino;
  w.chooseCamino               = chooseCamino;
  w.chooseBelica               = chooseBelica;
  w.updateBelicaUI             = updateBelicaUI;

})(window.FICHA, window);
