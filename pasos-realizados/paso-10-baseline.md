# Paso 10 realizado — Baseline comparable

Fecha de finalización: 12 de septiembre de 2026.

## Resultado

Se implementó el agente `Greedy Highest Gross Pay` y un flujo común para aceptar y ejecutar decisiones de Courier AI y baseline.

## Baseline

`BaselineService` obtiene las ofertas `AVAILABLE`, reutiliza `ScoringService` únicamente para comprobar factibilidad, descarta restricciones duras y ordena por:

1. pago bruto descendente;
2. menor tiempo estimado;
3. clave estable del pedido.

Selecciona como máximo un pedido y nunca usa score visual, demanda, future value, batches ni LLM. La explicación persistida es una frase auditable que coincide con esta política.

## Ejecución común

`PlanExecutionService` es compartido por ambos agentes y:

- bloquea run, shift y `shift_orders` relevantes;
- valida pertenencia, disponibilidad, expiración y plan factible;
- registra una recomendación/decisión y la marca `ACCEPTED`;
- cambia los pedidos a `ACCEPTED` y crea una `Delivery` `IN_PROGRESS`;
- avanza pickup, espera y delivery con el reloj simulado;
- al completar, actualiza posición, kilómetros, bruto, costo, neto y contadores;
- ignora avances repetidos de una entrega ya completada.

`SimulatorService` invoca el avance de entregas dentro del tick, manteniendo los estados de Courier AI y baseline independientes.

Las métricas realizadas se calculan como `bruto - costo`, sin reutilizar la utilidad visual ni modificar la oferta compartida.

## Pruebas

`tests/Feature/BaselineServiceTest.php` cubre:

- selección por mayor pago bruto aunque tenga peor tasa horaria;
- descarte de la oferta de mayor pago cuando es inviable;
- desempate determinista;
- ausencia de batches;
- igualdad de stream y aislamiento de `shift_orders`;
- entrega completada y métricas actualizadas exactamente una vez.

La suite completa conserva la regresión de pasos 03–09. No se agregaron UI, API ni benchmark agregado.
