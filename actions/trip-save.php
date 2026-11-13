<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
function tripSaveResponse(array $payload, int $status = 200): void
{
    global $wantsJson;
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
function tripSaveFail(string $message, int $tripId = 0, int $status = 422): void
{
    global $wantsJson;
    if ($wantsJson) tripSaveResponse(['ok' => false, 'message' => $message], $status);
    redirectWithMessage($tripId ? '../trip.php?id=' . $tripId . '&edit=1' : '../trips.php?create=1', $message, 'error');
}
function validTripDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? null)) {
    tripSaveFail('Запрос отклонён. Обновите страницу и попробуйте снова.', 0, 403);
}
$pdo = db();
if (!$pdo) tripSaveFail('Не удалось подключиться к MySQL.', 0, 503);

$tripId = filter_var($_POST['trip_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$city = trim((string) ($_POST['city'] ?? ''));
$country = trim((string) ($_POST['country'] ?? ''));
$start = (string) ($_POST['start_date'] ?? '');
$end = (string) ($_POST['end_date'] ?? '');
$budgetRaw = trim((string) ($_POST['budget_limit'] ?? ''));
$budget = $budgetRaw === '' ? null : filter_var($budgetRaw, FILTER_VALIDATE_FLOAT);

if ($city === '' || $country === '' || !validTripDate($start) || !validTripDate($end) || $end < $start || ($budgetRaw !== '' && ($budget === false || $budget < 0))) {
    tripSaveFail('Проверьте обязательные поля, даты и бюджет.', $tripId);
}

$coverPath = null;
if (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['cover']['error'] !== UPLOAD_ERR_OK || $_FILES['cover']['size'] > 8 * 1024 * 1024) tripSaveFail('Не удалось загрузить обложку или файл больше 8 МБ.', $tripId);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['cover']['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) tripSaveFail('Обложка должна быть JPG, PNG или WebP.', $tripId);
    $directory = dirname(__DIR__) . '/uploads/covers';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) tripSaveFail('Сервер не смог подготовить папку загрузок.', $tripId, 500);
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($_FILES['cover']['tmp_name'], $directory . '/' . $filename)) tripSaveFail('Не удалось сохранить обложку.', $tripId, 500);
    $coverPath = 'uploads/covers/' . $filename;
}

if ($tripId) {
    $existing = $pdo->prepare('SELECT cover_path FROM Trips WHERE trip_id=:id AND user_id=:user_id');
    $existing->execute(['id' => $tripId, 'user_id' => currentUserId()]);
    $row = $existing->fetch();
    if (!$row) tripSaveFail('Путешествие не найдено.', $tripId, 404);
    $previousCover = $row['cover_path'];
    $coverPath ??= $previousCover;
    $statement = $pdo->prepare('UPDATE Trips SET title=:title,destination=:destination,start_date=:start,end_date=:end,budget_limit=:budget,cover_path=:cover WHERE trip_id=:id AND user_id=:user_id');
    $statement->execute(['title'=>$city,'destination'=>$country,'start'=>$start,'end'=>$end,'budget'=>$budget,'cover'=>$coverPath,'id'=>$tripId,'user_id'=>currentUserId()]);
    if ($previousCover && $previousCover !== $coverPath) { $oldFile = dirname(__DIR__) . '/' . $previousCover; if (is_file($oldFile)) @unlink($oldFile); }
    if ($wantsJson) tripSaveResponse(['ok'=>true,'tripId'=>$tripId,'redirect'=>'trip.php?id='.$tripId]);
    redirectWithMessage('../trip.php?id=' . $tripId, 'Изменения сохранены.');
}

$statement = $pdo->prepare('INSERT INTO Trips (user_id,title,destination,start_date,end_date,budget_limit,cover_path) VALUES (:user_id,:title,:destination,:start,:end,:budget,:cover)');
$statement->execute(['user_id'=>currentUserId(),'title'=>$city,'destination'=>$country,'start'=>$start,'end'=>$end,'budget'=>$budget,'cover'=>$coverPath]);
$newTripId = (int) $pdo->lastInsertId();
if ($wantsJson) tripSaveResponse(['ok'=>true,'tripId'=>$newTripId,'redirect'=>'trip.php?id='.$newTripId]);
redirectWithMessage('../trip.php?id=' . $newTripId, 'Путешествие создано.');
