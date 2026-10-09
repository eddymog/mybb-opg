(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var abrir = event.target.closest('[data-ar-abrir-confirmacion]');
        if (abrir) {
            var dialogo = document.getElementById('ar-confirmacion');
            if (dialogo && typeof dialogo.showModal === 'function') {
                dialogo.showModal();
                var cerrar = dialogo.querySelector('[data-ar-cerrar-confirmacion]');
                if (cerrar) {
                    cerrar.focus();
                }
            }
            return;
        }

        var cerrar = event.target.closest('[data-ar-cerrar-confirmacion]');
        if (cerrar) {
            var actual = cerrar.closest('dialog');
            if (actual) {
                actual.close();
            }
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target.tagName === 'DIALOG') {
            event.target.close();
        }
    });
}());
