<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$pageTitle = $pageTitle ?? 'Tripy — планировщик путешествий';
$activePage = $activePage ?? 'home';
$pageStylesheet = $pageStylesheet ?? null;
$pageScript = $pageScript ?? null;
$flash = takeFlash();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Tripy — учебный веб-проект персонального планировщика путешествий.">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="theme-color" content="#ffd428">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=20261027-20">
    <?php if ($pageStylesheet): ?><link rel="stylesheet" href="<?= htmlspecialchars($pageStylesheet, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <script src="assets/js/main.js?v=20261027-18" defer></script>
    <?php if ($pageScript): ?><script src="<?= htmlspecialchars($pageScript, ENT_QUOTES, 'UTF-8') ?>" defer></script><?php endif; ?>
</head>
<body>
    <a class="skip-link" href="#main-content">Перейти к содержимому</a>

    <aside class="sidebar" id="app-sidebar" aria-label="Навигация Tripy">
        <div class="sidebar__top">
            <a class="tripy-brand" href="index.php" aria-label="Tripy — главная страница">
                <img class="tripy-brand__icon" src="assets/images/brand/tripy-globe.png" alt="">
                <span class="tripy-brand__name">tripy</span>
            </a>
            <button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-label="Раскрыть боковое меню" aria-expanded="false" aria-controls="sidebar-navigation">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
            </button>
        </div>

        <nav class="sidebar-nav" id="sidebar-navigation" aria-label="Основная навигация">
            <a class="sidebar-link <?= $activePage === 'home' ? 'is-active' : '' ?>" href="index.php" data-tooltip="Главная">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>
                <span>Главная</span>
            </a>
            <a class="sidebar-link <?= $activePage === 'trips' ? 'is-active' : '' ?>" href="trips.php" data-tooltip="Путешествия">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 6.5 8.5 4l7 2.5L21 4v13.5L15.5 20l-7-2.5L3 20Z"/><path d="M8.5 4v13.5M15.5 6.5V20"/></svg>
                <span>Путешествия</span>
            </a>
            <a class="sidebar-link <?= $activePage === 'gallery' ? 'is-active' : '' ?>" href="gallery.php" data-tooltip="Галерея">
                <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.5-4.5L7 20"/></svg>
                <span>Галерея</span>
            </a>
        </nav>

        <button class="sidebar-info" id="project-info-open" type="button" data-tooltip="О проекте" aria-haspopup="dialog">
            <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
            <span>О проекте</span>
        </button>
    </aside>

    <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>

    <div class="app-shell">
        <?php if ($flash): ?><div class="app-flash app-flash--<?= e($flash['type']) ?>" role="status" data-app-flash><?= e($flash['message']) ?></div><?php endif; ?>
