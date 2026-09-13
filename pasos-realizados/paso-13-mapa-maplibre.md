# Paso 13 realizado — Mapa con MapLibre GL JS

Fecha de finalización: 12 de septiembre de 2026.

## Resultado

El placeholder del dashboard fue reemplazado por un mapa MapLibre interactivo. La posición de Courier AI, pickups, dropoffs y la secuencia recomendada se actualizan sin recargar la página.

## Implementación

- Se instaló `maplibre-gl` y se importaron su JavaScript y CSS desde Vite.
- `resources/js/map.js` inicializa una sola instancia, conserva sources/layers y actualiza GeoJSON existente en cada evento Livewire.
- Se configuró un estilo raster OpenStreetMap con manejo no bloqueante de errores de tiles.
- Se añadieron capas diferenciadas para courier, pickups, dropoffs y ruta recomendada, además de navegación y leyenda.
- `MapPresenter` construye un FeatureCollection validado con ids estables, coordenadas `[lon, lat]`, secuencia de paradas y metadata de proveedor/fallback. No expone respuestas crudas de OSRM ni mueve lógica de negocio al navegador.
- El contenedor usa `wire:ignore`; Livewire emite `map-updated` al cambiar posición, ofertas o plan. El mapa sólo ajusta bounds cuando cambia la selección, no durante cada polling.
- Si no hay geometría o fallan tiles, se conserva la posición y el resto del dashboard muestra una estimación fallback claramente etiquetada.
- Se mantuvieron reservadas las capas de eventos dinámicos para el Paso 14.

## Verificación

- Revisión responsive: el contenedor usa alturas y anchos fluidos con breakpoints `sm`/`lg`, conservando controles táctiles en móvil y distribución de dos columnas en desktop.
- `tests/Unit/MapPresenterTest.php` valida GeoJSON, ids, orden de coordenadas, LineString y etiqueta fallback.
- `npm run build` compiló MapLibre sin errores ni warnings.
- La suite completa pasó con 61 tests y 411 aserciones.

