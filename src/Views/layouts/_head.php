<meta charset="utf-8">
<title><?= e($titulo ?? 'Veci') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Serif&family=JetBrains+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&family=Bricolage+Grotesque:wght@400;500;600;700&family=Inter+Tight:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
