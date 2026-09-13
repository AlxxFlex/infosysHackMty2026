# Paso 15 realizado — Tiempo real con Laravel Reverb

Fecha de finalización: 12 de septiembre de 2026.

## Resultado

El simulador publica señales de cambios en un canal público de demostración por run (`simulation.{runId}`). El dashboard conserva `wire:poll` como mecanismo de reconciliación y, cuando Echo/Reverb está disponible, usa el evento recibido para solicitar una lectura autoritativa a Livewire. Ningún cálculo de scoring, optimización o aceptación depende de WebSockets.

## Implementación

- Se instaló Laravel Reverb (`laravel/reverb`) y se publicaron `config/reverb.php`, `config/broadcasting.php` y `routes/channels.php`.
- Se instalaron `laravel-echo` y `pusher-js`; `resources/js/echo.js` centraliza la configuración, suscripción única por run, desuscripción, reconexión y mensajes de fallback.
- Se añadió `App\Events\SimulationUpdated`, con `ShouldBroadcastNow`, canal público `simulation.{runId}`, nombre explícito, versión `v1`, instante simulado, ids y datos mínimos marcados como simulados. No serializa modelos completos ni secretos.
- `ScenarioService`, `SimulationEventService`, `PlanExecutionService`, `SimulatorService` y `ShiftService` registran los broadcasts con `DB::afterCommit`, después de persistir ofertas, eventos, entregas o finalizaciones.
- Se cubrieron ofertas nuevas/expiradas, ranking, cambios de surge/tráfico/cierre/espera, entrega completada y run terminado.
- `resources/js/app.js` convierte las señales de Echo en un evento Livewire `simulation-refresh`; `CourierDashboard` actualiza el estado y el mapa desde los Services. Si Reverb no conecta, se muestra aviso discreto y continúa el polling.
- `.env.example` incluye únicamente placeholders locales para seleccionar `log`, `null` o `reverb`; no se agregaron credenciales reales al repositorio.

## Prueba manual documentada

1. Copiar `.env.example` a `.env`, configurar `BROADCAST_CONNECTION=reverb` y conservar valores locales de `REVERB_*`/`VITE_REVERB_*`.
2. En terminales separadas ejecutar `php artisan serve`, `npm run dev` y `php artisan reverb:start`.
3. Abrir dos pestañas del dashboard con el mismo `runId`, avanzar el reloj o inyectar un evento y comprobar que ambas reflejan la oferta/evento/ranking sin recarga manual.
4. Detener Reverb: el indicador cambia a fallback y `wire:poll` mantiene actualizado el dashboard y el mapa; al reiniciar Reverb no se duplican listeners.

El arranque del servidor se verificó con `php artisan reverb:start --no-interaction --host=127.0.0.1 --port=8099` y se cerró correctamente tras la comprobación.

## Pruebas automatizadas

- `tests/Feature/SimulationBroadcastTest.php` verifica canal/run, nombre, ids, versión, instante y payload pequeño; también comprueba señales de tráfico y ranking.
- Regresión completa: 66 tests, 429 aserciones.
- `npm run build`, `vendor/bin/pint --dirty`, `composer validate --strict`, `git diff --check` y `php artisan reverb:start --help` pasaron correctamente.
