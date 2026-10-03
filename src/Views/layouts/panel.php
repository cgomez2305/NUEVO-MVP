<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/_head.php'; ?>
<link rel="manifest" href="<?= e(base_url('manifest.json')) ?>">
<meta name="theme-color" content="#3B4CCA">
<link rel="apple-touch-icon" href="<?= e(base_url('assets/img/icon-192.png')) ?>">
</head>
<?php
// Un ícono por sección del nav, para que la barra deje de ser puro texto
// mono (ver public/assets/css/app.css). Mismo trazo (stroke 2, 24x24) que
// usa el resto de la marca.
$pqIconos = [
    'panel'          => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'pedidos'        => '<path d="M6 7h12l-1 13H7L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
    'citas'          => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    'recordatorios'  => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
    'copiloto'       => '<path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/>',
    'domicilios'     => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
    'caja'           => '<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M7 8V5h10v3"/><path d="M3 13h18"/><path d="M10 16.5h4"/>',
    'referidos'      => '<path d="M20 12v8H4v-8"/><rect x="2" y="7" width="20" height="5" rx="1"/><path d="M12 22V7"/><path d="M12 7H8.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Z"/><path d="M12 7h3.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z"/>',
    'resenas'        => '<path d="M12 3.5l2.5 5.2 5.7.7-4.2 3.9 1.1 5.6L12 16.1l-5.1 2.8 1.1-5.6-4.2-3.9 5.7-.7L12 3.5Z"/>',
    'paquetes'       => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/>',
    'fidelidad'      => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><circle cx="7.5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="16.5" cy="12" r="1.6"/>',
    'cupones'        => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4V8Z"/><path d="M9.5 14.5l5-5"/><circle cx="9.5" cy="9.5" r=".6"/><circle cx="14.5" cy="14.5" r=".6"/>',
    'servicios'      => '<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="M3 16l9 5 9-5M3 12l9 5 9-5"/>',
    'empleados'      => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="18" cy="8.5" r="2.6"/><path d="M16.5 14.3c2.3.5 4 2.5 4.5 5.7"/>',
    'fila'           => '<circle cx="6" cy="7" r="2.2"/><circle cx="12" cy="7" r="2.2"/><circle cx="18" cy="7" r="2.2"/><path d="M3 15c0-1.9 1.3-3.4 3-3.4s3 1.5 3 3.4M9 15c0-1.9 1.3-3.4 3-3.4s3 1.5 3 3.4M15 15c0-1.9 1.3-3.4 3-3.4s3 1.5 3 3.4"/><path d="M3 20h18"/>',
    'comisiones'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 15l8-6"/><circle cx="8.5" cy="9.5" r="1.3"/><circle cx="15.5" cy="14.5" r="1.3"/>',
    'repetir'        => '<path d="M4 12a8 8 0 0 1 13.7-5.6L20 8.7"/><path d="M20 4v4.7h-4.7"/><path d="M20 12a8 8 0 0 1-13.7 5.6L4 15.3"/><path d="M4 20v-4.7h4.7"/>',
    'cobertura'      => '<path d="M9 4 3 6.5v13.5l6-2.5 6 2.5 6-2.5V4l-6 2.5L9 4Z"/><path d="M9 4v13.5M15 6.5V20"/>',
    'horario'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    'productos'      => '<path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/>',
    'sedes'          => '<path d="M12 21s7-6.4 7-11.5a7 7 0 1 0-14 0C5 14.6 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.6"/>',
    'colaboradores'  => '<circle cx="8" cy="9" r="3"/><path d="M2 20c0-3 2.7-5.5 6-5.5s6 2.5 6 5.5"/><circle cx="17.5" cy="8" r="2.3"/><path d="M15.8 14.7c2.4.4 4.2 2.5 4.2 5.3"/>',
    'cuenta'         => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c1-4 4-6 7.5-6s6.5 2 7.5 6"/>',
    'plan'           => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/>',
    // Tiendas (fase 4): lector de códigos, cuaderno del fiado y caja que llega del proveedor.
    'mostrador'      => '<path d="M4 6v12M7 6v12M10 6v8M13 6v12M16 6v8M20 6v12"/><path d="M10 18h0M16 18h0"/>',
    'fiado'          => '<path d="M6 3h11a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6V3Z"/><path d="M6 3v18"/><path d="M10 8h5M10 12h5"/><path d="M3.5 7h2.5M3.5 12h2.5M3.5 17h2.5"/>',
    'compras'        => '<path d="M3 8l9-5 9 5v8l-9 5-9-5V8Z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
    'mas'            => '<circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/>',
];
$pqIcono = static function (string $clave) use ($pqIconos): string {
    return isset($pqIconos[$clave])
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $pqIconos[$clave] . '</svg>'
        : '';
};

