/*
 * Lemon PWA service worker (CLAUDE.md §11.8 "PWA (temel)").
 *
 * KAPSAM — bilerek DAR tutulur:
 *   - Yalnızca statik "app-shell" varlıkları (Vite `build/` çıktısı, ikonlar,
 *     manifest, çevrimdışı sayfası) cache'lenir.
 *   - Kimlik doğrulanmış/finansal HTML sayfaları ASLA cache'lenmez — sayfa
 *     navigasyonu her zaman ağdan istenir; yalnızca ağ TAMAMEN başarısız
 *     olursa (çevrimdışı) statik `offline.html`'e düşülür.
 *   - GET DIŞINDAKİ hiçbir istek (POST/PUT/PATCH/DELETE) asla yakalanmaz —
 *     finansal mutasyonların çevrimdışı kuyruğa alınması/tekrar oynatılması
 *     KESİNLİKLE yapılmaz.
 *
 * Sürüm damgalı cache adı: yeni bir sürümde `CACHE_VERSION` artırılır,
 * `activate` eski cache'i siler (CLAUDE.md §11.8).
 */
const CACHE_VERSION = 'lemon-shell-v1';
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
    OFFLINE_URL,
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icon-maskable-192.png',
    '/icons/icon-maskable-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Yalnızca GET. Yazma istekleri asla ele alınmaz — tarayıcının kendi
    // varsayılan (doğrudan ağ) davranışına bırakılır.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Farklı origin'e giden istekler (CDN, dış servis) hiç ele alınmaz.
    if (url.origin !== self.location.origin) {
        return;
    }

    // Sayfa navigasyonu: HER ZAMAN ağdan istenir. Kimlik doğrulanmış/
    // finansal HTML asla cache'lenmez — yalnızca ağ hatasında statik
    // çevrimdışı sayfasına düşülür.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
        return;
    }

    // Vite'ın içerik-hash'li build çıktısı sürüm başına DEĞİŞMEZDİR —
    // cache-first güvenlidir.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.open(CACHE_VERSION).then(async (cache) => {
                const cached = await cache.match(request);
                if (cached) {
                    return cached;
                }

                const response = await fetch(request);
                if (response.ok) {
                    cache.put(request, response.clone());
                }

                return response;
            }),
        );
        return;
    }

    // Önceden cache'lenmiş statik varlıklar (ikon/manifest/çevrimdışı sayfası).
    if (PRECACHE_URLS.includes(url.pathname)) {
        event.respondWith(
            caches.match(request).then((cached) => cached ?? fetch(request)),
        );
    }

    // Diğer her şey (API benzeri GET istekleri dahil) hiç ele alınmaz.
});
