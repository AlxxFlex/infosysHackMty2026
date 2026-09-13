# Courier AI

Courier AI es una demo reproducible para el reto Infosys Track 3 de HackMTY 2026. Ayuda a un repartidor a decidir qué pedido (o batch de hasta dos) conviene aceptar, considerando ruta, tiempo, costo, riesgo y valor de la posición futura. La decisión la toman las métricas y la optimización; el LLM, si se activa, únicamente explica una decisión ya calculada.

## Alcance y datos

El escenario `demo_normal` y sus seis pedidos, seeds, demanda, eventos de tráfico/surge/cierres y pagos son **datos sintéticos** para demostración. No representan a Uber, DiDi, Rappi ni a ningún proveedor real. OSRM es opcional: cuando no responde se usa una ruta geométrica determinista marcada como `fallback`. La explicación determinista y el polling funcionan sin servicios externos. No hay credenciales ni resultados de benchmark inventados en el repositorio.

## Arquitectura

```text
Blade + Livewire + Alpine.js + MapLibre
                ↓
Controllers / Form Requests / Livewire
                ↓
Application Services
                ↓
Eloquent Models + MySQL 8 + OSRM (opcional) + LLM (opcional)
```

`ShiftService` y `SimulatorService` controlan el reloj simulado; `RoutingService` normaliza OSRM/fallback; `ScoringService` calcula métricas; `OptimizationService` elige la utilidad absoluta; `BaselineService` elige el mayor pago bruto factible; `BenchmarkService` compara resultados realizados. Events notifican cambios y los Jobs encapsulan la explicación LLM. Las transiciones de tick, aceptación, eventos y reset son transaccionales e idempotentes.

## Stack y requisitos

- PHP 8.2+, Composer 2, extensiones `pdo_mysql`, `mbstring`, `bcmath`, `json`.
- Laravel 12, Livewire 3.6 y Blade; Node.js 20+ y npm.
- MySQL 8.0+ (la suite usa una base separada llamada `infoSys_testing`).
- MapLibre GL JS en el navegador. OSRM y Reverb son opcionales para la demo.

## Instalación reproducible

```bash
git clone <URL-del-repositorio>
cd infosysHackMty2026
composer install
cp .env.example .env
php artisan key:generate
```

Crea dos bases MySQL (por ejemplo `courier_ai` y `infoSys_testing`) y configura `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` en `.env`. Después ejecuta:

```bash
php artisan migrate --seed
npm install
npm run build
php artisan courier:check-config
```

Usa `APP_DEBUG=false` en producción o al compartir la demo públicamente. No commitees `.env`, tokens, claves LLM ni credenciales; `.gitignore` ya excluye esos archivos y artefactos locales.

## Ejecutar la aplicación

Para el flujo mínimo sin servicios opcionales:

```bash
php artisan serve
```

Abre [`/courier`](http://localhost:8000/courier). El panel controlado está en [`/demo/control`](http://localhost:8000/demo/control); sólo está disponible en `local`/`testing` o con el header `X-Demo-Token` que corresponda a `DEMO_ACCESS_TOKEN`. En una instalación pública configura un token aleatorio.

Para procesamiento asíncrono y tiempo real, en terminales separadas ejecuta:

```bash
php artisan queue:work --tries=1 --timeout=60
php artisan reverb:start
```

El dashboard conserva polling cuando Reverb está apagado. Para apagar servicios opcionales usa `BROADCAST_CONNECTION=log`, `LLM_PROVIDER=none` y `ROUTING_PROVIDER=fallback`; también puedes dejar `OSRM_BASE_URL` configurado y permitir que el timeout active el fallback automáticamente.

## Guion de demo (menos de tres minutos)

1. Entra a `/demo/control`, pulsa **Iniciar escenario** y muestra las seis ofertas, la seed y la etiqueta de datos simulados.
2. En `/courier`, observa ranking, batch recomendado, ruta y métricas en MXN, km y minutos; pulsa **Explicar decisión** (determinista si `LLM_PROVIDER=none`).
3. Pulsa **Aceptar recomendación** y avanza uno o dos ticks.
4. En el panel dispara surge, cierre de carretera o retraso de restaurante; avanza otro tick y muestra la reoptimización y los avisos de fallback si OSRM no está disponible.
5. Termina el turno y abre la comparación Courier AI/baseline. Reinicia con confirmación antes de repetir la demo.

El mapa es informativo y no bloquea el flujo si MapLibre o OSRM no cargan. Los controles tienen labels visibles, foco de teclado, estados de carga/error/vacío, contraste y respetan `prefers-reduced-motion` en las animaciones principales.

## Cálculos y comparación

Para cada pedido se calcula:

```text
tiempo = (ruta_pickup + ruta_entrega) × tráfico × clima + espera_restaurante
costo_operativo = distancia_km × costo_por_km
ganancia_neta = pago_base + surge + otros_bonos − costo_operativo
tasa_horaria = ganancia_neta / tiempo × 60
```

El score normalizado combina tasa horaria, ganancia neta, destino, batch, pickup y confiabilidad con pesos configurables que suman 1.0. La optimización evalúa singles y batches factibles dentro de `max_candidates` y `time_budget_ms`, maximizando utilidad absoluta (ganancia, bonus de batch y posición futura menos demora, riesgo y ocio). El baseline comparte exactamente escenario, pedidos, seed y reloj, pero elige sólo el mayor pago bruto factible. Un denominador baseline cero se muestra como `N/D`, nunca como un porcentaje falso.

## API, tests y benchmark

La API versionada está bajo `/api/v1` y limita tráfico a 120 solicitudes por minuto. Los endpoints de tick y aceptación exigen `Idempotency-Key`; los Form Requests limitan minutos, pedidos, candidatos y payloads esperados.

```bash
php artisan test
vendor/bin/pint --test
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear
```

Para un benchmark reproducible (todos los valores quedan marcados como simulados) ejecuta:

```bash
php artisan benchmark:run demo_normal --seeds=1,2,3
```

El comando imprime JSON con seeds, versión de algoritmo, métricas realizadas, fallos y comparaciones. **No se publica aquí un porcentaje de mejora**: vuelve a ejecutar el benchmark con la configuración final y conserva su salida real antes de comunicar cualquier cifra.

## Limitaciones y siguientes pasos

El escenario no es telemetría productiva, el routing fallback no modela calles reales, no existe autenticación de repartidores y Reverb/LLM dependen de la infraestructura que los hospede. Para producción se necesitarían datos reales consentidos, observabilidad y alertas, autenticación/autorización completa, colas durables, límites por usuario y validación operativa de rutas.

## Licencia

Código de demostración para HackMTY 2026; conservar las licencias de Laravel, Livewire, MapLibre y demás dependencias indicadas por Composer/npm.
