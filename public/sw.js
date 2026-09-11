const CACHE_NAME = 'novahire-v1';
const STATIC_ASSETS = [
    '/Job-portal-and-grooming/',
    '/Job-portal-and-grooming/css/style.css',
    '/Job-portal-and-grooming/images/icon-192.png',
    '/Job-portal-and-grooming/images/icon-512.png',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', e => {
    if (e.request.method !== 'GET') return;
    // Network-first for API/dynamic pages, cache-first for static
    if (e.request.url.includes('/api/') || e.request.url.includes('.php')) {
        e.respondWith(
            fetch(e.request).then(res => {
                const clone = res.clone();
                caches.open(CACHE_NAME).then(c => c.put(e.request, clone));
                return res;
            }).catch(() => caches.match(e.request))
        );
    } else {
        e.respondWith(
            caches.match(e.request).then(cached => cached || fetch(e.request))
        );
    }
});

self.addEventListener('push', e => {
    const data = e.data ? e.data.json() : { title: 'NovaHire', body: 'You have a new notification' };
    e.waitUntil(self.registration.showNotification(data.title, {
        body: data.body,
        icon: '/Job-portal-and-grooming/images/icon-192.png',
        badge: '/Job-portal-and-grooming/images/icon-96.png',
        data: data.url || '/Job-portal-and-grooming/'
    }));
});

self.addEventListener('notificationclick', e => {
    e.notification.close();
    e.waitUntil(clients.openWindow(e.notification.data));
});
