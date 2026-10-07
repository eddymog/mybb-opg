(function () {
    'use strict';

    var raiz = document.getElementById('periodico-editor');
    var formulario = document.getElementById('periodico-editor-form');
    var campoJson = document.getElementById('periodico-edicion-json');
    var campoAccion = document.getElementById('periodico-accion');
    var avisoValidacion = document.getElementById('periodico-validacion');
    var datos = window.OPG_PERIODICO_EDITOR;
    var paginaActual = 0;
    var tipos = ['noticia', 'dos_columnas', 'recuadro', 'wanted', 'galeria', 'separador'];
    var bloquesInvalidos = {};

    if (!raiz || !formulario || !datos) { return; }

    function crear(tag, clase, texto) {
        var el = document.createElement(tag);
        if (clase) { el.className = clase; }
        if (texto !== undefined) { el.textContent = texto; }
        return el;
    }

    function campo(contenedor, etiqueta, valor, alCambiar, tipo) {
        var label = crear('label', 'af-field');
        label.appendChild(crear('span', '', etiqueta));
        var input = crear(tipo === 'area' ? 'textarea' : 'input');
        if (tipo !== 'area') { input.type = tipo || 'text'; }
        input.value = valor || '';
        input.addEventListener('input', function () { alCambiar(input.value); bloquesInvalidos = {}; ocultarValidacion(); sincronizar(); });
        label.appendChild(input);
        contenedor.appendChild(label);
        return input;
    }

    function seleccionar(contenedor, etiqueta, valor, opciones, alCambiar) {
        var label = crear('label', 'af-field');
        label.appendChild(crear('span', '', etiqueta));
        var select = crear('select');
        opciones.forEach(function (opcion) {
            var item = crear('option', '', opcion.texto);
            item.value = opcion.valor;
            item.selected = opcion.valor === valor;
            select.appendChild(item);
        });
        select.addEventListener('change', function () { alCambiar(select.value); bloquesInvalidos = {}; ocultarValidacion(); sincronizar(); });
        label.appendChild(select);
        contenedor.appendChild(label);
    }

    function boton(texto, clase, evento) {
        var el = crear('button', clase, texto);
        el.type = 'button';
        el.addEventListener('click', evento);
        return el;
    }

    function bloqueVacio(tipo) {
        return { tipo: tipo, contenido: { titular: '', bajada: '', pie: '', autor: '', etiqueta: '', entradilla: '', titulo: '', cuerpo: '', nombre: '', recompensa: '', enlace: '', imagen: '', enlace_relacionado: '', imagenes: [], izquierda: '', derecha: '' } };
    }

    function sincronizar() {
        campoJson.value = JSON.stringify(datos);
    }

    function ocultarValidacion() {
        if (avisoValidacion) { avisoValidacion.hidden = true; avisoValidacion.textContent = ''; }
    }

    function validarPublicacion() {
        var errores = [];
        bloquesInvalidos = {};
        datos.paginas.forEach(function (pagina, indicePagina) {
            pagina.bloques.forEach(function (bloque, indiceBloque) {
                var contenido = bloque.contenido || {};
                var clave = indicePagina + '-' + indiceBloque;
                if (bloque.tipo === 'wanted' && (!String(contenido.nombre || '').trim() || !String(contenido.recompensa || '').trim() || !String(contenido.imagen || '').trim())) {
                    bloquesInvalidos[clave] = true;
                    errores.push('Página ' + (indicePagina + 1) + ', Wanted: completa nombre, recompensa e imagen.');
                }
                if (bloque.tipo === 'galeria' && !(contenido.imagenes || []).filter(function (imagen) { return String(imagen).trim() !== ''; }).length) {
                    bloquesInvalidos[clave] = true;
                    errores.push('Página ' + (indicePagina + 1) + ', Galería: agrega al menos una imagen.');
                }
            });
        });
        return errores;
    }

    function mostrarValidacion(errores) {
        if (!avisoValidacion) { return; }
        avisoValidacion.textContent = 'No se puede publicar todavía: ' + errores.join(' ');
        avisoValidacion.hidden = false;
        avisoValidacion.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function renderCabecera() {
        var meta = crear('section', 'opg-card periodico-editor__meta');
        meta.appendChild(crear('h2', '', 'Datos de la edición'));
        var fila = crear('div', 'periodico-editor__fila');
        campo(fila, 'Número', datos.numero, function (valor) { datos.numero = parseInt(valor, 10) || 0; }, 'number');
        campo(fila, 'Titular', datos.titulo, function (valor) { datos.titulo = valor; });
        campo(fila, 'Subtítulo', datos.subtitulo, function (valor) { datos.subtitulo = valor; });
        campo(fila, 'Dirección web', datos.slug, function (valor) { datos.slug = valor; });
        campo(fila, 'Firma visible', datos.firma_visible, function (valor) { datos.firma_visible = valor; });
        seleccionar(fila, 'Enfoque editorial', datos.tema, [{ valor: 'clasico', texto: 'Clásico' }, { valor: 'pirata', texto: 'Pirata' }, { valor: 'marine', texto: 'Marine' }, { valor: 'revolucionario', texto: 'Revolucionario' }, { valor: 'cipher-pol', texto: 'Cipher Pol' }, { valor: 'civil', texto: 'Civil' }], function (valor) { datos.tema = valor; });
        meta.appendChild(fila);
        raiz.appendChild(meta);
    }

    function renderPestanas() {
        var nav = crear('nav', 'periodico-editor__paginas');
        datos.paginas.forEach(function (pagina, indice) {
            var texto = pagina.titulo || 'Página ' + (indice + 1);
            var el = boton(texto, 'opg-chip' + (indice === paginaActual ? ' periodico-pagina-activa' : ''), function () { paginaActual = indice; render(); });
            nav.appendChild(el);
        });
        nav.appendChild(boton('+ Página', 'opg-chip', function () {
            datos.paginas.push({ titulo: '', tema: '', bloques: [] });
            paginaActual = datos.paginas.length - 1;
            sincronizar(); render();
        }));
        raiz.appendChild(nav);
    }

    function renderContenidoBloque(bloque, cuerpo, indiceBloque) {
        var contenido = bloque.contenido;
        if (bloque.tipo === 'separador') { cuerpo.appendChild(crear('p', 'periodico-editor__ayuda', 'Este bloque inserta una separación visual entre artículos.')); return; }
        if (bloque.tipo === 'wanted') {
            campo(cuerpo, 'Nombre', contenido.nombre, function (valor) { contenido.nombre = valor; });
            campo(cuerpo, 'Recompensa', contenido.recompensa, function (valor) { contenido.recompensa = valor; });
            campo(cuerpo, 'Imagen', contenido.imagen, function (valor) { contenido.imagen = valor; });
            seleccionar(cuerpo, 'Estado', contenido.estado || 'vivo', [{ valor: 'vivo', texto: 'Vivo' }, { valor: 'muerto', texto: 'Muerto' }], function (valor) { contenido.estado = valor; });
            return;
        }
        campo(cuerpo, bloque.tipo === 'portada' ? 'Titular de portada' : 'Titular', contenido.titular || contenido.titulo, function (valor) { if (bloque.tipo === 'portada') { contenido.titular = valor; } else { contenido.titulo = valor; } });
        if (bloque.tipo === 'recuadro') {
            campo(cuerpo, 'Contenido (BBCode permitido)', contenido.cuerpo, function (valor) { contenido.cuerpo = valor; }, 'area');
            return;
        }
        if (bloque.tipo === 'dos_columnas') {
            campo(cuerpo, 'Columna izquierda (BBCode permitido)', contenido.izquierda, function (valor) { contenido.izquierda = valor; }, 'area');
            campo(cuerpo, 'Columna derecha (BBCode permitido)', contenido.derecha, function (valor) { contenido.derecha = valor; }, 'area');
            return;
        }
        if (bloque.tipo === 'galeria') {
            campo(cuerpo, 'Imágenes (una URL por línea)', (contenido.imagenes || []).join('\n'), function (valor) { contenido.imagenes = valor.split('\n').map(function (url) { return url.trim(); }).filter(Boolean); }, 'area');
        } else {
            campo(cuerpo, bloque.tipo === 'portada' ? 'Bajada' : 'Entradilla', contenido.bajada || contenido.entradilla, function (valor) { if (bloque.tipo === 'portada') { contenido.bajada = valor; } else { contenido.entradilla = valor; } }, 'area');
            campo(cuerpo, 'Imagen', contenido.imagen, function (valor) { contenido.imagen = valor; });
        }
        campo(cuerpo, 'Cuerpo (BBCode permitido)', contenido.cuerpo, function (valor) { contenido.cuerpo = valor; }, 'area');
        if (bloque.tipo !== 'portada') { campo(cuerpo, 'Tema relacionado (ID o ruta interna)', contenido.enlace_relacionado, function (valor) { contenido.enlace_relacionado = valor; }); }
    }

    function renderPagina() {
        var pagina = datos.paginas[paginaActual];
        var panel = crear('section', 'periodico-editor__pagina');
        var titulo = crear('div', 'periodico-editor__pagina-cabecera');
        titulo.appendChild(crear('h2', '', 'Página ' + (paginaActual + 1)));
        var accionesPagina = crear('div', 'periodico-editor__acciones-inline');
        if (paginaActual > 0) { accionesPagina.appendChild(boton('Subir', 'opg-chip', function () { var anterior = datos.paginas[paginaActual - 1]; datos.paginas[paginaActual - 1] = pagina; datos.paginas[paginaActual] = anterior; paginaActual--; sincronizar(); render(); })); }
        if (paginaActual < datos.paginas.length - 1) { accionesPagina.appendChild(boton('Bajar', 'opg-chip', function () { var siguiente = datos.paginas[paginaActual + 1]; datos.paginas[paginaActual + 1] = pagina; datos.paginas[paginaActual] = siguiente; paginaActual++; sincronizar(); render(); })); }
        if (paginaActual > 0) { accionesPagina.appendChild(boton('Eliminar página', 'opg-chip chip-peligro', function () { datos.paginas.splice(paginaActual, 1); paginaActual = 0; sincronizar(); render(); })); }
        titulo.appendChild(accionesPagina);
        panel.appendChild(titulo);
        var fila = crear('div', 'periodico-editor__fila');
        campo(fila, 'Título interno', pagina.titulo, function (valor) { pagina.titulo = valor; });
        panel.appendChild(fila);
        pagina.bloques.forEach(function (bloque, indice) {
            var claveBloque = paginaActual + '-' + indice;
            var tarjeta = crear('article', 'opg-card periodico-editor__bloque' + (bloquesInvalidos[claveBloque] ? ' periodico-editor__bloque--invalido' : ''));
            var cabecera = crear('div', 'periodico-editor__bloque-cabecera');
            cabecera.appendChild(crear('h3', '', bloque.tipo.replace('_', ' ')));
            var accionesBloque = crear('div', 'periodico-editor__acciones-inline');
            if (!(paginaActual === 0 && indice === 0 && bloque.tipo === 'portada')) {
                if (indice > 0) { accionesBloque.appendChild(boton('Subir', 'opg-chip', function () { var anterior = pagina.bloques[indice - 1]; pagina.bloques[indice - 1] = bloque; pagina.bloques[indice] = anterior; sincronizar(); render(); })); }
                if (indice < pagina.bloques.length - 1) { accionesBloque.appendChild(boton('Bajar', 'opg-chip', function () { var siguiente = pagina.bloques[indice + 1]; pagina.bloques[indice + 1] = bloque; pagina.bloques[indice] = siguiente; sincronizar(); render(); })); }
                accionesBloque.appendChild(boton('Duplicar', 'opg-chip', function () { pagina.bloques.splice(indice + 1, 0, JSON.parse(JSON.stringify(bloque))); sincronizar(); render(); }));
                accionesBloque.appendChild(boton('Quitar', 'opg-chip chip-peligro', function () { pagina.bloques.splice(indice, 1); sincronizar(); render(); }));
            }
            cabecera.appendChild(accionesBloque);
            tarjeta.appendChild(cabecera);
            var cuerpo = crear('div', 'periodico-editor__campos periodico-editor__campos--' + bloque.tipo);
            renderContenidoBloque(bloque, cuerpo, indice);
            tarjeta.appendChild(cuerpo);
            panel.appendChild(tarjeta);
        });
        var anadir = crear('div', 'periodico-editor__anadir');
        tipos.forEach(function (tipo) { anadir.appendChild(boton('+ ' + tipo.replace('_', ' '), 'opg-chip', function () { pagina.bloques.push(bloqueVacio(tipo)); sincronizar(); render(); })); });
        panel.appendChild(anadir);
        raiz.appendChild(panel);
    }

    function render() {
        raiz.innerHTML = '';
        renderCabecera(); renderPestanas(); renderPagina(); sincronizar();
    }

    formulario.querySelectorAll('[data-accion]').forEach(function (el) {
        el.addEventListener('click', function () { campoAccion.value = el.getAttribute('data-accion'); sincronizar(); });
    });
    formulario.addEventListener('submit', function (evento) {
        sincronizar();
        if (campoAccion.value !== 'publicar') { return; }
        var errores = validarPublicacion();
        if (errores.length) { evento.preventDefault(); mostrarValidacion(errores); render(); }
    });
    render();
}());