// Un solo árbol de navegación (clave, etiqueta, href), agrupado como antes
// para el sidebar de escritorio. Se reutiliza también para el bottom nav de
// celular: los primeros 3 items entran directo en la barra, todo lo demás
// se agrupa bajo "Más" — así ambas navegaciones nunca se desincronizan.
$tipoReservas = ($negocio['tipo_negocio'] ?? 'pedidos') === 'reservas';
$esDueno = $negocio['rol'] === 'dueno';
$aDomicilio = $tipoReservas && ($negocio['modalidad'] ?? 'local') === 'domicilio';

$navOperacion = [['panel', 'Panel', base_url('/panel')]];
if ($tipoReservas) {
    $navOperacion[] = ['citas', 'Agenda', base_url('/panel/citas')];
    $navOperacion[] = ['recordatorios', 'Recordatorios', base_url('/panel/recordatorios')];
    // La fila es para quien llega sin cita al local; un técnico va a la casa.
    if (!$aDomicilio) {
        $navOperacion[] = ['fila', 'Fila de hoy', base_url('/panel/fila')];
    }
    $navOperacion[] = ['repetir', 'Toca repetir', base_url('/panel/repetir')];
    $navOperacion[] = ['servicios', 'Servicios', base_url('/panel/servicios')];
    $navOperacion[] = ['paquetes', 'Paquetes y bonos', base_url('/panel/paquetes')];
    if ($esDueno) {
        $navOperacion[] = ['empleados', 'Equipo', base_url('/panel/empleados')];
        $navOperacion[] = ['comisiones', 'Comisiones', base_url('/panel/comisiones')];
        if ($aDomicilio) {
            $navOperacion[] = ['cobertura', 'Zonas que cubres', base_url('/panel/cobertura')];
        }
        $navOperacion[] = ['horario', 'Horario', base_url('/panel/horario')];
    }
} else {
    $navOperacion[] = ['pedidos', 'Pedidos', base_url('/panel/pedidos')];
    // Tiendas (fase 4): vender en el local y el fiado son de todo el equipo;
    // las compras a proveedor (inventario y costos) solo del dueño.
    $navOperacion[] = ['mostrador', 'Mostrador', base_url('/panel/mostrador')];
    $navOperacion[] = ['productos', 'Menú', base_url('/panel/productos')];
    $navOperacion[] = ['fiado', 'Fiado', base_url('/panel/fiado')];
    if ($esDueno) {
        $navOperacion[] = ['compras', 'Compras', base_url('/panel/compras')];
    }
    // Antes solo los negocios de reservas podían poner horario: una tienda
    // de pedidos nunca mostraba "Abierto ahora" ni su hora de almuerzo.
    if ($esDueno) {
        $navOperacion[] = ['horario', 'Horario', base_url('/panel/horario')];
        $navOperacion[] = ['domicilios', 'Domicilios', base_url('/panel/domicilios')];
    }
}

