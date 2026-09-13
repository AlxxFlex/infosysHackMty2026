# Paso 07 realizado — Routing OSRM con fallback determinista

Fecha de finalización: 12 de septiembre de 2026.

## Resultado

Se implementó `RoutingService` como única puerta de entrada para rutas y matrices. El dominio continúa trabajando con coordenadas `lat, lon`; sólo el adaptador OSRM serializa los puntos como `lon,lat`.

## Contrato y proveedor OSRM

`RoutingService::route()` obtiene una ruta para una lista ordenada de puntos y devuelve `RouteData` con:

- distancia en kilómetros y duración en minutos;
- geometría GeoJSON `LineString`;
- proveedor (`osrm` o `fallback`);
- indicador `is_fallback` y advertencias.

`RoutingService::matrix()` usa `/table/v1/{profile}` y devuelve matrices de kilómetros/minutos con metadata uniforme. Ambas integraciones validan código `Ok`, valores no negativos, dimensiones y geometrías antes de entrar al dominio. Timeout, reintentos y URL base se leen de `config/courier.php`.

## Caché

La clave SHA-256 incluye:

- puntos redondeados y su orden;
- perfil;
- traffic bucket;
- versión de cierres;
- versión de algoritmo/proveedor;
- tipo de consulta y proveedor.

Las rutas usan Laravel Cache y la tabla `route_caches`, con expiración corta y advertencias persistidas. Las matrices usan Laravel Cache; modificar el bucket, la versión de cierres, el perfil o la versión genera una clave distinta. Los errores no se cachean indefinidamente: sólo se guarda el resultado estimado durante el TTL normal.

## Fallback y geografía

`app/Support/Geo.php` implementa Haversine simétrico. El fallback calcula distancia de carretera como `Haversine × road_factor`, duración con velocidad configurable y una geometría directa `LineString` marcada como estimada. También genera matrices con diagonal cero y simetría.

La configuración explícita `ROUTING_PROVIDER=fallback` evita red. Las fallas de timeout, red, HTTP o payload inválido también producen fallback con la causa en `warnings`; nunca se presentan como routing real.

## Pruebas ejecutadas

`tests/Feature/RoutingServiceTest.php` cubre:

- route OSRM correcto y serialización `lon,lat`;
- table OSRM y conversión de unidades;
- cache hit sin segunda solicitud;
- claves distintas por traffic bucket y closure version;
- fallback por 5xx/payload inválido;
- Haversine simétrico y matriz fallback con diagonal cero.

La suite completa conserva la regresión de pasos anteriores. No se agregó MapLibre, scoring, optimización ni lógica de recomendaciones.
