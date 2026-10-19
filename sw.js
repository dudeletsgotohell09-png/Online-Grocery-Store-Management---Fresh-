/* sw.js — Fresh Grocery service worker (PWA / offline support)
 *
 * Strategy:
 *  - Precache the shell (CSS, JS, manifest, icons, login page) on install.
 *  - Network-first for navigation (HTML) requests — falls back to cached login
 *    page when offline so the user can at least see the entry point.
 *  - Cache-first for static assets (CSS, JS, images, fonts).
 *  - Bypass the cache for any POST / API / admin request (always hit the network).
 *
 * Cache version: bump CACHE_VERSION to invalidate the cache after a deploy.
 */

var CACHE_VERSION = 'fresh-v1';
var SHELL_CACHE   = 'fresh-shell-v' + CACHE_VERSION;
var ASSET_CACHE   = 'fresh-assets-v' + CACHE_VERSION;

var SHELL_ASSETS = [
  './',
  './login.php',
  './manifest.json',
  './css/style.css',
  './js/theme.js',
  './js/app.js',
  './icon-192.png',
  './icon-512.png',
  './icon-192-maskable.png',
  './icon-512-maskable.png'
];

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(SHELL_CACHE).then(function (cache) {
      // addAll fails atomically if any URL is missing — that's fine for install.
      return cache.addAll(SHELL_ASSETS).catch(function () {
        // Best-effort: skip missing files instead of failing the whole install.
        return Promise.all(SHELL_ASSETS.map(function (url) {
          return cache.add(url).catch(function () { return null; });
        }));
      });
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) {
        return k !== SHELL_CACHE && k !== ASSET_CACHE;
      }).map(function (k) { return caches.delete(k); }));
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function (e) {
  var req = e.request;

  // Only handle GET.
  if (req.method !== 'GET') return;

  var url = new URL(req.url);

  // Don't cache cross-origin requests, the API, admin pages, or PHP endpoints
  // that change state. These always need the network.
  if (url.origin !== self.location.origin) return;
  if (url.pathname.indexOf('/api/') !== -1) return;
  if (url.pathname.indexOf('/admin/') !== -1) return;
  if (url.pathname.endsWith('logout.php')) return;
  if (url.pathname.endsWith('checkout.php')) return;
  if (url.pathname.endsWith('cart.php') && req.cache !== 'no-cache') {
    // Cart shows live data — let it use the network, fall back to cache.
  }

  // Navigation requests (HTML pages): network-first, fall back to cached login.
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).then(function (res) {
        // Cache a copy of the latest login page for offline use.
        if (url.pathname.endsWith('login.php')) {
          var copy = res.clone();
          caches.open(SHELL_CACHE).then(function (c) { c.put('./login.php', copy); });
        }
        return res;
      }).catch(function () {
        return caches.match(req).then(function (cached) {
          return cached || caches.match('./login.php');
        });
      })
    );
    return;
  }

  // Static assets: cache-first.
  e.respondWith(
    caches.match(req).then(function (cached) {
      if (cached) return cached;
      return fetch(req).then(function (res) {
        // Only cache successful, basic-type responses.
        if (!res || res.status !== 200 || res.type !== 'basic') return res;
        var copy = res.clone();
        caches.open(ASSET_CACHE).then(function (c) { c.put(req, copy); });
        return res;
      }).catch(function () {
        // For images that fail offline, return nothing — the alt text will show.
        return new Response('', { status: 504, statusText: 'Offline' });
      });
    })
  );
});

// Allow the page to trigger an immediate update.
self.addEventListener('message', function (e) {
  if (e.data === 'skipWaiting') self.skipWaiting();
});
