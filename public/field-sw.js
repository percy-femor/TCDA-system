const CACHE = 'tcda-field-v11';
const ASSETS = [
  '/field',
  '/css/app.css',
  '/css/field.css',
  '/js/field-app.js',
  '/field-manifest.json',
  '/icons/field-192.png',
  '/icons/field-512.png',
  '/icons/field-180.png',
  '/images/fisc-logo.jpg',
];

function isFieldRequest(url) {
  const path = url.pathname;
  return (
    path === '/field' ||
    path.startsWith('/field/') ||
    path === '/css/field.css' ||
    path === '/js/field-app.js' ||
    path === '/field-manifest.json' ||
    path === '/field-sw.js' ||
    path.startsWith('/icons/field') ||
    path === '/images/fisc-logo.jpg'
  );
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then(async (cache) => {
      await Promise.all(ASSETS.map((asset) => cache.add(asset).catch(() => undefined)));
      await self.skipWaiting();
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }
  if (url.pathname.startsWith('/api/')) {
    return;
  }
  if (!isFieldRequest(url)) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        const copy = response.clone();
        caches.open(CACHE).then((cache) => cache.put(event.request, copy));
        return response;
      })
      .catch(() => caches.match(event.request).then((cached) => cached || caches.match('/field')))
  );
});