// El cierre de caja es de quien cierra el local, dueño o colaborador.
$navOperacion[] = ['caja', 'Cierre de caja', base_url('/panel/caja')];

// Si el plan no incluye el copiloto (Gratis), el link sigue llevando ahí —
// el controller explica por qué y manda a /panel/plan — pero la etiqueta ya
// lo avisa de una vez en vez de dejar que se sienta como un link roto.
$navCrecimiento = $esDueno
    ? [
        ['copiloto', empty($negocio['incluye_copiloto']) ? 'Copiloto · Barrio+' : 'Copiloto', base_url('/panel/copiloto')],
        ['cupones', 'Cupones', base_url('/panel/cupones')],
        ['fidelidad', 'Tarjeta de sellos', base_url('/panel/fidelidad')],
        ['resenas', 'Reseñas', base_url('/panel/resenas')],
        ['referidos', 'Invita y gana', base_url('/panel/referidos')],
    ]
    : [];

$navConfiguracion = [['sedes', 'Sedes', base_url('/panel/sedes')]];
if ($esDueno) {
    $navConfiguracion[] = ['colaboradores', 'Colaboradores', base_url('/panel/colaboradores')];
}
// "Mi cuenta" no es un módulo de negocio: se separa del resto (divisor propio,
// ver el <span class="pq-nav-separador"> antes de pintarla) en vez de mezclarse
// con Sedes/Colaboradores dentro de Configuración. "Plan" va con ella por lo
// mismo (es plata del negocio, no catálogo ni operación) y solo para el
// dueño: un colaborador no puede cambiarlo (ver PanelController::plan).
$navCuenta = [['cuenta', 'Mi cuenta', base_url('/panel/cuenta')]];
if ($esDueno) {
    $navCuenta[] = ['plan', 'Plan', base_url('/panel/plan')];
}

// El bottom nav es angosto (3 pestañas en ~390px): la etiqueta larga de
// $navCrecimiento ("Copiloto · Barrio+") se queda solo para el sidebar de
// escritorio, que sí tiene el ancho; aquí va el nombre corto siempre.
$tercerTabMovil = $esDueno
    ? ['copiloto', 'Copiloto', base_url('/panel/copiloto')]
    : ($tipoReservas ? ['servicios', 'Servicios', base_url('/panel/servicios')] : ['productos', 'Menú', base_url('/panel/productos')]);
$navBottomPrincipal = [
    ['panel', 'Panel', base_url('/panel')],
    $tipoReservas ? ['citas', 'Agenda', base_url('/panel/citas')] : ['pedidos', 'Pedidos', base_url('/panel/pedidos')],
    $tercerTabMovil,
];
$clavesBottomPrincipal = array_column($navBottomPrincipal, 0);
$navMas = array_values(array_filter(
    array_merge($navOperacion, $navCrecimiento, $navConfiguracion, $navCuenta),
    fn ($item) => !in_array($item[0], $clavesBottomPrincipal, true)
));

