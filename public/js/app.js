/* Welfare Management System - shared behaviour (plain JS + jQuery, no build step). */
(function () {
    'use strict';

    // Mobile sidebar toggle.
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-sidebar-toggle]');
        var sidebar = document.querySelector('.app-sidebar');
        var backdrop = document.querySelector('.sidebar-backdrop');
        if (!sidebar) return;
        if (toggle) {
            sidebar.classList.toggle('show');
            backdrop && backdrop.classList.toggle('show');
        } else if (e.target === backdrop) {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        }
    });

    // Confirmation dialog: <form data-confirm="Are you sure?">
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            e.preventDefault();
            return;
        }
        // Block double submissions: disable buttons once the form is sent.
        if (form.method.toLowerCase() === 'post' && !form.hasAttribute('data-no-lock')) {
            setTimeout(function () {
                form.querySelectorAll('button[type=submit], input[type=submit]').forEach(function (btn) {
                    btn.disabled = true;
                    if (btn.tagName === 'BUTTON') {
                        btn.dataset.originalText = btn.innerHTML;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Please wait...';
                    }
                });
            }, 0);
        }
    });

    // Re-enable buttons if the page is restored from the back/forward cache.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[data-original-text]').forEach(function (btn) {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalText;
        });
    });

    // Image preview for file inputs: <input type="file" data-preview="#imgId">
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.matches('input[type=file][data-preview]') || !input.files || !input.files[0]) return;
        var img = document.querySelector(input.getAttribute('data-preview'));
        if (!img) return;
        img.src = URL.createObjectURL(input.files[0]);
        img.classList.remove('d-none');
    });

    // DataTables on any <table class="datatable">.
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery(function ($) {
            $('table.datatable').each(function () {
                var $t = $(this);
                if ($t.find('tbody tr').length === 1 && $t.find('tbody td[colspan]').length) return; // empty state row
                $t.DataTable({
                    pageLength: parseInt($t.data('page-length') || 25, 10),
                    order: $t.data('order') || [],
                    language: { search: '', searchPlaceholder: 'Search...' },
                    columnDefs: [{ targets: 'no-sort', orderable: false }]
                });
            });
        });
    }
})();
