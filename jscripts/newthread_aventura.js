/**
 * newthread.php: vincular temas de Aventura o Autonarrada a su solicitud.
 *
 * El bloque #nt-aventura aparece cuando el selector "Tipo de tema" vale data-prefijo y
 * desaparece (vaciando el campo) si se cambia a otro tipo. Con una aventura elegida solo
 * se ve la elección (el buscador se oculta hasta que se quite). El buscador pide al tablón
 * las aventuras que el usuario narra y aún no tienen tema; el servidor vuelve a
 * comprobarlo todo al publicar, así que este script solo ayuda a elegir.
 */
(function ($) {
    'use strict';

    $(function () {
        var $bloque = $('#nt-aventura');
        var $prefijo = $('#threadprefix');
        if (!$bloque.length || !$prefijo.length) {
            return;
        }

        var prefijosAventura = String($bloque.data('prefijos')).split(',');
        var urlTablon = String($bloque.data('url'));
        var $buscar = $('#nt-aventura-buscar');
        var $caja = $buscar.closest('.opg-buscar-caja');
        var $resultados = $('#nt-aventura-resultados');
        var $id = $('#nt-aventura-id');
        var $etiqueta = $('#nt-aventura-etiqueta');
        var $elegida = $('#nt-aventura-elegida');
        var temporizador = null;
        var peticion = 0;

        // Con una aventura elegida se oculta el buscador; al quitarla, vuelve a salir.
        function pintarElegida() {
            $elegida.empty();
            $caja.toggle(!$id.val());
            if (!$id.val()) {
                return;
            }
            var $chip = $('<span class="opg-chip nt-aventura-chip"></span>').text($etiqueta.val() || ('Aventura #' + $id.val()));
            var $quitar = $('<button type="button" class="nt-aventura-quitar" aria-label="Quitar la aventura">&times;</button>');
            $quitar.on('click', function () {
                limpiar();
                $buscar.trigger('focus');
            });
            $elegida.append($chip.append(' ', $quitar));
        }

        function limpiar() {
            $id.val('');
            $etiqueta.val('');
            $buscar.val('');
            $resultados.empty();
            pintarElegida();
        }

        function actualizarVisibilidad() {
            var visible = prefijosAventura.indexOf(String($prefijo.val())) !== -1;
            $bloque.prop('hidden', !visible);
            if (!visible) {
                limpiar();
            }
        }

        function buscar() {
            var mia = ++peticion;
            $.ajax({
                url: urlTablon,
                data: { action: 'buscar_aventuras', q: $.trim($buscar.val()), prefijo: String($prefijo.val()) },
                headers: { 'HX-Request': 'true' },
                dataType: 'html',
                cache: false
            }).done(function (html) {
                if (mia === peticion) {
                    $resultados.html(html);
                }
            }).fail(function () {
                if (mia === peticion) {
                    $resultados.empty().append($('<div class="opg-resultado-vacio"></div>').text('No se pudo buscar. Puedes crear el tema igualmente.'));
                }
            });
        }

        $prefijo.on('change', actualizarVisibilidad);

        $buscar.on('input focus', function () {
            clearTimeout(temporizador);
            temporizador = setTimeout(buscar, 250);
        });

        $resultados.on('click', '.opg-resultado', function () {
            $id.val(String($(this).data('aventura')));
            $etiqueta.val(String($(this).attr('data-etiqueta') || ''));
            $buscar.val('');
            $resultados.empty();
            pintarElegida();
        });

        // Cerrar la lista al hacer clic fuera del buscador.
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#nt-aventura .opg-buscar-caja').length) {
                $resultados.empty();
            }
        });

        actualizarVisibilidad();
        pintarElegida();
    });
})(window.jQuery);
