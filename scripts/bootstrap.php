<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelMap\Database\Connection;
use ParcelMap\Repositories\ParcelRepository;
use ParcelMap\Services\CoordinateTransformService;
use ParcelMap\Services\CuzkParcelParser;
use proj4php\Proj4php;

$projectRoot = dirname(__DIR__);
$storageDirectory = $projectRoot . '/storage/cuzk/final';
$databaseName = getenv('MAPA_PARCEL_DB') ?: 'mapa_parcel';
$username = getenv('MAPA_PARCEL_USER') ?: 'mapa_parcel';
$password = getenv('MAPA_PARCEL_PASSWORD') ?: '1111';
$host = getenv('MAPA_PARCEL_HOST') ?: '127.0.0.1';
$port = (int) (getenv('MAPA_PARCEL_PORT') ?: '3306');

function ensureDirectory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException("Failed to create directory: {$path}");
    }
}

function ensureDatabaseAndTable(string $host, int $port, string $database, string $username, string $password): void
{
    $adminConnection = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $adminConnection->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $database));

    $connection = new Connection($host, $port, $database, $username, $password);
    $pdo = $connection->getPdo();

    $migrationFile = $GLOBALS['projectRoot'] . '/database/migrations/001_create_parcels_table.sql';

    if (!file_exists($migrationFile)) {
        throw new RuntimeException("Migration file not found: {$migrationFile}");
    }

    $sql = file_get_contents($migrationFile);

    if ($sql === false) {
        throw new RuntimeException('Failed to read migration file.');
    }

    $pdo->exec($sql);

    echo "Database and table are ready." . PHP_EOL;
}

function fetchCuzkResponse(string $url, string $description): string
{
    $response = file_get_contents($url);

    if ($response === false) {
        throw new RuntimeException("Failed to fetch {$description}.");
    }

    return $response;
}

function buildCuzkUrl(string $baseUrl, array $params): string
{
    return $baseUrl . '?' . http_build_query($params);
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

    if ($lowerValues === false || $upperValues === false || count($lowerValues) < 2 || count($upperValues) < 2) {
        return null;
    }

    return [
        'minX' => (float) $lowerValues[0],
        'minY' => (float) $lowerValues[1],
        'maxX' => (float) $upperValues[0],
        'maxY' => (float) $upperValues[1],
    ];
}

