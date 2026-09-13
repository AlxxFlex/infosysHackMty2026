# Matriz de regresión integral

| Requisito crítico | Prueba principal |
|---|---|
| Flujo completo: run, ofertas, ranking, batch, ejecución, evento y finalización | `Feature/EndToEndSimulationTest.php` |
| Paridad API y Livewire para el mismo snapshot | `Feature/EndToEndSimulationTest.php` |
| Ofertas compartidas con estado independiente por agente | `Feature/CourierSimulationSchemaTest.php` |
| Fórmulas gross/cost/net, riesgo y future value separado | `Feature/ScoringServiceTest.php`, `Unit/DomainDtosTest.php` |
| Capacidad, deadlines y secuencia pickup→dropoff | `Feature/OptimizationServiceTest.php`, `Feature/EndToEndSimulationTest.php` |
| Baseline, aceptación y ejecución idempotente | `Feature/BaselineServiceTest.php` |
| Tick, transiciones terminales, locks e idempotencia | `Feature/SimulatorServiceTest.php` |
| Eventos dinámicos y reoptimización | `Feature/SimulationEventServiceTest.php` |
| OSRM timeout/5xx/payload inválido y fallback | `Feature/RoutingServiceTest.php` |
| Reverb deshabilitado conserva polling | `Feature/EndToEndSimulationTest.php`, `Feature/SimulationBroadcastTest.php` |
| LLM none, error, prompt restrictivo y validación de salida | `Unit/ExplanationServiceTest.php`, `Feature/ExplanationProviderTest.php` |
| Benchmark, mejora positiva/negativa/cero y agregados | `Feature/BenchmarkServiceTest.php` |
| Página de resultados y etiqueta de simulación | `Feature/ResultsPageTest.php` |
| API estable, validación, conflictos e idempotencia | `Feature/ApiSimulationTest.php` |
| Esquema, precisión decimal y ausencia de floats | `Feature/CourierSimulationSchemaTest.php` |

Los tests externos usan `Http::fake`; la regresión no depende de red pública, API keys, Reverb ni tiles.
