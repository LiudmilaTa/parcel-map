<?php

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Mapa parcel – Jičín</title>

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <link
        rel="stylesheet"
        href="/assets/css/app.css"
    >
</head>

<body>

<div class="app">

    <header class="app-header">
        <h1>Mapa parcel</h1>

        <div class="app-status">
            <span id="parcel-count">
                Parcely: 0
            </span>

            <span id="map-status">
                Připraveno
            </span>
        </div>
    </header>

    <main class="app-main">
        <div id="map"></div>
    </main>

</div>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
></script>

<script
    src="/assets/js/app.js"
></script>

</body>
</html>