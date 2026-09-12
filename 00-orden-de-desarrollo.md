# Orden completo de desarrollo

> Proyecto: **Courier AI — HackMTY 2026 / Infosys Track 3**

> Stack obligatorio: **Laravel + Blade + Livewire + Alpine.js + MySQL + MapLibre GL JS + OSRM**.

> Regla: **no avanzar al siguiente paso hasta cumplir todos los criterios de aceptación del paso actual**.

## Resultado final esperado

Al finalizar debe existir una aplicación web Laravel donde:

1. el repartidor abre `/courier`;
2. inicia un turno simulado;
3. aparecen solicitudes;
4. Laravel obtiene rutas;
5. calcula métricas y scores;
6. recomienda pedidos o un batch;
7. el repartidor acepta;
8. el turno avanza;
9. surge/tráfico/cierres cambian decisiones;
10. se compara Courier AI contra un baseline;
11. se muestran resultados y explicaciones.

## Orden obligatorio

| # | Archivo | Resultado |
|---|---|---|
| 01 | Prerrequisito ya definido (fuera de esta carpeta) | Proyecto base |
| 02 | Prerrequisito ya definido (fuera de esta carpeta) | Conexión MySQL |
| 03 | `pasos/paso-03-modelo-datos-migrations.md` | Esquema completo |
| 04 | `pasos/paso-04-modelos-enums-dtos-config.md` | Dominio base |
| 05 | `pasos/paso-05-simulador-turno-y-reloj.md` | Reloj/turno |
| 06 | `pasos/paso-06-pedidos-y-escenarios.md` | Stream de pedidos |
| 07 | `pasos/paso-07-routing-osrm.md` | Rutas/distancias |
| 08 | `pasos/paso-08-motor-economico-y-scoring.md` | Métricas + ranking |
| 09 | `pasos/paso-09-optimizacion-y-batching.md` | Mejor plan |
| 10 | `pasos/paso-10-baseline.md` | Comparador simple |
| 11 | `pasos/paso-11-api-rest.md` | API estable |
| 12 | `pasos/paso-12-vistas-blade-livewire.md` | Dashboard |
| 13 | `pasos/paso-13-mapa-maplibre.md` | Mapa |
| 14 | `pasos/paso-14-eventos-dinamicos.md` | Surge/tráfico/cierres |
| 15 | `pasos/paso-15-tiempo-real-reverb.md` | Tiempo real |
| 16 | `pasos/paso-16-explicaciones-ia.md` | Why/LLM |
| 17 | `pasos/paso-17-resultados-y-benchmark.md` | Evidencia cuantitativa |
| 18 | `pasos/paso-18-tests-integrales.md` | Regresión |
| 19 | `pasos/paso-19-panel-demo.md` | Demo controlada |
| 20 | `pasos/paso-20-hardening-readme-demo-final.md` | Entrega |

## Dependencias

No hacer MapLibre antes de `RoutingService`.

No hacer LLM antes de tener recomendaciones deterministas.

No hacer benchmark antes de baseline + agente.

No hacer Reverb antes de que la app funcione con polling.

## Prompt operativo

```text
Lee AGENTS.md.
Lee context_laravel_fullstack.md.
Lee 00-orden-de-desarrollo.md.
Lee pasos/00-estructura-objetivo.md.
Lee el paso actual.
Inspecciona el repositorio.
Implementa sólo ese paso.
Ejecuta tests.
Detente.
```
