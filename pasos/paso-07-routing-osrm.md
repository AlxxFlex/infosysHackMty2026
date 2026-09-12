# Paso 07 — Routing con OSRM

## Objetivo

Obtener distancias, duraciones y geometrías de ruta mediante OSRM, con caché y fallback determinista para que la simulación no dependa de internet.

## Puerta de entrada

- Paso 06 completo.
- Existen coordenadas válidas y pedidos disponibles.
- No iniciar MapLibre ni scoring en este paso.

## Contrato de routing

`RoutingService` debe ofrecer al menos:

- ruta entre una lista ordenada de coordenadas;
- matriz de distancia/duración entre puntos;
- resultado uniforme en `RouteData`;
- indicador `provider`, `is_fallback` y calidad/advertencias;
- geometría GeoJSON cuando esté disponible.

Regla crítica: el dominio usa `lat, lon`; únicamente el adaptador OSRM serializa `lon,lat`.

## Implementación OSRM

- Usar `Http` de Laravel con URL desde configuración.
- Timeout corto, retries limitados y `throw()`/manejo explícito.
- Usar `/route/v1/driving` para rutas y `/table/v1/driving` para matrices.
- Validar `code=Ok`, arrays, valores nulos e índices de respuesta.
- Solicitar geometría GeoJSON; no exponer la respuesta cruda al dominio.
- No llamar a OSRM desde Models, Controllers o Blade.

## Caché

La clave debe incluir:

- puntos redondeados consistentemente y su orden;
- perfil de transporte;
- traffic bucket;
- closure version;
- versión del algoritmo/proveedor.

Usar Laravel Cache como acceso principal y persistencia `route_caches` cuando corresponda. No cachear errores indefinidamente. La invalidación debe ser posible por expiración y cambio de versión de cierres.

## Fallback

Implementar Haversine en `Support/Geo`:

- distancia de carretera estimada = Haversine × `road_factor`;
- duración = distancia / velocidad configurable;
- matriz consistente y diagonal cero;
- geometría LineString directa marcada como estimada;
- nunca presentar esta ruta como tráfico/routing real.

El fallback se activa ante timeout, red, respuesta inválida o configuración explícita; la causa se registra sin romper la recomendación futura.

## Orden de implementación

1. Finalizar `RouteData` y el contrato público.
2. Implementar construcción segura de URLs OSRM.
3. Implementar route y table con validación.
4. Implementar cache keys y TTL.
5. Implementar Haversine/fallback.
6. Integrar sólo un método para evaluar trayectos de pedidos; no calcular economía.

## Pruebas obligatorias

- HTTP fake para route correcto, table correcta, timeout, 5xx y payload inválido.
- Coordenadas llegan a OSRM como `lon,lat`.
- Cache hit evita una segunda llamada.
- Cambiar traffic bucket o closure version produce otra key.
- Haversine es simétrico, no negativo y cero en el mismo punto.
- Fallback devuelve contrato completo y queda marcado.
- No hacer tests unitarios contra el endpoint público.
- Regresión completa.

## Criterios de aceptación

- Services pueden obtener ruta/matriz sin conocer OSRM directamente.
- La app sigue funcionando sin red mediante resultados estimados.
- Caché evita solicitudes repetidas.
- Duración, distancia y geometría se validan antes de entrar al dominio.
- No se implementa mapa, scoring ni optimización.

## Commit sugerido

`feat: add cached OSRM routing with deterministic fallback`
