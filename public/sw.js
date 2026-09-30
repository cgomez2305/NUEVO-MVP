// Service worker de la PWA del panel de Veci.
// Solo hace dos cosas: (1) existir, para que el navegador ofrezca instalar
// la app, y (2) mostrar la notificación cuando llega un push real del
// servidor (pedido o cita nueva), incluso con el panel cerrado.

self.addEventListener('install', function (evento) {
  self.skipWaiting();
});

self.addEventListener('activate', function (evento) {
  self.clients.claim();
});

// Sin caché de contenido: el panel es dinámico (pedidos, citas) y una
// versión vieja guardada sería más peligrosa que no tener offline.
self.addEventListener('fetch', function (evento) {
  // Passthrough intencional: no interceptamos nada, esto solo existe
  // porque un fetch handler es parte de los criterios de instalación.
});

self.addEventListener('push', function (evento) {
  var datos = { titulo: 'Veci', cuerpo: 'Tienes novedades en tu panel.', url: '/panel' };
  try {
    if (evento.data) {
      datos = evento.data.json();
    }
  } catch (e) {
    // payload no era JSON válido: usamos el mensaje genérico de arriba.
  }

  var opciones = {
    body: datos.cuerpo || '',
    icon: '/assets/img/icon-192.png',
    badge: '/assets/img/icon-192.png',
    data: { url: datos.url || '/panel' },
  };

  evento.waitUntil(self.registration.showNotification(datos.titulo || 'Veci', opciones));
});

self.addEventListener('notificationclick', function (evento) {
  evento.notification.close();
  var url = (evento.notification.data && evento.notification.data.url) || '/panel';

  evento.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (listaClientes) {
      for (var i = 0; i < listaClientes.length; i++) {
        if (listaClientes[i].url.indexOf(url) !== -1 && 'focus' in listaClientes[i]) {
          return listaClientes[i].focus();
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(url);
      }
    })
  );
});
