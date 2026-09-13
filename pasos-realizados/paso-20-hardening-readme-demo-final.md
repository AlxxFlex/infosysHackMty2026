# Paso 20 realizado — Hardening, README y entrega final

## Implementación

- Se agregó `CourierConfigValidator`, que valida al arrancar y reporta errores accionables para timeouts/retries de OSRM y LLM, URL/proveedor de routing, proveedor LLM opcional, pesos de scoring, límites de concurrencia y `APP_DEBUG` en producción.
- `AppServiceProvider` registra advertencias en local/testing y falla de forma segura en producción si la configuración es inválida.
- Se agregó `php artisan courier:check-config` para revisar la instalación antes de una demo.
- Se reforzaron los Form Requests con límites explícitos para planes, secuencias, pedidos, snapshots y payloads de eventos; se conserva el throttle de API (120/min) y la idempotencia de tick/aceptación (claves de hasta 128 caracteres).
- `.env.example` recomienda `APP_DEBUG=false`; `.gitignore` excluye `.env`, build, logs, vendor y node_modules. Se buscó contenido con patrones de API keys, tokens, private keys y rutas de debug sin encontrar secretos versionados.
- Se reemplazó el README de Laravel por documentación de problema/propuesta, datos simulados, arquitectura, instalación MySQL 8, modos opcionales, guion de demo, fórmulas, baseline, optimización, tests, benchmark y limitaciones.
- Se añadió una prueba de diez ciclos consecutivos de demo (tick, evento, finish y reset) y pruebas de configuración válida/inválida.

## Evidencia ejecutada

| Comando | Resultado |
|---|---|
| `php artisan test --compact` | 82 tests, 620 assertions, verde |
| `vendor/bin/pint --test` | Passed |
| `composer validate --strict` | `composer.json is valid` |
| `npm run build` | Vite produjo build de producción (68 módulos) |
| `php artisan config:cache` | Exitoso |
| `php artisan route:cache` | Exitoso |
| `php artisan view:cache` | Exitoso |
| `php artisan optimize:clear` | Exitoso con conexión MySQL dedicada |
| `php artisan courier:check-config` | Configuración válida; servicios opcionales desactivados |
| `git diff --check` | Sin errores |
| `git grep` de secretos/rutas debug | Sin coincidencias peligrosas |
| Routing + LLM + broadcasting tests | 9 tests, 30 assertions, verde |

El benchmark documentado se ejecutó con `demo_normal` y seeds `1,2,3`, versión de algoritmo `v1`, tres seeds completadas y cero fallos. La salida real quedó marcada como simulada; no se incorpora un porcentaje de mejora al README porque la muestra es sólo evidencia de ejecución y no una afirmación de producción.

## Modos degradados verificados

- OSRM caído/timeout/payload inválido: `RoutingServiceTest` confirma ruta y matriz fallback deterministas con advertencia explícita.
- `LLM_PROVIDER=none`: explicación determinista/polling continúan sin endpoint ni API key.
- `BROADCAST_CONNECTION=log` y Reverb apagado: el flujo principal permanece operativo.

## Pendientes

Ninguno para el alcance del Paso 20. Los límites de datos reales, autenticación productiva y observabilidad descritos en el README quedan como trabajo posterior, no como funcionalidades ocultas de esta entrega.
