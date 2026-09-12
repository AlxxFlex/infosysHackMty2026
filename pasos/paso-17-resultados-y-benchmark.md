# Paso 17 — Resultados y benchmark

## Objetivo

Medir Courier AI contra el baseline sobre exactamente las mismas condiciones y mostrar evidencia cuantitativa calculada, no inventada.

## Puerta de entrada

- Paso 16 completo.
- Ambos agentes pueden completar un run con decisiones y métricas realizadas.

## Métricas finales

Por agente calcular desde datos realizados:

- ganancia bruta;
- costo operativo;
- ganancia neta;
- MXN/hora sobre duración comparable;
- kilómetros totales y deadhead;
- minutos activos e idle;
- pedidos aceptados, rechazados y completados;
- entregas tardías;
- porcentaje de tiempo productivo.

Comparación principal:

```text
improvement_percent =
  (courier_ai_net_profit - baseline_net_profit)
  / baseline_net_profit × 100
```

Definir explícitamente el caso baseline = 0 sin división entre cero. No usar expected utility, score ni future position bonus como ganancia realizada.

## `BenchmarkService`

- Ejecutar una lista explícita de seeds sobre el mismo escenario/configuración.
- Crear un run comparable por seed.
- Avanzar hasta estado terminado sin depender del navegador.
- Persistir resultado por agente.
- Calcular media, mediana, win rate, distancia y retrasos.
- Registrar errores por seed sin fabricar resultados.
- Permitir reanudar/consultar ejecución idempotentemente.

El valor por defecto puede ser pequeño para tests. Proveer opción de 100 seeds para evidencia final, pero no hardcodear un resultado esperado favorable.

## Pantallas

Crear:

- `/results/{run}` con comparación lado a lado;
- resumen final accesible desde `/courier` al terminar;
- tabla de benchmark cuando exista una ejecución;
- etiquetas claras “Resultados del simulador controlado”.

Mostrar delta positivo, cero o negativo honestamente. No ocultar seeds donde pierda Courier AI.

## Reproducibilidad

Cada resultado debe enlazar/conservar:

- scenario key;
- seed;
- config version/snapshot;
- versión de algoritmo si existe;
- routing provider/fallback;
- timestamps simulados.

Permitir volver a ejecutar una seed concreta y obtener el mismo resultado dentro de la misma configuración.

## Pruebas obligatorias

- Fórmula de improvement positiva, negativa y baseline cero.
- Win rate y agregados con fixtures conocidos.
- Las métricas provienen de deliveries/shifts realizados.
- Courier AI y baseline comparten scenario/seed/config.
- Repetir una seed produce el mismo resumen.
- Fallo de una seed queda visible y no cuenta como victoria.
- Results page muestra cifras y etiqueta simulada.
- Benchmark no depende de LLM, Reverb o tiles.
- Regresión completa.

## Criterios de aceptación

- Un turno terminado muestra comparación final correcta.
- Se puede correr un benchmark multi-seed desde Service/comando o Job.
- Ningún porcentaje está hardcodeado.
- La evidencia distingue valores esperados de realizados.
- Todos los runs, incluidos empates/derrotas, quedan contabilizados.

## Commit sugerido

`feat: compare courier AI with baseline across reproducible runs`
