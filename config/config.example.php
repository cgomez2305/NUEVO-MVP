<?php
/**
 * Copia este archivo como config.php y ajusta los valores.
 * config.php nunca se sube al repositorio (ver .gitignore).
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'name'    => 'veci',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        // Sin barra al final. Se usa para armar el enlace público de cada tienda.
        'url'    => 'http://localhost:8000',
        'nombre' => 'Veci',
    ],

    // Opcional. Si defines una llave aquí, la pantalla "La IA arma tu tienda"
    // llama a la API de Claude (Anthropic) para leer productos y precios reales
    // de la foto del menú. Sin llave, usa datos de ejemplo para que el flujo
    // funcione igual de principio a fin. Ver src/Services/ExtractorMenu.php.
    'anthropic_api_key' => null,

    // Opcional. Credenciales de WhatsApp Business API (Meta Cloud API) para
    // enviar recordatorios de cita y notificaciones de forma automática.
    // Sin esto, los recordatorios se quedan como una cola de envío manual
    // en el panel (enlace wa.me, igual que el copiloto). Ver
    // src/Services/RecordatorioWhatsapp.php.
    'whatsapp_api' => [
        'token'              => null,
        'phone_number_id'    => null,
    ],

    // Opcional. Secreto compartido para verificar la firma de las
    // notificaciones (webhook) del proveedor de pagos Bre-B. Sin esto, el
    // endpoint /webhooks/breb rechaza cualquier notificación entrante.
    // Ver src/Controllers/WebhookController.php.
    'breb_webhook_secret' => null,

    // Opcional. Llaves VAPID para las notificaciones push de la PWA (avisa
    // al dueño de un pedido/cita nueva aunque tenga el panel cerrado). Se
    // generan UNA vez con: php bin/generar_claves_vapid.php
    // Sin esto, el botón "Activar notificaciones" del panel no aparece;
    // la notificación en pestaña abierta (polling) sigue funcionando igual.
    'push_vapid' => [
        'public_key'  => null,
        'private_key' => null,
        'subject'     => 'mailto:soporte@tuveci.co',
    ],

    // Opcional. Credenciales SMTP para enviar el correo de "recuperar mi
    // contraseña" (el dueño tiene que haber guardado un correo en
    // "Mi cuenta" primero). Sirve cualquier proveedor con AUTH LOGIN +
    // STARTTLS (Gmail con contraseña de aplicación, SendGrid, Zoho...).
    // Sin esto, la solicitud de recuperación queda pendiente para que el
    // equipo de Veci genere el enlace a mano desde /admin.
    // Ver src/Services/Correo.php.
    'smtp' => [
        'host'             => null,
        'port'             => 587,
        'usuario'          => null,
        'password'         => null,
        'remitente'        => null, // si es null, usa 'usuario'
        'remitente_nombre' => 'Veci',
    ],
];
