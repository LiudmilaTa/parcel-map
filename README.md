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

## Požadavky

- PHP 8.4+ — stáhnout z https://www.php.net/downloads.php
- Composer 2.x — stáhnout z https://getcomposer.org/download/
- MariaDB 10.6+ — stáhnout z https://mariadb.org/download/
- V PHP musí být povoleny rozšíření: `curl`, `mbstring`, `openssl`, `pdo_mysql`, `sqlite3`, `zip`

## Jak spustit

### 1. Instalace závislostí

Na novém počítači spusťte:
- `php scripts/setup-dependencies.php`

### 2. Spuštění inicializace

Pro první spuštění spusťte:
- `php scripts/bootstrap.php`

Skript vytvoří databázi, připraví tabulku `parcels` a načte data z ČÚZK.

### 3. Spuštění aplikace

Po úspěšné inicializaci spusťte:
- `php -S 127.0.0.1:8000 -t public`

Pak otevřete v prohlížeči:
- `http://127.0.0.1:8000/`

### 4. Pokud chcete změnit připojení k databázi

Můžete nastavit tyto proměnné prostředí:
- `MAPA_PARCEL_DB`
- `MAPA_PARCEL_USER`
- `MAPA_PARCEL_PASSWORD`
- `MAPA_PARCEL_HOST`
- `MAPA_PARCEL_PORT`

Výchozí hodnoty jsou:
- databáze: `mapa_parcel`
- uživatel: `mapa_parcel`
- heslo: `1111`
- host: `127.0.0.1`
- port: `3306`

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

Proměnné nastavte ve stejném terminálu, ve kterém pak spustíte bootstrap i web server:

```powershell
$env:MAPA_PARCEL_DB = "mapa_parcel"
$env:MAPA_PARCEL_USER = "mapa_parcel"
$env:MAPA_PARCEL_PASSWORD = "1111"
$env:MAPA_PARCEL_HOST = "127.0.0.1"
$env:MAPA_PARCEL_PORT = "3306"

php scripts/bootstrap.php
php -S 127.0.0.1:8000 -t public
```

Ověření, že proměnné jsou nastavené:

```powershell
Get-ChildItem Env:MAPA_PARCEL*
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

Po `setx` zavřete terminál a otevřete nový.

Priorita konfigurace:
1. Proměnné z prostředí / `.env`
2. Výchozí hodnoty v kódu

## Rozhodnutí a poznámky

- Zvolila jsem přístup „download once + import to DB“, protože to je stabilnější pro mapové rozhraní než každý pohyb mapy volat ČÚZK live.
- Hlavní důvod je výkon: při zobrazení většího území se nečeká na WFS request při každém zoomu/přesunu.
