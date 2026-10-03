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
        // Clave para las huellas (HMAC) de WhatsApp y cédula/NIT con que las
        // ofertas reconocen a quien ya tuvo un negocio. Si la dejas en null,
        // se crea sola en storage/.clave_hash. Inclúyela en tus respaldos:
        // si se pierde, Veci "olvida" quién ya usó una oferta.
        // Genera una con: php -r "echo bin2hex(random_bytes(32));"
        'clave_hash' => null,
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

    // Opcional. Wompi (Web Checkout) para que los negocios paguen su plan con
    // tarjeta, PSE o Nequi y se active solo. Las llaves están en el panel de
    // Wompi → Desarrolladores. Con llave pub_test_ usa el sandbox. Configura
    // la URL de eventos de Wompi a https://TU-DOMINIO/webhooks/wompi.
    // Sin estas llaves, el plan se paga por Bre-B y lo confirma un admin.
    'wompi' => [
        'llave_publica'      => null, // pub_test_... o pub_prod_...
        'secreto_integridad' => null, // test_integrity_... / prod_integrity_...
        'secreto_eventos'    => null, // test_events_... / prod_events_...
    ],

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

    // Llave Bre-B de VECI (no la del negocio) para el cobro manual
    // verificado de los planes Barrio/Pro: un dueño que pide subir de plan
    // ve esta llave en /panel/plan, transfiere ahí, y un admin confirma el
    // pago desde /admin (ver database/migrations/…_planes_suscripciones.sql
    // y src/Models/PagoPlan.php). Sin esto configurado, /panel/plan igual
    // deja pedir el cambio, solo que no muestra a dónde transferir.
    'cobro_planes' => [
        'llave_breb' => null, // p.ej. '3001234567' (celular) o un correo
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
