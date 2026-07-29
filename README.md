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

## Rozhodnutí a poznámky

- Zvolila jsem přístup „download once + import to DB“, protože to je stabilnější pro mapové rozhraní než každý pohyb mapy volat ČÚZK live.
- Hlavní důvod je výkon: při zobrazení většího území se nečeká na WFS request při každém zoomu/přesunu.
