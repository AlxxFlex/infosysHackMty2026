# Paso 04 realizado — Modelos, enums, DTOs y configuración

Fecha de finalización: 12 de septiembre de 2026.

## Objetivo completado

Se construyó el dominio base tipado sobre el esquema del Paso 03. Las clases se limitan a persistencia, contratos de datos, validación de invariantes y configuración; no contienen reloj, routing, scoring, optimización, endpoints ni interfaz.

## Enums implementados

Se agregaron enums string con valores estables para persistencia y API:

- `AgentType`: `COURIER_AI`, `BASELINE`.
- `ShiftStatus`: `IDLE`, `RUNNING`, `PAUSED`, `FINISHED`.
- `CourierStatus`: `AVAILABLE`, `GOING_TO_PICKUP`, `WAITING_AT_RESTAURANT`, `DELIVERING`.
- `OrderStatus`: ciclo completo de pedido desde `PENDING` hasta `DELIVERED`, `EXPIRED` o `REJECTED`.
- `EventType`: los 12 eventos definidos por el contexto.
- `Priority`: `HIGH`, `MEDIUM`, `LOW`.
- `ReasonCode`: razones positivas y negativas de selección, riesgo, turno, tráfico y batching.

Para que todo estado persistido use un enum, también se agregaron:

- `RecommendationStatus`.
- `DeliveryStatus`.
- `BenchmarkStatus`.

Los valores no son labels traducidos y cada enum puede reconstruirse con `::from()` desde el valor almacenado.

## Models implementados

Se crearon los 11 Models del esquema:

- `SimulationRun`.
- `Shift`.
- `Order`.
- `ShiftOrder`.
- `DemandZone`.
- `SimulationEvent`.
- `Recommendation`.
- `Delivery`.
- `RouteCache`.
- `BenchmarkRun`.
- `BenchmarkResult`.

Cada Model incluye `$fillable` explícito, casts de enums, fechas, JSON, booleanos, enteros y decimales, además de relaciones bidireccionales donde existen foreign keys.

Relaciones principales:

```text
SimulationRun → shifts, orders, events, benchmarkResults
Shift         → simulationRun, shiftOrders, recommendations, deliveries
Order         → simulationRun, shiftOrders
ShiftOrder    → shift, order
Recommendation→ shift, deliveries
Delivery      → shift, recommendation
BenchmarkRun  → results
BenchmarkResult → benchmarkRun, simulationRun
```

Scopes pequeños implementados:

- `running()` en `SimulationRun`, `Shift` y `BenchmarkRun`.
- `available()` y `pending()` en `ShiftOrder`.
- `pending()` en `Recommendation`.
- `pendingAt($instant)` en `SimulationEvent`, con orden por instante, secuencia e id.

No se agregaron fórmulas económicas ni llamadas HTTP/API a los Models.

## DTOs implementados

Todos son `readonly`, tienen tipos explícitos y `toArray()` estable:

- `Coordinates`: valida latitud `[-90, 90]`, longitud `[-180, 180]` y valores numéricos finitos.
- `CourierState`: valida tiempo restante, capacidad, ids únicos de pedidos y costo/km decimal no negativo.
- `EnvironmentState`: valida fecha simulada, clima, factor de tráfico positivo, versión de cierres y colecciones estructuradas.
- `RouteData`: valida distancia/duración no negativas, proveedor, warnings y geometría GeoJSON `LineString`; conserva `isFallback`.
- `EvaluatedOrder`: mantiene separados pago bruto, costo, neto, tasa/hora, valor futuro, riesgo, score, prioridad y factibilidad; valida rangos y consistencia de tiempo/distancia.
- `OptimizedPlan`: mantiene separados neto esperado, valor futuro, bonus de batch, penalizaciones y utilidad absoluta; valida secuencia, riesgo, proveedor y límites.

El dinero se representa como strings decimales en los DTOs para evitar pérdida de precisión. Las estructuras variables están documentadas como listas o mapas; los DTOs no consultan Eloquent, configuración ni servicios.

## Factories implementadas

Se crearon factories mínimas y reutilizables para las 11 entidades del dominio:

`SimulationRunFactory`, `ShiftFactory`, `OrderFactory`, `ShiftOrderFactory`, `DemandZoneFactory`, `SimulationEventFactory`, `RecommendationFactory`, `DeliveryFactory`, `RouteCacheFactory`, `BenchmarkRunFactory` y `BenchmarkResultFactory`.

