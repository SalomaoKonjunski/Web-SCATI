/**
 * Web SCATI - Service Worker
 *
 * Não faz cache agressivo de páginas: o sistema depende de dados sempre
 * atualizados (estoque, chamados, etc.), então cada navegação busca direto
 * da rede. Existe principalmente para (1) tornar o site instalável como
 * app e (2) receber e mostrar notificações push.
 */

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

// Handler "vazio" — deixa toda requisição passar direto pra rede. Alguns
// navegadores exigem um handler de fetch registrado para considerar o site
// instalável, mesmo que ele não faça nada além disso.
self.addEventListener('fetch', function () {
    return;
});

self.addEventListener('push', function (event) {
    if (!event.data) {
        return;
    }

    let dados = {};
    try {
        dados = event.data.json();
    } catch (e) {
        dados = { titulo: 'Web SCATI', corpo: event.data.text() };
    }

    const titulo = dados.titulo || 'Web SCATI';
    const opcoes = {
        body: dados.corpo || '',
        icon: dados.icone,
        badge: dados.icone,
        data: { url: dados.url || './' },
        tag: dados.tag || undefined,
    };

    event.waitUntil(self.registration.showNotification(titulo, opcoes));
});

// Ao tocar na notificação, foca uma aba já aberta no chamado (se existir)
// ou abre uma nova.
self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || './';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (listaClientes) {
            for (const cliente of listaClientes) {
                if (cliente.url === url && 'focus' in cliente) {
                    return cliente.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
