<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ParcelMap\Database\Connection;
use ParcelMap\Repositories\ParcelRepository;
use ParcelMap\Services\CoordinateTransformService;
use ParcelMap\Services\CuzkParcelParser;
use proj4php\Proj4php;

$xmlFile = __DIR__
    . '/../../storage/cuzk/sample-parcels.xml';

if (!file_exists($xmlFile)) {
    throw new RuntimeException(
        "CUZK XML file not found: {$xmlFile}"
    );
}

$xml = file_get_contents($xmlFile);

if ($xml === false) {
    throw new RuntimeException(
        'Failed to read CUZK XML file.'
    );
}

/*
 * Database connection
 */
$connection = new Connection(
    '127.0.0.1',
    3306,
    'mapa_parcel',
    'mapa_parcel',
    '1111'
);

$pdo = $connection->getPdo();

/*
 * Services
 */
$proj4 = new Proj4php();

$coordinateTransformService = new CoordinateTransformService(
    $proj4
);

$parser = new CuzkParcelParser(
    $coordinateTransformService
);

$repository = new ParcelRepository(
    $pdo
);

/*
 * Parse CUZK XML
 */
$geoJson = $parser->parse($xml);

$features = $geoJson['features'];

if (!is_array($features)) {
    throw new RuntimeException(
        'Invalid GeoJSON FeatureCollection.'
    );
}

/*
 * Import parcels
 */
$imported = 0;

foreach ($features as $feature) {
    if (!is_array($feature)) {
        continue;
    }

    $properties = $feature['properties'] ?? [];
    $geometry = $feature['geometry'] ?? null;

    if (
        !is_array($properties)
        || !isset($properties['id'])
        || !isset($properties['label'])
        || !is_array($geometry)
    ) {
        continue;
    }

    $repository->save([
        'id' => (string) $properties['id'],
        'label' => $properties['label'] !== null
            ? (string) $properties['label']
            : null,
        'nationalCadastralReference' =>
            $properties['nationalCadastralReference'] !== null
                ? (string) $properties['nationalCadastralReference']
                : null,
        'areaValue' => $properties['areaValue'] !== null
            ? (float) $properties['areaValue']
            : null,
        'geometry' => $geometry,
    ]);

    $imported++;

    echo sprintf(
        "Imported parcel: %s (%s)%s",
        $properties['id'],
        $properties['label'] ?? 'N/A',
        PHP_EOL
    );
}

echo PHP_EOL;
echo "Import completed." . PHP_EOL;
echo "Processed parcels: {$imported}" . PHP_EOL;