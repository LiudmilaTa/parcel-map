<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelMap\Database\Connection;

// Create the database connection.
$connection = new Connection(
    '127.0.0.1',
    3306,
    'mapa_parcel',
    'mapa_parcel',
    '1111'
);

$pdo = $connection->getPdo();

$migrationFile = __DIR__
    . '/../database/migrations/001_create_parcels_table.sql';

if (!file_exists($migrationFile)) {
    throw new RuntimeException(
        "Migration file not found: {$migrationFile}"
    );
}

$sql = file_get_contents($migrationFile);

if ($sql === false) {
    throw new RuntimeException(
        "Failed to read migration file."
    );
}


// Execute the migration SQL.
$pdo->exec($sql);

echo "Migration executed successfully." . PHP_EOL;
echo "Table 'parcels' is ready." . PHP_EOL;