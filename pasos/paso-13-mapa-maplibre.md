# Paso 13 — Mapa con MapLibre GL JS

## Objetivo

Reemplazar el placeholder por un mapa interactivo que represente estado y rutas calculadas, sin mover decisiones de negocio al navegador.

## Puerta de entrada

- Paso 12 completo y usable con polling.
- RoutingService entrega GeoJSON o fallback marcado.

## Dependencias y assets

- Instalar `maplibre-gl` mediante npm.
- Importar JS y CSS desde Vite.
- Crear `resources/js/map.js` como módulo aislado.
- No cargar librerías por CDN si ya están en el bundle.

## Capas mínimas

- posición de Courier AI;
- restaurantes de ofertas visibles;
- clientes de ofertas visibles;
- ruta recomendada destacada;
- rutas alternativas opcionales con menor contraste;
- leyenda que distingue ruta OSRM de estimación fallback.

Reservar sources/layers para surge y cierres, pero no simularlos todavía.

## Integración Livewire

- Usar un contenedor `wire:ignore` para que Livewire no destruya la instancia.
- Inicializar una sola vez y conservar cleanup si se desmonta.
- Livewire emite un evento de navegador con un payload GeoJSON validado.
- `map.js` actualiza sources existentes; no recrea el mapa por cada tick.
- Ajustar bounds sólo cuando cambia la selección, no continuamente.
- Evitar duplicar listeners tras navegación o rerender.

## Datos del mapa

Crear un presenter/resource dedicado que entregue:

- coordenadas actuales;
- features de pickup/dropoff con ids estables;
- LineString y orden de paradas del plan;
- provider/fallback;
- ninguna métrica secreta ni respuesta cruda de OSRM.

El frontend no calcula rutas, distancias, scores ni compatibilidad.

## Resiliencia

- Si fallan tiles, mantener tarjetas y mostrar un aviso no bloqueante.
- Si no hay geometría, dibujar línea estimada sólo si está marcada como fallback.
- Estado sin ofertas muestra únicamente la posición.
- Validar rangos y evitar centrar en `[0,0]` por datos faltantes.

## Pruebas obligatorias

- Resource/presenter produce GeoJSON válido y orden correcto de coordenadas.
- Payload incluye courier, pickups, dropoffs y ruta ganadora.
- Una ruta fallback queda etiquetada visual y estructuralmente.
- Livewire emite actualización cuando cambia plan/posición.
- No se emite geometría inválida.
- `npm run build` sin warnings/errores de importación.
- Prueba manual en ancho móvil y desktop documentada.
- Regresión completa.

## Criterios de aceptación

- `/courier` muestra posición, ofertas y ruta recomendada sobre MapLibre.
- El mapa se actualiza al aceptar/avanzar sin recargar la página.
- El dashboard sigue funcionando si mapa, tiles u OSRM fallan.
- No hay fórmulas de negocio en JavaScript/Blade.
- No se agregan todavía overlays de eventos dinámicos reales.

## Commit sugerido

`feat: visualize courier recommendations with MapLibre`
