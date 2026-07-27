<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelMap\Services\CoordinateTransformService;
use proj4php\Proj4php;

$proj4 = new Proj4php();

$coordinateTransformService = new CoordinateTransformService(
    $proj4
);

// Real coordinate from storage/cuzk/sample-parcels.xml.
$sourceX = -726297.24;
$sourceY = -942104.55;

$result = $coordinateTransformService->transformToWgs84(
    $sourceX,
    $sourceY
);

echo '<pre>';

echo "Coordinate transformation\n";
echo "=========================\n\n";

echo "Source CRS: EPSG:5514\n";
echo "Target CRS: EPSG:4326\n\n";

echo "Source coordinates:\n";
echo "X: {$sourceX}\n";
echo "Y: {$sourceY}\n\n";

echo "WGS84 coordinates:\n";
echo "Longitude: {$result['longitude']}\n";
echo "Latitude:  {$result['latitude']}\n";

if ($result['altitude'] !== null) {
    echo "Altitude:   {$result['altitude']}\n";
}

echo "\nGeoJSON coordinate:\n";

$geoJsonCoordinate = $coordinateTransformService
    ->transformToGeoJsonCoordinate(
        $sourceX,
        $sourceY
    );

print_r($geoJsonCoordinate);

echo '</pre>';