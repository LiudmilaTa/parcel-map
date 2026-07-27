<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelMap\Services\CoordinateTransformService;
use ParcelMap\Services\CuzkParcelParser;
use proj4php\Proj4php;

$xmlFile = __DIR__ . '/../storage/cuzk/sample-parcels.xml';

if (!file_exists($xmlFile)) {
    throw new RuntimeException(
        "CUZK XML file not found: {$xmlFile}"
    );
}

$xml = file_get_contents($xmlFile);

if ($xml === false) {
    throw new RuntimeException(
        "Failed to read CUZK XML file: {$xmlFile}"
    );
}

// Initialize Proj4php.
$proj4 = new Proj4php();

// Initialize the coordinate transformation service.
$coordinateTransformService = new CoordinateTransformService(
    $proj4
);

// Initialize the CUZK parcel parser.
$parser = new CuzkParcelParser(
    $coordinateTransformService
);

// Parse CUZK XML and convert parcels to GeoJSON.
$geoJson = $parser->parse($xml);

echo '<pre>';

echo "CUZK Parcel Parser Test\n";
echo "=======================\n\n";

echo "Input file:\n";
echo "{$xmlFile}\n\n";

echo "Features found:\n";
echo count($geoJson['features']) . "\n\n";

foreach ($geoJson['features'] as $index => $feature) {
    echo "Parcel #" . ($index + 1) . "\n";
    echo "-----------------------\n";

    echo "ID: ";
    echo $feature['id'] ?? 'N/A';
    echo "\n";

    echo "Label: ";
    echo $feature['properties']['label'] ?? 'N/A';
    echo "\n";

    echo "National cadastral reference: ";
    echo $feature['properties']['nationalCadastralReference'] ?? 'N/A';
    echo "\n";

    echo "Area: ";
    echo $feature['properties']['areaValue'] ?? 'N/A';
    echo " m²\n";

    echo "Geometry type: ";
    echo $feature['geometry']['type'];
    echo "\n";

    echo "Rings: ";
    echo count($feature['geometry']['coordinates']);
    echo "\n";

    echo "Exterior points: ";
    echo count($feature['geometry']['coordinates'][0]);
    echo "\n\n";
}

echo "GeoJSON:\n";

echo json_encode(
    $geoJson,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
);

echo '</pre>';