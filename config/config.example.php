<?php
declare(strict_types=1);

return [
    'db' => [
        'host' => 'MYSQL_HOST',
        'port' => 3306,
        'name' => 'DATABASE_NAME',
        'user' => 'DATABASE_USER',
        'password' => 'DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'debug' => false,
        'timezone' => 'Europe/Moscow',
        'default_user_id' => 1,
        'enable_db_check' => false,
    ],
];
