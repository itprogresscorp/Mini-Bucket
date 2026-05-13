// Service Worker для Mini-Bucket PWA
const CACHE_NAME = 'mini-bucket';
const API_CACHE_NAME = 'mini-bucket-api';

// Ресурсы для кэширования при установке
const STATIC_ASSETS = [
  //'/',
  //'/index.php',
  //'/style.css',
  '/css/loader.css',
  '/css/icon.ico',
  '/js/hosts_load.js',
  '/js/crt_checker.js',
  '/js/loader.js',
  '/manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://cdn.jsdelivr.net/npm/chart.js',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'
];

// Установка SW - кэширование статики
self.addEventListener('install', event => {
  console.log('[SW] Installing...');
  
  event.waitUntil(
    (async () => {
      const cache = await caches.open(CACHE_NAME);
      console.log('[SW] Caching static assets');
      
      // Кэшируем статические файлы с обработкой ошибок
      await Promise.allSettled(
        STATIC_ASSETS.map(async (asset) => {
          try {
            const response = await fetch(asset);
            if (response.ok) {
              await cache.put(asset, response);
            }
          } catch (error) {
            console.warn(`[SW] Failed to cache ${asset}:`, error);
          }
        })
      );
      
      // Кэшируем офлайн страницу
      const offlineResponse = new Response(
        '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title><style>body{font-family:system-ui;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#f5f5f7;text-align:center}</style></head><body><div><h1>📡 Нет соединения</h1><p>Пожалуйста, проверьте подключение к сети.</p><button onclick="location.reload()">Повторить</button></div></body></html>',
        { headers: { 'Content-Type': 'text/html' } }
      );
      await cache.put('/offline.html', offlineResponse);
      
      await self.skipWaiting();
    })()
  );
});

// Активация SW - очистка старых кэшей
self.addEventListener('activate', event => {
  console.log('[SW] Activating...');
  
  event.waitUntil(
    (async () => {
      const cacheNames = await caches.keys();
      await Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME && cacheName !== API_CACHE_NAME) {
            console.log('[SW] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
      await self.clients.claim();
    })()
  );
});

// Стратегия кэширования: только для GET запросов
async function staleWhileRevalidate(request) {
  // Только GET запросы попадают в кэш
  if (request.method !== 'GET') {
    return fetch(request);
  }
  
  const cache = await caches.open(CACHE_NAME);
  const cachedResponse = await cache.match(request);
  
  const fetchPromise = fetch(request).then(async networkResponse => {
    if (networkResponse && networkResponse.status === 200) {
      await cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  }).catch(error => {
    console.warn('[SW] Network request failed:', request.url, error);
    return cachedResponse;
  });
  
  return cachedResponse || fetchPromise;
}

// Для API запросов - только сеть, без кэширования
async function networkOnly(request) {
  try {
    const networkResponse = await fetch(request);
    return networkResponse;
  } catch (error) {
    console.log('[SW] Network failed for:', request.url);
    
    // Для GET запросов к API пробуем кэш
    if (request.method === 'GET') {
      const cache = await caches.open(API_CACHE_NAME);
      const cachedResponse = await cache.match(request);
      if (cachedResponse) {
        return cachedResponse;
      }
    }
    
    // Возвращаем заглушку
    return new Response(
      JSON.stringify({ 
        success: false, 
        error: 'offline',
        message: 'Нет подключения к серверу'
      }),
      { 
        status: 503,
        headers: { 'Content-Type': 'application/json' } 
      }
    );
  }
}

// Перехват запросов
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  
  // ===== ВАЖНО: Пропускаем все POST/PUT/DELETE запросы =====
  if (event.request.method !== 'GET') {
    console.log('[SW] Skipping non-GET request:', event.request.method, url.pathname);
    event.respondWith(fetch(event.request));
    return;
  }
  
  // Пропускаем все API запросы (оставляем только сеть)
  if (url.pathname.includes('/api/') || 
      url.pathname.includes('/system_settings_api.php') ||
      url.pathname.includes('/dashboard_api.php') ||
      url.pathname.includes('/raid_api.php') ||
      url.pathname.includes('/lvm_api.php') ||
      url.pathname.includes('/disk_usage_api.php')) {
    
    event.respondWith(networkOnly(event.request));
    return;
  }
  
  // Для статических ресурсов используем stale-while-revalidate
  if (event.request.destination === 'style' ||
      event.request.destination === 'script' ||
      event.request.destination === 'image' ||
      event.request.destination === 'font' ||
      url.pathname.endsWith('.css') ||
      url.pathname.endsWith('.js') ||
      url.pathname.endsWith('.json') ||
      url.pathname === '/' ||
      url.pathname === '/index.php') {
    
    event.respondWith(staleWhileRevalidate(event.request));
    return;
  }
  
  // Для остальных GET запросов - сначала сеть, потом кэш
  event.respondWith(
    fetch(event.request).catch(async () => {
      const cache = await caches.open(CACHE_NAME);
      const cachedResponse = await cache.match(event.request);
      if (cachedResponse) {
        return cachedResponse;
      }
      // Офлайн страница для навигации
      if (event.request.mode === 'navigate') {
        return cache.match('/offline.html');
      }
      return new Response('Offline', { status: 503 });
    })
  );
});

// Обработка push уведомлений
self.addEventListener('push', event => {
  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = {
        title: 'Mini-Bucket',
        body: event.data.text(),
        icon: '/icons/icon-192.png',
        badge: '/icons/badge-icon.png'
      };
    }
  }
  
  const options = {
    body: data.body || 'Статус системы обновлен',
    icon: data.icon || '/icons/icon-192.png',
    badge: data.badge || '/icons/badge-icon.png',
    vibrate: [200, 100, 200],
    tag: data.tag || 'system-update',
    data: {
      url: data.url || '/'
    },
    actions: [
      {
        action: 'open',
        title: 'Открыть'
      },
      {
        action: 'dismiss',
        title: 'Закрыть'
      }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title || 'Mini-Bucket NAS', options)
  );
});

// Обработка кликов по уведомлениям
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action === 'open' || !event.action) {
    const urlToOpen = event.notification.data?.url || '/';
    event.waitUntil(
      clients.matchAll({ type: 'window', includeUncontrolled: true })
        .then(windowClients => {
          for (let client of windowClients) {
            if (client.url === urlToOpen && 'focus' in client) {
              return client.focus();
            }
          }
          if (clients.openWindow) {
            return clients.openWindow(urlToOpen);
          }
        })
    );
  }
});

// Фоновая синхронизация
self.addEventListener('sync', event => {
  console.log('[SW] Sync event:', event.tag);
  
  if (event.tag === 'sync-system-data') {
    event.waitUntil(syncSystemData());
  }
});

async function syncSystemData() {
  try {
    const cache = await caches.open(API_CACHE_NAME);
    const requests = await cache.keys();
    
    for (const request of requests) {
      const storedData = await cache.match(request);
      if (storedData && storedData.headers.get('X-Pending-Sync') === 'true') {
        const data = await storedData.json();
        const response = await fetch(request.url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
        
        if (response.ok) {
          await cache.delete(request);
        }
      }
    }
  } catch (error) {
    console.error('[SW] Sync failed:', error);
  }
}