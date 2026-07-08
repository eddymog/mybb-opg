/**
 * ficha/tecnicas.js — Gestión y renderizado de técnicas
 * Requiere: core.js
 */
(function (FICHA, w) {

  // Obtener técnicas de forma segura
  function getTecnicas(key) {
    if (!tec_aprendidas_json) {
      console.error('Error: tec_aprendidas_json no está definido');
      return [];
    }
    if (!Object.prototype.hasOwnProperty.call(tec_aprendidas_json, key)) {
      console.log('No se encontraron técnicas para la clave: ' + key);
      return [];
    }
    return tec_aprendidas_json[key] || [];
  }

  // Fusionar técnicas de un camino en una disciplina
  function insertarDisciplinaTecs(disciplina, camino) {
    if (tec_aprendidas_json[disciplina]) {
      if (tec_aprendidas_json[camino]) {
        var tecCount = tec_aprendidas_json[camino].length;
        for (var i = 0; i < tecCount; i++) {
          tec_aprendidas_json[disciplina].push(tec_aprendidas_json[camino][i]);
        }
      }
    }
  }

  // Mostrar las técnicas del bloque seleccionado
  function showBlock(estilo, color) {
    $('#tecnicas-caja').html('');
    $('#tecnicas-background').css('background-color', color);

    sessionStorage.setItem('lastTecnicasTab', estilo);
    sessionStorage.setItem('lastTecnicasColor', color);

    var estiloKey = estilo.replace('_', ' ').trim();

    // Caso especial "todo"
    if (estiloKey === 'todo') {
      if (typeof tecnicas_html_json !== 'undefined' && tecnicas_html_json && tecnicas_html_json['todo']) {
        $('#tecnicas-caja').html(tecnicas_html_json['todo']);
        if (typeof TF !== 'undefined') { TF.initFiltros(); TF.aplicarFiltros(); }
        return;
      }
      if (tec_aprendidas_json && tec_aprendidas_json['todo']) {
        var todoTecs = tec_aprendidas_json['todo'];
        for (var i = 0; i < todoTecs.length; i++) crearTecnica(todoTecs[i]);
      }
      if (typeof TF !== 'undefined') { TF.initFiltros(); TF.aplicarFiltros(); }
      return;
    }

    // Caso especial "Haki"
    if (estiloKey === 'Haki') {
      if (typeof tecnicas_html_json !== 'undefined' && tecnicas_html_json && tecnicas_html_json['Haki']) {
        $('#tecnicas-caja').html(tecnicas_html_json['Haki']);
        if (typeof TF !== 'undefined') { TF.initFiltros(); TF.aplicarFiltros(); }
        return;
      }
      var hakiArray = ['Haoshoku', 'Kenbunshoku', 'Busoshoku'];
      var hayHaki = false;
      for (var k = 0; k < hakiArray.length; k++) {
        var tipoHaki = hakiArray[k];
        if (tec_aprendidas_json && tec_aprendidas_json[tipoHaki] && Array.isArray(tec_aprendidas_json[tipoHaki])) {
          var tecHaki = tec_aprendidas_json[tipoHaki];
          for (var j = 0; j < tecHaki.length; j++) { crearTecnica(tecHaki[j]); hayHaki = true; }
        }
      }
      if (!hayHaki) $('#tecnicas-caja').html('<div style="text-align: center; padding: 20px;">No hay técnicas de Haki aprendidas</div>');
      if (typeof TF !== 'undefined') { TF.initFiltros(); TF.aplicarFiltros(); }
      return;
    }

    // HTML pregenerado disponible
    if (typeof tecnicas_html_json !== 'undefined' && tecnicas_html_json && tecnicas_html_json[estiloKey]) {
      $('#tecnicas-caja').html(tecnicas_html_json[estiloKey]);
      if (typeof TF !== 'undefined') { TF.initFiltros(); TF.aplicarFiltros(); }
      return;
    }

    // Caso especial "Elementales"
    if (estiloKey === 'Elementales') {
      var elementosArr = ['Electro', 'Piro', 'Cryo', 'Aqua', 'Aero'];
      var htmlCombinado = '';
      var hayElem = false;
      for (var e = 0; e < elementosArr.length; e++) {
        var elem = elementosArr[e];
        if (typeof tecnicas_html_json !== 'undefined' && tecnicas_html_json && tecnicas_html_json[elem]) {
          htmlCombinado += tecnicas_html_json[elem]; hayElem = true;
        } else if (tec_aprendidas_json && tec_aprendidas_json[elem] && Array.isArray(tec_aprendidas_json[elem])) {
          var tecElem = tec_aprendidas_json[elem];
          for (var m = 0; m < tecElem.length; m++) { crearTecnica(tecElem[m]); hayElem = true; }
        }
      }
      if (htmlCombinado) { $('#tecnicas-caja').html(htmlCombinado); if (typeof TF !== 'undefined') { TF.initFiltros(); TF.aplicarFiltros(); } return; }
      if (!hayElem) $('#tecnicas-caja').html('<div style="text-align: center; padding: 20px;">No hay técnicas elementales aprendidas</div>');
      if (typeof TF !== 'undefined') { TF.initFiltros(); TF.aplicarFiltros(); }
      return;
    }

    // Fallback: buscar en tec_aprendidas_json
    if (!tec_aprendidas_json || !tec_aprendidas_json[estiloKey]) {
      var hayTecs = false;
      for (var rama in tec_aprendidas_json) {
        if (rama === 'todo') continue;
        if (tec_aprendidas_json[rama] && Array.isArray(tec_aprendidas_json[rama]) && tec_aprendidas_json[rama].length > 0) {
          if (tec_aprendidas_json[rama][0].estilo === estiloKey) {
            for (var r = 0; r < tec_aprendidas_json[rama].length; r++) { crearTecnica(tec_aprendidas_json[rama][r]); hayTecs = true; }
          }
        }
      }
      if (!hayTecs) {
        $('#tecnicas-caja').html('<div style="text-align: center; padding: 20px;">No hay técnicas disponibles para este estilo</div>');
        console.log('No se encontraron técnicas para el estilo:', estiloKey);
      }
      if (typeof TF !== 'undefined') { TF.initFiltros(); TF.aplicarFiltros(); }
      return;
    }

    var estiloTecs = tec_aprendidas_json[estiloKey];
    if (!Array.isArray(estiloTecs) || estiloTecs.length === 0) {
      $('#tecnicas-caja').html('<div style="text-align: center; padding: 20px;">No hay técnicas aprendidas en este estilo</div>');
      if (typeof TF !== 'undefined') { TF.initFiltros(); TF.aplicarFiltros(); }
      return;
    }
    for (var t = 0; t < estiloTecs.length; t++) crearTecnica(estiloTecs[t]);
    if (typeof TF !== 'undefined') { TF.initFiltros(); TF.aplicarFiltros(); }
  }

  // Crear y añadir el HTML de una técnica al DOM
  function crearTecnica(tecnica) {
    var requisitos = tecnica.requisitos ? (`<div class="tecnica_requisito">` + tecnica.requisitos + `</div>`) : ``;

    var tecnicaEnergia = '';
    var tecnicaEnergiaTurno = '';
    var tecnicaHaki = '';
    var tecnicaHakiTurno = '';
    var tecnicaEnfriamiento = '';

    var isPasiva = tecnica.clase == 'Pasiva';
    var pasivaBlock = `background-color: #f24a01;`;
    var pasivaGradient = `linear-gradient(180deg,#f26525 50%,#f48a5d 100%);`;
    var pasivaTextGradient = `linear-gradient(90deg, rgba(0, 116, 143, 0) 0%, #d15924 20%, #812f09 50%, #d15924 80%, rgba(0, 116, 143, 0) 100%)`;

    if (tecnica.energia) {
      tecnicaEnergia = `
        <div style=" display: flex; flex-direction: row; " title="Costo de Energía">
          <div style=" font-size: 26px; font-family: lemonMilkMedium; color: #ffa100; -webkit-text-stroke-width: 1px; -webkit-text-stroke-color: black; text-shadow: 1px 1px 2px black; ">` + tecnica.energia + `</div>
          <img src="/images/op/uploads/Energia_One_Piece_Gaiden_Foro_Rol.png" style="height: 27px;margin-top: 5px;" alt="Costo de Energía" />
        </div>`;
    }
    if (tecnica.energia_turno) {
      tecnicaEnergiaTurno = `
        <div style=" display: flex; flex-direction: row; " title="Costo de Energía por Turno">
          <div style=" font-size: 26px; font-family: lemonMilkMedium; color: #ffa100; -webkit-text-stroke-width: 1px; -webkit-text-stroke-color: black; text-shadow: 1px 1px 2px black; ">` + tecnica.energia_turno + `</div>
          <img src="/images/op/uploads/EnergiaPorTurno_One_Piece_Gaiden_Foro_Rol.png" style="height: 27px;margin-top: 5px;" alt="Costo de Energía por Turno" />
        </div>`;
    }
    if (tecnica.haki) {
      tecnicaHaki = `
        <div style=" display: flex; flex-direction: row; " title="Costo de Haki">
          <div style=" font-size: 26px; font-family: lemonMilkMedium; color: #21DEFF; -webkit-text-stroke-width: 1px; -webkit-text-stroke-color: black; text-shadow: 1px 1px 2px black; ">` + tecnica.haki + `</div>
          <img src="/images/op/uploads/PuntosHaki_One_Piece_Gaiden_Foro_Rol.png" style="height: 27px;margin-top: 5px;" alt="Costo de Haki" />
        </div>`;
    }
    if (tecnica.haki_turno) {
      tecnicaHakiTurno = `
        <div style=" display: flex; flex-direction: row; " title="Costo de Haki por Turno">
          <div style=" font-size: 26px; font-family: lemonMilkMedium; color: #21DEFF; -webkit-text-stroke-width: 1px; -webkit-text-stroke-color: black; text-shadow: 1px 1px 2px black; ">` + tecnica.haki_turno + `</div>
          <img src="/images/op/uploads/PuntosHakiMantenido_One_Piece_Gaiden_Foro_Rol.png" style="height: 27px;margin-top: 5px;" alt="Costo de Haki por Turno" />
        </div>`;
    }
    if (tecnica.enfriamiento) {
      tecnicaEnfriamiento = `
        <div style=" display: flex; flex-direction: row; " title="Enfriamiento">
          <div style=" font-size: 26px; font-family: lemonMilkMedium; color: #D8EC13; -webkit-text-stroke-width: 1px; -webkit-text-stroke-color: black; text-shadow: 1px 1px 2px black; ">` + tecnica.enfriamiento + `</div>
          <img src="/images/op/uploads/CD_One_Piece_Gaiden_Foro_Rol.png" style="height: 30px;margin-top: 4px;" alt="Enfriamiento" />
        </div>`;
    }

    var tecnicaNombre = `[` + tecnica.tid + `] ` + tecnica.nombre;

    var generalTecs = `
    <div class="tecnica_spoiler"
         data-tier="${tecnica.tier}"
         data-tipo="${(tecnica.tipo||'').toLowerCase()}"
         data-clase="${(tecnica.clase||'').toLowerCase()}"
         data-rama="${tecnica.rama||''}"
         data-fecha="${tecnica.tiempo?new Date(tecnica.tiempo).toLocaleDateString('es-ES'):''}"
         style=" width: 96%; margin: 5px auto; ">
      <div class="spoiler_title">
        <span class="tecnica_spoiler_button" style="${isPasiva ? pasivaBlock : ''}" onclick="javascript: if(parentNode.parentNode.getElementsByTagName('div')[1].style.height == parentNode.parentNode.querySelector('.tecnica_spoiler_content2').offsetHeight + 'px'){ parentNode.parentNode.getElementsByTagName('div')[1].style.height = '0px'; this.innerHTML='` + tecnicaNombre + `';parentNode.parentNode.querySelector('.tecnica_spoiler_content').style.overflow = 'hidden';sleep(150).then(() => { parentNode.parentNode.querySelector('.tecnica_spoiler_content').style.overflow = 'hidden'; });} else { parentNode.parentNode.getElementsByTagName('div')[1].style.height = parentNode.parentNode.querySelector('.tecnica_spoiler_content2').offsetHeight + 'px'; this.innerHTML='` + tecnicaNombre + `'; parentNode.parentNode.querySelector('.tecnica_spoiler_content').style.overflow = 'hidden';sleep(150).then(() => { parentNode.parentNode.querySelector('.tecnica_spoiler_content').style.overflow = 'visible'; });}">` + tecnicaNombre + `</span>
      </div>
      <div class="tecnica_spoiler_content" style="height: 0px;${isPasiva ? `background: ${pasivaGradient};` : ''}">
        <div class="tecnica_spoiler_content2" style='display: flex;flex-direction: row;'>
          <div style="display: flex; flex-direction: column;width: 20%; margin-right: 26px;">
            <div class="tecnica_field" style="${isPasiva ? `background: ${pasivaTextGradient};` : ''}">` + tecnica.tid + `</div>
            <div class="tecnica_field" style="${isPasiva ? `background: ${pasivaTextGradient};` : ''}">` + tecnica.rama + `</div>
            <div class="tecnica_field" style="${isPasiva ? `background: ${pasivaTextGradient};` : ''}">` + tecnica.clase + `</div>
            <div class="tecnica_field" style="${isPasiva ? `background: ${pasivaTextGradient};` : ''}">Tier ` + tecnica.tier + `</div>
            <div class="tecnica_field" style="${isPasiva ? `background: ${pasivaTextGradient};` : ''}">` + new Date(tecnica.tiempo).toLocaleDateString('es-ES') + `</div>
            <div style="display: flex;flex-direction: row;justify-content: space-around;margin-top: 7px;width: 160px;">
              ` + tecnicaEnergia + `
              ` + tecnicaEnergiaTurno + `
              ` + tecnicaHaki + `
              ` + tecnicaHakiTurno + `
              ` + tecnicaEnfriamiento + `
            </div>
          </div>
          <div style="display: flex; flex-direction: column;width: 80%;">
            ` + requisitos + `
            <div class="tecnica_descripcion">` + tecnica.descripcion + `</div>
            <div class="tecnica_efecto">` + tecnica.efectos + `</div>
          </div>
        </div>
      </div>
    </div>`;

    $('#tecnicas-caja').append(generalTecs);
  }

  w.getTecnicas          = getTecnicas;
  w.insertarDisciplinaTecs = insertarDisciplinaTecs;
  w.showBlock            = showBlock;
  w.crearTecnica         = crearTecnica;

})(window.FICHA, window);
