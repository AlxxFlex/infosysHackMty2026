# Paso 09 realizado — Optimización y batching

Fecha de finalización: 12 de septiembre de 2026.

## Resultado

Se implementó `OptimizationService`, separado de `ScoringService`, para elegir el plan de mayor utilidad económica absoluta entre pedidos individuales y pares. El score visual nunca se suma para seleccionar el plan.

## Candidatos y secuencias

- Genera cada single factible y cada par sin repetición, limitado por `max_candidates` y `max_candidate_set_size`.
- Poda pares cuando la capacidad restante no permite dos pedidos o algún pedido individual ya es inviable.
- Enumera todas las permutaciones pickup/dropoff válidas para cada candidato, respetando precedencia, capacidad, expiración, pickup/deadline, turno y restricciones.
- Usa `RoutingService::matrix()` desde la posición actual del courier; no consulta OSRM directamente.
- El resultado conserva la secuencia estructurada (`PICKUP`/`DROPOFF`) y métricas de ahorro, detour y overlap.

## Utilidad absoluta

Cada plan mantiene separados:

```text
expected_net_profit
+ future_position_value
+ batch_efficiency_bonus
- expected_delay_cost
- risk_penalty
- idle_penalty
= absolute_utility
```

La ganancia neta es siempre pago bruto menos costo de la ruta combinada. El valor futuro usa sólo el mejor destino del plan para no duplicarlo; el ahorro/distancia y el bonus de eficiencia se reportan aparte.

Si se supera el presupuesto se devuelve el mejor plan ya evaluado y se marca `budget_exceeded`. Sin plan factible se devuelve un plan vacío seguro.

## Integración

`RecommendationService` conserva `rankAvailable()` para el ranking individual y añade:

- `optimizeAvailable()` para el `OptimizedPlan` ganador;
- `recommend()` para ranking, plan, alternativas, facts y persistencia de una `Recommendation` `PENDING`.

La recomendación nunca se acepta automáticamente y no participa ningún LLM.

## Pruebas

`tests/Feature/OptimizationServiceTest.php` verifica:

- demo con batch factible de dos pedidos;
- precedencia pickup antes de delivery;
- poda por capacidad/deadline;
- ganador estable al repetir el mismo estado;
- persistencia de recomendación pendiente;
- límite de candidatos.

La suite completa conserva la regresión de pasos 03–08.
