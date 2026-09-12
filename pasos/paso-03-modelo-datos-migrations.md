# Paso 03 — Modelo de datos y migrations

## Objetivo

Crear en MySQL el esquema completo que sostendrá simulación reproducible, dos agentes independientes, pedidos, recomendaciones, rutas, eventos y benchmarks. En este paso no se implementan Services ni interfaz.

## Puerta de entrada

Antes de editar:

- confirmar Laravel 12 operativo con `php artisan --version`;
- confirmar que existe `.env` local y que `DB_CONNECTION=mysql`;
- confirmar `pdo_mysql` habilitado;
- ejecutar una conexión real con `php artisan migrate:status`;
- confirmar que los pasos 01 y 02 están terminados.

Si MySQL no responde, detenerse: no sustituirlo por SQLite para declarar el paso terminado.

## Tablas a crear

### `simulation_runs`

Una ejecución compartida por Courier AI y baseline:

- `id`;
- `scenario_key` string indexado;
- `seed` unsigned big integer;
- `status` string indexado;
- `simulated_started_at`, `simulated_current_at`, `simulated_ends_at`;
- `real_started_at`, `real_finished_at` nullable;
- `real_last_tick_at` nullable para sincronización idempotente;
- `traffic_factor` decimal;
- `weather` string;
- `closure_version` unsigned integer default 0;
- `speed_multiplier` decimal default 1;
- `environment_state` JSON nullable;
- `config_snapshot` JSON;
- timestamps;
- índice compuesto por escenario y seed.

### `shifts`

Estado y resultados independientes por agente:

- FK `simulation_run_id` con cascade;
- `agent_type` (`COURIER_AI` o `BASELINE`), indexado;
- `status`, `courier_status`;
- posición inicial y actual con `DECIMAL(10,7)`;
- `max_concurrent_orders`;
- `cost_per_km_mxn` decimal;
- ganancias bruta, neta y costo operativo decimal default 0;
- distancia total y deadhead decimal default 0;
- minutos activos e idle;
- contadores de aceptados, rechazados, completados y retrasados;
- timestamps;
- unique `(simulation_run_id, agent_type)`.

### `orders`

Definición inmutable de las ofertas compartidas:

- FK `simulation_run_id`;
- `external_id` y `scenario_order_key`;
- `spawn_time`, `expires_at`, deadlines nullable;
- restaurante: identificador, nombre y coordenadas;
- cliente: coordenadas y zona destino;
- pagos base, surge y otros bonos como decimal;
- espera estimada unsigned integer;
- restricciones y metadata JSON nullable;
- timestamps;
- unique `(simulation_run_id, scenario_order_key)`;
- índices de run/spawn y expiración.

### `shift_orders`

Estado independiente de una oferta para cada agente:

- FK `shift_id` y `order_id` con cascade;
- `status` indexado;
- timestamps simulados para disponible, aceptado, pickup, entrega, rechazo y expiración;
- métricas aceptadas y posición en plan en JSON/columnas nullable;
- unique `(shift_id, order_id)`.

### `demand_zones`

- clave única, nombre, centro lat/lon;
- polígono GeoJSON nullable;
- `demand_by_hour` JSON;
- activo y timestamps.

### `simulation_events`

- FK `simulation_run_id`;
- tipo y `scheduled_at` simulada;
- `applied_at` nullable;
- payload JSON;
- índice `(simulation_run_id, scheduled_at, applied_at)`.

### `recommendations`

- FK `shift_id`;
- instante simulado y `config_version`;
- ranking, pedidos seleccionados, secuencia, métricas y reason codes en JSON;
- score visual y utilidad absoluta en columnas distintas;
- ganancia neta, minutos, distancia, tasa por hora y riesgo estimados;
- `status`, explicación determinista/LLM nullable;
- duración de routing y optimización en ms;
- timestamps e índice shift/instante.

### `deliveries`

- FK `shift_id` y `recommendation_id` nullable;
- estado, secuencia y pedidos JSON;
- inicio/final simulados;
- importes brutos, costo y neto;
- distancia total/deadhead y duración real simulada;
- retraso y metadata;
- timestamps.

### `route_caches`

- `cache_key` hash unique;
- proveedor;
- origen/destino decimal;
- traffic bucket y closure version;
- distancia, duración, geometría GeoJSON y respuesta resumida;
- `expires_at` indexado y timestamps.

No almacenar API keys ni URLs con credenciales.

### `benchmark_runs` y `benchmark_results`

`benchmark_runs` guarda rango/lista de seeds, configuración, estado y tiempos. `benchmark_results` guarda por seed y agente las métricas finales, enlazando opcionalmente el `simulation_run_id`. Usar unique por benchmark/seed/agent.

## Orden de implementación

1. Dibujar las relaciones y verificar que una oferta compartida tenga estado independiente por shift.
2. Crear migrations en orden de dependencias.
3. Usar foreign keys, índices, unique constraints y defaults explícitos.
4. Usar `DECIMAL` para dinero/coordenadas y JSON sólo para estructuras variables.
5. Implementar `down()` en orden inverso sin deshabilitar globalmente constraints.
6. Agregar factories mínimas sólo para validar integridad del esquema; los Models completos pertenecen al paso 04.
7. Documentar brevemente el esquema en el propio código cuando una separación no sea obvia.

## Pruebas obligatorias

- `php artisan migrate:fresh` únicamente contra una base MySQL vacía y dedicada a pruebas, cuyo nombre se verifique antes de ejecutar.
- `php artisan migrate:rollback` y nueva migración.
- Feature test que inserte un run, dos shifts, un order y dos `shift_orders` con estados distintos.
- Test de rechazo de duplicados para agent por run y order por run.
- Test de foreign keys y cascade relevantes.
- Verificar que importes conservan dos decimales y coordenadas siete.
- Ejecutar `php artisan test` completo.

## Criterios de aceptación

- Todas las migrations suben y bajan limpiamente en MySQL 8.
- Courier AI y baseline pueden compartir ofertas sin compartir su estado de aceptación.
- El esquema soporta todas las entidades del árbol objetivo sin migrations futuras previsibles por omisión estructural.
- Dinero no usa float/double.
- Existen índices para consultas por run, shift, estado y tiempo simulado.
- No se añadieron Services, endpoints ni UI.
- No existen secretos versionados.

## Fuera de alcance

- Relaciones Eloquent completas, enums y DTOs.
- Generación de escenarios.
- Cálculos, routing, API o vistas.

## Commit sugerido

`feat: add complete courier simulation database schema`
