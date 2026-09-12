# Paso 05 realizado — Simulador de turno y reloj virtual

Fecha de finalización: 12 de septiembre de 2026.

## Objetivo completado

Se implementó el ciclo de vida de una ejecución reproducible y sus dos turnos comparables, junto con un reloj virtual manual y una sincronización segura contra tiempo real. El dominio sigue sin pedidos, routing, scoring, optimización, endpoints o interfaz.

## Servicios implementados

### `ShiftService`

Responsabilidades implementadas:

- crear un `SimulationRun` con escenario, seed y snapshot de configuración;
- crear exactamente un shift `COURIER_AI` y uno `BASELINE` con posición, capacidad y costo idénticos;
- iniciar, pausar, reanudar y finalizar una ejecución;
- proteger las transiciones con transacciones y `lockForUpdate()`;
- rechazar transiciones inválidas sin dejar cambios parciales;
- preservar `real_started_at` al reanudar;
- exponer estado autoritativo del run y DTOs de estado mediante `StateMapper`.

### `SimulatorService`

Responsabilidades implementadas:

- avanzar minutos simulados sólo para runs `RUNNING`;
- limitar el avance exactamente a `simulated_ends_at`;
- marcar run y shifts como `FINISHED` al llegar al límite;
- acumular minutos en `idle_minutes` mientras no hay pedidos activos;
- reservar `active_minutes` para estados con pedidos activos;
- sincronizar tiempo real usando `real_last_tick_at` como ancla;
- aplicar `speed_multiplier` para convertir segundos reales a minutos simulados;
- conservar fracciones de tiempo real no consumidas al sincronizar;
- hacer que polling repetido con el mismo instante no duplique minutos;
- rechazar ticks no positivos y claves de idempotencia vacías o demasiado largas.

## Persistencia de idempotencia

El Paso 03 no tenía una entidad para registrar claves de tick. Para cumplir la idempotencia entre peticiones y procesos se agregó la migration estrictamente necesaria:

`database/migrations/2026_09_12_001200_create_simulation_tick_requests_table.php`

La tabla guarda run, clave, minutos solicitados y los instantes simulado inicial/final. La restricción única `(simulation_run_id, idempotency_key)` garantiza que una misma clave no avance dos veces. La tabla se elimina automáticamente cuando se elimina su run.

También se agregó `SimulationTickRequest` y la relación `SimulationRun::tickRequests()`.

## Máquina de estados

```text
IDLE → RUNNING → PAUSED → RUNNING → FINISHED
```

- `FINISHED` es terminal.
- Iniciar un run dos veces produce una excepción de dominio y no crea shifts nuevos.
- Pausar sólo es válido desde `RUNNING`.
- Reanudar sólo es válido desde `PAUSED`.
- Finalizar es válido desde `RUNNING` o `PAUSED`.
- Un tick sobre un run pausado o finalizado es un no-op seguro.

Los shifts cambian junto con el run y conservan `CourierStatus::AVAILABLE` hasta que los pasos de pedidos introduzcan actividad.

## Mapper de estado

`app/Support/StateMapper.php` transforma Models persistidos a `CourierState` y `EnvironmentState` sin que los DTOs conozcan Eloquent o configuración. Calcula minutos restantes desde el reloj simulado y expone pedidos activos por su `external_id` cuando existan.

## Pruebas agregadas

`tests/Feature/SimulatorServiceTest.php` cubre:

- creación con seed, snapshot y dos agentes idénticos;
- estado autoritativo y DTOs de estado;
- transiciones válidas de run y shifts;
- transición inválida sin cambios parciales;
- tick exacto y acumulación de idle;
- idempotencia persistida aun cambiando los minutos solicitados;
- no avance en pausa y terminalidad en finalización;
- rechazo de minutos no positivos;
- sincronización por tiempo real con ancla;
- ausencia de doble consumo en polls repetidos;
- conversión determinista con `speed_multiplier`.

## Verificaciones ejecutadas

```text
php artisan test
vendor/bin/pint
vendor/bin/pint --test
php artisan config:cache
php artisan config:clear
composer validate --strict
DB_DATABASE=infoSys_testing php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
```

Resultado final:

```text
34 tests passed
250 assertions passed
Pint passed
Configuration cache/clear passed
Composer manifest válido
Migration de idempotencia aplicada en infoSys_testing e infoSys
```

## Archivos creados

- `app/Exceptions/InvalidSimulationTransition.php`
- `app/Exceptions/InvalidSimulationTick.php`
- `app/Services/ShiftService.php`
- `app/Services/SimulatorService.php`
- `app/Support/StateMapper.php`
- `app/Models/SimulationTickRequest.php`
- `database/migrations/2026_09_12_001200_create_simulation_tick_requests_table.php`
- `tests/Feature/SimulatorServiceTest.php`
- `pasos-realizados/paso-05-simulador-turno-y-reloj.md`

## Archivos modificados

- `app/Models/SimulationRun.php`: relación `tickRequests()`.
- `config/courier.php`: duración, inicio simulado, vehículo y posición inicial.
- `.env.example`: placeholders de vehículo y coordenadas iniciales.

## Estado final

El reloj virtual y el ciclo de vida del turno cumplen el alcance del Paso 05. No se implementó lógica de pedidos ni de pasos posteriores.
