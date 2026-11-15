/*
 * Service worker: lets the system be installed as an app and keeps it usable
 * on a poor connection.
 *
 * Privacy: pages with member or financial data are NEVER stored on the device.
 * Pages always come from the server; only the offline notice, styles, scripts,
 * fonts and icons are cached. Bump VERSION when app.css / app.js change.
 */
var VERSION = 'wms-v1';
var STATIC_CACHE = VERSION + '-static';
var OFFLINE_URL = new URL('offline', self.registration.scope).href;

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(STATIC_CACHE).then(function (cache) {
            return cache.addAll([OFFLINE_URL]);
        }).then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) { return k.indexOf(VERSION) !== 0; })
                .map(function (k) { return caches.delete(k); }));
        }).then(function () { return self.clients.claim(); })
    );
});

function isStaticAsset(url) {
    if (url.origin === self.location.origin) {
        return /\/(css|js|pwa)\//.test(url.pathname) || /\.(css|js|png|jpg|jpeg|webp|svg|ico|woff2?)$/.test(url.pathname);
    }
    // Libraries loaded from CDNs (Bootstrap, jQuery, DataTables, Chart.js, fonts).
    return /(^|\.)(jsdelivr\.net|cdnjs\.cloudflare\.com|code\.jquery\.com|googleapis\.com|gstatic\.com)$/.test(url.hostname);
}

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') return; // forms always go straight to the server

    // Pages: always from the network; show the offline notice if there is no connection.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(function () { return caches.match(OFFLINE_URL); }));
        return;
    }

    var url = new URL(req.url);
    if (!isStaticAsset(url)) return;

    // Styles, scripts and icons: serve the cached copy at once, refresh it in the background.
    event.respondWith(
        caches.open(STATIC_CACHE).then(function (cache) {
            return cache.match(req).then(function (cached) {
                var network = fetch(req).then(function (res) {
                    if (res && (res.ok || res.type === 'opaque')) cache.put(req, res.clone());
                    return res;
                }).catch(function () { return cached; });
                return cached || network;
            });
        })
    );
});
