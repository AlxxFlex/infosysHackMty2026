# Paso 08 — Motor económico y scoring

## Objetivo

Calcular métricas individuales reproducibles, aplicar restricciones y generar un ranking visual explicable de pedidos disponibles.

## Puerta de entrada

- Paso 07 completo con routing y fallback probados.
- Configuración económica y pesos válidos.

## Métricas obligatorias

Para cada pedido calcular y conservar sin mezclar:

```text
gross_pay = base_pay + surge_bonus + other_bonus
deadhead_distance = courier → restaurant
delivery_distance = restaurant → customer
total_distance = deadhead + delivery
adjusted_travel_time = route_time × traffic_factor × weather_factor
total_time = adjusted_travel_time + restaurant_wait
operating_cost = total_distance × cost_per_km
net_profit = gross_pay - operating_cost
net_hourly_rate = net_profit / total_time × 60
slack = deadline - predicted_delivery_time
reliability = 1 - risk
pickup_efficiency = 1 - normalized_deadhead
```

Agregar `destination_demand_score` mediante `DemandService` y mantener `future_position_bonus` separado de la ganancia real.

## Feasibilidad individual

Invalidar, no sólo penalizar, cuando:

- no se llega al hard deadline;
- el trayecto excede el turno restante;
- la capacidad ya está llena;
- una restricción de seguridad/ruta no tiene alternativa;
- tiempos o distancias son inválidos.

Cada invalidación devuelve reason codes verificables.

## Normalización y score

Normalizar por conjunto visible a `[0,1]`. Si máximo = mínimo, asignar 0.5. Soportar negativos sin división errónea.

Calcular con pesos de configuración:

```text
score = 100 × (
  hourly_profit_norm × weight
  net_profit_norm × weight
  destination_value × weight
  batch_potential × weight
  pickup_efficiency × weight
  reliability × weight
)
```

En este paso `batch_potential` puede ser una señal individual conservadora; la evaluación real de batches pertenece al paso 09.

El score es relativo y sirve para ranking visual. Nunca usarlo como dinero ni como métrica final del benchmark.

## Servicios

- `DemandService`: obtiene demanda simulada por zona y bucket horario.
- `ScoringService`: calcula métricas, normaliza, genera score/prioridad/reason codes.
- `RecommendationService`: en este paso sólo coordina evaluación y ranking individual; no selecciona batches.

Ninguna clase debe consultar configuración con valores secretos ni acceder a la UI.

## Precisión

- Mantener precisión interna suficiente.
- Redondear dinero a dos decimales al persistir/presentar.
- Evitar dividir entre cero.
- Definir una política única de rounding en `Support/Money`.

## Pruebas obligatorias

- Fórmulas de pago bruto, costo, neto y tasa por hora con ejemplos del contexto.
- Ajuste de tiempo por tráfico/clima y espera.
- Normalización normal, idéntica y con negativos.
- Invariantes: tiempo > 0, distancia >= 0, costo >= 0 y neto <= bruto cuando costo >= 0.
- Feasibilidad por deadline, turno, capacidad y seguridad.
- Ranking esperado cuando cambian pago, pickup, tiempo o demanda.
- Score siempre entre 0 y 100.
- Future value no aumenta `net_profit` realizado.
- Regresión completa.

## Criterios de aceptación

- Todos los pedidos disponibles reciben `EvaluatedOrder` tipado.
- Ranking individual es estable para el mismo estado/configuración.
- Cada posición puede explicarse con métricas y reason codes.
- Condiciones duras nunca reaparecen como recomendación factible.
- No se implementan combinaciones, baseline, API ni UI.

## Commit sugerido

`feat: add deterministic economic metrics and order scoring`
