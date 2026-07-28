'use strict';

const API_URL = '/api/parcels.php';
const ZONING_API_URL = '/api/zoning.php';

const INITIAL_CENTER = [
    50.43723,
    15.35162
];

const INITIAL_ZOOM = 14;
const MIN_ZOOM_FOR_PARCELS = 13;
const LOAD_PARCELS_DEBOUNCE_MS = 220;

const ZONING_STYLE = {
    color: '#166534',
    weight: 2,
    opacity: 0.9,
    fillColor: '#15803d',
    fillOpacity: 0.12,
};

const PARCEL_HOVER_STYLE = {
    color: '#1e3a8a',
    weight: 2.6,
    opacity: 1,
    fillOpacity: 0.62,
};

const PARCEL_SELECTED_STYLE = {
    color: '#dc2626',
    weight: 2.8,
    opacity: 1,
    fillColor: '#ef4444',
    fillOpacity: 0.5,
};

const PARCEL_POPUP_OPTIONS = {
    offset: L.point(22, -26),
    className: 'parcel-detail-popup',
    autoPan: true,
    autoPanPaddingTopLeft: L.point(20, 20),
    autoPanPaddingBottomRight: L.point(20, 20),
    keepInView: false,
    maxWidth: 300,
};

// Mutable runtime state
let parcelLayer = null;
let zoningLayer = null;

let requestInProgress = false;
let parcelsLoadTimeout = null;
let pendingParcelReload = false;
let pendingParcelViewKey = null;
let activeParcelController = null;
let parcelRequestSequence = 0;
let selectedParcelLayer = null;

const mapElement =
    document.getElementById('map');

const map = L.map('map').setView(
    INITIAL_CENTER,
    INITIAL_ZOOM
);

// Base OSM layer.
L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
        maxZoom: 19,
        attribution:
            '&copy; OpenStreetMap contributors',
    }
).addTo(map);

// Create dedicated panes so we can control layer stacking reliably.
map.createPane('zoningPane');
map.getPane('zoningPane').style.zIndex = '400';
map.createPane('parcelsPane');
map.getPane('parcelsPane').style.zIndex = '650';

parcelLayer = L.geoJSON(
    null,
    {
        pane: 'parcelsPane',
        style: getParcelStyle,

        onEachFeature: (
            feature,
            layer
        ) => {
            layer.on({
                click: (event) => {
                    setSelectedParcelLayer(layer);
                    openParcelPopup(
                        feature,
                        layer,
                        event.latlng
                    );
                },

                mouseover: () => {
                    layer.setStyle(PARCEL_HOVER_STYLE);
                },

                mouseout: () => {
                    if (layer === selectedParcelLayer) {
                        layer.setStyle(getSelectedParcelStyle());
                        return;
                    }

                    parcelLayer.resetStyle(
                        layer
                    );
                },
            });
        },
    }
).addTo(map);

zoningLayer = L.geoJSON(
    null,
    {
        pane: 'zoningPane',
        style: ZONING_STYLE,

        onEachFeature: (
            feature,
            layer
        ) => {
            layer.bindPopup(
                createZoningPopup(
                    feature
                )
            );
        },
    }
).addTo(map);

