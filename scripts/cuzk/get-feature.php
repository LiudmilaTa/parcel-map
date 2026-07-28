<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

$cadastralAreas = [
    'jicin' => [
        'name' => 'Jičín',
        'code' => '659541',
    ],

    'miletin' => [
        'name' => 'Miletín',
    ],

    'sobotka' => [
        'name' => 'Sobotka',
    ],

    'stara-paka' => [
        'name' => 'Stará Paka',
    ],
];

$baseUrl = 'https://services.cuzk.gov.cz/wfs/inspire-cp-wfs.asp';

$storageDirectory = __DIR__ . '/../../storage/cuzk/final';

function buildCuzkUrl(string $baseUrl, array $params): string
{
    return $baseUrl . '?' . http_build_query($params);
}

function fetchCuzkResponse(string $url, string $description): string
{
    $response = file_get_contents($url);

    if ($response === false) {
        throw new RuntimeException(
            "Failed to fetch {$description}."
        );
    }

    return $response;
}

function extractEnvelopeBoundsFromZoningXml(string $xml): ?array
{
    $lowerPattern = '/<gml:lowerCorner[^>]*>(.*?)<\/gml:lowerCorner>/s';
    $upperPattern = '/<gml:upperCorner[^>]*>(.*?)<\/gml:upperCorner>/s';

    $lowerMatch = [];
    $upperMatch = [];

    preg_match($lowerPattern, $xml, $lowerMatch);
    preg_match($upperPattern, $xml, $upperMatch);

    if (!isset($lowerMatch[1], $upperMatch[1])) {
        return null;
    }

    $lowerValues = preg_split('/\s+/', trim($lowerMatch[1]));
    $upperValues = preg_split('/\s+/', trim($upperMatch[1]));

    if (
        $lowerValues === false
        || $upperValues === false
        || count($lowerValues) < 2
        || count($upperValues) < 2
    ) {
        return null;
    }

    return [
        'minX' => (float) $lowerValues[0],
        'minY' => (float) $lowerValues[1],
        'maxX' => (float) $upperValues[0],
        'maxY' => (float) $upperValues[1],
    ];
}

if (!is_dir($storageDirectory)) {
    if (
        !mkdir($storageDirectory, 0777, true)
        && !is_dir($storageDirectory)
    ) {
        throw new RuntimeException(
            "Failed to create storage directory: {$storageDirectory}"
        );
    }
}

// Save the CadastralParcel schema locally.
echo "Fetching CadastralParcel schema..." . PHP_EOL;

$schemaParams = [
    'service' => 'WFS',
    'request' => 'DescribeFeatureType',
    'version' => '2.0.0',
    'typeNames' => 'cp:CadastralParcel',
];

$schemaUrl = $baseUrl . '?' . http_build_query(
    $schemaParams
);

$schema = file_get_contents($schemaUrl);

if ($schema === false) {
    throw new RuntimeException('Failed to fetch CadastralParcel schema.');
}

$schemaFile = __DIR__ . '/../../storage/cuzk/cadastral-parcel-schema.xml';

if (file_put_contents($schemaFile, $schema) === false) {
    throw new RuntimeException("Failed to save schema to: {$schemaFile}");
}

echo "Schema saved to: {$schemaFile}" . PHP_EOL;

// Save available CUZK stored queries locally.
echo "Fetching stored queries..." . PHP_EOL;

$storedQueriesParams = [
    'service' => 'WFS',
    'request' => 'ListStoredQueries',
    'version' => '2.0.0',
];

$storedQueriesUrl = $baseUrl
    . '?'
    . http_build_query(
        $storedQueriesParams
    );

$storedQueries = file_get_contents($storedQueriesUrl);

if ($storedQueries === false) {
    throw new RuntimeException('Failed to fetch stored queries.');
}

$storedQueriesFile = __DIR__ . '/../../storage/cuzk/stored-queries.xml';

if (file_put_contents($storedQueriesFile, $storedQueries) === false) {
    throw new RuntimeException("Failed to save stored queries to: {$storedQueriesFile}");
}

echo "Stored queries saved to: {$storedQueriesFile}" . PHP_EOL;

