<?php
declare(strict_types=1);

require __DIR__ . '/includes/trips-data.php';

$tripId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$trip = $tripId ? fetchTrip($tripId) : null;
$tripData = $trip ? fetchTripData($tripId) : ['places' => [], 'tickets' => [], 'expenses' => [], 'photos' => []];

if (!$trip) {
    http_response_code(404);
    $pageTitle = 'Путешествие не найдено — Tripy'; $activePage = 'trips';
    require __DIR__ . '/includes/header.php';
    echo '<main id="main-content"><section class="content-container empty-state"><h2>Путешествие не найдено</h2><p>' . (dbReady() ? 'Запись не существует или недоступна.' : 'Подключение к MySQL пока не настроено.') . '</p><a class="button button--primary" href="trips.php">К путешествиям</a></section></main>';
    require __DIR__ . '/includes/footer.php'; exit;
}

$pageTitle = $trip['city'] . ' — Tripy';
$activePage = 'trips';
$pageStylesheet = 'assets/css/trip-detail.css?v=20261027-6';
$pageScript = 'assets/js/trip-detail.js?v=20261027-7';
require __DIR__ . '/includes/header.php';
?>

<main id="main-content" class="trip-detail" data-trip-id="<?= (int) $trip['id'] ?>" data-budget="<?= e($trip['budget_value'] ?? '') ?>">
    <section class="trip-detail__container content-container" aria-labelledby="trip-title">
        <a class="back-link" href="trips.php"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>Путешествия</a>

        <header class="trip-overview">
            <div class="trip-overview__cover">
                <img src="<?= htmlspecialchars($trip['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($trip['image_alt'], ENT_QUOTES, 'UTF-8') ?>" width="400" height="400">
            </div>
            <div class="trip-overview__content">
                <p class="trip-overview__destination"><?= htmlspecialchars($trip['country'], ENT_QUOTES, 'UTF-8') ?></p>
                <h2 id="trip-title"><?= htmlspecialchars($trip['city'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="trip-overview__dates"><?= htmlspecialchars(tripDateRange($trip['start_date'], $trip['end_date']), ENT_QUOTES, 'UTF-8') ?> <span>·</span> <?= htmlspecialchars(tripDurationLabel($trip['start_date'], $trip['end_date']), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <button class="trip-overview__edit" type="button" data-open-trip-form aria-label="Редактировать путешествие"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg><span>Редактировать</span></button>
        </header>

        <?php if (!dbReady()): ?><p class="demo-mode"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><span><strong>MySQL не настроен.</strong> Заполните config/config.php и импортируйте database/schema.sql.</span></p><?php endif; ?>
        <script id="trip-initial-data" type="application/json"><?= json_encode($tripData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
        <meta name="trip-csrf-token" content="<?= e(csrfToken()) ?>">

        <div class="trip-tabs" role="tablist" aria-label="Разделы путешествия">
            <button class="is-active" type="button" role="tab" aria-selected="true" aria-controls="tickets-panel" data-trip-tab="tickets">Билеты <span id="tickets-count">0</span></button>
            <button type="button" role="tab" aria-selected="false" aria-controls="places-panel" data-trip-tab="places">Места <span id="places-count">0</span></button>
            <button type="button" role="tab" aria-selected="false" aria-controls="expenses-panel" data-trip-tab="expenses">Расходы</button>
            <button type="button" role="tab" aria-selected="false" aria-controls="photos-panel" data-trip-tab="photos">Фото <span id="photos-count">0</span></button>
        </div>

        <section class="trip-panel is-active" id="tickets-panel" role="tabpanel" data-trip-panel="tickets">
            <div class="trip-panel__heading"><div><h3>Билеты</h3><p id="tickets-summary">0 билетов · 0 закреплено</p></div><button class="button button--primary tab-add-button" type="button" data-open-dialog="ticket-dialog">+ Добавить билет</button></div>
            <div class="item-list" id="tickets-list"></div>
        </section>

        <section class="trip-panel" id="places-panel" role="tabpanel" data-trip-panel="places" hidden>
            <div class="trip-panel__heading"><div><h3>Места</h3><p id="places-summary">Посещено 0 из 0 мест</p></div><button class="button button--primary tab-add-button" type="button" data-open-dialog="place-dialog">+ Добавить место</button></div>
            <div class="item-list" id="places-list"></div>
        </section>

        <section class="trip-panel" id="expenses-panel" role="tabpanel" data-trip-panel="expenses" hidden>
            <div class="trip-panel__heading"><div><h3>Расходы</h3><p id="expenses-summary">0 ₽</p></div><button class="button button--primary tab-add-button" type="button" data-open-dialog="expense-dialog">+ Добавить расход</button></div>
            <div class="budget-summary" id="budget-summary">
                <div class="budget-summary__main"><span>Осталось</span><strong id="budget-left"></strong></div>
                <div class="budget-progress" role="progressbar" aria-label="Использование бюджета" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span></span></div>
                <div class="budget-summary__values"><div><span>Бюджет</span><strong id="budget-limit"></strong><button type="button" data-open-budget-dialog aria-label="Изменить бюджет"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg></button></div><div><span>Потрачено</span><strong id="budget-spent"></strong></div></div>
                <p class="budget-warning" id="budget-warning" hidden></p>
            </div>
            <div class="expense-history-heading"><h4>История расходов</h4><span id="expenses-count">0 записей</span></div>
            <div class="item-list" id="expenses-list"></div>
        </section>

        <section class="trip-panel" id="photos-panel" role="tabpanel" data-trip-panel="photos" hidden>
            <div class="trip-panel__heading"><div><h3>Фото</h3><p>Фотографии этого путешествия</p></div><button class="button button--primary tab-add-button" type="button" data-open-dialog="photo-dialog">+ Добавить фото</button></div>
            <div class="trip-photo-grid" id="photos-list"></div>
        </section>
    </section>
</main>

<dialog class="form-dialog" id="place-dialog" aria-labelledby="place-dialog-title">
    <form id="place-form" novalidate>
        <div class="form-dialog__head"><div><p>Маршрут</p><h2 id="place-dialog-title">Добавить место</h2></div><button class="dialog-close" type="button" data-dialog-close aria-label="Закрыть"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
        <div class="form-dialog__body">
            <label class="field"><span>Название места *</span><input id="place-name" name="name" type="text" placeholder="Например, Колизей" required maxlength="120"><small class="field-error"></small></label>
            <label class="field"><span>Категория</span><select id="place-category" name="category"><option value="">Не выбрана</option><option>Достопримечательность</option><option>Кафе или ресторан</option><option>Природа и прогулки</option><option>Другое</option></select></label>
            <label class="field"><span>Адрес или местоположение</span><input id="place-address" name="address" type="text" placeholder="Необязательно" maxlength="255"></label>
            <label class="field"><span>Заметка</span><textarea id="place-note" name="note" rows="3" placeholder="Что важно не забыть"></textarea></label>
            <label class="switch-field"><input id="place-visited" name="visited" type="checkbox"><span aria-hidden="true"></span>Уже посетила это место</label>
        </div>
        <div class="form-dialog__footer"><button class="button button--primary" type="submit" id="place-submit">Сохранить место</button><button class="button button--neutral" type="button" data-dialog-close>Отмена</button></div>
    </form>
</dialog>

<dialog class="form-dialog" id="ticket-dialog" aria-labelledby="ticket-dialog-title">
    <form id="ticket-form" novalidate>
        <div class="form-dialog__head"><div><p>Документы поездки</p><h2 id="ticket-dialog-title">Добавить билет</h2></div><button class="dialog-close" type="button" data-dialog-close aria-label="Закрыть"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
        <div class="form-dialog__body">
            <label class="field"><span>Название билета *</span><input id="ticket-name" name="name" type="text" placeholder="Например, Авиабилет Москва — Рим" required maxlength="140"><small class="field-error"></small></label>
            <label class="upload-field" for="ticket-file"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M12 18v-6M9 15l3-3 3 3"/></svg><strong>Выберите файл *</strong><span id="ticket-file-name">PDF, JPG или PNG · до 10 МБ</span><input id="ticket-file" name="file" type="file" accept="application/pdf,image/jpeg,image/png" required></label>
            <p class="upload-error" id="ticket-upload-error" hidden></p>
            <label class="field"><span>Тип билета</span><select id="ticket-type" name="type"><option>Самолёт</option><option>Поезд</option><option>Автобус</option><option>Музей или мероприятие</option><option>Другое</option></select></label>
            <div class="trip-option ticket-option" data-ticket-option="date"><button class="trip-option__toggle" type="button" data-ticket-option-toggle aria-expanded="false"><span>+</span> Добавить дату использования</button><div class="trip-option__content" hidden>
                <div class="single-date-field" data-single-date-picker><input id="ticket-date" name="date" type="hidden"><button class="single-date-field__control" type="button" data-single-date-control aria-expanded="false"><span data-single-date-value>Дата использования</span><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></button><div class="date-range-picker" data-single-date-popover hidden><div class="date-range-picker__head"><button type="button" data-single-date-prev aria-label="Предыдущий месяц"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg></button><strong data-single-date-title></strong><button type="button" data-single-date-next aria-label="Следующий месяц"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></button></div><div class="date-range-picker__week" aria-hidden="true"><span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span></div><div class="date-range-picker__days" data-single-date-days></div></div></div>
            </div></div>
            <div class="trip-option ticket-option" data-ticket-option="note"><button class="trip-option__toggle" type="button" data-ticket-option-toggle aria-expanded="false"><span>+</span> Добавить примечание</button><div class="trip-option__content" hidden><label class="field"><span>Примечание</span><textarea id="ticket-note" name="note" rows="3" placeholder="Необязательно"></textarea></label></div></div>
            <div class="trip-option ticket-option" data-ticket-option="pinned"><button class="trip-option__toggle" type="button" data-ticket-option-toggle aria-expanded="false"><span>+</span> Закрепить билет</button><div class="trip-option__content" hidden><label class="switch-field"><input id="ticket-pinned" name="pinned" type="checkbox"><span aria-hidden="true"></span>Закрепить билет вверху списка</label></div></div>
            <div class="trip-option ticket-option" data-ticket-option="cost">
                <button class="trip-option__toggle" id="ticket-cost-toggle" type="button" data-ticket-option-toggle aria-expanded="false" aria-controls="ticket-cost-fields"><span>+</span> Добавить стоимость</button>
                <div class="trip-option__content optional-cost__fields" id="ticket-cost-fields" hidden>
                    <label class="field"><span>Стоимость билета</span><span class="amount-input"><input id="ticket-cost" name="cost" type="number" inputmode="decimal" min="0.01" step="0.01" placeholder="0"><b>₽</b></span><small class="field-error"></small></label>
                    <label class="switch-field"><input id="ticket-include-expense" name="includeExpense" type="checkbox"><span aria-hidden="true"></span>Учитывать в расходах</label>
                    <p class="optional-cost__note">Стоимость билета будет добавлена к общим расходам путешествия. Без суммы переключатель недоступен.</p>
                </div>
            </div>
        </div>
        <div class="form-dialog__footer"><button class="button button--primary" type="submit">Сохранить билет</button><button class="button button--neutral" type="button" data-dialog-close>Отмена</button></div>
    </form>
</dialog>

<dialog class="form-dialog form-dialog--expense" id="expense-dialog" aria-labelledby="expense-dialog-title">
    <form id="expense-form" novalidate>
        <div class="form-dialog__head"><div class="expense-dialog-title"><span><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 6h16a2 2 0 0 1 2 2v10H5a2 2 0 0 1-2-2Z"/><path d="M16 11h5v4h-5a2 2 0 0 1 0-4Z"/></svg></span><div><p>Бюджет поездки</p><h2 id="expense-dialog-title">Добавить расход</h2></div></div><button class="dialog-close" type="button" data-dialog-close aria-label="Закрыть"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
        <div class="form-dialog__body">
            <label class="field"><span>Сумма, ₽ *</span><span class="amount-input"><input id="expense-amount" name="amount" type="number" inputmode="decimal" min="0.01" step="0.01" placeholder="0" required><b>₽</b></span><small class="field-error"></small></label>
            <label class="field"><span>Название расхода</span><input id="expense-name" name="name" type="text" placeholder="Расход" maxlength="140"><small class="field-error"></small></label>
            <fieldset class="category-field"><legend>Категория</legend><div class="category-grid">
                <label><input type="radio" name="category" value="Транспорт"><span><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M17.8 19.2 16 11l3.5-3.5c1.5-1.5 2-3.5 1-4.5s-3-.5-4.5 1l-3.5 3.5-8.2-1.8-1.8 1.8 6.5 4-3 3-3-.5-1.5 1.5 4 2 2 4L9 20l-.5-3 3-3 4 6.5Z"/></svg>Транспорт</span></label>
                <label><input type="radio" name="category" value="Проживание"><span><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 18V8M21 18v-6a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v6M3 15h18M7 10V7H4a1 1 0 0 0-1 1v4"/></svg>Проживание</span></label>
                <label><input type="radio" name="category" value="Питание"><span><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 3v8M4 3v5a3 3 0 0 0 6 0V3M7 11v10M17 3v18M17 3c3 2 3 7 0 9"/></svg>Питание</span></label>
                <label><input type="radio" name="category" value="Развлечения"><span><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a3 3 0 0 0 0 6v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a3 3 0 0 0 0-6Z"/></svg>Развлечения</span></label>
                <label><input type="radio" name="category" value="Покупки"><span><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 8h14l-1 13H6Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg>Покупки</span></label>
                <label><input type="radio" name="category" value="Другое"><span><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>Другое</span></label>
            </div></fieldset>
            <div class="trip-option expense-option" data-expense-option="date"><button class="trip-option__toggle" type="button" data-expense-option-toggle aria-expanded="false"><span>+</span> Добавить дату</button><div class="trip-option__content" hidden>
                <div class="single-date-field" data-single-date-picker><input id="expense-date" name="date" type="hidden"><button class="single-date-field__control" type="button" data-single-date-control aria-expanded="false"><span data-single-date-value>Дата расхода</span><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></button><div class="date-range-picker" data-single-date-popover hidden><div class="date-range-picker__head"><button type="button" data-single-date-prev aria-label="Предыдущий месяц"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg></button><strong data-single-date-title></strong><button type="button" data-single-date-next aria-label="Следующий месяц"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></button></div><div class="date-range-picker__week" aria-hidden="true"><span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span></div><div class="date-range-picker__days" data-single-date-days></div></div></div>
            </div></div>
            <div class="trip-option expense-option" data-expense-option="note"><button class="trip-option__toggle" type="button" data-expense-option-toggle aria-expanded="false"><span>+</span> Добавить примечание</button><div class="trip-option__content" hidden><label class="field"><span>Примечание</span><textarea id="expense-note" name="note" rows="3" placeholder="Например, ужин на двоих"></textarea></label></div></div>
        </div>
        <div class="form-dialog__footer"><button class="button button--primary" type="submit" id="expense-submit">Добавить расход</button><button class="button button--neutral" type="button" data-dialog-close>Отмена</button></div>
    </form>
</dialog>

<dialog class="form-dialog" id="photo-dialog" aria-labelledby="photo-dialog-title">
    <form id="photo-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="tripId" value="<?= (int) $trip['id'] ?>">
        <div class="form-dialog__head"><h2 id="photo-dialog-title">Добавить фото</h2><button class="dialog-close" type="button" data-dialog-close aria-label="Закрыть"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
        <div class="form-dialog__body">
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
        <div class="form-dialog__footer"><button class="button button--primary" type="submit">Добавить фото</button><button class="button button--neutral" type="button" data-dialog-close>Отмена</button></div>
    </form>
</dialog>

<dialog class="form-dialog budget-dialog" id="budget-dialog" aria-labelledby="budget-dialog-title">
    <form id="budget-form" novalidate>
        <div class="form-dialog__head"><h2 id="budget-dialog-title">Изменить бюджет</h2><button class="dialog-close" type="button" data-dialog-close aria-label="Закрыть"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
        <div class="form-dialog__body"><label class="field"><span>Бюджет, ₽</span><span class="amount-input"><input name="budget" type="number" min="0" step="0.01" value="<?= e($trip['budget_value'] ?? '') ?>" placeholder="Оставьте пустым, если бюджет не указан"><b>₽</b></span><small class="field-error"></small></label></div>
        <div class="form-dialog__footer"><button class="button button--primary" type="submit">Сохранить бюджет</button><button class="button button--neutral" type="button" data-dialog-close>Отмена</button></div>
    </form>
</dialog>

<dialog class="confirm-dialog" id="confirm-dialog" aria-labelledby="confirm-title"><div class="confirm-dialog__icon"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15M10 10v7M14 10v7"/></svg></div><h2 id="confirm-title">Удалить запись?</h2><p id="confirm-message">Действие нельзя отменить в текущей сессии.</p><div><button class="button button--neutral" type="button" data-dialog-close>Отмена</button><button class="button button--danger" id="confirm-delete" type="button">Удалить</button></div></dialog>

<dialog class="ticket-viewer" id="ticket-viewer" aria-labelledby="ticket-viewer-title"><button class="dialog-close" type="button" data-dialog-close aria-label="Закрыть"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button><div class="ticket-viewer__preview"><iframe id="ticket-preview-pdf" title="Предпросмотр билета PDF" hidden></iframe><img id="ticket-preview-image" src="" alt="" hidden><div id="ticket-preview-fallback" hidden><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg><span>Предпросмотр этого формата недоступен</span></div></div><h2 id="ticket-viewer-title"></h2><p id="ticket-viewer-meta"></p><a class="button button--neutral" id="ticket-download" href="#">Скачать оригинал</a></dialog>

<dialog class="photo-viewer" id="trip-photo-viewer" aria-labelledby="trip-photo-viewer-title"><button class="dialog-close" type="button" data-dialog-close aria-label="Закрыть"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button><img id="trip-photo-viewer-image" src="" alt=""><h2 id="trip-photo-viewer-title">Фотография путешествия</h2></dialog>

<div class="toast" id="trip-toast" role="status" aria-live="polite" hidden></div>

<?php $formTrip = $trip; require __DIR__ . '/includes/trip-form-modal.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
