<?php
declare(strict_types=1);

require __DIR__ . '/includes/trips-data.php';

$pageTitle = 'Путешествия — Tripy';
$statusCounts = ['upcoming' => 0, 'completed' => 0];
foreach ($trips as $item) { $key = tripStatus($item['start_date'], $item['end_date'])['key']; if (isset($statusCounts[$key])) $statusCounts[$key]++; }
$activePage = 'trips';
require __DIR__ . '/includes/header.php';
?>

<main id="main-content">
    <section class="trips trips--page content-container" aria-labelledby="trips-title">
        <div class="section-heading"><h2 id="trips-title">Путешествия</h2><button class="button button--primary" type="button" data-open-trip-form><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>Добавить путешествие</button></div>
        <div class="trips__toolbar" aria-label="Фильтры путешествий">
            <div class="filter-tabs" role="group" aria-label="Фильтр по статусу">
                <button class="filter-tab is-active" type="button" data-filter="all" aria-pressed="true">Все <span><?= count($trips) ?></span></button>
                <button class="filter-tab" type="button" data-filter="upcoming" aria-pressed="false">Предстоящие <span><?= $statusCounts['upcoming'] ?></span></button>
                <button class="filter-tab" type="button" data-filter="completed" aria-pressed="false">Завершённые <span><?= $statusCounts['completed'] ?></span></button>
            </div>
            <label class="sort-control"><span>Сортировка</span><select id="trip-sort"><option value="newest">Сначала новые</option><option value="oldest">Сначала ранние</option></select></label>
        </div>

        <div class="trip-grid" id="trip-grid" aria-live="polite">
            <?php foreach ($trips as $trip): ?>
                <?php
                $start = tripDateParts($trip['start_date'], $monthNames);
                $end = tripDateParts($trip['end_date'], $monthNames);
                $status = tripStatus($trip['start_date'], $trip['end_date']);
                $duration = tripDurationLabel($trip['start_date'], $trip['end_date']);
                $transportIcon = $trip['transport'] === 'train' ? 'assets/icons/train.svg' : 'assets/icons/plane.svg';
                ?>
                <article class="trip-ticket" data-status="<?= htmlspecialchars($status['key'], ENT_QUOTES, 'UTF-8') ?>" data-date="<?= htmlspecialchars($start['iso'], ENT_QUOTES, 'UTF-8') ?>" data-trip-url="trip.php?id=<?= (int) $trip['id'] ?>">
                    <div class="trip-ticket__main">
                        <div class="ticket-label">
                            <span><img src="<?= htmlspecialchars($transportIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" aria-hidden="true">Путешествие</span>
                            <button class="icon-button js-actions" type="button" aria-label="Действия с поездкой <?= htmlspecialchars($trip['city'], ENT_QUOTES, 'UTF-8') ?>" aria-expanded="false"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg></button>
                            <div class="card-menu" hidden><a href="trip.php?id=<?= (int) $trip['id'] ?>&edit=1">Редактировать</a><form action="actions/trip-delete.php" method="post" onsubmit="return confirm('Удалить путешествие и все связанные данные?');"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>"><button class="card-menu__danger" type="submit">Удалить</button></form></div>
                        </div>

                        <img class="trip-ticket__image" src="<?= htmlspecialchars($trip['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($trip['image_alt'], ENT_QUOTES, 'UTF-8') ?>" width="1400" height="788" loading="lazy">
                        <p class="ticket-destination"><span><?= htmlspecialchars($trip['country'], ENT_QUOTES, 'UTF-8') ?></span></p>
                        <h3><?= htmlspecialchars($trip['city'], ENT_QUOTES, 'UTF-8') ?></h3>

                        <div class="ticket-dates" aria-label="Даты путешествия">
                            <div class="ticket-date"><span>Отправление</span><strong><?= htmlspecialchars($start['day_month'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($start['year'], ENT_QUOTES, 'UTF-8') ?></small></div>
                            <div class="ticket-route" aria-hidden="true"><div><span></span><img src="<?= htmlspecialchars($transportIcon, ENT_QUOTES, 'UTF-8') ?>" alt=""><span></span></div><small><?= htmlspecialchars($duration, ENT_QUOTES, 'UTF-8') ?></small></div>
                            <div class="ticket-date ticket-date--end"><span>Возвращение</span><strong><?= htmlspecialchars($end['day_month'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($end['year'], ENT_QUOTES, 'UTF-8') ?></small></div>
                        </div>

                        <div class="ticket-facts"><div><span>Бюджет</span><strong><?= htmlspecialchars($trip['budget'], ENT_QUOTES, 'UTF-8') ?></strong></div><div><span>Места</span><strong><?= $trip['visited'] ?> из <?= $trip['places'] ?> мест</strong></div></div>
                    </div>

                    <div class="ticket-perforation" aria-hidden="true"><span></span></div>
                    <div class="trip-ticket__footer">
                        <div class="ticket-status ticket-status--<?= htmlspecialchars($status['key'], ENT_QUOTES, 'UTF-8') ?>"><span aria-hidden="true"></span><div><small>Статус</small><strong><?= htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8') ?></strong></div></div>
                        <a class="button button--ticket" href="trip.php?id=<?= (int) $trip['id'] ?>">Открыть <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 17 17 7M8 7h9v9"/></svg></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="empty-state" id="empty-state" <?= $trips ? 'hidden' : '' ?>><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 6.5 8.5 4l7 2.5L21 4v13.5L15.5 20l-7-2.5L3 20Z"/><path d="M8.5 4v13.5M15.5 6.5V20"/></svg><h3>Путешествий пока нет</h3><p>Добавьте первую поездку — данные сохранятся в MySQL.</p><button class="button button--primary" type="button" data-open-trip-form>Добавить путешествие</button></div>
    </section>
</main>

<?php require __DIR__ . '/includes/trip-form-modal.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
