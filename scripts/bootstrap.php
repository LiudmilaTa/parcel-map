<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelMap\Database\Connection;
use ParcelMap\Repositories\ParcelRepository;
use ParcelMap\Services\CoordinateTransformService;
use ParcelMap\Services\CuzkParcelParser;
use proj4php\Proj4php;
use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
$storageDirectory = $projectRoot . '/storage/cuzk/final';

if (file_exists($projectRoot . '/.env')) {
    $dotenv = Dotenv::createImmutable($projectRoot);
    $dotenv->load(); // Load into $_ENV and $_SERVER
}

function getDatabaseConfig(): array
{
    $getEnvValue = static function (string $key, string $alternateKey = '', string $default = ''): string {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: '';
        if ($value !== '') {
            return $value;
        }
        if ($alternateKey !== '') {
            return $_ENV[$alternateKey] ?? $_SERVER[$alternateKey] ?? getenv($alternateKey) ?: $default;
        }
        return $default;
    };

    return [
        'database' => $getEnvValue('MAPA_PARCEL_DB', 'DB_NAME', 'mapa_parcel'),
        'username' => $getEnvValue('MAPA_PARCEL_USER', 'DB_USER', 'mapa_parcel'),
        'password' => $getEnvValue('MAPA_PARCEL_PASSWORD', 'DB_PASSWORD', '1111'),
        'host' => $getEnvValue('MAPA_PARCEL_HOST', 'DB_HOST', '127.0.0.1'),
        'port' => (int) $getEnvValue('MAPA_PARCEL_PORT', 'DB_PORT', '3306'),
    ];
}

function printDatabaseConfig(array $config): void
{
    $passwordState = $config['password'] === '' ? '(empty)' : '[set]';
    echo 'Database config: host=' . $config['host'] . ', port=' . $config['port'] . ', database=' . $config['database'] . ', user=' . $config['username'] . ', password=' . $passwordState . PHP_EOL;
    echo 'Override with MAPA_PARCEL_HOST, MAPA_PARCEL_PORT, MAPA_PARCEL_DB, MAPA_PARCEL_USER, MAPA_PARCEL_PASSWORD.' . PHP_EOL;
}

