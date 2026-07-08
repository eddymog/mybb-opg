/* Editor Oficios - Módulo OPG */
(function(OPG, w){
  'use strict';
  
  // Verifica que el textarea exista antes de continuar
  if (!document.getElementById('oficios')) return;
  
  var OFICIOS = {
    'Cocinero': {sub: {'Chef': 0, 'Aprovisionador': 0}, espe1: '', nivel: 1},
    'Artesano': {sub: {'Herrero': 0, 'Modista': 0}, espe1: '', nivel: 1},
    'Navegante': {sub: {'Cartógrafo': 0, 'Timonel': 0}, espe1: '', nivel: 1},
    'Médico': {sub: {'Farmacólogo': 0, 'Doctor': 0}, espe1: '', nivel: 1},
    'Carpintero': {sub: {'Astillero': 0, 'Constructor': 0}, espe1: '', nivel: 1},
    'Inventor': {sub: {'Biólogo': 0, 'Ingeniero': 0}, espe1: '', nivel: 1},
    'Investigador': {sub: {'Arqueólogo': 0, 'Periodista': 0}, espe1: '', nivel: 1},
    'Mercader': {sub: {'Comerciante': 0, 'Contrabandista': 0}, espe1: '', nivel: 1},
    'Aventurero': {sub: {'Cazador': 0, 'Domador': 0}, espe1: '', nivel: 1},
    'Recolector': {sub: {'Mayorista': 0, 'Agreste': 0}, espe1: '', nivel: 1}
  };
  
  function crearEditorOficios() {
    var textarea = document.getElementById('oficios');
    if (!textarea) return;
    
    var dataActual = OPG.utils.safeJSON.parse(textarea.value.trim() || '{}', {});
    
    var contenedor = document.createElement('div');
    contenedor.className = 'editor-oficios-visual';
    contenedor.style.cssText = 'margin:10px 0; padding:15px; background:#ff7e00; border:2px solid #ff7e00; border-radius:8px;';
    
    var titulo = document.createElement('h4');
    titulo.textContent = 'Oficios disponibles:';
    titulo.style.cssText = 'color:#fff; margin:0 0 10px 0; font-size:14px; font-weight:bold; cursor:pointer; user-select:none; display:flex; align-items:center; justify-content:space-between;';
    
    var toggleIcon = document.createElement('span');
    toggleIcon.textContent = '▼';
    toggleIcon.style.cssText = 'font-size:12px; transition:transform 0.3s; transform:rotate(-90deg);';
    titulo.appendChild(toggleIcon);
    
    contenedor.appendChild(titulo);
    
    var grid = document.createElement('div');
    grid.style.cssText = 'display:none; flex-direction:column; gap:8px;';
    grid.className = 'oficios-grid';
    
    titulo.onclick = function() {
      var isCollapsed = grid.style.display === 'none';
      grid.style.display = isCollapsed ? 'flex' : 'none';
      toggleIcon.style.transform = isCollapsed ? 'rotate(0deg)' : 'rotate(-90deg)';
    };
    
    for (var oficio in OFICIOS) {
      if (!OFICIOS.hasOwnProperty(oficio)) continue;
      
      var oficeContainer = document.createElement('div');
      oficeContainer.style.cssText = 'display:flex; flex-direction:column;';
      
      var label = document.createElement('label');
      label.style.cssText = 'display:flex; align-items:center; padding:6px 10px; background:#fff; border-radius:4px; cursor:pointer; transition:background 0.2s;';
      label.onmouseover = function(){ this.style.background = '#ffe5cc'; };
      label.onmouseout = function(){ this.style.background = '#fff'; };
      
      var checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.checked = dataActual.hasOwnProperty(oficio);
      checkbox.setAttribute('data-oficio', oficio);
      checkbox.style.cssText = 'margin-right:8px;';
      
      var texto = document.createElement('span');
      texto.textContent = oficio;
      texto.style.cssText = 'color:#333; font-size:13px; font-weight:bold;';
      
      label.appendChild(checkbox);
      label.appendChild(texto);
      oficeContainer.appendChild(label);
      
      // Crear campo de nivel del oficio general
      var nivelOficioContainer = document.createElement('div');
      nivelOficioContainer.className = 'nivel-oficio-container-' + oficio.replace(/\s+/g, '_');
      nivelOficioContainer.style.cssText = 'display:' + (checkbox.checked ? 'flex' : 'none') + '; align-items:center; padding:4px 10px; margin-left:20px; margin-top:4px; background:#ffe5cc; border-radius:3px;';
      
      var nivelOficioLabel = document.createElement('span');
      nivelOficioLabel.textContent = 'Nivel del oficio:';
      nivelOficioLabel.style.cssText = 'color:#333; font-size:12px; font-weight:bold; margin-right:8px;';
      
      var nivelOficioInput = document.createElement('input');
      nivelOficioInput.type = 'number';
      nivelOficioInput.min = '0';
      nivelOficioInput.max = '2';
      nivelOficioInput.value = (dataActual[oficio] && dataActual[oficio].nivel) || 1;
      nivelOficioInput.setAttribute('data-oficio-nivel', oficio);
      nivelOficioInput.style.cssText = 'width:50px; padding:2px 4px; border:1px solid #ccc; border-radius:3px;';
      nivelOficioInput.className = 'nivel-oficio-input-' + oficio.replace(/\s+/g, '_');
      
      nivelOficioInput.onchange = actualizarJSON;
      
      nivelOficioContainer.appendChild(nivelOficioLabel);
      nivelOficioContainer.appendChild(nivelOficioInput);
      oficeContainer.appendChild(nivelOficioContainer);
      
      // Crear desplegable de especialidades
      var subContainer = document.createElement('div');
      subContainer.className = 'sub-container-' + oficio.replace(/\s+/g, '_');
      subContainer.style.cssText = 'display:' + (checkbox.checked ? 'flex' : 'none') + '; flex-direction:column; gap:4px; margin-left:20px; margin-top:4px;';
      
      for (var sub in OFICIOS[oficio].sub) {
        if (!OFICIOS[oficio].sub.hasOwnProperty(sub)) continue;
        
        var subLabel = document.createElement('label');
        subLabel.style.cssText = 'display:flex; align-items:center; padding:4px 8px; background:#ffe5cc; border-radius:3px; cursor:pointer; font-size:12px;';
        
        var subCheck = document.createElement('input');
        subCheck.type = 'checkbox';
        subCheck.checked = dataActual[oficio] && dataActual[oficio].sub && dataActual[oficio].sub[sub] > 0;
        subCheck.setAttribute('data-oficio', oficio);
        subCheck.setAttribute('data-sub', sub);
        subCheck.style.cssText = 'margin-right:6px;';
        
        var subTexto = document.createElement('span');
        subTexto.textContent = sub;
        subTexto.style.cssText = 'color:#333; flex:1;';
        
        var nivelInput = document.createElement('input');
        nivelInput.type = 'number';
        nivelInput.min = '0';
        nivelInput.max = '3';
        nivelInput.value = (dataActual[oficio] && dataActual[oficio].sub && dataActual[oficio].sub[sub]) || 1;
        nivelInput.setAttribute('data-oficio', oficio);
        nivelInput.setAttribute('data-sub', sub);
        nivelInput.style.cssText = 'width:50px; margin-left:8px; padding:2px 4px; border:1px solid #ccc; border-radius:3px; display:' + (subCheck.checked ? 'block' : 'none') + ';';
        nivelInput.className = 'nivel-input-' + oficio.replace(/\s+/g, '_') + '-' + sub.replace(/\s+/g, '_');
        
        subCheck.onchange = function() {
          var ofi = this.getAttribute('data-oficio');
          var su = this.getAttribute('data-sub');
          var inp = document.querySelector('.nivel-input-' + ofi.replace(/\s+/g, '_') + '-' + su.replace(/\s+/g, '_'));
          if (inp) {
            inp.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) inp.value = 1;
          }
          actualizarJSON();
        };
        
        nivelInput.onchange = actualizarJSON;
        
        subLabel.appendChild(subCheck);
        subLabel.appendChild(subTexto);
        subLabel.appendChild(nivelInput);
        subContainer.appendChild(subLabel);
      }
      
      oficeContainer.appendChild(subContainer);
      
      // Toggle del desplegable al marcar/desmarcar oficio
      checkbox.onchange = function() {
        var ofic = this.getAttribute('data-oficio');
        
        // Comprobar límite de 2 oficios
        if (this.checked) {
          var oficiosMarcados = document.querySelectorAll('input[data-oficio]:not([data-sub]):checked');
          if (oficiosMarcados.length > 2) {
            this.checked = false;
            alert('Solo puedes tener un máximo de 2 oficios activos');
            return;
          }
        }
        
        var nivelOficioCont = document.querySelector('.nivel-oficio-container-' + ofic.replace(/\s+/g, '_'));
        if (nivelOficioCont) {
          nivelOficioCont.style.display = this.checked ? 'flex' : 'none';
        }
        
        var subCont = document.querySelector('.sub-container-' + ofic.replace(/\s+/g, '_'));
        if (subCont) {
          subCont.style.display = this.checked ? 'flex' : 'none';
        }
        actualizarJSON();
      };
      
      grid.appendChild(oficeContainer);
    }
    
    contenedor.appendChild(grid);
    textarea.parentNode.insertBefore(contenedor, textarea);
    
    // Ocultar textarea original
    textarea.style.display = 'none';
  }
  
  function actualizarJSON() {
    var textarea = document.getElementById('oficios');
    if (!textarea) return;

    var checkboxes = document.querySelectorAll('input[data-oficio]:not([data-sub])');
    var nuevoData = {};

    for (var i = 0; i < checkboxes.length; i++) {
      if (!checkboxes[i].checked) continue;
      var oficio = checkboxes[i].getAttribute('data-oficio');

      var nivelOficioInput = document.querySelector('.nivel-oficio-input-' + oficio.replace(/\s+/g, '_'));
      var nivelOficio = nivelOficioInput ? parseInt(nivelOficioInput.value, 10) : 1;

      var subData = {sub: {}, nivel: nivelOficio};
      var especialidadesActivas = [];

      // Iterate over the OFICIOS template so all sub keys are always present
      for (var sub in OFICIOS[oficio].sub) {
        if (!OFICIOS[oficio].sub.hasOwnProperty(sub)) continue;
        var subCheck = document.querySelector('input[type="checkbox"][data-oficio="' + oficio + '"][data-sub="' + sub + '"]');
        if (subCheck && subCheck.checked) {
          var nivelInput = document.querySelector('input[type="number"][data-oficio="' + oficio + '"][data-sub="' + sub + '"]');
          var nivelValor = nivelInput ? Math.max(0, parseInt(nivelInput.value, 10) || 0) : 1;
          subData.sub[sub] = nivelValor;
          if (nivelValor > 0) especialidadesActivas.push(sub);
        } else {
          subData.sub[sub] = 0;
        }
      }

      if (especialidadesActivas.length > 0) {
        subData.espe1 = especialidadesActivas[0];
        if (especialidadesActivas.length > 1) subData.espe2 = especialidadesActivas[1];
      }

      nuevoData[oficio] = subData;
    }

    textarea.value = OPG.utils.safeJSON.stringify(nuevoData, '{}');
  }
  
  // Inicializar cuando el DOM esté listo
  OPG.onReady(function(){
    crearEditorOficios();
  });
  
})(window.OPG || {}, window);
