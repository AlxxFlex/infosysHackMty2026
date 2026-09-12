# Paso 11 — API REST estable

## Objetivo

Exponer los casos de uso ya implementados mediante una API JSON versionada y consistente, sin duplicar lógica de Services.

## Puerta de entrada

- Paso 10 completo.
- Casos de uso funcionan directamente desde Services.

## Preparación Laravel 12

- Crear `routes/api.php` si no existe.
- Registrarlo en `bootstrap/app.php` con el prefijo `/api`.
- Definir rate limiting razonable para acciones de simulación.
- Mantener `/courier` y vistas fuera de este paso.

## Endpoints mínimos

```text
POST   /api/v1/simulations
GET    /api/v1/simulations/{run}
POST   /api/v1/simulations/{run}/start
POST   /api/v1/simulations/{run}/pause
POST   /api/v1/simulations/{run}/resume
POST   /api/v1/simulations/{run}/tick
POST   /api/v1/simulations/{run}/finish
GET    /api/v1/shifts/{shift}
GET    /api/v1/shifts/{shift}/orders
POST   /api/v1/shifts/{shift}/recommendations
POST   /api/v1/shifts/{shift}/plans/accept
```

No agregar todavía endpoints de inyección de eventos, explicaciones, benchmark o panel demo.

## Capas HTTP

- Form Requests validan scenario, seed, minutos, ids e idempotency keys.
- Controllers son delgados: autorizan, llaman un Service y devuelven Resource.
- API Resources fijan nombres, formatos decimales/fechas, enums y estructuras.
- Excepciones de dominio se mapean a códigos HTTP coherentes.
- No retornar Models crudos ni stack traces.

## Contrato de respuesta

Usar un envelope consistente:

```json
{
  "data": {},
  "meta": {
    "simulation_time": "...",
    "is_simulated": true
  }
}
```

Errores:

```json
{
  "message": "...",
  "errors": {},
  "code": "STABLE_MACHINE_CODE"
}
```

Las respuestas de recomendación deben separar ranking, plan, expected metrics, realized metrics y routing fallback.

## Concurrencia e idempotencia

- `tick` y `plans/accept` requieren clave de idempotencia o token equivalente.
- Aceptar una oferta expirada/ocupada devuelve conflicto sin cambios parciales.
- Utilizar route model binding scoped donde corresponda.
- No permitir operar un shift que no pertenece al run indicado.

Para el MVP sin usuarios, documentar que la API es local/demo. No inventar autenticación incompleta.

## Pruebas obligatorias

- Happy path completo: crear, iniciar, tick, listar, recomendar y aceptar.
- Validación 422 de payloads inválidos.
- 404 para recursos inexistentes y 409 para transición/conflicto.
- Idempotencia de tick y accept.
- Resources no filtran config sensible ni payload OSRM crudo.
- Respuesta marca datos simulados.
- Un Controller test demuestra que delega en Services.
- Regresión completa.

## Criterios de aceptación

- Todos los endpoints tienen contrato estable y tests.
- La API reproduce exactamente los resultados obtenidos desde Services.
- Controllers no contienen fórmulas, queries complejas ni optimización.
- Se puede completar por API el flujo existente hasta aceptar/avanzar pedidos.
- No se implementan vistas ni funcionalidades futuras.

## Commit sugerido

`feat: expose versioned courier simulation API`
