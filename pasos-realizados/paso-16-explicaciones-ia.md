# Paso 16 realizado — Explicaciones deterministas e IA opcional

Fecha de finalización: 12 de septiembre de 2026.

## Resultado

Cada recomendación conserva una explicación determinista basada en facts calculados. El proveedor LLM es opcional, recibe únicamente ese snapshot y sólo puede completar `llm_explanation`; nunca selecciona pedidos, cambia el ranking ni modifica el plan.

## Implementación

- Se creó `ExplanationService` con facts versionados (`v1`) e inmutables por round-trip JSON.
- Los facts contienen pedidos seleccionados, neto, costo operativo, duración, distancia, pickup/deadhead, batch savings, overlap, valor futuro estimado, riesgo, restricciones, routing provider/fallback, configuración, entorno simulado y la mejor alternativa rechazada con diferencias cuantitativas.
- Se centralizaron templates por `ReasonCode` para explicar tasa/hora, neto, pickup, demanda, riesgo, surge, cierres, tráfico, batch y ajuste al turno.
- La explicación determinista maneja planes inviables, datos opcionales ausentes, batch, fallback de routing, surge, cierres y final de turno.
- Se añadieron `ExplanationProvider`, `NoneExplanationProvider` y `HttpExplanationProvider`. `LLM_PROVIDER=none` es el valor seguro por defecto; el adaptador HTTP usa timeout y tokens limitados, prompt restrictivo, no registra API keys y convierte errores/500/timeouts en fallback.
- `ExplanationService::validateOutput()` rechaza IDs o números que no existan en facts y limita la longitud de salida.
- `GenerateLlmExplanation` es un Job posterior al commit que sólo actualiza el texto LLM validado; el ranking y la selección permanecen intactos.
- RecommendationService persiste los facts junto con la recomendación y despacha el Job únicamente cuando el proveedor no es `none`.
- La API devuelve facts completos y distingue fuente determinista/IA. Livewire muestra un panel accesible “¿Por qué?”, métricas esperadas, alternativa comparada y separa ganancia esperada de ganancia realizada.
- `.env.example` documenta placeholders sin credenciales reales.

## Pruebas

- `tests/Unit/ExplanationServiceTest.php` cubre facts single, alternativa, todos los reason codes, provider `none` y validación de IDs/números.
- `tests/Feature/ExplanationProviderTest.php` usa `Http::fake` para verificar prompt restrictivo, facts, respuestas válidas, 500 y salida inválida.
- Regresión completa: 71 tests, 476 aserciones.
- `npm run build`, `vendor/bin/pint --dirty`, `composer validate --strict` y `git diff --check` pasaron correctamente.

