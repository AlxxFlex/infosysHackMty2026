# Paso 08 realizado — Motor económico y scoring

Fecha de finalización: 12 de septiembre de 2026.

## Resultado

Se implementó el cálculo determinista de métricas económicas y el ranking individual de pedidos disponibles. No se agregaron batches, baseline, API ni UI.

## Servicios

### `DemandService`

Obtiene la demanda simulada de una zona y bucket horario desde `demand_zones`. Devuelve un valor normalizado entre 0 y 1 y calcula `future_position_bonus_mxn` por separado; ese valor nunca se suma a la ganancia realizada.

### `ScoringService`

Combina `CourierState`, `EnvironmentState`, `Order` y `RoutingService` para calcular:

- pago bruto (`base + surge + other`);
- distancia sin pedido, distancia de entrega y distancia total;
- tiempo ajustado por tráfico y clima, espera y tiempo total;
- costo operativo, ganancia neta y tasa neta por hora;
- slack contra deadline, riesgo de retraso, eficiencia de pickup y compatibilidad/potencial conservador de batch;
- demanda de destino y valor futuro independiente.

Las restricciones duras invalidan el pedido con reason codes existentes del dominio: `SHIFT_TOO_SHORT` para deadline/turno insuficiente, `INCOMPATIBLE_BATCH` para capacidad o seguridad, y `ROAD_CLOSURE` para cierres. Las rutas inválidas nunca entran como factibles.

`rank()` normaliza métricas visibles en `[0,1]` (incluidos valores negativos; conjunto constante usa `0.5`), aplica los pesos configurados, limita el score a `[0,100]`, asigna prioridad y ordena con desempate estable por `order_id`. Los pedidos inviables permanecen tipados pero quedan fuera de la selección factible.

### `RecommendationService`

Coordina el estado autoritativo de un shift y devuelve únicamente el ranking individual de sus `AVAILABLE` orders. No persiste recomendaciones ni selecciona combinaciones; esa lógica pertenece a pasos posteriores.

## Precisión y configuración

`Support/Money` centraliza suma, resta, multiplicación y redondeo monetario a dos decimales usando BCMath. Se añadieron factores de clima (`clear`, `rain`, `storm`) al bloque de scoring.

## Pruebas

`tests/Feature/ScoringServiceTest.php` verifica:

- fórmulas de pago bruto, costo, neto, tasa y valor futuro separado;
- ajuste de tiempo por tráfico/clima y espera;
- normalización estable con valores negativos e idénticos;
- límites de score;
- deadline, turno, capacidad, seguridad y reason codes;
- ranking únicamente de ofertas disponibles.

La suite completa mantiene la regresión de pasos 03–07.
