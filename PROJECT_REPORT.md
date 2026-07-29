# Projektovy zapisnik

## Evidence casu

| Ukol | Poznamka | Skutecny cas, min |
| --- | --- | ---: |
| Initial project setup | Priprava vyvojoveho prostredi, kontrola PHP a rozsireni, inicializace Git a zakladni struktury projektu. | 30 |
| Nastaveni vyvojoveho prostredi | Instalace a overeni PHP 8.4, Composeru, Git a lokalniho PHP serveru. | 15 |
| Nastaveni MariaDB | Vytvoreni databaze a uzivatele, konfigurace pripojeni a overeni komunikace PHP -> MariaDB. | 30 |
| Analyza a pruzkum sluzeb CUZK | Overeni dostupnych WFS endpointu, atributu parcel a vhodne formy dat pro dalsi zpracovani. | 90 |
| Zpracovani geometrie parcel | Parsovani polygonu, vnitrnich prstencu a prevod souradnic z EPSG:5514 do EPSG:4326. | 90 |
| Implementace importu dat z CUZK | Priprava logiky pro stazeni XML dat a jejich nasledne zpracovani. | 120 |
| Vyber rozsahu dat | Overeni testovaciho rozsahu nekolika realnych parcel z CUZK a urceni vhodneho rozsahu pro finalni aplikaci. | 30 |
| Databazova vrstva | Implementace PDO pripojeni, migrace a repository vrstvy pro praci s parcelami. | 30 |
| Navrh databazove struktury | Navrzeni tabulky `parcels` s unikatnim identifikatorem CUZK, atributy parcel a ulozenou geometrii. | 30 |
| Import dat do MariaDB | Import testovacich parcel a nasledne rozsireny import finalniho datasetu. | 45 |
| Validace importovanych dat | Overeni poctu zaznamu, spravnosti atributu, bounding boxu a ulozene geometrie. | 30 |
| Implementace PHP API | Vytvoreni API endpointu pro nacitani parcel podle bounding boxu a vraceni GeoJSON. | 45 |
| Implementace mapy | Vytvoreni interaktivni mapy pomoci Leaflet a nastaveni vychoziho centra pro oblast Jicina. | 30 |
| Zobrazeni parcel na mape | Dynamicke nacitani parcel podle aktualniho pohledu na mape. | 30 |
| Interakce s parcelami | Zvyrazneni parcel pri najeti mysi a zobrazeni popupu po kliknuti. | 15 |
| Detail parcely | Omezeni nacitani dat pouze pro aktualni viewport a optimalizace front-endovych requestu. | 30 |
| Necekany problem | Behem implementace se ukazalo, ze puvodni pristup k nacitani parcel pouze na zaklade bounding boxu nebyl z hlediska stability a presnosti dostatecne vhodny. Pri zmenach priblizeni a pohybu mapy dochazelo k nekompletnimu nacteni dat a k problemum s jejich spravnym vykreslenim. V dusledku toho byla upravena logika nacitani tak, aby byla lepe sladena s datovym modelem v databazi a s aktualnim viewportem mapy. Soucasne byly vyreseny problemy s pristupem k databazi, importem dat z CUZK a ladenim chovani mapy pri zoomu. | 150 |
| Automatizovane testy | Byly implementovany zakladni automatizovane testy pro overeni API, pripojeni k databazi a klicovych modulu pro zpracovani dat. | 30 |
| README a dokumentace | Priprava popisu projektu, instalacnich kroku a zakladni dokumentace pro uzivatele. | 90 |

Projekt mi celkem zabral 16 hodin. Oproti odhadu 4-12 hodin se cas navysila hlavne kvuli reseni necekaneho problemu se stabilitou nacitani parcel pri zoomu a posunu mapy, uprave logiky viewportu a doladeni importu dat z CUZK i databazoveho napojeni.

## Kratky zapisnik projektu

### Download once + import do DB misto live WFS dotazu

Mapa je interaktivni (zoom, posun), takze volat CUZK pri kazdem pohybu by bylo pomale a nespolehlive.

Proto jsem data stahla jednou, ulozila do DB a aplikace cte z lokalnich dat.

Vyhoda je rychlejsi a stabilnejsi chovani mapy.

### Co me prekvapilo

Nejvetsi prekvapeni bylo, ze nacitat parcely jen podle bounding boxu nestacilo.

Pri zoomu a posunu mapy se nekdy nacetla neuplna data a vykresleni nebylo stabilni.

Proto jsem upravila logiku nacitani podle aktualniho viewportu a sladila ji s databazi.

Soucasne jsem doresila i pripojeni k DB a stabilnejsi import dat z CUZK.

### Co bych s vice casem resil jinak

1. **Lepsi data o parcelach**
Doplnila bych vice udaju o parcelach (kde to jde), aby popup ukazoval co nejvice informaci.

2. **Vyssi vykon pri velke mape**
Vice bych ladila rychlost pri zobrazeni vetsi oblasti (cache, vykonnostni testy), aby mapa zustala plynula i pri velkem mnozstvi parcel.

## Nejasnosti v zadani a ma rozhodnuti

| Nejasnost | Co zadani nereklo | Me rozhodnuti | Proc |
| --- | --- | --- | --- |
| **Rozsah dat** | "Okres Jicin" je vagni | Zvolila jsem 4 katastralni uzemi: Jicin, Miletin, Sobotka, Stara Paka | Jsou to realna uzemi bez hranice okresu, dobra pro demo |
| **Mapa - startovni zoom** | Neuvedeno | Nastavila jsem [49.6, 15.3] (Jicin), zoom 11 | Vhodne pro videni vsech 4 uzemi najednou |
| **Interakce s parcelou** | Pouze "zobrazit" | Pridala jsem click -> popup s detaily, hover -> zvyrazneni | Standard UX pro mapove aplikace |

