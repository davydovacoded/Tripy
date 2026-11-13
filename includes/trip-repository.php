<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function fetchTrips(): array
{
    $pdo = db();
    if (!$pdo) return [];
    $sql = "SELECT t.trip_id AS id, t.title, t.destination, t.start_date, t.end_date,
                   t.budget_limit AS budget_value, t.cover_path AS image,
                   COALESCE(COUNT(DISTINCT p.place_id), 0) AS places,
                   COALESCE(SUM(CASE WHEN p.status = 'visited' THEN 1 ELSE 0 END), 0) AS visited
            FROM Trips t
            LEFT JOIN Places p ON p.trip_id = t.trip_id
            WHERE t.user_id = :user_id
            GROUP BY t.trip_id, t.title, t.destination, t.start_date, t.end_date, t.budget_limit, t.cover_path
            ORDER BY t.start_date DESC, t.trip_id DESC";
    $statement = $pdo->prepare($sql);
    $statement->execute(['user_id' => currentUserId()]);
    return array_map('normalizeTrip', $statement->fetchAll());
}

function fetchTrip(int $tripId): ?array
{
    $pdo = db();
    if (!$pdo) return null;
    $statement = $pdo->prepare("SELECT t.trip_id AS id, t.title, t.destination, t.start_date, t.end_date,
                                      t.budget_limit AS budget_value, t.cover_path AS image,
                                      COALESCE(COUNT(DISTINCT p.place_id), 0) AS places,
                                      COALESCE(SUM(CASE WHEN p.status = 'visited' THEN 1 ELSE 0 END), 0) AS visited
                               FROM Trips t LEFT JOIN Places p ON p.trip_id = t.trip_id
                               WHERE t.trip_id = :trip_id AND t.user_id = :user_id
                               GROUP BY t.trip_id, t.title, t.destination, t.start_date, t.end_date, t.budget_limit, t.cover_path");
    $statement->execute(['trip_id' => $tripId, 'user_id' => currentUserId()]);
    $row = $statement->fetch();
    return $row ? normalizeTrip($row) : null;
}

function normalizeTrip(array $trip): array
{
    $hasBudget = array_key_exists('budget_value', $trip) && $trip['budget_value'] !== null && $trip['budget_value'] !== '';
    $budget = $hasBudget ? (float) $trip['budget_value'] : null;
    $rawTitle = trim((string) ($trip['title'] ?? ''));
    $rawDestination = trim((string) ($trip['destination'] ?? ''));
    if (str_contains($rawDestination, ',')) {
        $parts = array_values(array_filter(array_map('trim', explode(',', $rawDestination)), static fn(string $part): bool => $part !== ''));
        $trip['city'] = $parts[0] ?? $rawTitle;
        $trip['country'] = $parts ? $parts[count($parts) - 1] : $rawDestination;
    } else {
        $trip['city'] = $rawTitle;
        $trip['country'] = $rawDestination;
    }
    $trip['id'] = (int) $trip['id'];
    $trip['budget_value'] = $budget;
    $trip['budget'] = $hasBudget ? number_format((float) $budget, 0, ',', ' ') . ' ₽' : 'Бюджет не указан';
    $trip['places'] = (int) ($trip['places'] ?? 0);
    $trip['visited'] = (int) ($trip['visited'] ?? 0);
    $trip['transport'] = 'plane';
    $trip['image'] = $trip['image'] ?: 'assets/images/trip-cover-placeholder.png';
    $trip['image_alt'] = 'Обложка путешествия «' . $trip['city'] . '»';
    return $trip;
}

function fetchTripData(int $tripId): array
{
    $pdo = db();
    if (!$pdo) return ['places' => [], 'tickets' => [], 'expenses' => [], 'photos' => []];
    $owner = fetchTrip($tripId);
    if (!$owner) return ['places' => [], 'tickets' => [], 'expenses' => [], 'photos' => []];

    $places = $pdo->prepare("SELECT place_id AS id, place_name AS name, category, address, notes AS note,
                                    status = 'visited' AS visited FROM Places WHERE trip_id = :trip_id ORDER BY place_id DESC");
    $places->execute(['trip_id' => $tripId]);

    $tickets = $pdo->prepare("SELECT ticket_id AS id, title AS name, ticket_type AS type, use_date AS date,
                                     UPPER(file_extension) AS format, notes AS note, is_pinned AS pinned,
                                     cost, include_in_expenses AS includeExpense, file_path AS filePath
                              FROM Tickets WHERE trip_id = :trip_id ORDER BY is_pinned DESC, ticket_id DESC");
    $tickets->execute(['trip_id' => $tripId]);

    $expenses = $pdo->prepare("SELECT expense_id AS id, title AS name, amount, category, expense_date AS date,
                                      notes AS note, ticket_id AS ticketId, ticket_id IS NOT NULL AS fromTicket
                               FROM Expenses WHERE trip_id = :trip_id ORDER BY expense_date DESC, expense_id DESC");
    $expenses->execute(['trip_id' => $tripId]);

    $photos = $pdo->prepare("SELECT photo_id AS id, file_path AS filePath, caption, photo_date AS date,
                                    description FROM Photos WHERE trip_id = :trip_id
                             ORDER BY photo_date DESC, photo_id DESC");
    $photos->execute(['trip_id' => $tripId]);

    return ['places' => $places->fetchAll(), 'tickets' => $tickets->fetchAll(), 'expenses' => $expenses->fetchAll(), 'photos' => $photos->fetchAll()];
}

function fetchDashboardStats(): array
{
    $pdo = db();
    if (!$pdo) return ['trips' => 0, 'upcoming' => 0, 'countries' => 0, 'visited' => 0, 'places' => 0, 'photos' => 0];
    $statement = $pdo->prepare("SELECT
        COUNT(DISTINCT t.trip_id) AS trips,
        COUNT(DISTINCT CASE WHEN t.start_date > CURRENT_DATE THEN t.trip_id END) AS upcoming,
        COUNT(DISTINCT CASE WHEN t.end_date < CURRENT_DATE THEN NULLIF(TRIM(SUBSTRING_INDEX(t.destination, ',', -1)), '') END) AS countries,
        COUNT(DISTINCT CASE WHEN p.status = 'visited' THEN p.place_id END) AS visited,
        COUNT(DISTINCT p.place_id) AS places,
        COUNT(DISTINCT ph.photo_id) AS photos
        FROM Trips t
        LEFT JOIN Places p ON p.trip_id = t.trip_id
        LEFT JOIN Photos ph ON ph.trip_id = t.trip_id
        WHERE t.user_id = :user_id");
    $statement->execute(['user_id' => currentUserId()]);
    return array_map('intval', $statement->fetch() ?: []);
}

function fetchPhotoGroups(): array
{
    $pdo = db();
    if (!$pdo) return [];
    $statement = $pdo->prepare("SELECT ph.photo_id AS id, ph.trip_id, ph.file_path, ph.caption, ph.photo_date, ph.description,
                                      t.title, t.destination, t.start_date, t.end_date
                               FROM Photos ph JOIN Trips t ON t.trip_id = ph.trip_id
                               WHERE t.user_id = :user_id ORDER BY t.start_date DESC, ph.photo_date DESC, ph.photo_id DESC");
    $statement->execute(['user_id' => currentUserId()]);
    $groups = [];
    foreach ($statement->fetchAll() as $row) {
        $tripId = (int) $row['trip_id'];
        $groups[$tripId] ??= ['trip' => ['id' => $tripId, 'title' => $row['title'], 'destination' => $row['destination'], 'start_date' => $row['start_date'], 'end_date' => $row['end_date']], 'photos' => []];
        $groups[$tripId]['photos'][] = $row;
    }
    return array_values($groups);
}

function fetchLatestPhotos(int $limit = 4): array
{
    $pdo = db();
    if (!$pdo) return [];
    $limit = max(1, min($limit, 12));
    $statement = $pdo->prepare("SELECT ph.file_path, ph.caption, ph.photo_date, t.title, t.destination
                               FROM Photos ph JOIN Trips t ON t.trip_id = ph.trip_id
                               WHERE t.user_id = :user_id ORDER BY ph.photo_date DESC, ph.photo_id DESC LIMIT {$limit}");
    $statement->execute(['user_id' => currentUserId()]);
    return $statement->fetchAll();
}
