<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

$cadastralAreas = [
    'jicin' => [
        'name' => 'Jičín',
        'code' => '659541',
    ],

    /*
    'holin' => [
        'name' => 'Holín',
        'code' => '641243',
    ],

    'valdice' => [
        'name' => 'Valdice',
        'code' => '776530',
    ],

    'zeleznice' => [
        'name' => 'Železnice',
        'code' => '796123',
    ],
    */
];

$baseUrl = 'https://services.cuzk.gov.cz/wfs/inspire-cp-wfs.asp';

$storageDirectory = __DIR__ . '/../../storage/cuzk/final';

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
    throw new RuntimeException(
        'Failed to fetch CadastralParcel schema.'
    );
}

$schemaFile = __DIR__
    . '/../../storage/cuzk/cadastral-parcel-schema.xml';

if (
    file_put_contents(
        $schemaFile,
        $schema
    ) === false
) {
    throw new RuntimeException(
        "Failed to save schema to: {$schemaFile}"
    );
}

echo "Schema saved to: {$schemaFile}"
    . PHP_EOL;

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

$storedQueries = file_get_contents(
    $storedQueriesUrl
);

if ($storedQueries === false) {
    throw new RuntimeException(
        'Failed to fetch stored queries.'
    );
}

$storedQueriesFile = __DIR__
    . '/../../storage/cuzk/stored-queries.xml';

if (
    file_put_contents(
        $storedQueriesFile,
        $storedQueries
    ) === false
) {
    throw new RuntimeException(
        "Failed to save stored queries to: {$storedQueriesFile}"
    );
}

echo "Stored queries saved to: {$storedQueriesFile}"
    . PHP_EOL;

// Download cadastral zoning for each configured area.
foreach (
    $cadastralAreas
    as $slug => $cadastralArea
) {
    echo PHP_EOL;

    echo "Fetching cadastral zoning: "
        . $cadastralArea['name']
        . " ({$cadastralArea['code']})..."
        . PHP_EOL;

    $params = [
        'service' => 'WFS',
        'request' => 'GetFeature',
        'version' => '2.0.0',
        'storedQuery_Id' => 'GetZoningById',
        'ZONING_ID' => $cadastralArea['code'],
    ];

    $url = $baseUrl
        . '?'
        . http_build_query($params);

    echo "URL: {$url}"
        . PHP_EOL;

    $response = file_get_contents($url);

    if ($response === false) {
        throw new RuntimeException(
            "Failed to fetch cadastral zoning "
            . "for {$cadastralArea['name']}."
        );
    }

    $file = $storageDirectory
        . '/'
        . $slug
        . '-zoning.xml';

    if (
        file_put_contents(
            $file,
            $response
        ) === false
    ) {
        throw new RuntimeException(
            "Failed to save zoning data to: {$file}"
        );
    }

    echo "Saved: {$file}"
        . PHP_EOL;
}

// Download all parcels inside the configured BBOX.
foreach (
    $cadastralAreas
    as $slug => $cadastralArea
) {
    echo PHP_EOL;

    echo "Fetching ALL parcels for "
        . $cadastralArea['name']
        . " ({$cadastralArea['code']})..."
        . PHP_EOL;

    $params = [
        'service' => 'WFS',
        'request' => 'GetFeature',
        'version' => '2.0.0',
        'typeNames' => 'cp:CadastralParcel',

        // No "count" parameter means that the request is not limited to 5 parcels.
        'bbox' => implode(',', [
            '-673645.93',
            '-1015195.05',
            '-668247.44',
            '-1010681.11',
            'http://www.opengis.net/def/crs/EPSG/0/5514',
        ]),
    ];

    $url = $baseUrl
        . '?'
        . http_build_query($params);

    echo "URL: {$url}"
        . PHP_EOL;

    echo PHP_EOL;
    echo "Downloading all parcels..."
        . PHP_EOL;

    $response = file_get_contents($url);

    if ($response === false) {
        throw new RuntimeException(
            "Failed to fetch all parcels "
            . "for {$cadastralArea['name']}."
        );
    }

    if (
        !mb_check_encoding(
            $response,
            'UTF-8'
        )
    ) {
        throw new RuntimeException(
            'CUZK response is not valid UTF-8.'
        );
    }

    $file = $storageDirectory
        . '/'
        . $slug
        . '-parcels.xml';

    if (
        file_put_contents(
            $file,
            $response
        ) === false
    ) {
        throw new RuntimeException(
            "Failed to save parcel data to: {$file}"
        );
    }

    echo "Saved ALL parcels: {$file}"
        . PHP_EOL;

     // Print the number of matching and returned features.
    $xml = simplexml_load_string(
        $response
    );

    if ($xml !== false) {
        $attributes = $xml->attributes();

        if (
            isset(
                $attributes['numberMatched']
            )
        ) {
            echo "Number matched: "
                . $attributes['numberMatched']
                . PHP_EOL;
        }

        if (
            isset(
                $attributes['numberReturned']
            )
        ) {
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