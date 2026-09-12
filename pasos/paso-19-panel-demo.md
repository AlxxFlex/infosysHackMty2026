# Paso 19 — Panel de demo controlada

## Objetivo

Crear una experiencia de demostración reproducible de menos de tres minutos, operable desde la interfaz y resistente a errores humanos.

## Puerta de entrada

- Paso 18 completo y suite verde.
- El flujo completo funciona sin panel especial.

## Acceso

Crear `/demo/control` para entorno local/demo. Protegerlo con una condición explícita de entorno o token seguro configurable; no exponer controles destructivos en producción.

## Controles

- iniciar escenario curado;
- pausar/reanudar;
- velocidad x1/x5/x20;
- avanzar un tick;
- trigger surge;
- trigger road closure;
- agregar retraso de restaurante;
- terminar turno;
- reset del run actual;
- nueva seed aleatoria visible.

Cada botón:

- muestra loading/disabled;
- es idempotente;
- valida el estado permitido;
- confirma sólo acciones que perderían el run visible;
- no ejecuta JavaScript con lógica de dominio.

## Escenario curado obligatorio

Debe garantizar de forma determinista:

1. inicio con cinco ofertas;
2. pedido de pago bruto alto pero poco eficiente;
3. batch de dos pedidos más conveniente;
4. explicación cuantitativa clara;
5. mismo stream para baseline y Courier AI;
6. cierre o tráfico que cambia el ranking;
7. final con comparación calculada.

El resultado puede favorecer a Courier AI porque el escenario demuestra el caso de uso, pero todas las cifras deben surgir del motor real, nunca de valores de UI hardcodeados.

## Reset seguro

- Reset sólo elimina/recrea datos del run seleccionado.
- No usar truncates globales ni `migrate:fresh` desde la UI.
- Validar el id exacto y ejecutar en transacción.
- Registrar la acción.
- La nueva seed se muestra y se conserva para reproducirla.

## Guion visible

Agregar una guía discreta con etapas:

```text
1. Iniciar turno
2. Comparar cinco ofertas
3. Abrir ¿Por qué?
4. Aceptar/avanzar
5. Activar cierre
6. Observar reoptimización
7. Finalizar y comparar
```

No ocultar errores ni resultados desfavorables del modo aleatorio.

## Pruebas obligatorias

- Acceso bloqueado fuera del entorno/configuración autorizada.
- Cada control sólo funciona en estados permitidos.
- Dobles clicks no duplican eventos/ticks.
- Reset afecta únicamente el run objetivo.
- Escenario curado siempre contiene batch y cambio de ranking.
- Flujo completo termina y abre resultados.
- Random seed queda registrada y reproducible.
- Prueba manual cronometrada menor a tres minutos.
- Regresión completa y build frontend.

## Criterios de aceptación

- Una persona puede ejecutar la historia completa sin terminal ni edición de datos.
- El panel no compromete otros runs.
- El escenario curado es estable y las cifras son reales del simulador.
- El modo aleatorio comparte seed entre agentes y admite derrotas honestas.
- Ningún control depende exclusivamente de Reverb o LLM.

## Commit sugerido

`feat: add safe reproducible hackathon demo controls`
