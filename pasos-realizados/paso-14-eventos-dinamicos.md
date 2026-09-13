# Paso 14 realizado — Eventos dinámicos y reoptimización

Fecha de finalización: 12 de septiembre de 2026.

## Resultado

El simulador aplica eventos programados o inyectados durante el reloj virtual y reoptimiza Courier AI y baseline sobre el mismo escenario compartido.

## Implementación

- Se creó `SimulationEventService` para programar y aplicar eventos pendientes en transacciones, con locks y orden por instante, `sequence` e id.
- Se implementaron `SURGE_STARTED`/`SURGE_ENDED`, `TRAFFIC_CHANGED`, `ROAD_CLOSED`/`ROAD_REOPENED`, `RESTAURANT_DELAY_CHANGED` y `WEATHER_CHANGED`.
- Surge modifica únicamente el bono simulado de las ofertas afectadas, sin alterar el pago base.
- Tráfico modifica duración/riesgo mediante el factor del entorno, sin modificar distancia.
- Cierres guardan zona/restaurante/geometría, incrementan `closure_version` y permiten que scoring invalide la oferta afectada.
- Retrasos actualizan la espera prevista de restaurantes no entregados.
- Cada evento queda auditado con `state_before`, `state_after`, `reason_codes`, `applied_at` y comparación de ranking anterior/nuevo para ambos agentes.
- `SimulatorService` aplica eventos dentro de cada tick antes de avanzar entregas, evitando duplicados por `applied_at`.
- Se añadió `POST /api/v1/simulations/{run}/events`, con Form Request, Resource y restricción a entornos `local`/`testing`.
- Livewire muestra banner de evento simulado y el mapa incorpora overlays de cierres mediante sus propios datos GeoJSON; Reverb y LLM no se añadieron.

## Pruebas

`tests/Feature/SimulationEventServiceTest.php` cubre aplicación única, orden estable, reoptimización de ambos agentes, surge, tráfico, cierres e inyección. La API valida programación de eventos. La regresión completa pasó con 64 tests y 426 aserciones; `npm run build`, Pint y `git diff --check` también pasaron.

