const CACHE_NAME = 'gembok-isp-v3';
const urlsToCache = [
    '/',
    '/index.php',
    '/assets/icons/icon.webp',
    '/manifest.json'
];

function offlineResponse(status, message) {
    return new Response(message, {
        status,
        headers: {
            'Content-Type': 'text/plain; charset=utf-8'
        }
    });
}

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async cache => {
            // Cache each URL individually so one bad request does not fail SW install
            await Promise.all(
                urlsToCache.map(async url => {
                    try {
                        const response = await fetch(url, { cache: 'no-cache' });
                        if (response && response.ok) {
                            await cache.put(url, response.clone());
                        }
                    } catch (error) {
                        console.warn('SW preload skipped:', url, error);
                    }
                })
            );
            await self.skipWaiting();
        })
    );
});

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') {
        return;
    }

    const request = event.request;
    const url = new URL(request.url);
    const noCacheRoutes = new Set([
        '/admin/login.php',
        '/portal/login.php',
        '/sales/login.php',
        '/technician/login.php'
    ]);
    const isLoginRoute = noCacheRoutes.has(url.pathname);
    const isAdminPhpRoute = url.pathname.startsWith('/admin/') && url.pathname.endsWith('.php');

    // Only handle same-origin requests. Let browser handle external requests.
    if (url.origin !== self.location.origin) {
        return;
    }

    // Never cache login pages to avoid stale auth/session UI.
    if (isLoginRoute) {
        event.respondWith(
            fetch(request, { cache: 'no-store' })
                .catch(() => offlineResponse(504, 'Network request failed'))
        );
        return;
    }

    // Admin PHP pages are dynamic; always go to network and do not cache.
    if (isAdminPhpRoute) {
        event.respondWith(
            fetch(request, { cache: 'no-store' })
                .catch(() => offlineResponse(504, 'Network request failed'))
        );
        return;
    }

    const isNavigation = request.mode === 'navigate' || request.destination === 'document';
    const isHtml = (request.headers.get('accept') || '').includes('text/html');
    const isCriticalAsset = ['script', 'style'].includes(request.destination) || url.pathname.endsWith('/manifest.json');

    // For HTML/documents use network-first to avoid stale pages in Chrome.
    if (isNavigation || isHtml) {
        event.respondWith(
            fetch(request)
                .then(response => {
                    if (response && response.ok) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(request, responseClone));
                    }
                    return response;
                })
                .catch(() =>
                    caches.match(request).then(cached => {
                        if (cached) {
                            return cached;
                        }

                        return caches.match('/index.php').then(indexCached => {
                            return indexCached || offlineResponse(503, 'Offline');
                        });
                    })
                )
        );
        return;
    }

    // JS/CSS/manifest should use network-first so production updates appear immediately.
    if (isCriticalAsset) {
        event.respondWith(
            fetch(request)
                .then(response => {
                    if (response && response.ok) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(request, responseClone));
                    }
                    return response;
                })
                .catch(() => caches.match(request).then(cached => cached || offlineResponse(504, 'Network request failed')))
        );
        return;
    }

    event.respondWith(
        caches.match(request).then(cached => {
            const networkFetch = fetch(request)
                .then(response => {
                    if (response && response.ok) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(request, responseClone));
                    }
                    return response;
                })
                .catch(() => cached || offlineResponse(504, 'Network request failed'));

            // Stale-while-revalidate for assets: fast load + background update.
            if (cached) {
                return cached;
            }

            return networkFetch;
        })
    );
});

self.addEventListener('activate', event => {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});
