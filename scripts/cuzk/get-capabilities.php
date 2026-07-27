<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

$url = 'https://services.cuzk.gov.cz/wfs/inspire-cp-wfs.asp'
    . '?service=WFS'
    . '&request=GetCapabilities'
    . '&version=2.0.0';

$response = file_get_contents($url);

if ($response === false) {
    throw new RuntimeException('Failed to fetch GetCapabilities.');
}

$file = __DIR__ . '/../../storage/cuzk/get-capabilities.xml';

file_put_contents($file, $response);

echo "GetCapabilities saved to: {$file}" . PHP_EOL;