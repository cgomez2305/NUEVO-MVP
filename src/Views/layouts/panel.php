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
    'servicios'      => '<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="M3 16l9 5 9-5M3 12l9 5 9-5"/>',
    'empleados'      => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="18" cy="8.5" r="2.6"/><path d="M16.5 14.3c2.3.5 4 2.5 4.5 5.7"/>',
    'horario'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    'productos'      => '<path d="M4 8a2 2 0 0 1 2-2h1.5l1-2h7l1 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="3.4"/>',
    'sedes'          => '<path d="M12 21s7-6.4 7-11.5a7 7 0 1 0-14 0C5 14.6 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.6"/>',
    'colaboradores'  => '<circle cx="8" cy="9" r="3"/><path d="M2 20c0-3 2.7-5.5 6-5.5s6 2.5 6 5.5"/><circle cx="17.5" cy="8" r="2.3"/><path d="M15.8 14.7c2.4.4 4.2 2.5 4.2 5.3"/>',
    'cuenta'         => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c1-4 4-6 7.5-6s6.5 2 7.5 6"/>',
    'plan'           => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/>',
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

$navOperacion = [['panel', 'Panel', base_url('/panel')]];
if ($tipoReservas) {
    $navOperacion[] = ['citas', 'Agenda', base_url('/panel/citas')];
    $navOperacion[] = ['recordatorios', 'Recordatorios', base_url('/panel/recordatorios')];
    $navOperacion[] = ['servicios', 'Servicios', base_url('/panel/servicios')];
    if ($esDueno) {
        $navOperacion[] = ['empleados', 'Empleados', base_url('/panel/empleados')];
        $navOperacion[] = ['horario', 'Horario', base_url('/panel/horario')];
    }
} else {
    $navOperacion[] = ['pedidos', 'Pedidos', base_url('/panel/pedidos')];
    $navOperacion[] = ['productos', 'Menú', base_url('/panel/productos')];
    // Antes solo los negocios de reservas podían poner horario: una tienda
    // de pedidos nunca mostraba "Abierto ahora" ni su hora de almuerzo.
    if ($esDueno) {
        $navOperacion[] = ['horario', 'Horario', base_url('/panel/horario')];
    }
}

// Si el plan no incluye el copiloto (Gratis), el link sigue llevando ahí —
// el controller explica por qué y manda a /panel/plan — pero la etiqueta ya
// lo avisa de una vez en vez de dejar que se sienta como un link roto.
$navCrecimiento = $esDueno
    ? [['copiloto', empty($negocio['incluye_copiloto']) ? 'Copiloto · Barrio+' : 'Copiloto', base_url('/panel/copiloto')]]
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
</body>
</html>
