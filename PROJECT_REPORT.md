# Projektový zápisník

## Evidence času

| Úkol | Poznámka | Skutečný čas, min |
| --- | --- | ---: |
| Initial project setup | Příprava vývojového prostředí, kontrola PHP a rozšíření, inicializace Git a základní struktury projektu. | 30 |
| Nastavení vývojového prostředí | Instalace a ověření PHP 8.4, Composeru, Git a lokálního PHP serveru. | 15 |
| Nastavení MariaDB | Vytvoření databáze a uživatele, konfigurace připojení a ověření komunikace PHP -> MariaDB. | 30 |
| Analýza a průzkum služeb ČÚZK | Ověření dostupných WFS endpointů, atributů parcel a vhodné formy dat pro další zpracování. | 90 |
| Zpracování geometrie parcel | Parsování polygonů, vnitřních prstenců a převod souřadnic z EPSG:5514 do EPSG:4326. | 90 |
| Implementace importu dat z ČÚZK | Příprava logiky pro stažení XML dat a jejich následné zpracování. | 120 |
| Výběr rozsahu dat | Ověření testovacího rozsahu několika reálných parcel z ČÚZK a určení vhodného rozsahu pro finální aplikaci. | 30 |
| Databázová vrstva | Implementace PDO připojení, migrace a repository vrstvy pro práci s parcelami. | 30 |
| Návrh databázové struktury | Navržení tabulky `parcels` s unikátním identifikátorem ČÚZK, atributy parcel a uloženou geometrií. | 30 |
| Import dat do MariaDB | Import testovacích parcel a následně rozšířený import finálního datasetu. | 45 |
| Validace importovaných dat | Ověření počtu záznamů, správnosti atributů, bounding boxu a uložené geometrie. | 30 |
| Implementace PHP API | Vytvoření API endpointu pro načítání parcel podle bounding boxu a vracení GeoJSON. | 45 |
| Implementace mapy | Vytvoření interaktivní mapy pomocí Leaflet a nastavení výchozího centra pro oblast Jičína. | 30 |
| Zobrazení parcel na mapě | Dynamické načítání parcel podle aktuálního pohledu na mapě. | 30 |
| Interakce s parcelami | Zvýraznění parcel při najetí myši a zobrazení popupu po kliknutí. | 15 |
| Detail parcely | Omezení načítání dat pouze pro aktuální viewport a optimalizace front-endových requestů. | 30 |
| Nečekaný problém | Během implementace se ukázalo, že původní přístup k načítání parcel pouze na základě bounding boxu nebyl z hlediska stability a přesnosti dostatečně vhodný. Při změnách přiblížení a pohybu mapy docházelo k nekompletnímu načtení dat a k problémům s jejich správným vykreslením. V důsledku toho byla upravena logika načítání tak, aby byla lépe sladěna s datovým modelem v databázi a s aktuálním viewportem mapy. Současně byly vyřešeny problémy s přístupem k databázi, importem dat z ČÚZK a laděním chování mapy při zoomu. | 150 |
| Automatizované testy | Byly implementovány základní automatizované testy pro ověření API, připojení k databázi a klíčových modulů pro zpracování dat. | 30 |
| README a dokumentace | Příprava popisu projektu, instalačních kroků a základní dokumentace pro uživatele. | 90 |

Projekt mi celkem zabral 16 hodin. Oproti odhadu 4–12 hodin se čas navýšil hlavně kvůli řešení nečekaného problému se stabilitou načítání parcel při zoomu a posunu mapy, úpravě logiky viewportu a doladění importu dat z ČÚZK i databázového napojení.

## Krátký zápisník projektu

### Download once + import do DB místo live WFS dotazu

Mapa je interaktivní (zoom, posun), takže volat ČÚZK při každém pohybu by bylo pomalé a nespolehlivé.
Proto jsem data stáhla jednou, uložila do DB a aplikace čte z lokálních dat.
Výhoda je rychlejší a stabilnější chování mapy.

### Co mě překvapilo

Největší překvapení bylo, že načítat parcely jen podle bounding boxu nestačilo.
Při zoomu a posunu mapy se někdy načetla neúplná data a vykreslení nebylo stabilní.
Proto jsem upravila logiku načítání podle aktuálního viewportu a sladila ji s databází.
Současně jsem dořešila i připojení k DB a stabilnější import dat z ČÚZK.

### Co bych s více časem řešila jinak

1. **Lepší data o parcelách**

Doplnila bych více údajů o parcelách (kde to jde), aby popup ukazoval co nejvíce informací.

2. **Vyšší výkon při velké mapě**

Více bych ladila rychlost při zobrazení větší oblasti (cache, výkonnostní testy), aby mapa zůstala plynulá i při velkém množství parcel.

## Nejasnosti v zadání a má rozhodnutí

| Nejasnost | Co zadání neřeklo | Mé rozhodnutí | Proč |
| --- | --- | --- | --- |
| **Rozsah dat** | "Okres Jičín" je vágní | Zvolila jsem 4 katastrální území: Jičín, Miletín, Sobotka, Stará Paka | Jsou to reálná území bez hranice okresu, dobrá pro demo |
| **Mapa - startovní zoom** | Neuvedeno | Nastavila jsem [49.6, 15.3] (Jičín), zoom 11 | Vhodné pro vidění všech 4 území najednou |
| **Interakce s parcelou** | Pouze "zobrazit" | Přidala jsem click -> popup s detaily, hover -> zvýraznění | Standard UX pro mapové aplikace |