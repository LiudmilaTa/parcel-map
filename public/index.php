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
        href="/vendor/leaflet/leaflet.css"
    >

    <link
        rel="stylesheet"
        href="/assets/css/app.css"
    >

</head>

<body>

<div class="app">

    <main class="app-main">
        <div id="map"></div>
    </main>

</div>

<script
    src="/vendor/leaflet/leaflet.min.js?v=1.9.4-p1"
></script>

<script>
    (function patchLeafletFirefoxEventCopy() {
        if (!window.L || !L.Util || typeof L.Util.extend !== 'function') {
            return;
        }

        const originalExtend = L.Util.extend;
        const blockedKeys = new Set([
            'mozPressure',
            'mozInputSource',
        ]);

        function safeExtend(dest, ...sources) {
            for (const src of sources) {
                if (!src) {
                    continue;
                }

                for (const key in src) {
                    if (blockedKeys.has(key)) {
                        continue;
                    }

                    dest[key] = src[key];
                }
            }

            return dest;
        }

        L.Util.extend = safeExtend;
        L.extend = safeExtend;
        L.Util._originalExtend = originalExtend;
    })();
</script>

<script
    src="/assets/js/app.js"
></script>

</body>
</html>