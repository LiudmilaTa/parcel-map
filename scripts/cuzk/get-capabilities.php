<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

// Build the CUZK WFS GetCapabilities URL.
$url = 'https://services.cuzk.gov.cz/wfs/inspire-cp-wfs.asp'
    . '?service=WFS'
    . '&request=GetCapabilities'
    . '&version=2.0.0';

// Fetch the WFS capabilities document
$response = file_get_contents($url);

if ($response === false) {
    throw new RuntimeException('Failed to fetch GetCapabilities.');
}

$file = __DIR__ . '/../../storage/cuzk/get-capabilities.xml';

// Save the response to a local file.
file_put_contents($file, $response);

echo "GetCapabilities saved to: {$file}" . PHP_EOL;