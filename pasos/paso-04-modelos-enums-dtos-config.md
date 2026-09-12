# Paso 04 — Modelos, enums, DTOs y configuración

## Objetivo

Construir el dominio base tipado sobre el esquema del paso 03, sin implementar todavía el reloj, routing, scoring ni optimización.

## Puerta de entrada

- Paso 03 completado y migrations limpias en MySQL.
- Confirmar nombres y columnas reales antes de generar Models.
- Ejecutar la regresión existente antes de editar.

## Enums requeridos

Crear enums string respaldados por PHP para:

- `AgentType`: `COURIER_AI`, `BASELINE`;
- `ShiftStatus`: `IDLE`, `RUNNING`, `PAUSED`, `FINISHED`;
- `CourierStatus`: `AVAILABLE`, `GOING_TO_PICKUP`, `WAITING_AT_RESTAURANT`, `DELIVERING`;
- `OrderStatus`: `PENDING`, `AVAILABLE`, `RECOMMENDED`, `ACCEPTED`, `PICKING_UP`, `PICKED_UP`, `DELIVERING`, `DELIVERED`, `EXPIRED`, `REJECTED`;
- `EventType`: todos los eventos descritos en el contexto;
- `Priority`: `HIGH`, `MEDIUM`, `LOW`;
- `ReasonCode`: razones positivas y negativas de la sección 78.

Los valores persistidos/API deben ser estables. No utilizar labels traducidos como valor del enum.

## Models requeridos

Crear Models para todas las tablas del paso 03 y definir:

- relaciones bidireccionales;
- casts de enums, fechas, booleanos, JSON y decimales;
- `$fillable` o asignación protegida explícita;
- scopes pequeños para estados frecuentes (`available`, `running`, `pendingAt`);
- factories útiles para tests;
- ninguna fórmula económica ni consulta a APIs externas.

`Order` representa la oferta compartida; `ShiftOrder` representa su ciclo por agente. No mezclar ambas responsabilidades.

## DTOs requeridos

Crear objetos inmutables (`readonly` cuando sea viable):

- `Coordinates` con validación de rangos;
- `CourierState`;
- `EnvironmentState`;
- `RouteData`;
- `EvaluatedOrder`;
- `OptimizedPlan`.

Los DTOs deben:

- tener tipos explícitos;
- rechazar estados imposibles;
- poder construirse desde Models mediante factories/mappers dedicados;
- exponer `toArray()` con contrato estable;
- mantener dinero con precisión consistente y redondeo en fronteras de presentación/persistencia.

No hacer que los DTOs consulten Eloquent, configuración o servicios.

## Configuración

Crear `config/courier.php` con secciones:

- `simulation`: duración, velocidad, concurrencia, horizonte y costo/km;
- `scoring`: pesos que sumen 1.0, umbrales de prioridad y safe slack;
- `optimization`: máximo de candidatos, tamaño de batch y presupuesto de tiempo;
- `routing`: provider, URL, timeouts, retries, TTL, road factor;
- `demand`: bono máximo de posición;
- `explanation`: provider deshabilitado por defecto y timeout;
- `benchmark`: seeds/cantidad por defecto.

Agregar variables no secretas y placeholders vacíos a `.env.example`; nunca crear valores secretos reales. Si `.env.example` no existe, crearlo desde una plantilla segura de Laravel/MySQL.

## Orden de implementación

1. Implementar enums y tests de valores.
2. Implementar Models, relaciones y casts según migrations reales.
3. Implementar factories sin lógica de escenarios.
4. Implementar DTOs y validación de invariantes.
5. Crear `config/courier.php` y `.env.example` seguro.
6. Agregar una prueba que confirme que los pesos suman exactamente 1 dentro de tolerancia.

## Pruebas obligatorias

- Unit tests de enums y serialización.
- Unit tests de rangos de coordenadas y DTOs inválidos.
- Feature tests de relaciones run → shifts/orders/events y shift → estados/recomendaciones/deliveries.
- Tests de casts JSON, decimal, fecha y enum.
- Test de configuración y suma de pesos.
- `php artisan config:cache` seguido de `php artisan config:clear`.
- `php artisan test` completo.

## Criterios de aceptación

- El dominio se construye sin arrays ambiguos en los límites de Services futuros.
- Todos los estados persistidos usan enums.
- Models sólo contienen persistencia, relaciones, casts y scopes simples.
- Configuración cuantitativa no está hardcodeada en vistas ni controllers.
- `.env.example` permite configurar MySQL, OSRM, simulación y LLM sin secretos.
- No se implementó lógica de pasos 05 o posteriores.

## Commit sugerido

`feat: define courier domain models enums dtos and config`
