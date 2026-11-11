
const CACHE_NAME = "findit-cache-v1";

const FILES_TO_CACHE = [
    "/findit/offline.html",
    "/findit/assets/css/style.css",
    "/findit/assets/images/icon-192.png",
    "/findit/assets/images/icon-512.png"
];

// Install Service Worker
self.addEventListener("install", event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(FILES_TO_CACHE))
    );

    self.skipWaiting();
});

// Activate Service Worker
self.addEventListener("activate", event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(name => name !== CACHE_NAME)
                    .map(name => caches.delete(name))
            );
        })
    );

    self.clients.claim();
});

// Handle requests
self.addEventListener("fetch", event => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle requests from this FindIt installation
    if (url.origin !== self.location.origin ||
        !url.pathname.startsWith("/findit/")) {
        return;
    }

    // Only handle GET requests
    if (request.method !== "GET") {
        return;
    }

    // Do not cache API requests or private pages
    if (
        url.pathname.includes("/api/") ||
        url.pathname.includes("/admin/") ||
        url.pathname.includes("/user/") ||
        url.pathname.endsWith("/login.php") ||
        url.pathname.endsWith("/register.php") ||
        url.pathname.endsWith("/dashboard.php")
    ) {
        return;
    }

    // Network first for page navigation
    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request).catch(() => {
                return caches.match("/findit/offline.html");
            })
        );
        return;
    }

    // Cache first for static files
    event.respondWith(
        caches.match(request).then(cachedResponse => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request);
        })
    );
});
