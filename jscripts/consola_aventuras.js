(function () {
    'use strict';

    var invocadorHistorial = null;
    var cacheTabs = Object.create(null);
    var cacheMaxAge = 30000;
    var tabAnterior = '';

    function claveTab(enlace) {
        try {
            return new URL(enlace.href, window.location.href).searchParams.get('tab') || 'solicitudes';
        } catch (error) {
            return '';
        }
    }

    function tabActiva() {
        var enlace = document.querySelector('.ca-tab-activa');
        return enlace ? claveTab(enlace) : '';
    }

    function guardarTab(clave) {
        var panel = document.getElementById('ca-panel');
        if (!clave || !panel) {
            return;
        }
        cacheTabs[clave] = {html: panel.innerHTML, guardada: Date.now()};
    }

    function invalidarCache() {
        cacheTabs = Object.create(null);
    }

    function marcarTab(clave, cargando) {
        document.querySelectorAll('.ca-tab').forEach(function (enlace) {
            var activa = claveTab(enlace) === clave;
            enlace.classList.toggle('ca-tab-activa', activa);
            enlace.classList.toggle('ca-tab-cargando', activa && cargando);
            if (activa) {
                enlace.setAttribute('aria-current', 'page');
            } else {
                enlace.removeAttribute('aria-current');
            }
        });
    }

    /** Presenta una copia inmediata mientras HTMX revalida el panel. */
    function mostrarCache(clave) {
        var entrada = cacheTabs[clave];
        var panel = document.getElementById('ca-panel');
        if (!entrada || Date.now() - entrada.guardada > cacheMaxAge || !panel) {
            delete cacheTabs[clave];
            return false;
        }
        panel.innerHTML = entrada.html;
        if (window.htmx) {
            window.htmx.process(panel);
        }
        return true;
    }

    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('.ca-abrir-historial');
        if (boton) {
            invocadorHistorial = boton;
        }
    });

    document.addEventListener('click', function (evento) {
        var enlace = evento.target.closest ? evento.target.closest('.ca-tab') : null;
        if (!enlace || evento.button !== 0 || evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) {
            return;
        }
        var destino = claveTab(enlace);
        var actual = tabActiva();
        if (destino === actual) {
            return;
        }
        tabAnterior = actual;
        guardarTab(actual);
        mostrarCache(destino);
        marcarTab(destino, true);
    }, true);

    document.addEventListener('htmx:beforeRequest', function (evento) {
        var trigger = evento.detail && evento.detail.elt;
        var config = evento.detail && evento.detail.requestConfig;
        if (config && config.verb && config.verb.toLowerCase() !== 'get') {
            invalidarCache();
        }
        if (trigger && trigger.classList && trigger.classList.contains('ca-tab')) {
            marcarTab(claveTab(trigger), true);
            document.body.classList.add('ca-tabs-cargando');
            var panel = document.getElementById('ca-panel');
            if (panel) {
                panel.setAttribute('aria-busy', 'true');
            }
        }
    });

    document.addEventListener('htmx:afterSwap', function (evento) {
        var destino = evento.detail && evento.detail.target;
        if (!destino) {
            return;
        }
        if (destino.id === 'ca-overlay') {
            var dialogo = destino.querySelector('[role="dialog"]');
            if (dialogo) {
                dialogo.focus();
            } else if (invocadorHistorial && document.contains(invocadorHistorial)) {
                invocadorHistorial.focus();
                invocadorHistorial = null;
            }
            return;
        }
        if (destino.classList.contains('ca-lista') || destino.id.indexOf('ca-lista') === 0) {
            var resumen = destino.querySelector('.ca-resultados');
            if (resumen) {
                resumen.focus({preventScroll: true});
            }
        }
    });

    document.addEventListener('htmx:afterSettle', function (evento) {
        var destino = evento.detail && evento.detail.target;
        if (!destino || destino.id !== 'ca-panel') {
            return;
        }
        document.body.classList.remove('ca-tabs-cargando');
        destino.setAttribute('aria-busy', 'false');
        document.querySelectorAll('.ca-tab-cargando').forEach(function (enlace) {
            enlace.classList.remove('ca-tab-cargando');
        });
        guardarTab(tabActiva());
        tabAnterior = '';
    });

    ['htmx:responseError', 'htmx:sendError', 'htmx:timeout'].forEach(function (nombre) {
        document.addEventListener(nombre, function () {
            document.body.classList.remove('ca-tabs-cargando');
            var panel = document.getElementById('ca-panel');
            if (panel) {
                panel.setAttribute('aria-busy', 'false');
            }
            document.querySelectorAll('.ca-tab-cargando').forEach(function (enlace) {
                enlace.classList.remove('ca-tab-cargando');
            });
            if (tabAnterior) {
                mostrarCache(tabAnterior);
                marcarTab(tabAnterior, false);
                tabAnterior = '';
            }
        });
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Escape') {
            return;
        }
        var cerrar = document.querySelector('#ca-overlay .ca-cerrar-historial');
        if (cerrar) {
            cerrar.click();
        }
    });

    document.addEventListener('solicitudCambiada', invalidarCache);
    guardarTab(tabActiva());
}());
