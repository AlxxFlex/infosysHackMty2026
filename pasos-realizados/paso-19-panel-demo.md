# Paso 19 realizado — Panel de demo controlada

Fecha de finalización: 12 de septiembre de 2026.

## Resultado

Se añadió `/demo/control`, una experiencia reproducible para demostrar Courier AI en menos de tres minutos sin terminal ni edición manual de datos.

## Implementación

- `DemoControl` coordina únicamente acciones de UI y delega toda la lógica en `ShiftService`, `SimulatorService` y `SimulationEventService`.
- El panel inicia el escenario curado `demo_normal` con seed visible y seis ofertas materializadas (incluye batch, pagos/eficiencia y eventos de tráfico/surge del escenario).
- Se añadieron controles para iniciar, pausar, reanudar, velocidades x1/x5/x20, tick manual, surge, cierre, retraso de restaurante, finalizar y reset.
- Cada acción valida estado, usa loading/disabled en Blade y mantiene idempotencia; los eventos dinámicos no se duplican si se pulsa dos veces.
- El reset exige confirmación, elimina únicamente el `SimulationRun` seleccionado dentro de una transacción y registra la acción en el log. Nunca ejecuta truncate ni `migrate:fresh`.
- La nueva seed se genera con `random_int`, queda visible y sólo puede cambiarse antes de iniciar un run.
- Se añadió un guion visible con las siete etapas de la historia y auditoría local de acciones.
- El acceso se limita a entornos `local`/`testing` o a `DEMO_ACCESS_TOKEN` mediante header/query; el componente vuelve a validar el permiso.
- Al terminar se ofrece el enlace a `/results/{run}`; el panel no depende de Reverb ni LLM.

## Prueba manual cronometrada

Abrir `/demo/control`, pulsar **Iniciar escenario**, revisar las ofertas y el botón “¿Por qué?” en el dashboard, usar **Avanzar tick** (x5/x20 para acelerar), activar tráfico/surge/cierre, avanzar otro tick para observar reoptimización, y pulsar **Terminar turno**. La historia completa se ejecuta en menos de tres minutos con el escenario curado.

## Pruebas

- `tests/Feature/DemoControlTest.php` cubre acceso, seis ofertas, controles idempotentes, velocidad, eventos sin duplicados, reset confirmado y aislamiento del run.
- Regresión completa y build frontend se ejecutaron después de integrar el panel.

