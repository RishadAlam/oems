'use strict';

const CACHE_NAME = 'oems-public-static-20260813-dashboard-header-v1';
const CACHE_PREFIX = 'oems-public-static-';
const OFFLINE_URL = '/offline.html';
const STATIC_ASSETS = Object.freeze([
    OFFLINE_URL,
    '/assets/css/app.css?v=20260813-dashboard-header-v1',
    '/assets/js/theme.js?v=20260811-form-controls-fix',
    '/assets/js/app.js?v=20260812-form-system',
    '/assets/js/pwa.js?v=20260811-form-controls-fix',
    '/assets/icons/oems-192.png',
    '/assets/icons/oems-512.png',
]);
const CACHEABLE_PATHS = new Set(STATIC_ASSETS.filter(path => path !== OFFLINE_URL));

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(names => Promise.all(names
                .filter(name => name.startsWith(CACHE_PREFIX) && name !== CACHE_NAME)
                .map(name => caches.delete(name))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.open(CACHE_NAME).then(cache => cache.match(OFFLINE_URL))),
        );
        return;
    }

    const cacheKey = url.pathname + url.search;
    if (!CACHEABLE_PATHS.has(cacheKey)) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(cache => cache.match(cacheKey).then(cached => cached || fetch(request).then(response => {
            if (!response.ok || response.type !== 'basic') {
                return response;
            }
            return cache.put(cacheKey, response.clone()).then(() => response);
        }))),
    );
});
