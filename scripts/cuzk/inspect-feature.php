<?php

declare(strict_types=1);

$file = __DIR__ . '/../../storage/cuzk/sample-parcels.xml';

$xml = simplexml_load_file($file);

if ($xml === false) {
    throw new RuntimeException('Failed to parse XML.');
}

$namespaces = $xml->getNamespaces(true);

// Register namespaces used in the CUZK XML.
$xml->registerXPathNamespace('wfs', 'http://www.opengis.net/wfs/2.0');
$xml->registerXPathNamespace('cp', 'http://inspire.ec.europa.eu/schemas/cp/4.0');
$xml->registerXPathNamespace('gml', 'http://www.opengis.net/gml/3.2');

$parcels = $xml->xpath('//cp:CadastralParcel');

if ($parcels === false || $parcels === []) {
    throw new RuntimeException('No CadastralParcel features found.');
}

$parcel = $parcels[0];

echo "=== CadastralParcel ===" . PHP_EOL;
echo "gml:id: " . ($parcel->attributes('gml', true)['id'] ?? '') . PHP_EOL;

echo PHP_EOL . "=== Attributes ===" . PHP_EOL;

// Print all elements from the CP namespace.
foreach ($parcel->children('cp', true) as $element) {
    echo $element->getName() . ': ' . trim((string) $element) . PHP_EOL;
}

echo PHP_EOL . "=== Geometry XML ===" . PHP_EOL;

$geometry = $parcel->children('cp', true)->geometry;

if ($geometry !== null) {
    echo $geometry->asXML() . PHP_EOL;
}