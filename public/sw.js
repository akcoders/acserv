const assetCache = 'acserv-assets-v2';
const privateCache = 'acserv-private-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(Promise.all([
    self.clients.claim(),
    caches.keys().then((keys) => Promise.all(keys
        .filter((key) => key.startsWith('acserv-assets-') && key !== assetCache)
        .map((key) => caches.delete(key)))),
])));

self.addEventListener('message', (event) => {
    if (event.data?.type === 'CLEAR_PRIVATE') {
        event.waitUntil(caches.delete(privateCache));
    }
});

self.addEventListener('push', (event) => {
    const payload = event.data?.json() ?? {};
    event.waitUntil(self.registration.showNotification(payload.title ?? 'ACServ', {
        body: payload.body ?? 'You have a new service update.',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        data: { url: payload.url ?? '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(self.clients.openWindow(event.notification.data?.url ?? '/'));
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(caches.open(assetCache).then(async (cache) => {
            const cached = await cache.match(request);

            if (cached) {
                return cached;
            }

            const response = await fetch(request);
            if (response.ok) {
                await cache.put(request, response.clone());
            }

            return response;
        }));
        return;
    }

    if (url.pathname.startsWith('/technician') || url.pathname.startsWith('/customer')) {
        event.respondWith(caches.open(privateCache).then(async (cache) => {
            try {
                const response = await fetch(request);

                if (response.ok) {
                    cache.put(request, response.clone());
                }

                return response;
            } catch {
                return cache.match(request);
            }
        }));
    }
});
