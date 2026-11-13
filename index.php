<?php
declare(strict_types=1);

require __DIR__ . '/includes/trips-data.php';
$stats = fetchDashboardStats();
$latestPhotos = fetchLatestPhotos(4);

$pageTitle = 'Главная — Tripy';
$activePage = 'home';
require __DIR__ . '/includes/header.php';
?>

<main id="main-content">
    <section class="hero content-container" aria-labelledby="hero-title">
        <div class="hero__content">
            <h2 id="hero-title">Путешествия<br>начинаются здесь</h2>
            <p>Планируйте поездки, сохраняйте интересные места, управляйте бюджетом и собирайте воспоминания.</p>
            <button class="button button--dark" type="button" data-open-trip-form>Создать путешествие <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 17 17 7M8 7h9v9"/></svg></button>
        </div>
        <div class="hero__visual" aria-hidden="true"><img src="assets/images/hero-travel-new.png" alt=""></div>
    </section>

    <section class="stats content-container" aria-labelledby="stats-title">
        <div class="section-heading section-heading--stats"><h2 id="stats-title">Всё важное в одном месте</h2></div>
        <div class="stats__grid">
            <article class="stat-card stat-card--accent"><span class="stat-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 6.5 8.5 4l7 2.5L21 4v13.5L15.5 20l-7-2.5L3 20Z"/><path d="M8.5 4v13.5M15.5 6.5V20"/></svg></span><p>Всего путешествий</p><strong><?= (int) $stats['trips'] ?></strong><span><?= (int) $stats['upcoming'] ?> впереди</span></article>
            <article class="stat-card"><span class="stat-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 6h16a2 2 0 0 1 2 2v10H5a2 2 0 0 1-2-2Z"/><path d="M16 11h5v4h-5a2 2 0 0 1 0-4Z"/></svg></span><p>Посещено стран</p><strong><?= (int) $stats['countries'] ?></strong><span>по завершённым поездкам</span></article>
            <article class="stat-card"><span class="stat-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span><p>Посещено мест</p><strong><?= (int) $stats['visited'] ?></strong><span>из <?= (int) $stats['places'] ?> запланированных</span></article>
            <article class="stat-card"><span class="stat-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.5-4.5L7 20"/></svg></span><p>Фотографии</p><strong><?= (int) $stats['photos'] ?></strong><span>сохранено в галерее</span></article>
        </div>
    </section>

    <section class="moments content-container" aria-labelledby="moments-title">
        <div class="section-heading">
            <h2 id="moments-title">Моменты из путешествий</h2>
            <a class="section-link" href="gallery.php">Все фотографии <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>

        <div class="moments__scroller">
            <div class="moments__grid">
                <?php for ($moment = 0; $moment < 4; $moment++): $photo = $latestPhotos[$moment] ?? null; ?>
                    <?php if ($photo): ?><article class="moment-card" data-has-photo="true"><img src="<?= e($photo['file_path']) ?>" alt="<?= e($photo['caption'] ?: $photo['title']) ?>"><div class="moment-card__caption"><strong><?= e($photo['caption'] ?: $photo['destination']) ?></strong><time><?= e($photo['photo_date'] ?? '') ?></time></div></article>
                    <?php else: ?><article class="moment-card moment-card--placeholder" data-has-photo="false" aria-label="Место для будущей фотографии"><div class="moment-card__caption" hidden><strong></strong><time></time></div></article><?php endif; ?>
                <?php endfor; ?>

                <a class="moment-card moment-card--add" href="gallery.php" aria-label="Перейти в галерею, чтобы добавить фотографию">
                    <span class="moment-card__plus" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
                    <strong>Добавить фото</strong>
                </a>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/includes/trip-form-modal.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
