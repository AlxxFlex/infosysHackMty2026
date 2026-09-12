# Paso 06 — Pedidos y escenarios reproducibles

## Objetivo

Agregar un stream determinista de ofertas que aparece y expira conforme al reloj simulado, idéntico para Courier AI y baseline.

## Puerta de entrada

- Paso 05 completo.
- Reloj y transiciones deterministas probados.

## Escenarios

Crear archivos versionados en `database/data/scenarios/` con un esquema validable. Incluir al menos:

- escenario `demo_normal` con cinco o más ofertas visibles y potencial futuro de batch;
- definición de posición inicial, duración y zona horaria;
- pedidos con clave estable, spawn, expiración, restaurante, cliente, pagos, espera y deadlines;
- eventos futuros declarados, pero sin aplicarlos todavía;
- metadata que declare explícitamente que pagos, demanda, surge y tráfico son simulados.

Crear `ScenarioService` para cargar, validar y materializar escenarios. No leer JSON directamente desde Controllers o tests.

## Generación reproducible

Soportar dos fuentes:

1. escenario curado versionado;
2. generador seedable para challenge mode.

La misma combinación `scenario + seed + config` debe producir los mismos pedidos, orden, importes y tiempos. No utilizar generadores globales sin inicialización controlada.

## Ciclo de oferta

Al iniciar un run:

- materializar una sola colección de `orders` compartida;
- crear un `shift_order` por pedido y por agente en `PENDING`;
- en cada tick cambiar a `AVAILABLE` cuando llegue `spawn_time`;
- cambiar `AVAILABLE` a `EXPIRED` cuando corresponda;
- no expirar pedidos ya aceptados/rechazados/completados;
- conservar los mismos timestamps simulados en ambos agentes.

La transición debe ejecutarse dentro de la transacción del tick o mediante una operación atómica coordinada por `SimulatorService`.

## Orden de implementación

1. Definir y documentar el esquema JSON.
2. Crear validador con mensajes precisos por campo/rango.
3. Implementar carga del escenario curado.
4. Implementar PRNG/generador reproducible encapsulado.
5. Materializar orders y estados por shift.
6. Integrar spawn/expiration al tick sin routing ni scoring.
7. Crear factory/seeder de `DemandZone` mínima para zonas usadas; su valoración llegará después.

## Pruebas obligatorias

- Dos runs con misma seed generan ofertas equivalentes.
- Seeds distintas generan al menos una diferencia en challenge mode.
- Ambos agentes reciben exactamente las mismas definiciones y disponibilidad.
- Antes de spawn el pedido está `PENDING`; al llegar está `AVAILABLE`; al expirar está `EXPIRED`.
- Un pedido aceptado no expira.
- Escenario inválido falla antes de crear datos parciales.
- El escenario demo contiene al menos cinco ofertas.
- Regresión completa.

## Criterios de aceptación

- Un turno iniciado y avanzado muestra un flujo reproducible de pedidos en persistencia/Services.
- Estados por agente son independientes aunque la definición sea compartida.
- Los datos simulados están etiquetados como tales.
- No se calculan rutas, métricas, ranking ni recomendaciones.
- No se implementan endpoints o UI.

## Commit sugerido

`feat: add reproducible order streams and demo scenarios`
