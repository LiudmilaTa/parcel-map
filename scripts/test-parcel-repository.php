<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelMap\Database\Connection;
use ParcelMap\Repositories\ParcelRepository;

$connection = new Connection(
    '127.0.0.1',
    3306,
    'mapa_parcel',
    'mapa_parcel',
    '1111'
);

$pdo = $connection->getPdo();

$repository = new ParcelRepository($pdo);

$parcels = $repository->findAll();

echo '<pre>';

echo "Parcel Repository Test\n";
echo "======================\n\n";

echo "Parcels found: ";
echo count($parcels);
echo PHP_EOL . PHP_EOL;

foreach ($parcels as $parcel) {
    echo "Parcel\n";
    echo "----------------------\n";
    echo "ID: {$parcel['id']}\n";
    echo "CUZK ID: {$parcel['cuzk_id']}\n";
    echo "Label: {$parcel['label']}\n";
    echo "National cadastral reference: "
        . ($parcel['national_cadastral_reference'] ?? 'N/A')
        . PHP_EOL;
    echo "Area: {$parcel['area_value']} m²\n";
    echo "Geometry length: "
        . strlen($parcel['geometry'])
        . " bytes\n";
    echo PHP_EOL;
}

echo '</pre>';