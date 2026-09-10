/* Service worker — PWA + Web Push (RRHH empleados) */

var CACHE_NAME = 'rrhh-pwa-v4';
var STATIC_ASSETS = [
    './css/style.css',
    './img/pwa/icon-192.png',
    './img/pwa/logo-pm.png',
    './img/default-avatar.svg',
];

function pwaAsset(path) {
    return new URL(path, self.location.origin + self.registration.scope).href;
}

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(STATIC_ASSETS).catch(function () {
                return undefined;
            });
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key !== CACHE_NAME) {
                    return caches.delete(key);
                }
                return undefined;
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') {
        return;
    }
    var url = new URL(req.url);
    if (url.origin !== self.location.origin) {
        return;
    }
    if (url.pathname.indexOf('/css/') !== -1
        || url.pathname.indexOf('/img/') !== -1
        || url.pathname.indexOf('/js/pwa-push.js') !== -1) {
        event.respondWith(
            caches.match(req).then(function (cached) {
                return cached || fetch(req).then(function (res) {
                    if (res && res.status === 200) {
                        var clone = res.clone();
                        caches.open(CACHE_NAME).then(function (cache) {
                            cache.put(req, clone);
                        });
                    }
                    return res;
                });
            })
        );
    }
});

self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'RRHH', body: event.data ? event.data.text() : 'Nueva notificación' };
    }

    var iconUrl = data.icon || pwaAsset('./img/pwa/icon-192.png');
    var title = data.title || 'RRHH';
    var options = {
        body: data.body || 'Tenés un aviso nuevo en el portal.',
        icon: iconUrl,
        badge: iconUrl,
        tag: data.tag || 'rrhh-notification',
        renotify: true,
        data: { url: data.url || './employee/notifications' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var target = (event.notification.data && event.notification.data.url) || './employee/notifications';
    var absolute = new URL(target, self.location.origin).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url.indexOf(self.location.origin) === 0 && 'focus' in client) {
                    if ('navigate' in client) {
                        return client.navigate(absolute).then(function () { return client.focus(); });
                    }
                    client.focus();
                    return clients.openWindow(absolute);
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(absolute);
            }
            return undefined;
        })
    );
});
