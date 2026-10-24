(function () {
    'use strict';

    var opener = null;
    var tabCache = Object.create(null);
    var cacheMaxAge = 60000;
    var tabAnterior = '';

    function claveTab(enlace) {
        try {
            return new URL(enlace.href, window.location.href).searchParams.get('tab') || 'mias';
        } catch (error) {
            return '';
        }
    }

    function tabActiva() {
        var enlace = document.querySelector('.ta-tab-activa');
        return enlace ? claveTab(enlace) : '';
    }

    function guardarTab(clave) {
        var panel = document.getElementById('ta-panel');
        var novedades = document.getElementById('ta-novedades-slot');
        if (!clave || !panel || !novedades) {
            return;
        }
        tabCache[clave] = {
            panel: panel.innerHTML,
            novedades: novedades.innerHTML,
            guardada: Date.now()
        };
    }

    function invalidarCache() {
        tabCache = Object.create(null);
    }

    function marcarTab(clave, cargando) {
        document.querySelectorAll('.ta-tab').forEach(function (enlace) {
            var activa = claveTab(enlace) === clave;
            enlace.classList.toggle('ta-tab-activa', activa);
            enlace.classList.toggle('ta-tab-cargando', activa && cargando);
            if (activa) {
                enlace.setAttribute('aria-current', 'page');
            } else {
                enlace.removeAttribute('aria-current');
            }
        });
    }

    /** Muestra una copia inmediata; la petición HTMX continúa para revalidarla. */
    function mostrarCache(clave) {
        var entrada = tabCache[clave];
        var panel = document.getElementById('ta-panel');
        var novedades = document.getElementById('ta-novedades-slot');
        if (!entrada || Date.now() - entrada.guardada > cacheMaxAge || !panel || !novedades) {
            delete tabCache[clave];
            return false;
        }
        panel.innerHTML = entrada.panel;
        novedades.innerHTML = entrada.novedades;
        if (window.htmx) {
            window.htmx.process(panel);
            window.htmx.process(novedades);
        }
        return true;
    }

    function actualizarTipo(form) {
        if (!form || !form.classList.contains('ta-form')) {
            return;
        }
        var elegido = form.querySelector('input[name="tipo"]:checked, input[type="hidden"][name="tipo"]');
        var auto = elegido && elegido.value === 'autonarrada';
        form.classList.toggle('ta-es-auto', !!auto);
        form.querySelectorAll('.ta-normal-only input, .ta-normal-only select, .ta-normal-only textarea, .ta-normal-only button').forEach(function (control) {
            control.disabled = !!auto;
        });
        // Año y estación solo se envían en las crónicas autonarradas.
        form.querySelectorAll('.ta-auto-only input, .ta-auto-only select').forEach(function (control) {
            control.disabled = !auto;
        });
    }

    function prepararTipos(root) {
        (root || document).querySelectorAll('.ta-form').forEach(actualizarTipo);
    }

    document.addEventListener('change', function (event) {
        if (event.target && event.target.name === 'tipo') {
            actualizarTipo(event.target.closest('.ta-form'));
        }
    });

    document.addEventListener('click', function (event) {
        var enlace = event.target.closest ? event.target.closest('.ta-tab') : null;
        if (!enlace || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
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

    document.addEventListener('htmx:beforeRequest', function (event) {
        var trigger = event.detail && event.detail.elt;
        var config = event.detail && event.detail.requestConfig;
        if (config && config.verb && config.verb.toLowerCase() !== 'get') {
            invalidarCache();
        }
        if (trigger && trigger.classList && trigger.classList.contains('ta-tab')) {
            marcarTab(claveTab(trigger), true);
            document.body.classList.add('ta-tabs-cargando');
            var panel = document.getElementById('ta-panel');
            if (panel) {
                panel.setAttribute('aria-busy', 'true');
            }
        }
        if (trigger && trigger.getAttribute('hx-target') === '#ta-modal') {
            opener = document.activeElement;
        }
    });

    document.addEventListener('htmx:afterSwap', function (event) {
        var target = event.detail && event.detail.target;
        if (!target) {
            return;
        }
        prepararTipos(target);
        if (target.id === 'ta-modal') {
            var dialog = target.querySelector('[role="dialog"]');
            if (dialog) {
                dialog.focus();
            } else if (opener && document.contains(opener)) {
                opener.focus();
                opener = null;
            }
            return;
        }
        if (target.classList.contains('ta-lista')) {
            var resumen = target.querySelector('.ta-resultados-total');
            if (resumen) {
                resumen.focus({preventScroll: true});
            }
        }
    });

    document.addEventListener('htmx:afterSettle', function (event) {
        var target = event.detail && event.detail.target;
        if (!target || target.id !== 'ta-panel') {
            return;
        }
        document.body.classList.remove('ta-tabs-cargando');
        target.setAttribute('aria-busy', 'false');
        document.querySelectorAll('.ta-tab-cargando').forEach(function (enlace) {
            enlace.classList.remove('ta-tab-cargando');
        });
        guardarTab(tabActiva());
        tabAnterior = '';
    });

    ['htmx:responseError', 'htmx:sendError', 'htmx:timeout'].forEach(function (nombre) {
        document.addEventListener(nombre, function () {
            document.body.classList.remove('ta-tabs-cargando');
            var panel = document.getElementById('ta-panel');
            if (panel) {
                panel.setAttribute('aria-busy', 'false');
            }
            document.querySelectorAll('.ta-tab-cargando').forEach(function (enlace) {
                enlace.classList.remove('ta-tab-cargando');
            });
            if (tabAnterior) {
                mostrarCache(tabAnterior);
                marcarTab(tabAnterior, false);
                tabAnterior = '';
            }
        });
    });
    document.addEventListener('aventuraCreada', invalidarCache);
    document.addEventListener('aventuraCambiada', invalidarCache);
    // Enlaces ?id=N (por ejemplo desde Novedades): lleva la vista a la solicitud resaltada.
    function mostrarResaltada() {
        var tarjeta = document.querySelector('.ta-resaltada');
        if (!tarjeta) {
            return;
        }
        var reducir = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        tarjeta.scrollIntoView({ block: 'center', behavior: reducir ? 'auto' : 'smooth' });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mostrarResaltada);
    } else {
        mostrarResaltada();
    }
    guardarTab(tabActiva());
    prepararTipos(document);
}());
