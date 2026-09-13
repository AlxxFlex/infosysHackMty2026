# Paso 17 realizado — Resultados y benchmark

Fecha de finalización: 12 de septiembre de 2026.

## Resultado

Courier AI y baseline pueden ejecutarse automáticamente sobre el mismo escenario, seed y snapshot de configuración. Los resultados se calculan exclusivamente desde estados y deliveries realizados; no se usan expected utility, score ni future position bonus como ganancias.

## Implementación

- Se creó `BenchmarkService` para crear, ejecutar, reanudar y resumir benchmarks multi-seed.
- Cada seed crea un único `SimulationRun` con ambos agentes, comparte ofertas/eventos/configuración y avanza el reloj sin navegador.
- El agente Courier AI usa `RecommendationService`; baseline usa `BaselineService`. Ambos ejecutan entregas mediante `PlanExecutionService`.
- Se persisten dos `BenchmarkResult` por seed con ganancia bruta, costo, neto, MXN/h comparable, kilómetros, deadhead, activo/idle, aceptados, rechazados, completados, tardíos y porcentaje productivo.
- Se calculan medias, medianas, comparaciones por seed, delta, win rate y mejora con `(AI - baseline) / baseline × 100`. Baseline cero devuelve `null` y se muestra como “n/d”, sin división entre cero.
- Los errores quedan como filas `FAILED` con seed y mensaje visible; no se cuentan como victorias ni se inventan métricas.
- La ejecución es idempotente: seeds completas no se duplican y `resume()` reanuda ejecuciones incompletas.
- Se añadió `benchmark:run {scenario} --seeds=...`, permitiendo listas pequeñas para pruebas o hasta 100 seeds para evidencia final.
- Se creó `/results/{run}` con comparación lado a lado, métricas realizadas, fórmula de mejora, etiqueta de simulador controlado y tabla multi-seed cuando existe benchmark.
- El dashboard `/courier` enlaza a resultados al finalizar un turno.
- Cada resultado conserva scenario key, seed, versiones, routing provider/fallback y timestamps simulados.

## Pruebas

- `tests/Feature/BenchmarkServiceTest.php` cubre ejecución compartida, métricas realizadas, agregados, mejora positiva/negativa/cero e idempotencia.
- `tests/Feature/ResultsPageTest.php` verifica la página de resultados y la etiqueta de datos simulados.
- Regresión completa: 74 tests, 492 aserciones.
- `npm run build`, `vendor/bin/pint --dirty`, `composer validate --strict`, `git diff --check` y `php artisan benchmark:run --help` pasaron correctamente.

