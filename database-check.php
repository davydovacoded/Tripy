<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!(bool) (appConfig('app')['enable_db_check'] ?? false)) {
    http_response_code(404);
    exit('Страница проверки отключена.');
}

$checks = [];
$passed = false;
$failedStep = '';
$pdo = db();

try {
    require_once __DIR__ . '/includes/trip-repository.php';
    $checks[] = 'Репозиторий приложения загружен без синтаксических ошибок';
    if (!$pdo) throw new RuntimeException('connection');
    $checks[] = 'PDO подключился к MySQL';

    $expected = [
        'Users' => ['user_id','username','password_hash'],
        'Trips' => ['trip_id','user_id','title','destination','start_date','end_date','budget_limit','cover_path'],
        'Places' => ['place_id','trip_id','place_name','category','address','notes','status'],
        'Tickets' => ['ticket_id','trip_id','title','ticket_type','use_date','file_path','file_extension','original_name','is_pinned','cost','include_in_expenses'],
        'Expenses' => ['expense_id','trip_id','ticket_id','title','amount','category','expense_date','notes'],
        'Photos' => ['photo_id','trip_id','file_path','caption','photo_date','description'],
    ];
    foreach ($expected as $table => $columns) {
        $actual = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
        if (array_diff($columns, $actual)) throw new RuntimeException('schema_' . $table);
    }
    $checks[] = 'Все шесть таблиц и обязательные поля найдены';

    $owner = $pdo->prepare('SELECT 1 FROM Users WHERE user_id = :id');
    $owner->execute(['id' => currentUserId()]);
    if (!$owner->fetchColumn()) throw new RuntimeException('owner');
    $checks[] = 'Технический владелец данных существует';

    fetchTrips();
    fetchDashboardStats();
    fetchPhotoGroups();
    $checks[] = 'Запросы чтения главной, путешествий и галереи выполняются';

    $pdo->beginTransaction();
    $insertTrip = $pdo->prepare("INSERT INTO Trips (user_id,title,destination,start_date,end_date,budget_limit) VALUES (:user,'__tripy_connection_check__','Проверка, Система',CURRENT_DATE,CURRENT_DATE,1000.00)");
    $insertTrip->execute(['user' => currentUserId()]);
    $tripId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE Trips SET title='__tripy_connection_check_updated__' WHERE trip_id=:id")->execute(['id'=>$tripId]);
    if ($pdo->query('SELECT title FROM Trips WHERE trip_id=' . $tripId)->fetchColumn() !== '__tripy_connection_check_updated__') throw new RuntimeException('trip_crud');

    $pdo->prepare("INSERT INTO Places (trip_id,place_name,status) VALUES (:trip,'__check_place__','planned')")->execute(['trip'=>$tripId]);
    $pdo->prepare("INSERT INTO Tickets (trip_id,title,file_path,file_extension,original_name,cost,include_in_expenses) VALUES (:trip,'__check_ticket__','__transaction_only__','pdf','check.pdf',50.00,1)")->execute(['trip'=>$tripId]);
    $ticketId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO Expenses (trip_id,ticket_id,title,amount,category,expense_date) VALUES (:trip,:ticket,'__check_expense__',50.00,'Транспорт',CURRENT_DATE)")->execute(['trip'=>$tripId,'ticket'=>$ticketId]);
    $pdo->prepare("INSERT INTO Photos (trip_id,file_path,caption) VALUES (:trip,'__transaction_only__','__check_photo__')")->execute(['trip'=>$tripId]);
    $checks[] = 'Создание, чтение, изменение и удаление путешествия работают';
    $checks[] = 'Места, билеты, расходы и фотографии принимаются схемой';

    $pdo->prepare('DELETE FROM Trips WHERE trip_id=:id')->execute(['id'=>$tripId]);
    foreach (['Places','Tickets','Expenses','Photos'] as $table) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '` WHERE trip_id=' . $tripId)->fetchColumn() !== 0) throw new RuntimeException('cascade_' . $table);
    }
    $checks[] = 'Каскадное удаление связанных записей работает';
    if ($pdo->inTransaction()) $pdo->rollBack();
    $checks[] = 'Проверочные записи отменены транзакцией и не сохранены';
    $passed = true;
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $failedStep = $error->getMessage();
    error_log('Tripy database check failed at ' . $error->getMessage());
}
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Проверка MySQL — Tripy</title><style>body{margin:0;padding:48px;background:#f7f8fa;color:#242528;font:16px Manrope,Arial,sans-serif}.card{max-width:720px;margin:auto;padding:32px;background:#fff;border:1px solid #e9eaee;border-radius:24px}.status{font-size:28px;font-weight:800}.ok{color:#287b4c}.bad{color:#a63232}li{margin:10px 0}code{background:#f0f1f3;padding:2px 6px;border-radius:5px}</style></head><body><main class="card"><h1>Tripy — проверка MySQL</h1><?php if($passed): ?><p class="status ok">Подключение и схема работают</p><ul><?php foreach($checks as $check): ?><li><?= e($check) ?></li><?php endforeach; ?></ul><p>Теперь верните <code>enable_db_check</code> в <code>false</code>.</p><?php else: ?><p class="status bad">Проверка не пройдена</p><ul><?php foreach($checks as $check): ?><li><?= e($check) ?></li><?php endforeach; ?></ul><p>Этап: <code><?= e($failedStep ?: 'не определён') ?></code></p><?php endif; ?></main></body></html>
