'use strict';

const API_URL = '/api/parcels.php';

const INITIAL_CENTER = [
    50.43723,
    15.35162
];

const INITIAL_ZOOM = 14;

let parcelLayer = null;

let requestInProgress = false;

const parcelCountElement =
    document.getElementById(
        'parcel-count'
    );

const mapStatusElement =
    document.getElementById(
        'map-status'
    );

const mapElement =
    document.getElementById(
        'map'
    );

const map = L.map(
    'map'
).setView(
    INITIAL_CENTER,
    INITIAL_ZOOM
);

L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
        maxZoom: 19,

        attribution:
            '&copy; OpenStreetMap contributors',
    }
).addTo(map);

parcelLayer = L.geoJSON(
    null,
    {
        style: {
            color: '#2563eb',
            weight: 1,
            opacity: 0.8,
            fillColor: '#3b82f6',
            fillOpacity: 0.25,
        },

        onEachFeature: (
            feature,
            layer
        ) => {
            layer.bindPopup(
                createParcelPopup(
                    feature
                )
            );

            layer.on({
                mouseover: () => {
                    layer.setStyle({
                        weight: 2,
                        fillOpacity: 0.45,
                    });
                },

                mouseout: () => {
                    parcelLayer.resetStyle(
                        layer
                    );
                },
            });
        },
    }
).addTo(map);

function createParcelPopup(
    feature
) {
    const properties =
        feature.properties || {};

    return `
        <div class="parcel-popup">

            <div class="parcel-popup-title">
                Parcela
                ${escapeHtml(
                    properties.label ?? '—'
                )}
            </div>

            <div class="parcel-popup-row">
                <span class="parcel-popup-label">
                    CUZK ID:
                </span>
                ${escapeHtml(
                    properties.cuzk_id ?? '—'
                )}
            </div>

            <div class="parcel-popup-row">
                <span class="parcel-popup-label">
                    Katastrální číslo:
                </span>
                ${escapeHtml(
                    properties
                        .national_cadastral_reference
                        ?? '—'
                )}
            </div>

            <div class="parcel-popup-row">
                <span class="parcel-popup-label">
                    Výměra:
                </span>
                ${
                    properties.area_value !== null
                    && properties.area_value !== undefined
                        ? escapeHtml(
                            `${properties.area_value} m²`
                        )
                        : '—'
                }
            </div>

            <div class="parcel-popup-row">
                <span class="parcel-popup-label">
                    Katastrální území:
                </span>
                ${escapeHtml(
                    properties.zoning_name
                    ?? '—'
                )}
            </div>

            <div class="parcel-popup-row">
                <span class="parcel-popup-label">
                    Správní jednotka:
                </span>
                ${escapeHtml(
                    properties
                        .administrative_unit_name
                        ?? '—'
                )}
            </div>

        </div>
    `;
}

async function loadParcels() {
    if (requestInProgress) {
        return;
    }

    requestInProgress = true;

    mapElement.classList.add(
        'loading'
    );

    mapStatusElement.textContent =
        'Načítání parcel...';

    try {
        const bounds =
            map.getBounds();

        const bbox = [
            bounds.getWest(),
            bounds.getSouth(),
            bounds.getEast(),
            bounds.getNorth(),
        ].join(',');

        const response =
            await fetch(
                `${API_URL}?bbox=${encodeURIComponent(
                    bbox
                )}`,
                {
                    headers: {
                        Accept:
                            'application/json',
                    },
                }
            );

        if (!response.ok) {
            throw new Error(
                `HTTP ${response.status}`
            );
        }

        const data =
            await response.json();

        if (
            data.type !==
                'FeatureCollection'
            || !Array.isArray(
                data.features
            )
        ) {
            throw new Error(
                'Invalid GeoJSON response'
            );
        }

        parcelLayer.clearLayers();

        parcelLayer.addData(
            data
        );

        parcelCountElement.textContent =
            `Parcely: ${
                data.features.length
            }`;

        mapStatusElement.textContent =
            'Připraveno';

    } catch (error) {
        console.error(
            'Failed to load parcels:',
            error
        );

        mapStatusElement.textContent =
            'Chyba při načítání parcel';

    } finally {
        requestInProgress = false;

        mapElement.classList.remove(
            'loading'
        );
    }
}

function escapeHtml(
    value
) {
    return String(value)
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
}

map.on(
    'moveend',
    loadParcels
);

loadParcels();