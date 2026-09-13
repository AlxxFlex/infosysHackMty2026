# Paso 11 realizado — API REST estable

Fecha de finalización: 12 de septiembre de 2026.

## Resultado

Se publicó una API versionada bajo `/api/v1` para crear, consultar y controlar simulaciones, consultar shifts y pedidos, generar recomendaciones y aceptar planes.

## Implementación

- Se registró `routes/api.php` en Laravel y se aplicó throttling de 120 solicitudes por minuto.
- Se crearon Form Requests para creación, tick, recomendaciones y aceptación de planes.
- Se crearon controladores delgados que delegan en `ShiftService`, `SimulatorService`, `RecommendationService` y `PlanExecutionService`.
- Se crearon Resources JSON para simulaciones, shifts, pedidos, recomendaciones y entregas.
- Todas las respuestas exitosas usan `data` y `meta`; los errores exponen `message`, `errors` y `code`.
- Las transiciones inválidas devuelven 409, la validación devuelve 422 y el binding de modelos devuelve 404.
- `Idempotency-Key` (o `idempotency_key`) hace idempotentes los ticks y la aceptación de planes. La aceptación se persiste en `plan_execution_requests` con una clave única por shift.
- Se mantuvo el modo local/demo sin autenticación y se excluyeron endpoints de eventos, explicación LLM, benchmark y panel.
- Las respuestas marcan explícitamente los datos como simulados y no exponen `config_snapshot` ni payloads crudos del proveedor de routing.

## Pruebas

`tests/Feature/ApiSimulationTest.php` verifica el flujo HTTP completo, envelopes, idempotencia, validación y 404. La suite completa pasó con 58 tests y 396 aserciones.

