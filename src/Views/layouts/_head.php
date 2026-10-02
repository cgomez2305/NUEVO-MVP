<meta charset="utf-8">
<title><?= e($titulo ?? 'Veci') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="icon" type="image/png" href="<?= e(base_url('assets/img/icon-192.png')) ?>">
<?php if (!empty($metaDescripcion)): ?>
<meta name="description" content="<?= e($metaDescripcion) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Veci">
<meta property="og:title" content="<?= e($ogTitulo ?? ($titulo ?? 'Veci')) ?>">
<meta property="og:description" content="<?= e($metaDescripcion) ?>">
<?php if (!empty($canonicalUrl)): ?>
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php endif; ?>
<?php endif; ?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/fuentes.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
