# Paso 03 realizado — Modelo de datos y migrations

Fecha de finalización: 12 de septiembre de 2026.

## Objetivo completado

Se implementó el esquema persistente completo de Courier AI para soportar:

- ejecuciones reproducibles de simulación;
- un turno de Courier AI y otro de baseline por cada ejecución;
- ofertas compartidas con estados independientes por agente;
- demanda, eventos, recomendaciones, entregas y caché de rutas;
- ejecuciones y resultados futuros de benchmark.

Este paso no agregó Models de dominio, enums, DTOs, Services, endpoints ni interfaz. Esas piezas permanecen reservadas para los pasos siguientes.

## Prerrequisitos verificados

- PHP 8.5.10 disponible.
- Laravel Framework 12.69.2 operativo.
- Extensión `pdo_mysql` habilitada.
- Archivo `.env` local presente con `DB_CONNECTION=mysql`.
- Conexión real a la base local `infoSys` confirmada con `php artisan migrate:status`.
- Servidor local MySQL 9.2.0 disponible. Las migrations utilizan tipos, índices y constraints compatibles con MySQL 8 o superior.
- Los tres migrations originales de Laravel estaban aplicados antes de comenzar.

## Modelo relacional implementado

```text
SimulationRun
├── Shift (COURIER_AI)
│   ├── ShiftOrder
│   ├── Recommendation
│   │   └── Delivery
│   └── Delivery
├── Shift (BASELINE)
│   ├── ShiftOrder
│   ├── Recommendation
│   │   └── Delivery
│   └── Delivery
├── Order
│   └── ShiftOrder por cada Shift
└── SimulationEvent

BenchmarkRun
└── BenchmarkResult
    └── SimulationRun opcional
```

La separación entre `orders` y `shift_orders` es la garantía central del esquema: ambos agentes observan exactamente la misma oferta, pero cada uno conserva su propio estado de aceptación, rechazo, pickup, entrega o expiración.

## Tablas creadas

### `simulation_runs`

Guarda escenario, seed, estado, reloj simulado, anclas de tiempo real, tráfico, clima, versión de cierres, velocidad, estado del entorno y snapshot de configuración. Incluye índices por escenario/seed y estado/tiempo simulado.

### `shifts`

Guarda el estado independiente de Courier AI y baseline, posiciones inicial/actual, capacidad, costo por kilómetro, ganancias, costos, distancias, minutos y contadores. La restricción única `(simulation_run_id, agent_type)` impide duplicar un agente dentro de la misma ejecución.

### `orders`

Guarda la definición inmutable y compartida de una oferta: tiempos, restaurante, cliente, zona, pagos, espera, restricciones y metadata. Las claves de escenario y externa son únicas dentro de cada ejecución.

### `shift_orders`

Guarda el ciclo de vida de cada oferta para cada agente, sus timestamps simulados, métricas al aceptar y posición dentro del plan. La restricción única `(shift_id, order_id)` evita estados duplicados.

### `demand_zones`

Guarda clave, nombre, centro, polígono GeoJSON, demanda simulada por hora y estado activo.

### `simulation_events`

Guarda tipo, instante programado/aplicado, orden estable, payload y snapshots antes/después con reason codes. Sus índices permiten buscar eventos pendientes por ejecución y tiempo simulado.

### `recommendations`

Guarda ranking, pedidos elegidos, secuencia, métricas, razones, score visual y utilidad absoluta por separado, además de cifras esperadas, riesgo, explicaciones y mediciones de routing/optimización.

### `deliveries`

Guarda pedidos y secuencia ejecutados, tiempos simulados, importes realizados, costo, neto, distancia, deadhead, duración, retraso y metadata. La recomendación es opcional y usa `nullOnDelete` para no borrar una entrega realizada.

### `route_caches`

Guarda una clave hash única, proveedor, coordenadas, bucket de tráfico, versión de cierres, distancia, duración, geometría GeoJSON, resumen seguro y expiración. No almacena credenciales ni respuestas externas completas.

### `benchmark_runs`

Guarda escenario, lista de seeds, snapshot/versiones de configuración y algoritmo, estado, progreso y tiempos del benchmark.

### `benchmark_results`

