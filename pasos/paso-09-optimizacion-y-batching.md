# Paso 09 — Optimización y batching

## Objetivo

Seleccionar el mejor plan factible entre pedidos individuales y pares, optimizando su secuencia pickup/dropoff con utilidad económica absoluta.

## Puerta de entrada

- Paso 08 completo.
- Ranking individual y matrices de routing confiables.
- Máximo concurrente configurable, con valor MVP de 2.

## Separación obligatoria

- `ScoringService` ordena ofertas para la interfaz.
- `OptimizationService` elige el plan.
- El plan ganador no tiene que coincidir con el pedido de mayor score individual.

No elegir planes sumando scores normalizados.

## Generación de candidatos

Generar:

- cada pedido individual factible;
- cada par sin repetición hasta el límite configurado;
- candidato vacío/rechazar todo cuando ninguna opción produzca valor aceptable o sea factible.

No implementar tríos en el MVP. Aplicar poda por capacidad, expiración, restricciones y límites de candidatos antes de pedir matrices grandes.

## Secuencias válidas

Para cada candidato enumerar las secuencias que cumplen:

- pickup de cada pedido antes de su delivery;
- capacidad máxima durante todo el trayecto;
- deadlines individuales;
- turno restante;
- restricciones de seguridad/cierre;
- punto inicial igual a la posición actual del agente.

Para A y B deben evaluarse todas las permutaciones válidas, no sólo `PA → PB → DA → DB`.

## Utilidad

Calcular y persistir componentes separados:

```text
expected_net_profit
+ future_position_value
+ batch_efficiency_bonus
- expected_delay_cost
- risk_penalty
- idle_penalty
= absolute_utility
```

Además calcular:

- tiempo/distancia de la secuencia;
- tasa neta por hora;
- tiempo/distancia por separado;
- ahorro real por batch;
- detour ratio;
- route overlap o señal equivalente;
- riesgo y reason codes.

No contar dos veces ahorro, future value o surge. La ganancia neta esperada sigue siendo pago menos costo operativo; los demás conceptos viven en utilidad.

## Límites y fallback

- Respetar `max_candidates` y `time_budget_ms`.
- Podar candidatos inviables pronto.
- Si se supera el presupuesto, devolver el mejor plan ya evaluado y marcarlo; si ninguno, usar el mejor individual factible.
- Ordenar empates de forma determinista por utilidad, net/hour, menor riesgo y claves de pedido.

## Integración

`RecommendationService` debe devolver:

- ranking individual completo;
- `OptimizedPlan` ganador;
- alternativas principales;
- facts y reason codes deterministas;
- mediciones de tiempo/candidatos.

Persistir la recomendación sin aceptarla automáticamente.

## Pruebas obligatorias

- Singles y todos los pares esperados se generan una sola vez.
- Pickup siempre precede al delivery.
- Un par compatible supera a rutas separadas sólo cuando la utilidad lo justifica.
- Par incompatible/deadline/capacidad se descarta.
- El mejor plan puede diferir del #1 individual.
- Empates producen siempre el mismo ganador.
- Ningún plan excede turno o hard constraints.
- Límite de candidatos/presupuesto activa fallback válido.
- Reason codes y cifras corresponden al plan.
- Regresión completa.

## Criterios de aceptación

- Con el escenario demo existe al menos un batch de dos pedidos claramente conveniente.
- La recomendación incluye secuencia, ruta, métricas, utilidad y razones.
- Repetir el mismo estado devuelve el mismo resultado.
- La latencia del optimizador puro cumple el presupuesto configurado en el escenario demo.
- El LLM no participa.
- No se implementa baseline, API ni UI.

## Commit sugerido

`feat: optimize feasible courier plans with two-order batching`
