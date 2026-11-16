/* Human Coins — service worker
   Solo cachea lo estático. Las páginas con saldos van siempre a la red,
   para que nadie vea un saldo viejo. */

const CACHE = 'human-coins-v1';

const ESTATICOS = [
  'estilo.css',
  'moneda.webp',
  'manifest.json',
  'iconos/icono-192.jpg',
  'iconos/icono-512.jpg',
  'iconos/icono-maskable-512.jpg',
  'iconos/apple-touch-icon.jpg'
];

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(CACHE)
      .then(function (c) { return c.addAll(ESTATICOS); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys()
      .then(function (llaves) {
        return Promise.all(llaves.map(function (k) {
          if (k !== CACHE) { return caches.delete(k); }
        }));
      })
      .then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (e) {
  const req = e.request;

  if (req.method !== 'GET') { return; }

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) { return; }

  /* Las páginas PHP siempre desde la red */
  if (url.pathname.endsWith('.php') || req.mode === 'navigate') {
    e.respondWith(
      fetch(req).catch(function () {
        return new Response(
          '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">' +
          '<meta name="viewport" content="width=device-width, initial-scale=1">' +
          '<title>Sin conexión</title>' +
          '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;' +
          'background:#EFE9DC;color:#23262B;font-family:Georgia,serif;text-align:center;padding:24px}</style>' +
          '</head><body><div><h1>Sin conexión</h1>' +
          '<p>Tu cartera necesita internet para mostrar el saldo al día.</p></div></body></html>',
          { headers: { 'Content-Type': 'text/html; charset=UTF-8' } }
        );
      })
    );
    return;
  }

  /* Lo estático: del caché primero */
  e.respondWith(
    caches.match(req).then(function (hit) {
      return hit || fetch(req).then(function (res) {
        if (res && res.status === 200) {
          const copia = res.clone();
          caches.open(CACHE).then(function (c) { c.put(req, copia); });
        }
        return res;
      });
    })
  );
});
