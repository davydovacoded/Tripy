<?php
declare(strict_types=1);

require_once __DIR__ . '/trip-repository.php';
$trips = fetchTrips();
$monthNames = [1 => 'ЯНВ', 2 => 'ФЕВ', 3 => 'МАР', 4 => 'АПР', 5 => 'МАЙ', 6 => 'ИЮН', 7 => 'ИЮЛ', 8 => 'АВГ', 9 => 'СЕН', 10 => 'ОКТ', 11 => 'НОЯ', 12 => 'ДЕК'];

function tripDateParts(string $date, array $monthNames): array {
    $value = new DateTimeImmutable($date);
    return ['day_month' => $value->format('d') . ' ' . $monthNames[(int) $value->format('n')], 'year' => $value->format('Y'), 'iso' => $value->format('Y-m-d')];
}
function tripStatus(string $startDate, string $endDate): array {
    $today = new DateTimeImmutable('today'); $start = new DateTimeImmutable($startDate); $end = new DateTimeImmutable($endDate);
    if ($today < $start) return ['key' => 'upcoming', 'label' => 'Предстоит'];
    if ($today > $end) return ['key' => 'completed', 'label' => 'Завершено'];
    return ['key' => 'current', 'label' => 'В путешествии'];
}
function tripDurationLabel(string $startDate, string $endDate): string {
    $days = max(1, (int) (new DateTimeImmutable($startDate))->diff(new DateTimeImmutable($endDate))->days);
    $lastTwo = $days % 100; $last = $days % 10;
    $word = ($lastTwo >= 11 && $lastTwo <= 14) ? 'дней' : ($last === 1 ? 'день' : (($last >= 2 && $last <= 4) ? 'дня' : 'дней'));
    return $days . ' ' . $word;
}
function tripDateRange(string $startDate, string $endDate): string {
    $months = [1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'];
    $start = new DateTimeImmutable($startDate); $end = new DateTimeImmutable($endDate);
    if ($startDate === $endDate) return $start->format('j') . ' ' . $months[(int) $start->format('n')] . ' ' . $start->format('Y');
    if ($start->format('Y-m') === $end->format('Y-m')) return $start->format('j') . '–' . $end->format('j') . ' ' . $months[(int) $end->format('n')] . ' ' . $end->format('Y');
    return $start->format('j') . ' ' . $months[(int) $start->format('n')] . ' – ' . $end->format('j') . ' ' . $months[(int) $end->format('n')] . ' ' . $end->format('Y');
}
