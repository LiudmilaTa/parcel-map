<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelMap\Services\CoordinateTransformService;
use ParcelMap\Services\CuzkParcelParser;
use proj4php\Proj4php;

$xmlPath = __DIR__ . '/../storage/cuzk/final/jicin-zoning.xml';

if (!file_exists($xmlPath)) {
    fwrite(STDERR, "Missing zoning file: {$xmlPath}\n");
    exit(1);
}

$xml = file_get_contents($xmlPath);

if ($xml === false) {
    fwrite(STDERR, "Unable to read zoning XML\n");
    exit(1);
}

$parser = new CuzkParcelParser(new CoordinateTransformService(new Proj4php()));
$features = $parser->parseZoning($xml);

if (!isset($features['features']) || !is_array($features['features']) || count($features['features']) === 0) {
    fwrite(STDERR, "No zoning features parsed\n");
    exit(1);
}

echo "Parsed zoning features: " . count($features['features']) . PHP_EOL;
