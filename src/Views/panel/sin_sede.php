<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= e($titulo ?? 'Veci') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
</head>
<body class="pq-panel-bg">
  <div class="pq-shell" style="justify-content: center; align-items: center; display: flex; text-align: center; min-height: 100vh; padding: 24px">
    <div>
      <h1 class="pq-h1">Hola, <?= e($usuario['nombre']) ?></h1>
      <p class="pq-lead" style="margin-top: 10px">Todavía no tienes ninguna sede asignada. Pídele al dueño del negocio que te asigne una desde Sedes → Colaboradores.</p>
      <form method="post" action="<?= e(base_url('/logout')) ?>" style="margin-top: 20px">
        <?= csrf_campo() ?>
        <button type="submit" class="pq-btn pq-btn-ghost">Cerrar sesión</button>
      </form>
    </div>
  </div>
</body>
</html>
