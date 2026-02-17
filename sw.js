const CACHE_NAME = 'fogs-pos-v2';

const ASSETS_TO_CACHE = [
  '/fogs-1/',
  '/fogs-1/login.php',
  '/fogs-1/index.php',
  '/fogs-1/manifest.json',
  '/fogs-1/assets/style.css',
  '/fogs-1/assets/icon-192.png',
  '/fogs-1/assets/icon-512.png'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();

  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(ASSETS_TO_CACHE))
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.filter(key => key !== CACHE_NAME)
            .map(key => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        return response || fetch(event.request);
      })
  );
});
