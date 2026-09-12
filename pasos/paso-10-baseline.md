# Paso 10 — Baseline comparable

## Objetivo

Implementar el agente baseline `Greedy Highest Gross Pay` y demostrar que recibe exactamente el mismo stream y condiciones que Courier AI.

## Puerta de entrada

- Paso 09 completo.
- Existe un plan optimizado persistible y estados independientes por agente.

## Regla del baseline

En cada decisión:

1. obtener ofertas `AVAILABLE` del shift baseline;
2. descartar las que violen restricciones duras;
3. ordenar por pago bruto descendente;
4. resolver empates por menor tiempo estimado y luego clave estable;
5. aceptar únicamente la primera;
6. nunca formar batches ni usar score, demanda, future value o LLM.

La factibilidad puede reutilizar el mismo evaluador de restricciones/routing, pero la estrategia de selección debe permanecer deliberadamente simple y auditable.

## Ejecución de decisiones

Crear un flujo común para aceptar/ejecutar un plan que:

- bloquee el shift y las ofertas relevantes;
- valide que siguen disponibles y no expiraron;
- registre la recomendación/decisión;
- cambie estados de `shift_orders`;
- avance pickup, espera y delivery conforme al reloj;
- al completar, actualice posición, ganancias, costos, kilómetros y contadores una sola vez;
- sea idempotente ante ticks repetidos.

Courier AI y baseline deben utilizar el mismo mecanismo de ejecución; sólo cambia cómo eligen.

## Equidad experimental

- mismo `simulation_run`, escenario, seed y definición de orders;
- mismo tráfico, clima, surge, deadlines y restricciones;
- misma posición/configuración inicial y costo/km;
- relojes alineados;
- decisiones y estados independientes;
- ningún agente puede modificar la oferta base del otro.

## Servicios

- `BaselineService` encapsula únicamente la política greedy.
- `ShiftService` o un servicio de ejecución aplica planes de ambos agentes.
- `SimulatorService` progresa entregas y métricas.

No duplicar fórmulas económicas.

## Pruebas obligatorias

- Baseline elige el mayor pago bruto factible aunque tenga peor MXN/hora.
- Si el mayor pago es inviable, elige el siguiente factible.
- Empates se resuelven determinísticamente.
- Nunca selecciona más de un pedido.
- Ambos agentes ven los mismos pedidos/entorno en el mismo instante.
- Aceptar en baseline no cambia `shift_orders` de Courier AI.
- Una entrega completada actualiza métricas exactamente una vez.
- Ganancia neta realizada = bruto realizado − costo realizado.
- Regresión completa.

## Criterios de aceptación

- Un run puede avanzar con decisiones de ambos agentes.
- La regla baseline se explica en una frase y coincide con el código.
- Las métricas se calculan con las mismas reglas para ambos.
- El estado permite una comparación posterior justa.
- No existe todavía UI, API ni benchmark agregado.

## Commit sugerido

`feat: add fair highest-pay greedy baseline`
