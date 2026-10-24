(function () {
    'use strict';

    function formatNumber(value) {
        return new Intl.NumberFormat('es-ES').format(value);
    }

    function updateCart(form) {
        var submit = form.querySelector('[data-cart-submit]');
        if (!submit) return;
        var balance = Number(submit.dataset.balance) || 0;
        var total = 0;
        form.querySelectorAll('.hlw-product').forEach(function (product) {
            var input = product.querySelector('input[type="number"]');
            var quantity = Math.max(0, Math.min(100, Number(input.value) || 0));
            input.value = quantity;
            total += quantity * (Number(product.dataset.price) || 0);
            product.classList.toggle('has-quantity', quantity > 0);
        });
        form.querySelector('[data-cart-total]').textContent = formatNumber(total);
        form.querySelector('[data-cart-remaining]').textContent = formatNumber(balance - total);
        submit.disabled = submit.hasAttribute('data-store-disabled') || total <= 0 || total > balance;
    }

    function syncTabs() {
        var panel = document.getElementById('hlw-panel');
        if (!panel) return;
        var view = panel.dataset.view;
        document.querySelectorAll('.hlw-tabs a').forEach(function (tab) {
            var isActive = tab.getAttribute('href').indexOf('vista=' + view) !== -1;
            tab.classList.toggle('is-active', isActive);
            if (isActive) tab.setAttribute('aria-current', 'page');
            else tab.removeAttribute('aria-current');
        });
    }

    document.addEventListener('click', function (event) {
        var step = event.target.closest('[data-step]');
        if (!step) return;
        var input = step.parentNode.querySelector('input[type="number"]');
        input.value = Math.max(0, Math.min(100, (Number(input.value) || 0) + Number(step.dataset.step)));
        updateCart(step.closest('[data-hlw-cart]'));
    });

    document.addEventListener('input', function (event) {
        if (!event.target.matches('[data-hlw-cart] input[type="number"]')) return;
        updateCart(event.target.closest('[data-hlw-cart]'));
    });

    document.addEventListener('htmx:afterSwap', function (event) {
        var panel = document.getElementById('hlw-panel');
        if (!panel) return;
        syncTabs();
        var form = panel.querySelector('[data-hlw-cart]');
        if (form) updateCart(form);
    });

    document.addEventListener('htmx:beforeRequest', function () {
        document.documentElement.classList.add('hlw-is-loading');
    });

    document.addEventListener('htmx:afterRequest', function () {
        document.documentElement.classList.remove('hlw-is-loading');
    });

    document.addEventListener('click', function (event) {
        var tab = event.target.closest('.hlw-tabs a');
        if (!tab) return;
        document.querySelectorAll('.hlw-tabs a').forEach(function (item) {
            var isActive = item === tab;
            item.classList.toggle('is-active', isActive);
            if (isActive) item.setAttribute('aria-current', 'page');
            else item.removeAttribute('aria-current');
        });
    });

    document.addEventListener('htmx:historyRestore', syncTabs);
    window.addEventListener('popstate', function () {
        window.setTimeout(syncTabs, 0);
    });

    syncTabs();
    document.querySelectorAll('[data-hlw-cart]').forEach(updateCart);
}());
