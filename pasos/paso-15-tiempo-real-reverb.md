# Paso 15 — Tiempo real con Laravel Reverb

## Objetivo

Transmitir cambios del simulador al dashboard en tiempo real sin eliminar el polling funcional ni acoplar el dominio a WebSockets.

## Puerta de entrada

- Paso 14 completo.
- Toda la aplicación funciona correctamente sólo con polling.

## Instalación

- Instalar/configurar Laravel Reverb según Laravel 12.
- Instalar `laravel-echo` y `pusher-js`.
- Registrar `routes/channels.php` y configuración de broadcasting.
- Añadir placeholders seguros a `.env.example`.
- Mantener el driver configurable (`log`/`null` en tests, `reverb` en ejecución real).

## Eventos broadcast

Implementar payloads pequeños y versionados para:

- nueva oferta;
- oferta expirada;
- ranking actualizado;
- surge/tráfico/cierre/espera cambiados;
- delivery completada;
- shift/run terminado.

Los Services emiten Events después de confirmar la transacción. Los Events no calculan estado ni serializan Models completos.

Canal sugerido:

```text
simulation.{runId}
```

Si el MVP no tiene usuarios, documentar que el canal es de demo local. No fingir autorización privada incompleta.

## Integración cliente

- Centralizar Echo en `resources/js/echo.js`.
- Suscribirse/desuscribirse una sola vez por run.
- Convertir eventos Echo en eventos del navegador/Livewire.
- Livewire vuelve a consultar estado autoritativo; el payload broadcast es señal, no base definitiva.
- MapLibre consume la misma actualización de estado.

## Fallback

- Si Echo/Reverb no conecta, mostrar aviso discreto y conservar `wire:poll`.
- Evitar doble refresco excesivo cuando Reverb funciona; polling puede hacerse menos frecuente como reconciliación.
- Reconexión no debe duplicar listeners ni acciones.
- Ninguna recomendación depende de que el WebSocket esté disponible.

## Pruebas obligatorias

- `Event::fake` confirma que cada cambio emite el evento correcto después del commit.
- Payload contiene ids, versión, instante simulado y tipo; no contiene secretos ni Models completos.
- Eventos no se emiten si la transacción falla.
- Canal/nombre de evento coincide con el cliente Echo.
- El dashboard sigue actualizando con broadcasting deshabilitado.
- `npm run build` y regresión completa.
- Prueba manual documentada con servidor Laravel, Vite y Reverb.

## Criterios de aceptación

- Dos pestañas del mismo run reflejan una oferta/evento/ranking sin recarga manual.
- Una caída de Reverb no interrumpe simulación, scoring, aceptación ni mapa.
- No se duplican ticks o aceptaciones por eventos de UI.
- La arquitectura Events → Broadcast → refresh mantiene Services como fuente de verdad.

## Commit sugerido

`feat: broadcast simulation updates with Reverb fallback`