// Un solo renderer de link para el sidebar de escritorio: así aria-current,
// el tooltip del modo colapsado (data-tooltip, CSS puro, sin JS) y la
// etiqueta envuelta en <span> —lo que se oculta al colapsar— salen
// idénticos en los 4 grupos en vez de repetirse a mano cuatro veces.
$pqLinkSidebar = static function (string $clave, string $etiqueta, string $href) use ($pqIcono, $activo): string {
    $esActivo = ($activo ?? '') === $clave;
    return '<a href="' . e($href) . '" class="' . ($esActivo ? 'activo' : '') . '" data-tooltip="' . e($etiqueta) . '"'
        . ($esActivo ? ' aria-current="page"' : '') . '>' . $pqIcono($clave) . '<span class="pq-nav-etiqueta">' . e($etiqueta) . '</span></a>';
};
?>
<?php
// El color del negocio también vive en el panel (insignia de la sede,
// cenefa bajo la cabecera, vitrina del inicio): es el puente con la tienda,
// para que el dueño reconozca su marca. Mismo cálculo que layouts/tienda.php.
$marcaNegocio = color_seguro($negocio['color_marca'] ?? null);
?>
<body class="pq-panel-bg" style="--marca: <?= e($marcaNegocio) ?>; --marca-sobre: <?= e(color_texto_sobre($marcaNegocio)) ?>" data-negocio-id="<?= (int) $negocio['id'] ?>" data-es-reservas="<?= ($negocio['tipo_negocio'] ?? 'pedidos') === 'reservas' ? '1' : '0' ?>">
  <script src="<?= e(base_url('assets/js/panel-sidebar-bootstrap.js')) ?>"></script>
  <div class="pq-shell">
    <div class="pq-topbar">
      <div class="pq-topbar-brand-fila">
        <a href="<?= e(base_url('/panel')) ?>" class="pq-topbar-brand">
          <img class="pq-topbar-brand-completo" src="<?= e(base_url('assets/img/logo-veci-lockup-transparente.png')) ?>" alt="Veci">
          <img class="pq-topbar-brand-isotipo" src="<?= e(base_url('assets/img/icon-192.png')) ?>" alt="Veci" width="28" height="28">
        </a>
        <button type="button" id="pq-sidebar-toggle" class="pq-sidebar-toggle" aria-label="Colapsar menú" aria-pressed="false" title="Colapsar menú">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
        </button>
      </div>
      <nav class="pq-topbar-links">
        <span class="pq-nav-grupo">Operación</span>
        <?php foreach ($navOperacion as [$clave, $etiqueta, $href]): ?>
          <?= $pqLinkSidebar($clave, $etiqueta, $href) ?>
        <?php endforeach; ?>

        <?php if ($navCrecimiento !== []): ?>
          <span class="pq-nav-grupo">Crecimiento</span>
          <?php foreach ($navCrecimiento as [$clave, $etiqueta, $href]): ?>
            <?= $pqLinkSidebar($clave, $etiqueta, $href) ?>
          <?php endforeach; ?>
        <?php endif; ?>

        <span class="pq-nav-grupo">Configuración</span>
        <?php foreach ($navConfiguracion as [$clave, $etiqueta, $href]): ?>
          <?= $pqLinkSidebar($clave, $etiqueta, $href) ?>
        <?php endforeach; ?>
      </nav>

      <!-- Empuja la cuenta al fondo del sidebar (flex column) en vez de
           dejarla flotando justo debajo de Configuración cuando el menú no
           llena la altura disponible. -->
      <div class="pq-topbar-spacer"></div>

      <nav class="pq-topbar-links pq-topbar-links-cuenta">
        <span class="pq-nav-separador" role="separator"></span>
        <?php foreach ($navCuenta as [$clave, $etiqueta, $href]): ?>
          <?= $pqLinkSidebar($clave, $etiqueta, $href) ?>
        <?php endforeach; ?>
      </nav>
    </div>

    <nav class="pq-bottomnav">
      <?php foreach ($navBottomPrincipal as [$clave, $etiqueta, $href]): ?>
        <a href="<?= e($href) ?>" class="<?= ($activo ?? '') === $clave ? 'activo' : '' ?>"<?= ($activo ?? '') === $clave ? ' aria-current="page"' : '' ?>><?= $pqIcono($clave) ?><span><?= e($etiqueta) ?></span></a>
      <?php endforeach; ?>
      <details class="pq-bottomnav-mas">
        <summary class="<?= in_array($activo ?? '', array_column($navMas, 0), true) ? 'activo' : '' ?>"><?= $pqIcono('mas') ?><span>Más</span></summary>
        <div class="pq-bottomnav-mas-panel">
          <?php foreach ($navMas as [$clave, $etiqueta, $href]): ?>
            <a href="<?= e($href) ?>" class="<?= ($activo ?? '') === $clave ? 'activo' : '' ?>"<?= ($activo ?? '') === $clave ? ' aria-current="page"' : '' ?>><?= $pqIcono($clave) ?><?= e($etiqueta) ?></a>
          <?php endforeach; ?>
        </div>
      </details>
    </nav>

    <div class="pq-shell-main">
      <?php
      $sedesAcceso = \App\Auth::sedesAccesibles($negocio);
      $inicialSede = static fn (array $s): string => mb_strtoupper(mb_substr((string) ($s['inicial'] ?? $s['nombre']), 0, 1));
      ?>
      <header class="pq-header">
        <div class="pq-header-espacio"></div>
        <?php if (count($sedesAcceso) > 1): ?>
          <details class="pq-switcher">
            <summary class="pq-switcher-boton" aria-haspopup="true">
              <span class="pq-switcher-avatar"><?= e($inicialSede($negocio)) ?></span>
              <span class="pq-switcher-texto">
                <span class="pq-switcher-nombre"><?= e($negocio['nombre']) ?></span>
                <span class="pq-switcher-sub">Sede activa</span>
              </span>
              <svg class="pq-switcher-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </summary>
            <div class="pq-switcher-panel" role="menu">
              <span class="pq-switcher-panel-titulo">Cambiar sede</span>
              <?php foreach ($sedesAcceso as $s): ?>
                <?php $esActiva = (int) $s['id'] === (int) $negocio['id']; ?>
                <?php if ($esActiva): ?>
                  <div class="pq-switcher-item pq-switcher-item-activa">
                    <span class="pq-switcher-avatar pq-switcher-avatar-chico"><?= e($inicialSede($s)) ?></span>
                    <span class="pq-switcher-item-nombre"><?= e($s['nombre']) ?></span>
                    <svg class="pq-switcher-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                  </div>
                <?php else: ?>
                  <form method="post" action="<?= e(base_url('/panel/sede/cambiar')) ?>">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="sede_id" value="<?= (int) $s['id'] ?>">
                    <input type="hidden" name="volver" value="<?= e($_SERVER['REQUEST_URI'] ?? base_url('/panel')) ?>">
                    <button type="submit" class="pq-switcher-item" role="menuitem">
                      <span class="pq-switcher-avatar pq-switcher-avatar-chico"><?= e($inicialSede($s)) ?></span>
                      <span class="pq-switcher-item-nombre"><?= e($s['nombre']) ?></span>
                    </button>
                  </form>
                <?php endif; ?>
              <?php endforeach; ?>
              <span class="pq-switcher-divisor" role="separator"></span>
              <a href="<?= e(base_url('/panel/sedes')) ?>" class="pq-switcher-admin">+ Administrar sedes</a>
            </div>
          </details>
        <?php else: ?>
          <div class="pq-switcher-boton pq-switcher-boton-estatico">
            <span class="pq-switcher-avatar"><?= e($inicialSede($negocio)) ?></span>
            <span class="pq-switcher-texto">
              <span class="pq-switcher-nombre"><?= e($negocio['nombre']) ?></span>
              <span class="pq-switcher-sub">Sede activa</span>
            </span>
          </div>
        <?php endif; ?>
      </header>

      <div class="pq-content">
        <?= $contenido ?>
      </div>

    </div>
  </div>
  <script src="<?= e(base_url('assets/js/confirmar.js')) ?>" defer></script>
  <script src="<?= e(base_url('assets/js/interacciones.js')) ?>" defer></script>
  <script src="<?= e(base_url('assets/js/panel-sidebar.js')) ?>" defer></script>
  <script src="<?= e(base_url('assets/js/panel-notificaciones.js')) ?>" defer></script>
  <script src="<?= e(base_url('assets/js/panel-push.js')) ?>" defer></script>
  <?php if (!$tipoReservas): ?>
  <script src="<?= e(base_url('assets/js/tiendas.js')) ?>" defer></script>
  <?php endif; ?>
</body>
</html>
