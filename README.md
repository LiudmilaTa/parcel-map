# Parcel Map – Jičín

Webová aplikace pro zobrazení katastrálních parcel v okolí okresu Jičín na mapě.

## O projektu

- Aplikace zobrazuje skutečné katastrální parcely z ČÚZK.
- Na mapě jsou dostupná reálná katastrální území:
  - Jičín
  - Miletín
  - Sobotka
  - Stará Paka
- Pro každé území se nejdříve stáhne jeho zoning a z jeho obálky se vypočítá oblast pro načtení parcel.
- Data se uloží do lokální databáze a z ní se pak vykreslují na mapě.

- Evidence času a odpovědi k projektu: [Projektový zápisník](PROJECT_REPORT.md)

## Požadavky

- PHP 8.4+
- Composer 2.x
- MariaDB 10.6+
- V PHP musí být povoleny rozšíření: `curl`, `mbstring`, `openssl`, `pdo_mysql`, `sqlite3`, `zip`

## Jak spustit

### 1. Instalace závislostí

Na novém počítači spusťte: - `php scripts/setup-dependencies.php`

### 2. Spuštění inicializace

Pro první spuštění spusťte: - `php scripts/bootstrap.php`

Skript vytvoří databázi, připraví tabulku `parcels` a načte data z ČÚZK.

### 3. Spuštění aplikace

Po úspěšné inicializaci spusťte:
- `php -S 127.0.0.1:8000 -t public`

Pak otevřete v prohlížeči:
- `http://127.0.0.1:8000/`

### 4. Smoke testy API

Po spuštění web serveru můžete ověřit nejdůležitější endpointy:
- `php scripts/test-api-smoke.php`

Testy kontrolují:
- `GET /api/health.php`:
  endpoint musí vrátit HTTP `200`, `status = ok`, `database.status = ok` a počet parcel `parcels.count > 0`.
- `GET /api/zoning.php`:
  endpoint musí vrátit HTTP `200` a validní GeoJSON (`type = FeatureCollection`, `features` je pole a není prázdné).
- `GET /api/parcels.php`:
  endpoint musí vrátit HTTP `200` a validní GeoJSON parcel pro testovací oblast (`bbox`) a vybraný `zoning_names`.
- Validace chybného `bbox`:
  test schválně volá neplatný `bbox` (např. jen 3 čísla místo 4) a očekává HTTP `400` s chybou `Invalid bbox format.`.

`bbox` znamená obdélník v mapě ve formátu `minX,minY,maxX,maxY`:
- `minX` = západní hranice
- `minY` = jižní hranice
- `maxX` = východní hranice
- `maxY` = severní hranice

Pokud aplikace běží na jiné adrese/portu, nastavte před testem base URL:

```powershell
$env:PARCEL_MAP_BASE_URL = "http://127.0.0.1:8010"
php scripts/test-api-smoke.php
```

### 5. Pokud chcete změnit připojení k databázi

Můžete nastavit tyto proměnné prostředí:
- `MAPA_PARCEL_DB`
- `MAPA_PARCEL_USER`
- `MAPA_PARCEL_PASSWORD`
- `MAPA_PARCEL_HOST`
- `MAPA_PARCEL_PORT`

#### Varianta A: `.env` soubor

V kořeni projektu vytvořte soubor `.env` (nejlépe zkopírujte `.env.example`) s těmito hodnotami:

```dotenv
MAPA_PARCEL_DB=mapa_parcel
MAPA_PARCEL_USER=mapa_parcel
MAPA_PARCEL_PASSWORD=1111
MAPA_PARCEL_HOST=127.0.0.1
MAPA_PARCEL_PORT=3306
```

Pak spusťte:

```powershell
php scripts/bootstrap.php
php -S 127.0.0.1:8000 -t public
```

Poznámka: `.env` se načítá automaticky v `scripts/bootstrap.php` i `public/api/parcels.php`.

#### Varianta B: Windows PowerShell 

Proměnné nastavte ve stejném terminálu, ve kterém pak spustíte bootstrap i web server.

php scripts/bootstrap.php
php -S 127.0.0.1:8000 -t public
```

Poznámka: když otevřete nový terminál, proměnné nastavené přes `$env:` se ztratí a je potřeba je nastavit znovu.

#### Varianta C: Trvalé nastavení v systému 

```powershell
setx MAPA_PARCEL_DB "mapa_parcel"
setx MAPA_PARCEL_USER "mapa_parcel"
setx MAPA_PARCEL_PASSWORD "1111"
setx MAPA_PARCEL_HOST "127.0.0.1"
setx MAPA_PARCEL_PORT "3306"
```

Priorita konfigurace:
1. Proměnné z prostředí / `.env`
2. Výchozí hodnoty v kódu

