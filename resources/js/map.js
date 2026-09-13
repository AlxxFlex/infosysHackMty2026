import { LngLatBounds, Map, NavigationControl } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

let courierMap = null;
let mapContainer = null;
let lastSelection = '';

const emptyStyle = {
    version: 8,
    sources: {
        osm: {
            type: 'raster',
            tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
            tileSize: 256,
            attribution: '© OpenStreetMap contributors',
        },
    },
    layers: [
        { id: 'background', type: 'background', paint: { 'background-color': '#0f172a' } },
        { id: 'osm-tiles', type: 'raster', source: 'osm', paint: { 'raster-opacity': 0.7 } },
    ],
};

function validFeature(feature) {
    const coordinates = feature?.geometry?.coordinates;
    if (!feature?.geometry || !['Point', 'LineString'].includes(feature.geometry.type)) return false;
    if (feature.geometry.type === 'Point') return Array.isArray(coordinates) && coordinates.length === 2 && coordinates.every(Number.isFinite) && coordinates[0] >= -180 && coordinates[0] <= 180 && coordinates[1] >= -90 && coordinates[1] <= 90;
    return Array.isArray(coordinates) && coordinates.length >= 2 && coordinates.every((point) => Array.isArray(point) && point.length === 2 && point.every(Number.isFinite) && point[0] >= -180 && point[0] <= 180 && point[1] >= -90 && point[1] <= 90);
}

function collection(features) {
    return { type: 'FeatureCollection', features };
}

function updateMap(payload) {
    if (!courierMap || !payload || payload.type !== 'FeatureCollection' || !Array.isArray(payload.features)) return;
    const features = payload.features.filter(validFeature);
    const points = features.filter((feature) => feature.geometry.type === 'Point');
    const routes = features.filter((feature) => feature.geometry.type === 'LineString' && feature.properties?.kind === 'recommended-route');
    const closures = features.filter((feature) => feature.geometry.type === 'LineString' && feature.properties?.kind === 'road-closure');
    const pointSource = courierMap.getSource('courier-points');
    const routeSource = courierMap.getSource('courier-route');
    const closureSource = courierMap.getSource('courier-closures');
    if (pointSource) pointSource.setData(collection(points));
    if (routeSource) routeSource.setData(collection(routes));
    if (closureSource) closureSource.setData(collection(closures));
    const selection = JSON.stringify(routes.map((route) => route.id));
    if (selection !== lastSelection && points.length > 0) {
        const bounds = new LngLatBounds();
        points.forEach((feature) => bounds.extend(feature.geometry.coordinates));
        routes.forEach((route) => route.geometry.coordinates.forEach((point) => bounds.extend(point)));
        closures.forEach((route) => route.geometry.coordinates.forEach((point) => bounds.extend(point)));
        if (!bounds.isEmpty()) courierMap.fitBounds(bounds, { padding: 36, maxZoom: 14, duration: 350 });
        lastSelection = selection;
    }
}

function initMap() {
    mapContainer = document.getElementById('courier-map');
    if (!mapContainer || courierMap) return;
    courierMap = new Map({ container: mapContainer, style: emptyStyle, center: [-100.31, 25.675], zoom: 11, attributionControl: false });
    courierMap.addControl(new NavigationControl({ showCompass: false }), 'top-right');
    courierMap.on('error', () => {
        const status = document.getElementById('map-status');
        if (status) status.textContent = 'El mapa base no está disponible; las tarjetas siguen activas.';
    });
    courierMap.on('load', () => {
        courierMap.addSource('courier-points', { type: 'geojson', data: collection([]) });
        courierMap.addSource('courier-route', { type: 'geojson', data: collection([]) });
        courierMap.addSource('courier-closures', { type: 'geojson', data: collection([]) });
        courierMap.addLayer({ id: 'recommended-route', type: 'line', source: 'courier-route', paint: { 'line-color': '#22d3ee', 'line-width': 4, 'line-opacity': 0.9 } });
        courierMap.addLayer({ id: 'road-closures', type: 'line', source: 'courier-closures', paint: { 'line-color': '#fb7185', 'line-width': 4, 'line-dasharray': [2, 2], 'line-opacity': 0.9 } });
        courierMap.addLayer({ id: 'courier-points-layer', type: 'circle', source: 'courier-points', paint: { 'circle-color': ['match', ['get', 'kind'], 'courier', '#f59e0b', 'pickup', '#22d3ee', '#f472b6'], 'circle-radius': ['match', ['get', 'kind'], 'courier', 9, 6], 'circle-stroke-color': '#0f172a', 'circle-stroke-width': 2 } });
        updateMap(window.__courierMapPayload);
    });
}

document.addEventListener('livewire:init', () => {
    initMap();
    if (!window.__courierMapListener) {
        window.__courierMapListener = true;
        window.addEventListener('map-updated', (event) => {
            window.__courierMapPayload = event.detail?.payload ?? event.detail;
            initMap();
            updateMap(window.__courierMapPayload);
        });
    }
});
document.addEventListener('DOMContentLoaded', initMap);
