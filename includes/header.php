<?php
require_once __DIR__ . '/functions.php';
if (!isset($pageTitle)) {
    $pageTitle = APP_NAME;
}
$headerRequestUri = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $headerRequestUri = $_SERVER['REQUEST_URI'];
}
$currentPath = parse_url($headerRequestUri, PHP_URL_PATH);
if (!$currentPath) {
    $currentPath = '/';
}
$isWorkspacePage = preg_match('#^/(admin|customer|photographer)/#', $currentPath) === 1;
$shouldShowAdminOverview = preg_match('#^/admin/#', $currentPath) === 1 && $currentPath !== '/admin/dashboard.php';
$shouldLoadDataTables = $isWorkspacePage || preg_match('#^/admin/#', $currentPath) === 1;
$pageMetaDescription = isset($pageMetaDescription) ? trim((string)$pageMetaDescription) : '';
$pageMetaKeywords = isset($pageMetaKeywords) ? trim((string)$pageMetaKeywords) : '';
$pageRobots = isset($pageRobots) ? trim((string)$pageRobots) : ($isWorkspacePage ? 'noindex, nofollow' : 'index, follow');
$pageCanonical = isset($pageCanonical) ? trim((string)$pageCanonical) : rtrim(APP_URL, '/') . ($currentPath === '/index.php' ? '/' : $currentPath);
$pageOgTitle = isset($pageOgTitle) ? trim((string)$pageOgTitle) : (string)$pageTitle;
$pageOgDescription = isset($pageOgDescription) ? trim((string)$pageOgDescription) : $pageMetaDescription;
$pageOgImage = isset($pageOgImage) ? trim((string)$pageOgImage) : rtrim(APP_URL, '/') . '/assets/uploads/seed/photo-1511285560929-80b456fea0bc.jpg';
$pageOgType = isset($pageOgType) ? trim((string)$pageOgType) : 'website';
$pageJsonLd = isset($pageJsonLd) && is_array($pageJsonLd) ? $pageJsonLd : [];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?></title>
    <?php if ($pageMetaDescription !== ''): ?>
        <meta name="description" content="<?= h($pageMetaDescription) ?>">
    <?php endif; ?>
    <?php if ($pageMetaKeywords !== ''): ?>
        <meta name="keywords" content="<?= h($pageMetaKeywords) ?>">
    <?php endif; ?>
    <meta name="robots" content="<?= h($pageRobots) ?>">
    <link rel="canonical" href="<?= h($pageCanonical) ?>">
    <meta property="og:locale" content="th_TH">
    <meta property="og:site_name" content="<?= h(APP_NAME) ?>">
    <meta property="og:type" content="<?= h($pageOgType) ?>">
    <meta property="og:title" content="<?= h($pageOgTitle) ?>">
    <?php if ($pageOgDescription !== ''): ?>
        <meta property="og:description" content="<?= h($pageOgDescription) ?>">
    <?php endif; ?>
    <meta property="og:url" content="<?= h($pageCanonical) ?>">
    <meta property="og:image" content="<?= h($pageOgImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($pageOgTitle) ?>">
    <?php if ($pageOgDescription !== ''): ?>
        <meta name="twitter:description" content="<?= h($pageOgDescription) ?>">
    <?php endif; ?>
    <meta name="twitter:image" content="<?= h($pageOgImage) ?>">
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
    <link rel="shortcut icon" href="/assets/favicon.svg">
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <?php if ($shouldLoadDataTables): ?>
        <link rel="preconnect" href="https://cdn.datatables.net">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?php if ($shouldLoadDataTables): ?>
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] } } } };
    </script>
    <?php foreach ($pageJsonLd as $jsonLd): ?>
        <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <?php endforeach; ?>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<?php include __DIR__ . '/navbar.php'; ?>
<?php if ($isWorkspacePage): ?>
    <div class="workspace-shell">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <main class="workspace-main">
<?php else: ?>
        <main>
<?php endif; ?>
<?php include __DIR__ . '/breadcrumb.php'; ?>
<?php if ($shouldShowAdminOverview): ?>
    <?php include __DIR__ . '/admin_overview.php'; ?>
<?php endif; ?>