// Download cadastral zoning for each configured area.
foreach (
    $cadastralAreas
    as $slug => $cadastralArea
) {
    echo PHP_EOL;

    $displayCode = isset($cadastralArea['code'])
        ? $cadastralArea['code']
        : 'n/a';

    echo "Fetching cadastral zoning: "
        . $cadastralArea['name']
        . " ({$displayCode})..."
        . PHP_EOL;

    $params = [
        'service' => 'WFS',
        'request' => 'GetFeature',
        'version' => '2.0.0',
    ];

    if (isset($cadastralArea['code'])) {
        $params['storedQuery_Id'] = 'GetZoningById';
        $params['ZONING_ID'] = $cadastralArea['code'];
    } else {
        $params['storedQuery_Id'] = 'GetZoningByName';
        $params['ZONING_NAME'] = $cadastralArea['name'];
    }

    $url = buildCuzkUrl($baseUrl, $params);

    echo "URL: {$url}"
        . PHP_EOL;

    $response = fetchCuzkResponse(
        $url,
        "cadastral zoning for {$cadastralArea['name']}"
    );

    $file = $storageDirectory
        . '/'
        . $slug
        . '-zoning.xml';

    if (file_put_contents($file, $response) === false) {
        throw new RuntimeException("Failed to save zoning data to: {$file}");
    }

    echo "Saved: {$file}"
        . PHP_EOL;
}

// Download all parcels inside the configured BBOX.
foreach (
    $cadastralAreas
    as $slug => $cadastralArea
) {
    $zoningFile = $storageDirectory
        . '/'
        . $slug
        . '-zoning.xml';

    if (!file_exists($zoningFile)) {
        throw new RuntimeException("Missing zoning file for {$cadastralArea['name']}: {$zoningFile}");
    }

    $zoningXml = file_get_contents($zoningFile);

    if ($zoningXml === false) {
        throw new RuntimeException("Failed to read zoning file: {$zoningFile}");
    }

    $displayCode = isset($cadastralArea['code'])
        ? $cadastralArea['code']
        : 'n/a';

    echo PHP_EOL;

    echo "Fetching ALL parcels for "
        . $cadastralArea['name']
        . " ({$displayCode})..."
        . PHP_EOL;

    $zoningBounds = extractEnvelopeBoundsFromZoningXml($zoningXml);

    if ($zoningBounds === null) {
        throw new RuntimeException("Unable to determine envelope for {$cadastralArea['name']}.");
    }

    $params = [
        'service' => 'WFS',
        'request' => 'GetFeature',
        'version' => '2.0.0',
        'typeNames' => 'cp:CadastralParcel',
        'bbox' => implode(',', [
            $zoningBounds['minX'],
            $zoningBounds['minY'],
            $zoningBounds['maxX'],
            $zoningBounds['maxY'],
            'http://www.opengis.net/def/crs/EPSG/0/5514',
        ]),
    ];

    $url = buildCuzkUrl($baseUrl, $params);

    echo "URL: {$url}"
        . PHP_EOL;

    echo PHP_EOL;
    echo "Downloading all parcels..."
        . PHP_EOL;

    $response = fetchCuzkResponse(
        $url,
        "all parcels for {$cadastralArea['name']}"
    );

    if (!mb_check_encoding($response, 'UTF-8')) {
        throw new RuntimeException('CUZK response is not valid UTF-8.');
    }

    $file = $storageDirectory
        . '/'
        . $slug
        . '-parcels.xml';

    if (file_put_contents($file, $response) === false) {
        throw new RuntimeException("Failed to save parcel data to: {$file}");
    }

    echo "Saved ALL parcels: {$file}"
        . PHP_EOL;

     // Print the number of matching and returned features.
    $xml = simplexml_load_string($response);

    if ($xml !== false) {
        $attributes = $xml->attributes();

        if (isset($attributes['numberMatched'])) {
            echo "Number matched: "
                . $attributes['numberMatched']
                . PHP_EOL;
        }

        if (isset($attributes['numberReturned'])) {
            echo "Number returned: "
                . $attributes['numberReturned']
                . PHP_EOL;
        }
    }
}

echo PHP_EOL;

echo "CUZK data download completed."
    . PHP_EOL;

echo "Cadastral areas: "
    . count($cadastralAreas)
    . PHP_EOL;

echo "Parcel limit: NONE"
    . PHP_EOL;

echo "Output:"
    . PHP_EOL;

echo "storage/cuzk/final/jicin-parcels.xml"
    . PHP_EOL;