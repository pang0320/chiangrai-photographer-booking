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
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
    <link rel="shortcut icon" href="/assets/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] } } } };
    </script>
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
