<?php

declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=mapa_parcel;charset=utf8mb4',
    'mapa_parcel',
    '1111',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

// Check the database connection
echo "PDO connection successful." . PHP_EOL;

$result = $pdo->query('SELECT DATABASE() AS db, USER() AS user')->fetch();

print_r($result);