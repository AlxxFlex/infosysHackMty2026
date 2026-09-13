# Paso 06 realizado — Pedidos y escenarios reproducibles

Fecha de finalización: 12 de septiembre de 2026.

## Resultado

Se agregó un stream de ofertas determinista y compartido por ejecución. Las definiciones viven en escenarios versionados y cada agente conserva su propio estado mediante `shift_orders`.

## Esquema de escenarios

Los archivos JSON de `database/data/scenarios/` contienen:

- `scenario_key`, `version`, `timezone`, `duration_min` e `initial_position`;
- metadata obligatoria con `is_simulated: true` y pagos, demanda, surge y tráfico etiquetados como `simulated`;
- pedidos con `scenario_order_key`, `external_id`, spawn, expiración, restaurante, cliente, zona, importes, espera y deadlines;
- eventos futuros con tipo, minuto, secuencia y payload simulado.

`demo_normal.json` incluye seis ofertas, dos eventos futuros y restaurantes/destinos próximos que dejan potencial de batch para pasos posteriores. Los eventos sólo se persisten como pendientes; todavía no se aplican.

## `ScenarioService`

`app/Services/ScenarioService.php` es la única puerta de entrada para escenarios. Valida tipos, rangos, unicidad de claves, coordenadas, fechas relativas, eventos y etiquetas de simulación antes de cualquier escritura.

Soporta:

- carga del escenario curado `demo_normal`;
- generación `challenge`/`challenge_mode` con un PRNG local encapsulado, sin estado global, para que `scenario + seed + config` produzca las mismas ofertas;
- materialización atómica de una colección compartida de `orders`, un `shift_order` `PENDING` por pedido/agente y eventos pendientes;
- transición de ofertas en el reloj simulado: `PENDING → AVAILABLE` al spawn y `AVAILABLE → EXPIRED` al vencimiento.

La expiración sólo consulta estados pendientes/disponibles, por lo que no modifica pedidos aceptados, rechazados ni completados. Los timestamps de disponibilidad se toman de la definición compartida y son iguales para ambos agentes.

## Integración del turno

`ShiftService::createRun()` carga y valida el escenario antes de abrir la transacción, usa su duración/posición y materializa pedidos junto con los dos shifts. Un escenario inválido no crea datos parciales.

`SimulatorService::tick()` y `syncElapsedRealTime()` ejecutan `advanceOffers()` dentro de la misma transacción que avanza el reloj y bloquean los `shift_orders` afectados.

## Zonas de demanda

Se agregó `DemandZoneSeeder` con `CENTRO`, `TEC` y `SAN_PEDRO`, las zonas utilizadas por el demo. Usa `updateOrCreate` para ser idempotente y se invoca desde `DatabaseSeeder`.

## Pruebas ejecutadas

`tests/Feature/ScenarioServiceTest.php` cubre:

- demo con al menos cinco ofertas y etiquetas simuladas;
- equivalencia de dos runs con la misma seed;
- diferencia entre seeds en challenge mode;
- materialización independiente con timestamps idénticos para ambos agentes;
- estados antes del spawn, al spawn y al vencimiento;
- conservación de un pedido aceptado;
- rechazo de escenario inexistente antes de crear un run;
- seeder de zonas idempotente.

También se conserva la regresión del simulador del Paso 05.

No se agregaron rutas, UI, routing, scoring, recomendaciones ni lógica de pasos posteriores.
