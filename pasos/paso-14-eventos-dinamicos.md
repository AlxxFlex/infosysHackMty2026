# Paso 14 — Eventos dinámicos y reoptimización

## Objetivo

Aplicar surge, tráfico, cierres y retrasos de restaurante al escenario compartido y recalcular decisiones de ambos agentes de forma determinista.

## Puerta de entrada

- Paso 13 completo.
- Mapa y ranking funcionan con polling.
- El escenario contiene eventos programables.

## Eventos obligatorios

- `SURGE_STARTED` / `SURGE_ENDED`;
- `TRAFFIC_CHANGED`;
- `ROAD_CLOSED` / `ROAD_REOPENED`;
- `RESTAURANT_DELAY_CHANGED`;
- eventos existentes de order lifecycle siguen funcionando.

Cada evento incluye instante simulado, payload validado, estado aplicado y etiqueta de dato simulado.

## Aplicación de eventos

Crear un Service dedicado o ampliar `SimulatorService` de forma cohesionada para:

1. obtener eventos pendientes hasta el tiempo actual;
2. bloquear y aplicar una sola vez en orden determinista;
3. actualizar `environment_state` o la entidad afectada;
4. incrementar `closure_version` cuando cambie una restricción de ruta;
5. invalidar naturalmente caché mediante la nueva key;
6. recalcular rutas/métricas/recomendación;
7. persistir antes/después y reason codes.

Los mismos eventos afectan a los dos agentes. Las decisiones resultantes pueden diferir.

## Semántica

- Surge modifica la oferta simulada aplicable sin reescribir importes base.
- Tráfico multiplica duración, no distancia.
- Retraso modifica espera prevista desde el instante del evento; no retrocede entregas completadas.
- Cierre invalida ruta o selecciona alternativa/factor precalculado del escenario.
- El OSRM público no se presenta como capaz de conocer cierres simulados.

## Reoptimización

Disparar `RecommendationService` cuando:

- llega/expira una oferta;
- cambia surge/tráfico/cierre/espera;
- se completa una entrega.

Guardar comparación de ranking previa y nueva para mostrar cambios. Si una recomendación ya aceptada no puede cancelarse según reglas del MVP, recalcular ETA/riesgo pero no revertir aceptación silenciosamente.

## UI y mapa

- Mostrar banner con evento, instante y efecto medible.
- Mostrar cambios de posición del ranking.
- Agregar overlays de surge y cierres desde datos del escenario.
- Mantener polling; Reverb pertenece al paso 15.
- Etiquetar tráfico/surge/cierres como simulados.

Agregar `POST /api/v1/simulations/{run}/events` con Form Request y Resource para inyección controlada. El endpoint delega en el mismo Service que usan los eventos programados y, fuera del entorno demo autorizado, debe estar deshabilitado o protegido.

## Pruebas obligatorias

- Cada evento se aplica exactamente una vez.
- Eventos con mismo timestamp respetan orden estable.
- Surge cambia gross pay aplicable y puede cambiar ranking.
- Tráfico cambia duración/riesgo, no distancia.
- Cierre cambia cache key y penaliza/invalida la ruta correcta.
- Retraso de restaurante reduce prioridad esperada.
- Courier AI y baseline reciben el mismo evento.
- El escenario demo garantiza al menos un cambio visible de ranking.
- UI/mapa muestran el evento mediante polling.
- Regresión completa.

## Criterios de aceptación

- Activar un cierre o cambio de tráfico produce una reoptimización real y explicable.
- No se duplican bonos ni eventos al repetir ticks.
- La app conserva un log auditable del antes/después.
- Ningún dato simulado se presenta como feed real.
- No se instala Reverb ni se llama al LLM.

## Commit sugerido

`feat: reoptimize courier plans on simulated dynamic events`