Las factories sólo entregan datos de prueba coherentes y etiquetados como simulados; no generan escenarios ni ejecutan lógica de negocio.

## Configuración

Se creó `config/courier.php` con secciones para:

- simulación: duración, velocidad, capacidad, horizonte, zona horaria y costo/km;
- scoring: pesos, umbrales de prioridad y safe slack;
- optimización: candidatos, tamaño de batch y presupuesto;
- routing: proveedor, OSRM, perfil, timeout, retries, TTL y road factor;
- demanda: bono máximo de posición;
- explicación: proveedor `none` por defecto, timeout y límite;
- benchmark: seeds, cantidad y versión del algoritmo.

Los pesos configurados suman exactamente `1.0` dentro de tolerancia.

Se restauró `.env.example` como plantilla segura con configuración MySQL, OSRM, simulación y LLM. Incluye placeholders vacíos; no contiene credenciales reales ni API keys.

## Pruebas agregadas

- `tests/Unit/EnumsTest.php`: valores persistidos estables y round-trip de enums.
- `tests/Unit/DomainDtosTest.php`: serialización, rangos, invariantes, razones y fallback de routing.
- `tests/Feature/DomainModelsTest.php`: factories, relaciones, casts, enums y scopes sobre MySQL.
- `tests/Feature/CourierConfigTest.php`: secciones obligatorias y suma de pesos.

Las pruebas de base usan exclusivamente `infoSys_testing`, con una guarda que impide que `RefreshDatabase` ejecute `migrate:fresh` sobre otra base.

## Verificaciones ejecutadas

```text
php artisan test
vendor/bin/pint
vendor/bin/pint --test
php artisan config:cache
php artisan config:clear
php artisan test
```

Resultado final:

```text
24 tests passed
207 assertions passed
Pint passed
config:cache passed
config:clear passed
```

## Archivos creados

Enums:

- `app/Enums/AgentType.php`
- `app/Enums/ShiftStatus.php`
- `app/Enums/CourierStatus.php`
- `app/Enums/OrderStatus.php`
- `app/Enums/EventType.php`
- `app/Enums/Priority.php`
- `app/Enums/ReasonCode.php`
- `app/Enums/RecommendationStatus.php`
- `app/Enums/DeliveryStatus.php`
- `app/Enums/BenchmarkStatus.php`

Models:

- `app/Models/SimulationRun.php`
- `app/Models/Shift.php`
- `app/Models/Order.php`
- `app/Models/ShiftOrder.php`
- `app/Models/DemandZone.php`
- `app/Models/SimulationEvent.php`
- `app/Models/Recommendation.php`
- `app/Models/Delivery.php`
- `app/Models/RouteCache.php`
- `app/Models/BenchmarkRun.php`
- `app/Models/BenchmarkResult.php`

DTOs:

- `app/DTOs/Coordinates.php`
- `app/DTOs/CourierState.php`
- `app/DTOs/EnvironmentState.php`
- `app/DTOs/RouteData.php`
- `app/DTOs/EvaluatedOrder.php`
- `app/DTOs/OptimizedPlan.php`

Factories:

- `database/factories/SimulationRunFactory.php`
- `database/factories/ShiftFactory.php`
- `database/factories/OrderFactory.php`
- `database/factories/ShiftOrderFactory.php`
- `database/factories/DemandZoneFactory.php`
- `database/factories/SimulationEventFactory.php`
- `database/factories/RecommendationFactory.php`
- `database/factories/DeliveryFactory.php`
- `database/factories/RouteCacheFactory.php`
- `database/factories/BenchmarkRunFactory.php`
- `database/factories/BenchmarkResultFactory.php`

Otros:

- `config/courier.php`
- `.env.example`
- `tests/Unit/EnumsTest.php`
- `tests/Unit/DomainDtosTest.php`
- `tests/Feature/DomainModelsTest.php`
- `tests/Feature/CourierConfigTest.php`
- `pasos-realizados/paso-04-modelos-enums-dtos-config.md`

## Archivos modificados

- Los Models y factories del paso usan el esquema creado en el Paso 03.
- `.env.example` se recreó desde una plantilla segura, sin secretos.

## Estado final

El dominio tipado y su configuración cumplen el alcance del Paso 04. No se implementó lógica de los pasos 05 o posteriores.
