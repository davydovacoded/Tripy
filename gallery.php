<?php
declare(strict_types=1);

require __DIR__ . '/includes/trips-data.php';

$photoGroups = fetchPhotoGroups();

$pageTitle = 'Галерея — Tripy';
$activePage = 'gallery';
$pageStylesheet = 'assets/css/gallery.css?v=20261026-1';
$pageScript = 'assets/js/gallery.js?v=20261027-4';
require __DIR__ . '/includes/header.php';
?>

<main id="main-content" class="gallery-page">
    <section class="gallery-page__container content-container" aria-labelledby="gallery-title">
        <div class="gallery-intro">
            <div><h2 id="gallery-title">Фотографии путешествий</h2><p>Воспоминания будут собраны по поездкам, а не смешаны в общей ленте.</p></div>
            <button class="button button--primary" type="button" data-open-photo-dialog><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>Добавить фото</button>
        </div>

        <?php if ($photoGroups === []): ?>
            <div class="gallery-empty">
                <span class="gallery-empty__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.5-4.5L7 20"/></svg></span>
                <h3>Здесь появятся фотографии ваших путешествий</h3>
                <p>Выберите поездку и добавьте первый снимок.</p>
                <button class="button button--primary" type="button" data-open-photo-dialog>Добавить фотографию</button>
            </div>
        <?php else: ?>
            <div class="gallery-groups">
                <?php foreach ($photoGroups as $group): ?>
                    <section class="gallery-group" aria-labelledby="gallery-trip-<?= (int) $group['trip']['id'] ?>">
                        <div class="gallery-group__heading">
                            <div><h3 id="gallery-trip-<?= (int) $group['trip']['id'] ?>"><?= htmlspecialchars($group['trip']['title'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($group['trip']['destination'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(tripDateRange($group['trip']['start_date'], $group['trip']['end_date']), ENT_QUOTES, 'UTF-8') ?></p></div>
                            <button class="inline-photo-action" type="button" data-open-photo-dialog data-trip-id="<?= (int) $group['trip']['id'] ?>">+ Добавить фото</button>
                        </div>
                        <div class="gallery-grid">
                            <?php foreach ($group['photos'] as $photo): ?>
                                <button class="gallery-photo" type="button" data-photo-src="<?= htmlspecialchars($photo['file_path'], ENT_QUOTES, 'UTF-8') ?>" data-photo-caption="<?= htmlspecialchars($photo['caption'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><img src="<?= htmlspecialchars($photo['file_path'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($photo['caption'] ?? 'Фотография путешествия', ENT_QUOTES, 'UTF-8') ?>" loading="lazy"></button>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<dialog class="form-dialog gallery-dialog" id="photo-dialog" aria-labelledby="photo-dialog-title">
    <form id="photo-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="form-dialog__head"><div><p>Галерея Tripy</p><h2 id="photo-dialog-title">Добавить фотографию</h2></div><button class="dialog-close" type="button" data-dialog-close aria-label="Закрыть"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
        <div class="form-dialog__body">
            <label class="field"><span>Путешествие *</span><select id="photo-trip" name="tripId" required><option value="">Выберите путешествие</option><?php foreach ($trips as $trip): ?><option value="<?= (int) $trip['id'] ?>"><?= htmlspecialchars($trip['city'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($trip['country'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><small class="field-error"></small></label>
            <label class="upload-field" for="photo-file"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.5-4.5L7 20"/></svg><strong>Выберите фотографию *</strong><span id="photo-file-name">JPG, PNG или WebP · до 10 МБ</span><input id="photo-file" name="file" type="file" accept="image/jpeg,image/png,image/webp" required></label>
            <p class="upload-error" id="photo-upload-error" hidden></p>
            <div class="trip-option photo-option" data-photo-option>
                <button class="trip-option__toggle" type="button" data-photo-option-toggle aria-expanded="false"><span>+</span> Добавить название</button>
                <div class="trip-option__content" hidden><label class="field"><span>Название или подпись</span><input name="caption" type="text" maxlength="180" placeholder="Название фотографии"></label></div>
            </div>
            <div class="trip-option photo-option" data-photo-option>
                <button class="trip-option__toggle" type="button" data-photo-option-toggle aria-expanded="false"><span>+</span> Добавить дату</button>
                <div class="trip-option__content" hidden><div class="single-date-field" data-single-date-picker><input id="photo-date" name="photoDate" type="hidden"><button class="single-date-field__control" type="button" data-single-date-control aria-expanded="false"><span data-single-date-value>Дата фотографии</span><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></button><div class="date-range-picker" data-single-date-popover hidden><div class="date-range-picker__head"><button type="button" data-single-date-prev aria-label="Предыдущий месяц"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg></button><strong data-single-date-title></strong><button type="button" data-single-date-next aria-label="Следующий месяц"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></button></div><div class="date-range-picker__week" aria-hidden="true"><span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span></div><div class="date-range-picker__days" data-single-date-days></div></div></div></div>
            </div>
            <div class="trip-option photo-option" data-photo-option>
                <button class="trip-option__toggle" type="button" data-photo-option-toggle aria-expanded="false"><span>+</span> Добавить описание</button>
                <div class="trip-option__content" hidden><label class="field"><span>Описание</span><textarea name="description" rows="3" placeholder="Описание фотографии"></textarea></label></div>
            </div>
        </div>
        <div class="form-dialog__footer"><button class="button button--neutral" type="button" data-dialog-close>Отмена</button><button class="button button--primary" type="submit">Сохранить фотографию</button></div>
    </form>
</dialog>

<dialog class="photo-viewer" id="photo-viewer" aria-labelledby="photo-viewer-title"><button class="dialog-close" type="button" data-dialog-close aria-label="Закрыть"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button><img id="photo-viewer-image" src="" alt=""><h2 id="photo-viewer-title">Фотография путешествия</h2></dialog>
<div class="toast" id="gallery-toast" role="status" aria-live="polite" hidden></div>

<?php require __DIR__ . '/includes/footer.php'; ?>
