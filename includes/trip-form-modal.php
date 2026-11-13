<?php
$formTrip = $formTrip ?? null;
$hasBudget = $formTrip && $formTrip['budget_value'] !== null && $formTrip['budget_value'] !== '';
$hasCustomCover = $formTrip && !empty($formTrip['image']) && $formTrip['image'] !== 'assets/images/trip-cover-placeholder.png';
$formCity = $formTrip['city'] ?? '';
$formCountry = $formTrip['country'] ?? '';
?>
<dialog class="form-dialog trip-form-dialog" id="trip-form-dialog" aria-labelledby="trip-form-title">
  <form action="actions/trip-save.php" method="post" enctype="multipart/form-data" novalidate data-trip-form>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="trip_id" value="<?= (int) ($formTrip['id'] ?? 0) ?>">
    <input type="hidden" name="start_date" value="<?= e($formTrip['start_date'] ?? '') ?>">
    <input type="hidden" name="end_date" value="<?= e($formTrip['end_date'] ?? '') ?>">

    <div class="form-dialog__head">
      <h2 id="trip-form-title"><?= $formTrip ? 'Редактировать путешествие' : 'Новое путешествие' ?></h2>
      <button class="dialog-close" type="button" data-trip-form-close aria-label="Закрыть"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
    </div>

    <div class="form-dialog__body trip-form-dialog__body">
      <p class="trip-form-error" data-trip-form-error hidden></p>
      <label class="minimal-field">
        <span>Город</span>
        <input name="city" maxlength="140" required value="<?= e($formCity) ?>" placeholder="Город" autocomplete="off">
        <small class="field-error"></small>
      </label>
      <label class="minimal-field">
        <span>Страна</span>
        <input name="country" maxlength="180" required value="<?= e($formCountry) ?>" placeholder="Страна" autocomplete="off">
        <small class="field-error"></small>
      </label>

      <div class="date-range-field">
        <button class="date-range-field__control" type="button" aria-expanded="false" aria-controls="trip-date-picker">
          <span class="date-range-field__label">Даты путешествия</span>
          <span class="date-range-field__value" data-date-range-value>Даты путешествия</span>
          <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
        </button>
        <small class="field-error" data-date-error></small>
        <div class="date-range-picker" id="trip-date-picker" hidden>
          <div class="date-range-picker__head">
            <button type="button" data-calendar-prev aria-label="Предыдущий месяц"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg></button>
            <strong data-calendar-title></strong>
            <button type="button" data-calendar-next aria-label="Следующий месяц"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></button>
          </div>
          <div class="date-range-picker__week" aria-hidden="true"><span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span></div>
          <div class="date-range-picker__days" data-calendar-days></div>
          <p data-calendar-hint>Выберите дату начала, затем дату окончания</p>
        </div>
      </div>

      <div class="trip-option <?= $hasBudget ? 'is-open' : '' ?>" data-trip-option="budget">
        <button class="trip-option__toggle" type="button" aria-expanded="<?= $hasBudget ? 'true' : 'false' ?>"><span>+</span> Добавить бюджет</button>
        <div class="trip-option__content" <?= $hasBudget ? '' : 'hidden' ?>>
          <label class="minimal-field minimal-field--amount"><span>Бюджет, ₽</span><input name="budget_limit" type="number" min="0" step="0.01" value="<?= $hasBudget ? e($formTrip['budget_value']) : '' ?>" placeholder="Бюджет, ₽"><b>₽</b></label>
        </div>
      </div>

      <div class="trip-option <?= $hasCustomCover ? 'is-open' : '' ?>" data-trip-option="cover">
        <button class="trip-option__toggle" type="button" aria-expanded="<?= $hasCustomCover ? 'true' : 'false' ?>"><span>+</span> Выбрать обложку</button>
        <div class="trip-option__content" <?= $hasCustomCover ? '' : 'hidden' ?>>
          <label class="trip-cover-upload" for="trip-cover">
            <img data-cover-preview src="<?= e($formTrip['image'] ?? 'assets/images/trip-cover-placeholder.png') ?>" alt="Предварительный просмотр обложки">
            <span><strong><?= $hasCustomCover ? 'Заменить обложку' : 'Выбрать фотографию' ?></strong><small>JPG, PNG или WebP · до 8 МБ</small></span>
            <input id="trip-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp">
          </label>
          <p class="upload-error" data-cover-error hidden></p>
        </div>
      </div>
    </div>

    <div class="form-dialog__footer trip-form-dialog__footer">
      <button class="button button--primary" type="submit"><?= $formTrip ? 'Сохранить изменения' : 'Создать путешествие' ?></button>
      <button class="button button--neutral" type="button" data-trip-form-close>Отмена</button>
    </div>
  </form>
</dialog>
