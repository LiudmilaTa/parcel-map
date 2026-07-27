<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

$params = [
    'service' => 'WFS',
    'request' => 'GetFeature',
    'version' => '2.0.0',
    'typeNames' => 'cp:CadastralParcel',
    'count' => '5',
];

$url = 'https://services.cuzk.gov.cz/wfs/inspire-cp-wfs.asp?'
    . http_build_query($params);

$response = file_get_contents($url);

if ($response === false) {
    throw new RuntimeException('Failed to fetch GetFeature.');
}

$file = __DIR__ . '/../../storage/cuzk/sample-parcels.xml';

file_put_contents($file, $response);

echo "GetFeature saved to: {$file}" . PHP_EOL;