<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ParcelMap\Database\Connection;
use ParcelMap\Repositories\ParcelRepository;
use ParcelMap\Services\CoordinateTransformService;
use ParcelMap\Services\CuzkParcelParser;
use proj4php\Proj4php;
use XMLReader;


$storageDirectory = __DIR__ . '/../../storage/cuzk/final';

$xmlFiles = glob($storageDirectory . '/*-parcels.xml');

if ($xmlFiles === false || $xmlFiles === []) {
    throw new RuntimeException("No CUZK parcel XML files found in: {$storageDirectory}");
}

echo "Connecting to MariaDB..." . PHP_EOL;

// Create database connection.
$connection = new Connection(
    '127.0.0.1',
    3306,
    'mapa_parcel',
    'mapa_parcel',
    '1111'
);

$pdo = $connection->getPdo();
echo "Connected." . PHP_EOL;

// Initialize services.
$proj4 = new Proj4php();

$coordinateTransformService = new CoordinateTransformService($proj4);

$parser = new CuzkParcelParser($coordinateTransformService);

$repository = new ParcelRepository($pdo);

echo "Parsing CUZK XML..." . PHP_EOL;

$imported = 0;
$skipped = 0;

foreach ($xmlFiles as $xmlFile) {
    echo "Importing file: {$xmlFile}" . PHP_EOL;

    $reader = new XMLReader();

    if (!$reader->open($xmlFile)) {
        echo "Failed to open CUZK XML file: {$xmlFile}" . PHP_EOL;
        continue;
    }

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'CadastralParcel') {
            continue;
        }

        $parcelXml = $reader->readOuterXml();

        if ($parcelXml === '') {
            $skipped++;
            continue;
        }

        try {
            $feature = $parser->parseSingleParcel(
                $parcelXml
            );

            if ($feature === null) {
                $skipped++;
                continue;
            }

            $properties = $feature['properties'] ?? null;
            $geometry = $feature['geometry'] ?? null;

            if (!is_array($properties) || !is_array($geometry)) {
                $skipped++;
                continue;
            }

            if (!isset($properties['id']) || !isset($properties['label'])) {
                $skipped++;
                continue;
            }

            $repository->save([
                'cuzk_id' => (string) $properties['id'],

                'local_id' => isset($properties['localId'])
                    ? (string) $properties['localId']
                    : null,

                'label' => (string) $properties['label'],

                'national_cadastral_reference' =>
                    isset($properties['nationalCadastralReference'])
                        ? (string) $properties[
                            'nationalCadastralReference'
                        ]
                        : null,

                'area_value' =>
                    isset($properties['areaValue'])
                        ? (float) $properties['areaValue']
                        : null,

                'zoning_name' =>
                    isset($properties['zoningName'])
                        ? (string) $properties['zoningName']
                        : null,

                'administrative_unit_name' =>
                    isset($properties['administrativeUnitName'])
                        ? (string) $properties[
                            'administrativeUnitName'
                        ]
                        : null,

                'min_x' => (float) ($properties['minX'] ?? 0),
                'min_y' => (float) ($properties['minY'] ?? 0),
                'max_x' => (float) ($properties['maxX'] ?? 0),
                'max_y' => (float) ($properties['maxY'] ?? 0),

                'geometry' => $geometry,
            ]);

            $imported++;

            if ($imported % 1000 === 0) {
                echo "Imported {$imported} parcels..."
                    . PHP_EOL;
            }
        } catch (Throwable $exception) {
            $skipped++;

            echo sprintf(
                "Skipped parcel: %s",
                $exception->getMessage()
            ) . PHP_EOL;
        }
    }

    $reader->close();
}

echo PHP_EOL;
echo "Import completed." . PHP_EOL;
echo "Imported parcels: {$imported}" . PHP_EOL;
echo "Skipped parcels: {$skipped}" . PHP_EOL;