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

/* Installable app (PWA): service worker + "Install app" buttons. */
(function () {
    'use strict';

    var meta = document.querySelector('meta[name="sw-url"]');
    if ('serviceWorker' in navigator && meta && window.isSecureContext) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(meta.content, { scope: meta.getAttribute('data-scope') })
                .catch(function (e) { console.warn('Service worker not registered:', e); });
        });
    }

    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (standalone) return; // already running as an installed app

    var buttons = function () { return document.querySelectorAll('[data-install-app]'); };
    var show = function (on) { buttons().forEach(function (b) { b.classList.toggle('d-none', !on); }); };
    var deferred = null;

    // Chrome, Edge, Samsung Internet, Opera (Android, Windows, macOS, ChromeOS, Linux).
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
        show(true);
    });
    window.addEventListener('appinstalled', function () { deferred = null; show(false); });

    // iPhone / iPad Safari has no install prompt: show how to add it to the Home Screen.
    var ua = navigator.userAgent;
    var isIos = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (isIos) {
        document.addEventListener('DOMContentLoaded', function () { show(true); });
    }

    function iosHelp() {
        var html = '<div class="modal fade" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
            '<div class="modal-header"><h5 class="modal-title">Install on iPhone / iPad</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>' +
            '<div class="modal-body"><ol class="mb-0 ps-3">' +
            '<li class="mb-2">Open this page in <strong>Safari</strong>.</li>' +
            '<li class="mb-2">Tap the <strong>Share</strong> button <i class="bi bi-box-arrow-up"></i>.</li>' +
            '<li class="mb-2">Choose <strong>Add to Home Screen</strong> <i class="bi bi-plus-square"></i>.</li>' +
            '<li>Tap <strong>Add</strong>. The app icon appears on your Home Screen.</li>' +
            '</ol></div></div></div></div>';
        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        var el = wrap.firstChild;
        document.body.appendChild(el);
        el.addEventListener('hidden.bs.modal', function () { el.remove(); });
        if (window.bootstrap) { new bootstrap.Modal(el).show(); }
        else { alert('In Safari tap Share, then "Add to Home Screen".'); el.remove(); }
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-install-app]')) return;
        if (deferred) {
            deferred.prompt();
            deferred.userChoice.finally(function () { deferred = null; show(false); });
        } else if (isIos) {
            iosHelp();
        }
    });
})();
