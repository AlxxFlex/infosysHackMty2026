# Paso 05 — Simulador de turno y reloj virtual

## Objetivo

Implementar el ciclo de vida de una ejecución y sus dos turnos comparables, junto con un reloj virtual determinista. Todavía no aparecen pedidos.

## Puerta de entrada

- Paso 04 completo.
- Models, enums y configuración cargan correctamente.
- MySQL parte de migrations válidas.

## Diseño requerido

### `ShiftService`

Responsable de:

- crear un `SimulationRun` con escenario, seed y snapshot de configuración;
- crear exactamente un shift `COURIER_AI` y uno `BASELINE` con el mismo estado inicial;
- iniciar, pausar, reanudar y finalizar el run;
- obtener estado consistente para otros Services;
- impedir transiciones inválidas o repetidas.

### `SimulatorService`

Responsable de:

- avanzar el reloj en minutos simulados;
- sincronizar tiempo real transcurrido contra `speed_multiplier` usando `real_last_tick_at` como ancla;
- avanzar únicamente runs `RUNNING`;
- finalizar al alcanzar `simulated_ends_at`;
- actualizar minutos activos/idle de cada shift de forma idéntica mientras no existan pedidos;
- ejecutar cada tick dentro de una transacción y con lock para impedir doble avance concurrente.

Todos los tiempos de dominio reciben explícitamente el instante simulado. No usar `now()` para decidir spawn, expiración, deadlines o duración de entregas.

## Estado y transiciones

```text
IDLE → RUNNING → PAUSED → RUNNING → FINISHED
```

- `FINISHED` es terminal.
- Iniciar dos veces no crea turnos duplicados.
- Un tick con un identificador/idempotency key repetido no avanza dos veces.
- Dos polls simultáneos sólo consumen una vez el tiempo real transcurrido.
- Pausar o finalizar un run ya pausado/finalizado debe ser seguro o producir una excepción de dominio explícita.

## Orden de implementación

1. Definir excepciones de transición de dominio necesarias.
2. Crear el mapper de Model a `CourierState`/`EnvironmentState`.
3. Implementar creación del run y los dos shifts dentro de una transacción.
4. Implementar state machine del run/shift.
5. Implementar tick manual determinista.
6. Implementar `syncElapsedRealTime()` idempotente para que polling/scheduler pueda avanzar el turno sin duplicarlo.
7. Implementar finalización por tiempo y resumen base en cero.
8. Añadir logs estructurados sin datos sensibles.

No crear scheduler, comandos en background, endpoints ni componentes Livewire todavía.

## Pruebas obligatorias

- Crear run con seed fija produce dos shifts con posición/configuración idénticas.
- Unique constraint evita agentes duplicados.
- Tick avanza exactamente la cantidad solicitada.
- Tick repetido con la misma clave es idempotente.
- La sincronización concurrente por tiempo real no duplica minutos simulados.
- Un run pausado no avanza.
- El run termina exactamente al límite y nunca lo sobrepasa.
- Transiciones inválidas fallan sin dejar cambios parciales.
- Dos intentos concurrentes no duplican avance.
- Regresión completa.

## Criterios de aceptación

- Es posible crear, iniciar, pausar, reanudar, avanzar y terminar un turno desde tests/Services.
- Cada run conserva seed y snapshot de configuración.
- Courier AI y baseline comienzan exactamente iguales.
- El reloj virtual es la única fuente temporal del dominio.
- No existen todavía pedidos, routing, scoring, API ni UI.

## Commit sugerido

`feat: add deterministic shift simulator and virtual clock`