function createAdminConnection(string $host, int $port, string $username, string $password): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);

    // Try env variables for admin
    $envAdminUser = getenv('MAPA_PARCEL_ADMIN_USER') ?: 'root';
    $envAdminPassword = getenv('MAPA_PARCEL_ADMIN_PASSWORD') ?: '';

    // First try with default credentials
    try {
        $pdo = new \PDO($dsn, $envAdminUser, $envAdminPassword, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        echo "Connected as: {$envAdminUser}" . PHP_EOL;
        return $pdo;
    } catch (\Throwable $exception) {
        // Default credentials didn't work, ask for password
    }

    // Ask user for password
    echo PHP_EOL . "Cannot connect with default credentials." . PHP_EOL;
    echo "Enter your MariaDB/MySQL root password: ";
    
    $userPassword = trim(fgets(STDIN));
    
    try {
        $pdo = new \PDO($dsn, 'root', $userPassword, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        echo "Connected as: root" . PHP_EOL;
        return $pdo;
    } catch (\Throwable $exception) {
        // Even with password it didn't work
    }

    // If all failed
    echo PHP_EOL . "ERROR: Cannot connect to MySQL/MariaDB database." . PHP_EOL;
    echo "Please ensure:" . PHP_EOL;
    echo "  1. MariaDB/MySQL server is running" . PHP_EOL;
    echo "  2. You provided the correct root password" . PHP_EOL;
    echo "  3. Database is accessible on {$host}:{$port}" . PHP_EOL;
    echo PHP_EOL;
    echo "Alternative setup (manual SQL):" . PHP_EOL;
    echo "  mysql -u root -p" . PHP_EOL;
    echo "  Then execute:" . PHP_EOL;
    echo "    CREATE DATABASE IF NOT EXISTS mapa_parcel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" . PHP_EOL;
    echo "    CREATE USER IF NOT EXISTS 'mapa_parcel'@'localhost' IDENTIFIED BY '1111';" . PHP_EOL;
    echo "    GRANT ALL PRIVILEGES ON mapa_parcel.* TO 'mapa_parcel'@'localhost';" . PHP_EOL;
    echo "    FLUSH PRIVILEGES;" . PHP_EOL;
    echo "    EXIT;" . PHP_EOL;
    echo PHP_EOL;

    throw new RuntimeException('Unable to connect to MySQL server.');
}

function ensureAppDatabaseUser(PDO $adminConnection, string $database, string $username, string $password, string $host): void
{
    if ($username === '') {
        return;
    }

    $hostPattern = in_array($host, ['127.0.0.1', 'localhost'], true) ? 'localhost' : '%';
    $escapedUser = str_replace("'", "''", $username);
    $escapedHost = str_replace("'", "''", $hostPattern);
    $escapedPassword = str_replace("'", "''", $password);

    // Create user or update password if exists
    try {
        $adminConnection->exec("CREATE USER IF NOT EXISTS '{$escapedUser}'@'{$escapedHost}' IDENTIFIED BY '{$escapedPassword}'");
        echo "User '{$username}'@'{$hostPattern}' created or already exists." . PHP_EOL;
    } catch (\Throwable $exception) {
        if (stripos($exception->getMessage(), '1227') !== false || stripos($exception->getMessage(), 'CREATE USER') !== false) {
            // No CREATE USER privilege, but try GRANT - user may already exist
            echo "Skipping user creation (no CREATE USER privilege), attempting to grant privileges..." . PHP_EOL;
        } else {
            throw $exception;
        }
    }

    // Try to update password (in case user already existed)
    try {
        $adminConnection->exec("ALTER USER '{$escapedUser}'@'{$escapedHost}' IDENTIFIED BY '{$escapedPassword}'");
        echo "User password updated." . PHP_EOL;
    } catch (\Throwable $exception) {
        // If ALTER USER fails, continue - user may not have password set
    }

    // Try to grant privileges
    try {
        $adminConnection->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$escapedUser}'@'{$escapedHost}'");
        $adminConnection->exec('FLUSH PRIVILEGES');
        echo "Privileges granted to '{$username}'@'{$hostPattern}'." . PHP_EOL;
    } catch (\Throwable $exception) {
        // If it fails, ignore - user may already have privileges
        if (stripos($exception->getMessage(), '1227') !== false) {
            echo "Skipping privilege grant (no privilege to grant)." . PHP_EOL;
        } else {
            throw $exception;
        }
    }
}

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
    // First try to connect as application user (if already exists with privileges)
    try {
        $connection = new Connection($host, $port, $database, $username, $password);
        $pdo = $connection->getPdo();
        echo "Connected as application user '{$username}' — database and user already exist." . PHP_EOL;
        
        // Verify table exists
        $migrationFile = $GLOBALS['projectRoot'] . '/database/migrations/001_create_parcels_table.sql';
        if (file_exists($migrationFile)) {
            $sql = file_get_contents($migrationFile);
            if ($sql !== false) {
                $pdo->exec($sql);
                echo "Table 'parcels' is ready." . PHP_EOL;
            }
        }
        return;
    } catch (\Throwable $e) {
        // Application user cannot connect - need admin
        echo "Application user connection failed, attempting admin setup..." . PHP_EOL;
    }

    // If direct connection fails, use admin
    $adminConnection = createAdminConnection($host, $port, $username, $password);
    ensureAppDatabaseUser($adminConnection, $database, $username, $password, $host);

    try {
        $adminConnection->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $database));
        echo "Database '{$database}' is ready." . PHP_EOL;
    } catch (\Throwable $e) {
        echo "Warning: Could not create database (may already exist): " . $e->getMessage() . PHP_EOL;
    }

    // Now try to connect as application user to create table
    try {
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
        echo "Table 'parcels' is ready." . PHP_EOL;
    } catch (\Throwable $e) {
        throw new RuntimeException("Failed to set up table: " . $e->getMessage());
    }
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
    $config = getDatabaseConfig();
    $databaseName = $config['database'];
    $username = $config['username'];
    $password = $config['password'];
    $host = $config['host'];
    $port = $config['port'];

    printDatabaseConfig($config);
    ensureDatabaseAndTable($host, $port, $databaseName, $username, $password);
    downloadAndImportData($projectRoot, $storageDirectory, $host, $port, $databaseName, $username, $password);
    echo 'Bootstrap completed successfully.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Bootstrap failed: ' . $exception->getMessage() . PHP_EOL . PHP_EOL);
    fwrite(STDERR, 'Řešení:' . PHP_EOL);
    fwrite(STDERR, '1. Ověřte, že MySQL/MariaDB je spuštěný: mysql -u root' . PHP_EOL);
    fwrite(STDERR, '2. Pokud máte heslo, nastavte environment proměnnou: $env:MAPA_PARCEL_ADMIN_PASSWORD = "vase_heslo"' . PHP_EOL);
    fwrite(STDERR, '3. Vytvořte uživatele a databázi ručně (viz níže).' . PHP_EOL . PHP_EOL);
    fwrite(STDERR, 'Manuální setup v MySQL:' . PHP_EOL);
    fwrite(STDERR, 'mysql -u root' . PHP_EOL);
    fwrite(STDERR, 'CREATE DATABASE IF NOT EXISTS mapa_parcel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' . PHP_EOL);
    fwrite(STDERR, 'CREATE USER IF NOT EXISTS \'mapa_parcel\'@\'localhost\' IDENTIFIED BY \'1111\';' . PHP_EOL);
    fwrite(STDERR, 'GRANT ALL PRIVILEGES ON mapa_parcel.* TO \'mapa_parcel\'@\'localhost\';' . PHP_EOL);
    fwrite(STDERR, 'FLUSH PRIVILEGES;' . PHP_EOL);
    fwrite(STDERR, 'EXIT;' . PHP_EOL . PHP_EOL);
    fwrite(STDERR, 'Poté spusťte znovu: php scripts/bootstrap.php' . PHP_EOL);
    exit(1);
}
