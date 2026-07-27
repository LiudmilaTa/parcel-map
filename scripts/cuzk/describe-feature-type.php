<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

$url = 'https://services.cuzk.gov.cz/wfs/inspire-cp-wfs.asp'
    . '?service=WFS'
    . '&request=DescribeFeatureType'
    . '&version=2.0.0'
    . '&typeNames=cp:CadastralParcel';

$response = file_get_contents($url);

if ($response === false) {
    throw new RuntimeException('Failed to fetch DescribeFeatureType.');
}

$file = __DIR__ . '/../../storage/cuzk/describe-feature-type.xsd';

file_put_contents($file, $response);

echo "DescribeFeatureType saved to: {$file}" . PHP_EOL;