function downloadAndImportData(string $projectRoot, string $storageDirectory, string $host, int $port, string $database, string $username, string $password): void
{
    $cadastralAreas = [
        'jicin' => ['name' => 'Jičín', 'code' => '659541'],
        'miletin' => ['name' => 'Miletín'],
        'sobotka' => ['name' => 'Sobotka'],
        'stara-paka' => ['name' => 'Stará Paka'],
    ];

    $baseUrl = 'https://services.cuzk.gov.cz/wfs/inspire-cp-wfs.asp';

    ensureDirectory($storageDirectory);

    echo 'Fetching CadastralParcel schema...' . PHP_EOL;
    $schemaUrl = buildCuzkUrl($baseUrl, [
        'service' => 'WFS',
        'request' => 'DescribeFeatureType',
        'version' => '2.0.0',
        'typeNames' => 'cp:CadastralParcel',
    ]);
    $schema = fetchCuzkResponse($schemaUrl, 'CadastralParcel schema');
    file_put_contents($projectRoot . '/storage/cuzk/cadastral-parcel-schema.xml', $schema);

    echo 'Fetching stored queries...' . PHP_EOL;
    $storedQueriesUrl = buildCuzkUrl($baseUrl, [
        'service' => 'WFS',
        'request' => 'ListStoredQueries',
        'version' => '2.0.0',
    ]);
    $storedQueries = fetchCuzkResponse($storedQueriesUrl, 'stored queries');
    file_put_contents($projectRoot . '/storage/cuzk/stored-queries.xml', $storedQueries);

    foreach ($cadastralAreas as $slug => $cadastralArea) {
        echo PHP_EOL . "Fetching zoning for {$cadastralArea['name']}..." . PHP_EOL;
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

        $zoningResponse = fetchCuzkResponse(buildCuzkUrl($baseUrl, $params), 'zoning data');
        file_put_contents($storageDirectory . '/' . $slug . '-zoning.xml', $zoningResponse);

        echo "Downloading parcels for {$cadastralArea['name']}..." . PHP_EOL;
        $zoningXml = file_get_contents($storageDirectory . '/' . $slug . '-zoning.xml');
        if ($zoningXml === false) {
            throw new RuntimeException("Failed to read zoning file for {$cadastralArea['name']}");
        }

        $bounds = extractEnvelopeBoundsFromZoningXml($zoningXml);
        if ($bounds === null) {
            throw new RuntimeException("Unable to determine envelope for {$cadastralArea['name']}");
        }

        $parcelParams = [
            'service' => 'WFS',
            'request' => 'GetFeature',
            'version' => '2.0.0',
            'typeNames' => 'cp:CadastralParcel',
            'bbox' => implode(',', [
                $bounds['minX'],
                $bounds['minY'],
                $bounds['maxX'],
                $bounds['maxY'],
                'http://www.opengis.net/def/crs/EPSG/0/5514',
            ]),
        ];

        $parcelResponse = fetchCuzkResponse(buildCuzkUrl($baseUrl, $parcelParams), 'parcel data');
        file_put_contents($storageDirectory . '/' . $slug . '-parcels.xml', $parcelResponse);
    }

    echo 'Importing parcels into database...' . PHP_EOL;
    $connection = new Connection($host, $port, $database, $username, $password);
    $pdo = $connection->getPdo();
    $proj4 = new Proj4php();
    $parser = new CuzkParcelParser(new CoordinateTransformService($proj4));
    $repository = new ParcelRepository($pdo);

    $xmlFiles = glob($storageDirectory . '/*-parcels.xml');
    if ($xmlFiles === false || $xmlFiles === []) {
        throw new RuntimeException('No parcel XML files found after download.');
    }

    $imported = 0;
    $skipped = 0;

    foreach ($xmlFiles as $xmlFile) {
        $reader = new XMLReader();
        if (!$reader->open($xmlFile)) {
            echo "Failed to open {$xmlFile}" . PHP_EOL;
            continue;
        }

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'CadastralParcel') {
                continue;
            }

            $parcelXml = $reader->readOuterXml();
            if ($parcelXml === '') {
                $skipped++;
                continue;
            }

            try {
                $feature = $parser->parseSingleParcel($parcelXml);
                if ($feature === null) {
                    $skipped++;
                    continue;
                }

                $properties = $feature['properties'] ?? [];
                $geometry = $feature['geometry'] ?? null;
                if (!is_array($properties) || !is_array($geometry)) {
                    $skipped++;
                    continue;
                }

                $repository->save([
                    'cuzk_id' => (string) ($properties['id'] ?? ''),
                    'local_id' => isset($properties['localId']) ? (string) $properties['localId'] : null,
                    'label' => (string) ($properties['label'] ?? ''),
                    'national_cadastral_reference' => isset($properties['nationalCadastralReference']) ? (string) $properties['nationalCadastralReference'] : null,
                    'area_value' => isset($properties['areaValue']) ? (float) $properties['areaValue'] : null,
                    'zoning_name' => isset($properties['zoningName']) ? (string) $properties['zoningName'] : null,
                    'administrative_unit_name' => isset($properties['administrativeUnitName']) ? (string) $properties['administrativeUnitName'] : null,
                    'min_x' => (float) ($properties['minX'] ?? 0),
                    'min_y' => (float) ($properties['minY'] ?? 0),
                    'max_x' => (float) ($properties['maxX'] ?? 0),
                    'max_y' => (float) ($properties['maxY'] ?? 0),
                    'geometry' => $geometry,
                ]);

                $imported++;
            } catch (Throwable $exception) {
                $skipped++;
                echo 'Skipped parcel: ' . $exception->getMessage() . PHP_EOL;
            }
        }

        $reader->close();
    }

    echo 'Import completed.' . PHP_EOL;
    echo "Imported parcels: {$imported}" . PHP_EOL;
    echo "Skipped parcels: {$skipped}" . PHP_EOL;
}

try {
    ensureDatabaseAndTable($host, $port, $databaseName, $username, $password);
    downloadAndImportData($projectRoot, $storageDirectory, $host, $port, $databaseName, $username, $password);
    echo 'Bootstrap completed successfully.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Bootstrap failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
