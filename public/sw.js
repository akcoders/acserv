const assetCache = 'acserv-assets-v1';
const privateCache = 'acserv-private-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('message', (event) => {
    if (event.data?.type === 'CLEAR_PRIVATE') {
        event.waitUntil(caches.delete(privateCache));
    }
});

self.addEventListener('push', (event) => {
    const payload = event.data?.json() ?? {};
    event.waitUntil(self.registration.showNotification(payload.title ?? 'ACServ', {
        body: payload.body ?? 'You have a new service update.',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
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

    if (['style', 'script', 'font', 'image'].includes(request.destination)) {
        event.respondWith(caches.open(assetCache).then(async (cache) => {
            const cached = await cache.match(request);

            if (cached) {
                return cached;
            }

            const response = await fetch(request);
            cache.put(request, response.clone());

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
