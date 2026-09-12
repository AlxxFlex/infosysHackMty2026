# Paso 18 — Tests integrales y regresión

## Objetivo

Cerrar huecos de comportamiento entre componentes y demostrar que el flujo completo, fallbacks e invariantes funcionan juntos. Este paso no añade features de producto.

## Puerta de entrada

- Paso 17 completo.
- Todos los tests unitarios/feature anteriores pasan.

## Matriz mínima de pruebas

### Flujo end-to-end de dominio

1. crear run curado con seed;
2. iniciar y avanzar reloj;
3. publicar cinco ofertas;
4. obtener rutas;
5. generar ranking y batch;
6. baseline seleccionar mayor pago factible;
7. aceptar/ejecutar planes;
8. aplicar evento dinámico;
9. reoptimizar;
10. terminar;
11. comparar resultados.

### HTTP y Livewire

- flujo principal mediante API;
- flujo principal mediante componentes Livewire;
- ambos producen el mismo plan para el mismo snapshot;
- validación, conflictos e idempotencia;
- navegación `/courier` → resultados.

### Fallos externos

- OSRM timeout/5xx/payload incompleto;
- tiles no afectan backend ni UI crítica;
- Reverb deshabilitado conserva polling;
- LLM deshabilitado/fallido conserva explicación;
- queue síncrona y diferida no cambian decisión.

### Invariantes

- `net = gross - operating_cost` con redondeo acordado;
- no hay distancia/tiempo negativos;
- pickup precede delivery;
- capacidad nunca se excede;
- hard constraint implica plan inviable;
- transición terminal no retrocede;
- oferta no se completa dos veces;
- ambos agentes reciben mismo entorno/stream;
- future value nunca se suma a ganancias realizadas.

### Reproducibilidad y concurrencia

- misma seed/config = mismas ofertas/resultados;
- tick/accept/event repetidos son idempotentes;
- locks evitan doble entrega o doble acumulación;
- cache/fallback no cambia contratos.

## Calidad técnica

- Usar factories/builders legibles; evitar fixtures gigantes duplicados.
- Congelar tiempo real sólo para infraestructura; el dominio usa reloj simulado.
- Tests externos siempre con fakes.
- No borrar tests anteriores para hacer pasar la suite.
- Corregir causas, no relajar assertions válidas.

## Comandos obligatorios

Ejecutar, según scripts existentes:

```bash
php artisan test
vendor/bin/pint --test
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Limpiar caches después si corresponde. Ejecutar `migrate:fresh` en una base MySQL de testing dedicada y nunca sobre datos del usuario.

## Criterios de aceptación

- Suite completa pasa sin depender de red pública ni API keys.
- Existe al menos un test end-to-end del dominio completo.
- API y Livewire coinciden.
- Fallos OSRM/Reverb/LLM tienen cobertura.
- Invariantes y carreras principales tienen cobertura.
- Build frontend y caches Laravel se generan correctamente.
- No se agregaron features del panel demo ni documentación final.

## Entregable adicional

Crear una breve matriz en `tests/README.md` que relacione requisito crítico con archivo de prueba, sin incluir resultados inventados.

## Commit sugerido

`test: add end-to-end courier simulation regression suite`
