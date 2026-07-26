<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelMap\Database\Connection;

$env = parse_ini_file(__DIR__ . '/../.env');

$connection = new Connection(
    host: $env['DB_HOST'],
    port: (int) $env['DB_PORT'],
    database: $env['DB_NAME'],
    username: $env['DB_USER'],
    password: $env['DB_PASSWORD'],
);

$pdo = $connection->getPdo();

echo 'Database connection successful!' . PHP_EOL;