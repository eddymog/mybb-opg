/**
 * ficha/modales.js — Modales de edición de ficha (avatares, textos, cronología, etc.)
 * Requiere: core.js
 */
(function (FICHA, w) {

  // ── Estado del módulo ────────────────────────────────────────
  var modal;
  document.addEventListener('DOMContentLoaded', function () {
    modal = document.getElementById('myModal');
  });

  // ── Avatares ─────────────────────────────────────────────────

  function openAvatar1Modal() {
    if (!(is_owner || g_is_staff)) return;
    $('#myModal').html(`
      <div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:250px;width:620px;">
        <div class="modal-body" style="display:flex;flex-direction:column;">
          <div style="font-size:20px;text-align:center;font-family:moonGetHeavy;color:black;margin-top:10px;">Editar Imagen</div>
          <div style="margin-top:10px;font-size:18px;text-align:justify;">Esta imagen también cambiará la de los post. La imagen tiene unas dimensiones de 250x450.</div>
          <input id="avatar1URL" type="text" class="textbox" style="width:540px;margin:auto;margin-top:20px;" placeholder="URL de la imagen">
          <button onclick="cambiarAvatar1();" style="width:100px;margin:auto;margin-top:20px;">Guardar</button>
        </div>
      </div>`);
    modal.style.display = 'block';
  }

  function openAvatar2Modal() {
    if (!(is_owner || g_is_staff)) return;
    $('#myModal').html(`
      <div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:250px;width:620px;">
        <div class="modal-body" style="display:flex;flex-direction:column;">
          <div style="font-size:20px;text-align:center;font-family:moonGetHeavy;color:black;margin-top:10px;">Editar Imagen</div>
          <div style="margin-top:10px;font-size:18px;text-align:justify;">La imagen tiene unas dimensiones de 250x350.</div>
          <input id="avatar2URL" type="text" class="textbox" style="width:540px;margin:auto;margin-top:20px;" placeholder="URL de la imagen">
          <button onclick="cambiarAvatar2();" style="width:100px;margin:auto;margin-top:20px;">Guardar</button>
        </div>
      </div>`);
    modal.style.display = 'block';
  }

  function openAvatar3Modal() {
    if (!(is_owner || g_is_staff)) return;
    $('#myModal').html(`
      <div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:250px;width:620px;">
        <div class="modal-body" style="display:flex;flex-direction:column;">
          <div style="font-size:20px;text-align:center;font-family:moonGetHeavy;color:black;margin-top:10px;">Editar Imagen</div>
          <div style="margin-top:10px;font-size:18px;text-align:justify;">La imagen tiene unas dimensiones de 580x280.</div>
          <input id="avatar3URL" type="text" class="textbox" style="width:540px;margin:auto;margin-top:20px;" placeholder="URL de la imagen">
          <button onclick="cambiarAvatar3();" style="width:100px;margin:auto;margin-top:20px;">Guardar</button>
        </div>
      </div>`);
    modal.style.display = 'block';
  }

  function openAvatar4Modal() {
    if (!(is_owner || g_is_staff)) return;
    $('#myModal').html(`
      <div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:250px;width:620px;">
        <div class="modal-body" style="display:flex;flex-direction:column;">
          <div style="font-size:20px;text-align:center;font-family:moonGetHeavy;color:black;margin-top:10px;">Editar Imagen</div>
          <div style="margin-top:10px;font-size:18px;text-align:justify;">La imagen tiene unas dimensiones de 300x300.</div>
          <input id="avatar4URL" type="text" class="textbox" style="width:540px;margin:auto;margin-top:20px;" placeholder="URL de la imagen">
          <button onclick="cambiarAvatar4();" style="width:100px;margin:auto;margin-top:20px;">Guardar</button>
        </div>
      </div>`);
    modal.style.display = 'block';
  }

  function openAvatar5Modal() {
    if (!(is_owner || g_is_staff)) return;
    $('#myModal').html(`
      <div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:250px;width:620px;">
        <div class="modal-body" style="display:flex;flex-direction:column;">
          <div style="font-size:20px;text-align:center;font-family:moonGetHeavy;color:black;margin-top:10px;">Editar Imagen</div>
          <div style="margin-top:10px;font-size:18px;text-align:justify;">La imagen tiene unas dimensiones de 250x330.</div>
          <input id="avatar5URL" type="text" class="textbox" style="width:540px;margin:auto;margin-top:20px;" placeholder="URL de la imagen">
          <button onclick="cambiarAvatar5();" style="width:100px;margin:auto;margin-top:20px;">Guardar</button>
        </div>
      </div>`);
    modal.style.display = 'block';
  }

  function openAvatar1S1Modal() {
    if (!(is_owner || g_is_staff)) return;
    $('#myModal').html(`
      <div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:250px;width:620px;">
        <div class="modal-body" style="display:flex;flex-direction:column;">
          <div style="font-size:20px;text-align:center;font-family:moonGetHeavy;color:black;margin-top:10px;">Editar Imagen</div>
          <div style="margin-top:10px;font-size:18px;text-align:justify;">Esta imagen también cambiará la de los post. La imagen tiene unas dimensiones de 250x450.</div>
          <input id="avatar1URLS1" type="text" class="textbox" style="width:540px;margin:auto;margin-top:20px;" placeholder="URL de la imagen">
          <button onclick="cambiarAvatar1S1();" style="width:100px;margin:auto;margin-top:20px;">Guardar</button>
        </div>
      </div>`);
    modal.style.display = 'block';
  }

  function openAvatar2S1Modal() {
    if (!(is_owner || g_is_staff)) return;
    $('#myModal').html(`
      <div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:250px;width:620px;">
        <div class="modal-body" style="display:flex;flex-direction:column;">
          <div style="font-size:20px;text-align:center;font-family:moonGetHeavy;color:black;margin-top:10px;">Editar Imagen</div>
          <div style="margin-top:10px;font-size:18px;text-align:justify;">La imagen tiene unas dimensiones de 250x350.</div>
          <input id="avatar2URLS1" type="text" class="textbox" style="width:540px;margin:auto;margin-top:20px;" placeholder="URL de la imagen">
          <button onclick="cambiarAvatar2S1();" style="width:100px;margin:auto;margin-top:20px;">Guardar</button>
        </div>
      </div>`);
    modal.style.display = 'block';
  }

  // ── Descripción / Historia ────────────────────────────────────

  function openAparienciaModal() {
    var textBoxModal = (is_owner || g_is_staff)
      ? `<div style="text-align:center;"><textarea id="aparienciaText" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:288px;">${apariencia2}</textarea><button onclick="cambiarApariencia();" style="width:100px;margin:auto;margin-top:8px;">Guardar</button></div>`
      : `<div style="width:787px;height:340px;background-color:white;border:5px solid #ff7e00;box-sizing:border-box;overflow:auto;"><div style="font-family:InterRegular;margin:3px;font-size:14px;">${apariencia1}</div></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:400px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Apariencia</div>` + textBoxModal + `</div></div>`);
    modal.style.display = 'block';
  }

  function openAparienciaS1Modal() {
    var textBoxModal = (is_owner || g_is_staff)
      ? `<div style="text-align:center;"><textarea id="aparienciaTextS1" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:488px;">${ficha_secret1.apariencia}</textarea><button onclick="cambiarAparienciaS1();" style="width:100px;margin:auto;margin-top:8px;">Guardar</button></div>`
      : `<div style="width:787px;height:540px;background-color:white;border:5px solid #ff7e00;box-sizing:border-box;overflow:auto;"><div style="font-family:InterRegular;margin:3px;font-size:14px;">${ficha_secret1.apariencia}</div></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:600px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Apariencia</div>` + textBoxModal + `</div></div>`);
    modal.style.display = 'block';
  }

  function openPersonalidadModal() {
    var textBoxModal = (is_owner || g_is_staff)
      ? `<div style="text-align:center;"><textarea id="personalidadText" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:288px;">${personalidad2}</textarea><button onclick="cambiarPersonalidad();" style="width:100px;margin:auto;margin-top:8px;">Guardar</button></div>`
      : `<div style="width:787px;height:340px;background-color:white;border:5px solid #ff7e00;box-sizing:border-box;overflow:auto;"><div style="font-family:InterRegular;margin:3px;font-size:14px;">${personalidad1}</div></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:400px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Personalidad</div>` + textBoxModal + `</div></div>`);
    modal.style.display = 'block';
  }

  function openPersonalidadS1Modal() {
    var textBoxModal = (is_owner || g_is_staff)
      ? `<div style="text-align:center;"><textarea id="personalidadTextS1" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:488px;">${ficha_secret1.personalidad}</textarea><button onclick="cambiarPersonalidadS1();" style="width:100px;margin:auto;margin-top:8px;">Guardar</button></div>`
      : `<div style="width:787px;height:540px;background-color:white;border:5px solid #ff7e00;box-sizing:border-box;overflow:auto;"><div style="font-family:InterRegular;margin:3px;font-size:14px;">${ficha_secret1.personalidad}</div></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:600px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Personalidad</div>` + textBoxModal + `</div></div>`);
    modal.style.display = 'block';
  }

  function openHistoriaModal() {
    var textBoxModal = (is_owner || g_is_staff)
      ? `<div style="text-align:center;"><textarea id="historiaText" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:488px;">${historia2}</textarea><button onclick="cambiarHistoria();" style="width:100px;margin:auto;margin-top:8px;">Guardar</button></div>`
      : `<div style="width:787px;height:540px;background-color:white;border:5px solid #ff7e00;box-sizing:border-box;overflow:auto;"><div style="font-family:InterRegular;margin:3px;font-size:14px;">${historia1}</div></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:600px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Historia</div>` + textBoxModal + `</div></div>`);
    modal.style.display = 'block';
  }

  function openHistoriaS1Modal() {
    var textBoxModal = (is_owner || g_is_staff)
      ? `<div style="text-align:center;"><textarea id="historiaTextS1" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:488px;">${ficha_secret1.historia}</textarea><button onclick="cambiarHistoriaS1();" style="width:100px;margin:auto;margin-top:8px;">Guardar</button></div>`
      : `<div style="width:787px;height:540px;background-color:white;border:5px solid #ff7e00;box-sizing:border-box;overflow:auto;"><div style="font-family:InterRegular;margin:3px;font-size:14px;">${ficha_secret1.historia}</div></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:600px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Historia</div>` + textBoxModal + `</div></div>`);
    modal.style.display = 'block';
  }

  function openExtrasModal() {
    var textBoxModal = (is_owner || g_is_staff)
      ? `<div style="text-align:center;"><textarea id="extrasText" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:288px;">${extras2}</textarea><button onclick="cambiarExtras();" style="width:100px;margin:auto;margin-top:8px;">Guardar</button></div>`
      : `<div style="width:787px;height:340px;background-color:white;border:5px solid #ff7e00;box-sizing:border-box;overflow:auto;"><div style="font-family:InterRegular;margin:3px;font-size:14px;">${extras1}</div></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:400px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Extras</div>` + textBoxModal + `</div></div>`);
    modal.style.display = 'block';
  }

  function openExtrasS1Modal() {
    var textBoxModal = (is_owner || g_is_staff)
      ? `<div style="text-align:center;"><textarea id="extrasTextS1" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:488px;">${ficha_secret1.extra}</textarea><button onclick="cambiarExtrasS1();" style="width:100px;margin:auto;margin-top:8px;">Guardar</button></div>`
      : `<div style="width:787px;height:540px;background-color:white;border:5px solid #ff7e00;box-sizing:border-box;overflow:auto;"><div style="font-family:InterRegular;margin:3px;font-size:14px;">${ficha_secret1.extra}</div></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:600px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Extras</div>` + textBoxModal + `</div></div>`);
    modal.style.display = 'block';
  }

  // ── Campos cortos ─────────────────────────────────────────────

  function openApodoModal() {
    if (!(is_owner || g_is_staff)) return;
    var textBoxModal = `<div style="text-align:center;"><textarea id="apodoText" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:19px;">${apodo}</textarea><button onclick="cambiarApodo();" style="width:100px;margin:auto;margin-top:8px;">Guardar</button></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:128px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Apodo</div>` + textBoxModal + `</div></div>`);
    modal.style.display = 'block';
  }

  function openApodoS1Modal() {
    if (!(is_owner || g_is_staff)) return;
    var textBoxModal = `<div style="text-align:center;"><input id="apodoTextS1" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:28px;" placeholder="${ficha_secret1.apodo}"><button onclick="cambiarApodoS1();" style="width:100px;margin:auto;margin-top:8px;">Guardar</button></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:128px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Apodo</div>` + textBoxModal + `</div></div>`);
    modal.style.display = 'block';
  }

  function openNombreS1Modal() {
    if (!(is_owner || g_is_staff)) return;
    var textBoxModal = `<div style="text-align:center;"><input id="nombreTextS1" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:28px;" placeholder="${ficha_secret1.nombre}"><button onclick="cambiarNombreS1();" style="width:100px;margin:auto;margin-top:8px;">Guardar</button></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:128px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Nombre</div>` + textBoxModal + `</div></div>`);
    modal.style.display = 'block';
  }

  function openVisibleS1Modal() {
    if (!g_is_staff) return;
    var textBoxModal = `<div style="text-align:center;"><div id="visibleTextS1" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:19px;">Visibilidad Actual: ${ficha_secret1.es_visible ? 'Sí' : 'No'}</div><button onclick="cambiarVisibleS1();" style="width:100px;margin:auto;margin-top:8px;">Cambiar</button></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:128px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">¿Es Visible Tu Identidad?</div>` + textBoxModal + `</div></div>`);
    modal.style.display = 'block';
  }

  // ── Rango / Facción ───────────────────────────────────────────

  function openRangoS1Modal() {
    if (!g_is_staff) return;
    var rangosPorFaccion = {
      "Civil":         { "Civil": "Ciudadano" },
      "Pirata":        { "Pirata": "Pirata", "Capitán Pirata": "CapitanPirata", "Corsario": "Corsario", "Bucanero": "Bucanero", "Lobo de Mar": "LoboDeMar", "Supernova": "Supernova", "Pirata Afamado": "PirataAfamado", "Vice Capitán Famoso": "ViceCapitanFamoso", "Capitán Famoso": "CapitanFamoso", "Shichibukai": "Shichibukai", "Gran Pirata": "GranPirata", "Gran Vice Capitán": "GranViceCapitan", "Gran Capitán": "GranCapitan", "Comandante de Yonkou": "ComandanteP", "Primer Comandante de Yonkou": "PrimerComandante", "Yonkou": "Yonkou", "Leyenda del Mar": "LeyendaDelMar", "Ala del Rey": "AladelRey", "Rey de los Piratas": "ReyPirata" },
      "Marine":        { "Recluta": "ReclutaM", "Soldado Raso": "SoldadoM", "Sargento": "SargentoM", "Suboficial": "Suboficial", "Alferez": "Alferez", "Teniente": "Teniente", "Comandante": "ComandanteM", "Capitán": "Capitan", "Comodoro": "Comodoro", "Contralmirante": "ContraAlmirante", "Vice Almirante": "Vicealmirante", "Almirante": "Almirante", "Almirante de Flota": "AlmiranteFlota", "Instructor": "Instructor", "Inspector General": "Inspector", "Heroe de la Marina": "HeroeDeLaMarina" },
      "CipherPol":     { "Cipher Pol 1": "CP1", "Cipher Pol 2": "CP2", "Cipher Pol 3": "CP3", "Cipher Pol 4": "CP4", "Cipher Pol 5": "CP5", "Cipher Pol 6": "CP6", "Cipher Pol 7": "CP7", "Cipher Pol 8": "CP8", "Cipher Pol 9": "CP9", "Cipher Pol 0": "CPAegis0", "Cipher Pol Masquerade": "CPMasquerade", "Comisario": "CPComisario", "Comandante Ejecutivo": "CPComandanteEjecutivo", "Caballero Divino": "CPCaballeroDivino", "Comandante Supremo": "CPComandanteSupremo" },
      "Cazador":       { "Cazador": "Cazador", "Cazador Zeta": "CazadorZeta", "Cazador Epsilon": "CazadorEpsilon", "Cazador Delta": "CazadorDelta", "Cazador Gamma": "CazadorGamma", "Cazador Beta": "CazadorBeta", "Cazador Alpha": "CazadorAlpha", "Cazador Omega": "CazadorOmega", "Rey de los Cazadores": "ReyCazador" },
      "Revolucionario": { "Recluta": "ReclutaR", "Soldado Raso": "SoldadoR", "Sargento": "SargentoR", "Agente": "AgenteR", "Oficial": "Oficial", "Mariscal Revolucionario": "Mariscal", "General": "General", "Comandante Adjunto": "ComandanteAdjunto", "Comandante Revolucionario": "ComandanteR", "Jefe de Personal": "JefePersonal", "Comandante Supremo": "ComandanteSupremo" }
    };
    var faccionGuardada = (ficha_secret1.faccion || '').trim();
    if (faccionGuardada === 'Gob. Mundial') faccionGuardada = 'CipherPol';
    var opcionesRango = rangosPorFaccion[faccionGuardada] || { '': '' };
    var selectOptions = '';
    for (var valorEnvio in opcionesRango) {
      var valorEnBD = opcionesRango[valorEnvio];
      var isSelected = (ficha_secret1.rango === valorEnBD) ? 'selected="selected"' : '';
      selectOptions += `<option value="${valorEnBD}" ${isSelected}>${valorEnvio}</option>`;
    }
    var textBoxModal = `<div style="text-align:center;"><select id="rangoTextS1" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:30px;cursor:pointer;">${selectOptions}</select><button onclick="cambiarRangoS1();" style="width:100px;margin:auto;margin-top:8px;cursor:pointer;">Guardar</button></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:140px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Rango</div>${textBoxModal}</div></div>`);
    modal.style.display = 'block';
  }

  function openFaccionS1Modal() {
    if (!g_is_staff) return;
    var opcionesFaccion = { "Civil": "Civil", "Pirata": "Pirata", "Marine": "Marine", "Gob. Mundial": "Gob. Mundial", "Cazador": "Cazador", "Revolucionario": "Revolucionario" };
    var faccionGuardada = (ficha_secret1.faccion || '').trim();
    if (faccionGuardada === 'CipherPol') faccionGuardada = 'Gob. Mundial';
    var selectOptions = '';
    for (var valorEnvio in opcionesFaccion) {
      var textoVisible = opcionesFaccion[valorEnvio];
      var isSelected = (faccionGuardada === valorEnvio) ? 'selected="selected"' : '';
      selectOptions += `<option value="${valorEnvio}" ${isSelected}>${textoVisible}</option>`;
    }
    var textBoxModal = `<div style="text-align:center;"><select id="faccionTextS1" style="font-family:InterRegular;margin:3px;font-size:14px;width:765px;height:30px;cursor:pointer;">${selectOptions}</select><button onclick="cambiarFaccionS1();" style="width:100px;margin:auto;margin-top:8px;cursor:pointer;">Guardar</button></div>`;
    $('#myModal').html(`<div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:140px;width:820px;"><div class="modal-body" style="display:flex;flex-direction:column;"><div style="font-size:20px;text-align:center;font-family:interRegular;margin-top:10px;">Facción</div>${textBoxModal}</div></div>`);
    modal.style.display = 'block';
  }

  // ── Banda sonora ──────────────────────────────────────────────

  function openBandaSonoraModal() {
    if (!(is_owner || g_is_staff)) return;
    $('#myModal').html(`
      <div class="modal-content" style="background-color:#ffedd2;border:4px solid #ffe59b;border-radius:8px;height:300px;width:620px;">
        <div class="modal-body" style="display:flex;flex-direction:column;">
          <div style="font-size:20px;text-align:center;font-family:moonGetHeavy;color:black;margin-top:10px;">Editar Banda Sonora</div>
          <div style="margin-top:10px;font-size:18px;text-align:justify;">Ingresa un enlace de YouTube. Esta canción se reproducirá automáticamente cuando alguien visite tu ficha.</div>
          <input id="bandaSonoraURL" type="text" class="textbox" style="width:540px;margin:auto;margin-top:20px;" placeholder="https://www.youtube.com/watch?v=..." value="${banda_sonora}">
          <div style="margin:auto;margin-top:15px;display:flex;gap:10px;">
            <button onclick="cambiarBandaSonora();" style="width:100px;">Guardar</button>
            <button onclick="probarSonido();" style="width:100px;">Probar</button>
          </div>
          <div style="margin-top:15px;font-size:14px;text-align:center;font-style:italic;">Formatos aceptados: youtube.com/watch?v=ID o youtu.be/ID</div>
        </div>
      </div>`);
    modal.style.display = 'block';
  }

  // ── Cronología ────────────────────────────────────────────────

  function openCronologiaModal() {
    var cronologiaContent = '';
    var cronologiaActions = '';
    if (cronologia && cronologia.trim() !== '') {
      cronologiaContent = `<div style="text-align:center;padding:10px;font-family:InterRegular;color:#8B4513;"><p style="margin:5px 0;font-size:14px;">Cronología disponible</p><button onclick="abrirCronologia();" style="padding:8px 16px;background-color:#ff7e00;color:white;border:none;border-radius:4px;cursor:pointer;font-size:14px;font-weight:bold;">📖 Ver</button></div>`;
    } else {
      cronologiaContent = `<div style="text-align:center;padding:10px;font-family:InterRegular;color:#8B4513;"><p style="margin:5px 0;font-size:14px;">No hay cronología disponible</p></div>`;
    }
    if (is_owner || g_is_staff) {
      cronologiaActions = `<div style="text-align:center;border-top:1px solid #ffe59b;padding-top:10px;"><input id="cronologiaURL" type="text" style="width:85%;padding:6px;margin-bottom:8px;border:1px solid #ffe59b;border-radius:3px;font-size:12px;" placeholder="URL de la cronología..." value="${cronologia}"><br><button onclick="cambiarCronologia();" style="padding:6px 12px;background-color:#ff7e00;color:white;border:none;border-radius:3px;cursor:pointer;font-size:12px;">Guardar</button></div>`;
    }
    document.getElementById('cronologiaContent').innerHTML = cronologiaContent;
    document.getElementById('cronologiaActions').innerHTML = cronologiaActions;
    var cm = document.getElementById('cronologiaModal');
    cm.style.display = 'block';
    cm.onclick = function (event) { if (event.target === cm) closeCronologiaModal(); };
  }

  function abrirCronologia() {
    if (cronologia && cronologia.trim() !== '') window.open(cronologia, '_blank');
  }

  function closeCronologiaModal() {
    document.getElementById('cronologiaModal').style.display = 'none';
  }

  function cambiarCronologia() {
    var cronologiaUrl = document.getElementById('cronologiaURL').value;
    FICHA.PendingQueue.addParams({ cambiar_cronologia: cronologiaUrl });
    closeCronologiaModal();
  }

  // ── Banda sonora — acciones ───────────────────────────────────

  function cambiarBandaSonora() {
    var url = $('#bandaSonoraURL').val();
    FICHA.PendingQueue.addParams({ cambiar_banda_sonora: url });
    modal.style.display = 'none';
  }

  function probarSonido() {
    var url = $('#bandaSonoraURL').val();
    if (!url) { alert('Por favor, introduce un enlace de YouTube.'); return; }
    var videoId = '';
    var youtubeRegex = [
      /youtube\.com\/watch\?v=([^&]+)/,
      /youtu\.be\/([^?]+)/,
      /youtube\.com\/embed\/([^?]+)/,
      /youtube\.com\/v\/([^?]+)/
    ];
    for (var i = 0; i < youtubeRegex.length; i++) {
      var m = url.match(youtubeRegex[i]);
      if (m && m[1]) { videoId = m[1]; break; }
    }
    if (videoId && typeof player !== 'undefined') {
      var currentVideoId = player.getVideoData().video_id;
      player.loadVideoById({ videoId: videoId, startSeconds: 0 });
      alert('Reproduciendo música de prueba. El video actual se restaurará al cerrar el modal.');
      $(window).one('click', function (e) {
        if (e.target == modal) player.loadVideoById({ videoId: currentVideoId, startSeconds: 0 });
      });
    } else {
      alert('No se pudo extraer un ID de YouTube válido del enlace proporcionado.');
    }
  }

  // ── Guardar avatares ──────────────────────────────────────────

  function cambiarAvatar1()   { FICHA.PendingQueue.addParams({ cambiar_avatar1:   $('#avatar1URL').val()   || '/images/op/uploads/AvatarBiografia_One_Piece_Gaiden_Foro_Rol.png'   }); modal.style.display = 'none'; }
  function cambiarAvatar2()   { FICHA.PendingQueue.addParams({ cambiar_avatar2:   $('#avatar2URL').val()   || '/images/op/uploads/AvatarReputacion1_One_Piece_Gaiden_Foro_Rol.png' }); modal.style.display = 'none'; }
  function cambiarAvatar3()   { FICHA.PendingQueue.addParams({ cambiar_avatar3:   $('#avatar3URL').val()   || '/images/op/uploads/AvatarReputacion2_One_Piece_Gaiden_Foro_Rol.png' }); modal.style.display = 'none'; }
  function cambiarAvatar4()   { FICHA.PendingQueue.addParams({ cambiar_avatar4:   $('#avatar4URL').val()   || '/images/op/uploads/AvatarHabilidades_One_Piece_Gaiden_Foro_Rol.png' }); modal.style.display = 'none'; }
  function cambiarAvatar5()   { FICHA.PendingQueue.addParams({ cambiar_avatar5:   $('#avatar5URL').val()   || '/images/op/uploads/AvatarInventario_One_Piece_Gaiden_Foro_Rol.png'  }); modal.style.display = 'none'; }
  function cambiarAvatar1S1() { FICHA.PendingQueue.addParams({ cambiar_avatar1S1: $('#avatar1URLS1').val() || '/images/op/uploads/AvatarBiografia_One_Piece_Gaiden_Foro_Rol.png'   }); modal.style.display = 'none'; }
  function cambiarAvatar2S1() { FICHA.PendingQueue.addParams({ cambiar_avatar2S1: $('#avatar2URLS1').val() || '/images/op/uploads/AvatarReputacion1_One_Piece_Gaiden_Foro_Rol.png' }); modal.style.display = 'none'; }

  // ── Guardar textos ────────────────────────────────────────────

  function cambiarApariencia()   { FICHA.PendingQueue.addParams({ cambiar_apariencia:   $('#aparienciaText').val()   }); modal.style.display = 'none'; }
  function cambiarPersonalidad() { FICHA.PendingQueue.addParams({ cambiar_personalidad: $('#personalidadText').val() }); modal.style.display = 'none'; }
  function cambiarHistoria()     { FICHA.PendingQueue.addParams({ cambiar_historia:     $('#historiaText').val()     }); modal.style.display = 'none'; }
  function cambiarExtras()       { FICHA.PendingQueue.addParams({ cambiar_extras:       $('#extrasText').val()       }); modal.style.display = 'none'; }
  function cambiarApodo()        { FICHA.PendingQueue.addParams({ cambiar_apodo:        $('#apodoText').val()        }); modal.style.display = 'none'; }

  function cambiarAparienciaS1()   { FICHA.PendingQueue.addParams({ cambiar_aparienciaS1:   $('#aparienciaTextS1').val()   }); modal.style.display = 'none'; }
  function cambiarPersonalidadS1() { FICHA.PendingQueue.addParams({ cambiar_personalidadS1: $('#personalidadTextS1').val() }); modal.style.display = 'none'; }
  function cambiarHistoriaS1()     { FICHA.PendingQueue.addParams({ cambiar_historiaS1:     $('#historiaTextS1').val()     }); modal.style.display = 'none'; }
  function cambiarExtrasS1()       { FICHA.PendingQueue.addParams({ cambiar_extrasS1:       $('#extrasTextS1').val()       }); modal.style.display = 'none'; }
  function cambiarApodoS1()        { FICHA.PendingQueue.addParams({ cambiar_apodoS1:        $('#apodoTextS1').val()        }); modal.style.display = 'none'; }
  function cambiarRangoS1()        { FICHA.PendingQueue.addParams({ cambiar_rangoS1:        $('#rangoTextS1').val()        }); modal.style.display = 'none'; }
  function cambiarFaccionS1()      { FICHA.PendingQueue.addParams({ cambiar_faccionS1:      $('#faccionTextS1').val()      }); modal.style.display = 'none'; }

  function cambiarNombreS1() {
    FICHA.PendingQueue.addParams({ cambiar_nombreS1: $('#nombreTextS1').val() });
    modal.style.display = 'none';
  }

  function cambiarVisibleS1() {
    var opp = ficha_secret1.es_visible == '0' ? '1' : '0';
    FICHA.PendingQueue.addParams({ cambiar_visibleS1: opp });
    modal.style.display = 'none';
  }

  // ── Modal principal (myModal) ─────────────────────────────────

  function closeModal()      { modal.style.display = 'none'; }
  function clickAkuma(id)    { modal.style.display = 'block'; }

  // ── Exposición global ─────────────────────────────────────────

  w.openAvatar1Modal       = openAvatar1Modal;
  w.openAvatar2Modal       = openAvatar2Modal;
  w.openAvatar3Modal       = openAvatar3Modal;
  w.openAvatar4Modal       = openAvatar4Modal;
  w.openAvatar5Modal       = openAvatar5Modal;
  w.openAvatar1S1Modal     = openAvatar1S1Modal;
  w.openAvatar2S1Modal     = openAvatar2S1Modal;
  w.openAparienciaModal    = openAparienciaModal;
  w.openAparienciaS1Modal  = openAparienciaS1Modal;
  w.openPersonalidadModal  = openPersonalidadModal;
  w.openPersonalidadS1Modal= openPersonalidadS1Modal;
  w.openHistoriaModal      = openHistoriaModal;
  w.openHistoriaS1Modal    = openHistoriaS1Modal;
  w.openExtrasModal        = openExtrasModal;
  w.openExtrasS1Modal      = openExtrasS1Modal;
  w.openApodoModal         = openApodoModal;
  w.openApodoS1Modal       = openApodoS1Modal;
  w.openNombreS1Modal      = openNombreS1Modal;
  w.openVisibleS1Modal     = openVisibleS1Modal;
  w.openRangoS1Modal       = openRangoS1Modal;
  w.openFaccionS1Modal     = openFaccionS1Modal;
  w.openBandaSonoraModal   = openBandaSonoraModal;
  w.openCronologiaModal    = openCronologiaModal;
  w.abrirCronologia        = abrirCronologia;
  w.closeCronologiaModal   = closeCronologiaModal;
  w.cambiarCronologia      = cambiarCronologia;
  w.cambiarBandaSonora     = cambiarBandaSonora;
  w.probarSonido           = probarSonido;
  w.cambiarAvatar1         = cambiarAvatar1;
  w.cambiarAvatar2         = cambiarAvatar2;
  w.cambiarAvatar3         = cambiarAvatar3;
  w.cambiarAvatar4         = cambiarAvatar4;
  w.cambiarAvatar5         = cambiarAvatar5;
  w.cambiarAvatar1S1       = cambiarAvatar1S1;
  w.cambiarAvatar2S1       = cambiarAvatar2S1;
  w.cambiarApariencia      = cambiarApariencia;
  w.cambiarPersonalidad    = cambiarPersonalidad;
  w.cambiarHistoria        = cambiarHistoria;
  w.cambiarExtras          = cambiarExtras;
  w.cambiarApodo           = cambiarApodo;
  w.cambiarAparienciaS1    = cambiarAparienciaS1;
  w.cambiarPersonalidadS1  = cambiarPersonalidadS1;
  w.cambiarHistoriaS1      = cambiarHistoriaS1;
  w.cambiarExtrasS1        = cambiarExtrasS1;
  w.cambiarApodoS1         = cambiarApodoS1;
  w.cambiarNombreS1        = cambiarNombreS1;
  w.cambiarRangoS1         = cambiarRangoS1;
  w.cambiarFaccionS1       = cambiarFaccionS1;
  w.cambiarVisibleS1       = cambiarVisibleS1;
  w.closeModal             = closeModal;
  w.clickAkuma             = clickAkuma;

})(window.FICHA, window);