Guarda por seed y agente las métricas realizadas del turno y un enlace opcional a la ejecución. La restricción única `(benchmark_run_id, seed, agent_type)` evita duplicar resultados.

## Decisiones de implementación

- Todo dinero usa `DECIMAL` con dos posiciones decimales.
- Las coordenadas usan `DECIMAL(10,7)`.
- Distancias usan tres posiciones decimales.
- Factores, scores y riesgos mantienen precisión decimal explícita.
- No existe ninguna columna `FLOAT`, `DOUBLE` o `REAL` en el dominio.
- JSON se usa únicamente para estructuras variables como snapshots, geometrías, rankings, secuencias y metadata.
- Las foreign keys usan cascade cuando el dato sólo tiene sentido dentro de su padre.
- Los `down()` eliminan tablas individualmente y el rollback las ejecuta en orden inverso de dependencias.
- No se crearon factories Eloquent porque este paso prohíbe adelantar Models; los fixtures de integridad usan Query Builder directamente. Las factories de Models corresponden al Paso 04.

## Base de pruebas

Se creó la base aislada `infoSys_testing` con `utf8mb4` y `utf8mb4_unicode_ci`. Antes de ejecutar `migrate:fresh` se verificó:

```text
database: infoSys_testing
tablas existentes: 0
```

`phpunit.xml` quedó configurado para MySQL y esa base dedicada. La prueba del esquema además contiene una guarda previa a `RefreshDatabase`: si la conexión o el nombre de base cambian, se detiene antes de ejecutar `migrate:fresh`.

## Pruebas implementadas

El archivo `tests/Feature/CourierSimulationSchemaTest.php` valida:

- estados distintos para una misma oferta compartida;
- rechazo de agentes duplicados por ejecución;
- rechazo de claves de pedido duplicadas por ejecución;
- rechazo de foreign keys huérfanas;
- cascadas desde una ejecución hacia turnos, pedidos y datos dependientes;
- conservación de dos decimales monetarios y siete decimales geográficos;
- existencia de las once tablas y sus columnas estructurales;
- existencia de índices y constraints críticos;
- ausencia de columnas float/double/real.

## Verificaciones ejecutadas

```text
php artisan --version
php -m | rg -i '^pdo_mysql$'
php artisan migrate:status
DB_DATABASE=infoSys_testing php artisan migrate:fresh --force
DB_DATABASE=infoSys_testing php artisan migrate:rollback --force
DB_DATABASE=infoSys_testing php artisan migrate --force
php artisan test tests/Feature/CourierSimulationSchemaTest.php
vendor/bin/pint --test
php artisan test
composer validate --strict
php artisan migrate --force
php artisan migrate:status
```

Resultados finales:

```text
Pint: passed
Tests: 10 passed, 60 assertions
Composer: composer.json válido
Rollback y nueva migración: correctos
Base local infoSys: 11 migrations del dominio aplicadas en batch 2
```

## Archivos creados

- `database/migrations/2026_09_12_000100_create_simulation_runs_table.php`
- `database/migrations/2026_09_12_000200_create_shifts_table.php`
- `database/migrations/2026_09_12_000300_create_orders_table.php`
- `database/migrations/2026_09_12_000400_create_shift_orders_table.php`
- `database/migrations/2026_09_12_000500_create_demand_zones_table.php`
- `database/migrations/2026_09_12_000600_create_simulation_events_table.php`
- `database/migrations/2026_09_12_000700_create_recommendations_table.php`
- `database/migrations/2026_09_12_000800_create_deliveries_table.php`
- `database/migrations/2026_09_12_000900_create_route_caches_table.php`
- `database/migrations/2026_09_12_001000_create_benchmark_runs_table.php`
- `database/migrations/2026_09_12_001100_create_benchmark_results_table.php`
- `tests/Feature/CourierSimulationSchemaTest.php`
- `pasos-realizados/paso-03-modelo-datos-migrations.md`

## Archivos modificados

- `phpunit.xml`: cambió la suite de SQLite en memoria a la base MySQL dedicada `infoSys_testing`.

## Estado final

Todos los criterios funcionales y estructurales del Paso 03 quedaron cubiertos. El siguiente trabajo permitido es el Paso 04, pero no se adelantó ninguna de sus funcionalidades.