// Popup and styling
// Parcel popup content.
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
                    Místní ID:
                </span>
                ${escapeHtml(
                    properties.local_id ?? '—'
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

// Adaptive parcel style by zoom level.
function getParcelStyle() {
    const zoom = map.getZoom();

    return {
        color: '#3b82f6',
        weight: zoom >= 14 ? 1.6 : 1.0,
        opacity: 0.9,
        fillColor: '#bfdbfe',
        fillOpacity: zoom >= 14 ? 0.32 : 0.2,
    };
}

// Place popup near the parcel, not over its center.
function getParcelPopupLatLng(
    layer,
    fallbackLatLng
) {
    if (!layer || !layer.getBounds) {
        return fallbackLatLng;
    }

    const bounds = layer.getBounds();

    if (!bounds || !bounds.isValid()) {
        return fallbackLatLng;
    }

    const center = bounds.getCenter();
    const spanLng = Math.max(
        bounds.getEast() - bounds.getWest(),
        0.00035
    );

    return L.latLng(
        center.lat,
        bounds.getEast() + spanLng * 0.25
    );
}

// Open parcel popup with common options.
function openParcelPopup(
    feature,
    layer,
    fallbackLatLng
) {
    L.popup(PARCEL_POPUP_OPTIONS)
        .setLatLng(
            getParcelPopupLatLng(
                layer,
                fallbackLatLng
            )
        )
        .setContent(
            createParcelPopup(feature)
        )
        .openOn(map);
}

// Style for currently selected parcel.
function getSelectedParcelStyle() {
    return PARCEL_SELECTED_STYLE;
}

// Toggle map loading cursor.
function setMapLoading(isLoading) {
    mapElement.classList.toggle('loading', isLoading);
}

// Replace parcel layer data in one place.
function refreshParcelLayer(features) {
    parcelLayer.clearLayers();

    parcelLayer.addData({
        type: 'FeatureCollection',
        features,
    });

    if (typeof parcelLayer.bringToFront === 'function') {
        parcelLayer.bringToFront();
    }
}

// Clear selected parcel highlight.
function clearSelectedParcelLayer() {
    if (selectedParcelLayer && parcelLayer) {
        parcelLayer.resetStyle(selectedParcelLayer);
    }

    selectedParcelLayer = null;
}

// Set selected parcel highlight.
function setSelectedParcelLayer(layer) {
    if (!layer) {
        return;
    }

    if (selectedParcelLayer && selectedParcelLayer !== layer && parcelLayer) {
        parcelLayer.resetStyle(selectedParcelLayer);
    }

    selectedParcelLayer = layer;
    layer.setStyle(getSelectedParcelStyle());

    if (typeof layer.bringToFront === 'function') {
        layer.bringToFront();
    }
}

// Geometry helpers
// Recursively collect all coordinate pairs from nested geometry arrays.
function collectCoordinates(values, coordinates = []) {
    if (!Array.isArray(values)) {
        return coordinates;
    }

    values.forEach((value) => {
        if (Array.isArray(value) && value.length >= 2 && typeof value[0] === 'number' && typeof value[1] === 'number') {
            coordinates.push(value);
            return;
        }

        if (Array.isArray(value)) {
            collectCoordinates(value, coordinates);
        }
    });

    return coordinates;
}

// Fast intersection check using feature bounds.
function featureIntersectsBounds(feature, bounds) {
    if (!feature || !feature.geometry) {
        return false;
    }

    const coordinates = collectCoordinates(feature.geometry.coordinates);

    if (coordinates.length === 0) {
        return false;
    }

    let minLng = Infinity;
    let minLat = Infinity;
    let maxLng = -Infinity;
    let maxLat = -Infinity;

    coordinates.forEach(([lng, lat]) => {
        minLng = Math.min(minLng, lng);
        minLat = Math.min(minLat, lat);
        maxLng = Math.max(maxLng, lng);
        maxLat = Math.max(maxLat, lat);
    });

    const featureBounds = L.latLngBounds(
        [minLat, minLng],
        [maxLat, maxLng]
    );

    if (bounds.intersects(featureBounds)) {
        return true;
    }

    const center = bounds.getCenter();
    const centerPoint = L.latLng(center.lat, center.lng);

    return featureBounds.contains(centerPoint);
}

// Parcel loading pipeline
// Stable key for current map view and zoom.
function getMapViewKey(bounds) {
    return [
        bounds.getWest().toFixed(6),
        bounds.getSouth().toFixed(6),
        bounds.getEast().toFixed(6),
        bounds.getNorth().toFixed(6),
        map.getZoom().toFixed(0),
    ].join('|');
}

// Collect visible cadastral area names.
function getVisibleZoningNames(bounds) {
    const zoningNames = new Set();

    zoningLayer.eachLayer((layer) => {
        if (!layer || !layer.getBounds) {
            return;
        }

        const layerBounds = layer.getBounds();

        if (!layerBounds.intersects(bounds)) {
            return;
        }

        const props = layer.feature && layer.feature.properties
            ? layer.feature.properties
            : {};

        const name = props.label || null;

        if (!name) {
            return;
        }

        zoningNames.add(name);
    });

    return Array.from(zoningNames);
}

// Cancel active parcel request if it exists.
function abortActiveParcelRequest() {
    if (activeParcelController) {
        activeParcelController.abort();
        activeParcelController = null;
    }
}

// Expand current bounds to reduce edge flicker while panning.
function createExpandedBounds(bounds, zoom) {
    const padding = zoom < 13 ? 0.03 : 0.01;

    return L.latLngBounds(
        [bounds.getSouth() - padding, bounds.getWest() - padding],
        [bounds.getNorth() + padding, bounds.getEast() + padding]
    );
}

// Build parcel API query params for current view.
function buildParcelQuery(expandedBounds) {
    const bbox = [
        expandedBounds.getWest(),
        expandedBounds.getSouth(),
        expandedBounds.getEast(),
        expandedBounds.getNorth(),
    ].join(',');

    const query = new URLSearchParams({
        bbox,
    });

    const visibleZoningNames = getVisibleZoningNames(expandedBounds);

    if (visibleZoningNames.length > 0) {
        query.set('zoning_names', visibleZoningNames.join('||'));
    }

    return query;
}

// Validate FeatureCollection payload shape.
function assertFeatureCollection(data, errorMessage) {
    if (
        data.type !== 'FeatureCollection'
        || !Array.isArray(data.features)
    ) {
        throw new Error(errorMessage);
    }
}

// Fetch and validate GeoJSON FeatureCollection.
async function fetchFeatureCollection(url, controller, errorMessage) {
    const response =
        await fetch(
            url,
            {
                headers: {
                    Accept:
                        'application/json',
                },
                signal: controller ? controller.signal : undefined,
            }
        );

    if (!response.ok) {
        throw new Error(
            `HTTP ${response.status}`
        );
    }

    const data = await response.json();

    assertFeatureCollection(data, errorMessage);

    return data;
}

// Parcel-specific wrapper around generic collection fetch.
async function fetchParcelsGeoJson(query, controller) {
    return fetchFeatureCollection(
        `${API_URL}?${query.toString()}`,
        controller,
        'Invalid GeoJSON response'
    );
}

// Finalize parcel request and handle queued reloads.
function finalizeParcelRequest(viewKey, requestId) {
    if (activeParcelController && activeParcelController.signal.aborted) {
        activeParcelController = null;
    }

    if (activeParcelController && requestId === parcelRequestSequence) {
        activeParcelController = null;
    }

    requestInProgress = false;

    setMapLoading(false);

    if (pendingParcelReload) {
        pendingParcelReload = false;
        const nextViewKey = pendingParcelViewKey;
        pendingParcelViewKey = null;

        if (nextViewKey && nextViewKey !== viewKey) {
            loadParcels();
        }
    }
}

// Debounced parcel reload on map view changes.
function scheduleLoadParcels() {
    if (parcelsLoadTimeout) {
        window.clearTimeout(parcelsLoadTimeout);
    }

    parcelsLoadTimeout = window.setTimeout(() => {
        loadParcels();
    }, LOAD_PARCELS_DEBOUNCE_MS);
}

// Main parcel loading flow for current map view.
async function loadParcels() {
    const bounds = map.getBounds();
    const zoom = map.getZoom();
    const viewKey = getMapViewKey(bounds);
    const requestId = ++parcelRequestSequence;

    if (zoom < MIN_ZOOM_FOR_PARCELS) {
        abortActiveParcelRequest();

        requestInProgress = false;
        parcelLayer.clearLayers();
        return;
    }

    if (requestInProgress) {
        pendingParcelReload = true;
        pendingParcelViewKey = viewKey;
        return;
    }

    requestInProgress = true;

    abortActiveParcelRequest();

    activeParcelController = new AbortController();

    setMapLoading(true);

    try {
        const expandedBounds = createExpandedBounds(bounds, zoom);
        const query = buildParcelQuery(expandedBounds);
        const data = await fetchParcelsGeoJson(query, activeParcelController);

        if (requestId !== parcelRequestSequence) {
            return;
        }

        const visibleFeatures = data.features.filter((feature) =>
            featureIntersectsBounds(feature, expandedBounds)
        );

        refreshParcelLayer(visibleFeatures);

    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }

    } finally {
        finalizeParcelRequest(viewKey, requestId);
    }
}

// Zoning popup content.
function createZoningPopup(
    feature
) {
    const properties =
        feature.properties || {};

    return `
        <div class="parcel-popup">
            <div class="parcel-popup-title">
                Katastrální území
                ${escapeHtml(
                    properties.label ?? '—'
                )}
            </div>

            <div class="parcel-popup-row">
                <span class="parcel-popup-label">
                    Reference:
                </span>
                ${escapeHtml(
                    properties.reference ?? '—'
                )}
            </div>
        </div>
    `;
}

// Replace zoning layer data in one place.
function refreshZoningLayer(data) {
    zoningLayer.clearLayers();
    zoningLayer.addData(data);
}

// Load zoning areas once at startup.
async function loadZonings() {
    try {
        const data = await fetchFeatureCollection(
            ZONING_API_URL,
            null,
            'Invalid zoning GeoJSON response'
        );

        refreshZoningLayer(data);
    } catch {
    }
}

// Escape HTML in popup values.
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

// Close any open popup when user starts map interaction.
function closePopupOnInteraction() {
    map.closePopup();
}

// Register map event handlers.
function registerMapEvents() {
    map.on('dragstart', closePopupOnInteraction);
    map.on('zoomstart', closePopupOnInteraction);
    map.on('popupclose', clearSelectedParcelLayer);
    map.on('moveend', scheduleLoadParcels);
    map.on('zoomend', scheduleLoadParcels);
}

// App bootstrap.
function initializeApp() {
    registerMapEvents();
    loadZonings();
    scheduleLoadParcels();
}

initializeApp();