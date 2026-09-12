# Paso 12 — Vistas Blade y Livewire

## Objetivo

Crear un dashboard responsive y funcional en `/courier` para iniciar y operar el turno mediante los mismos Services de la API. En este paso el mapa es un placeholder, no MapLibre.

## Puerta de entrada

- Paso 11 completo.
- API y Services realizan el flujo hasta aceptar un plan.

## Dependencias

- Instalar una versión de Livewire compatible con Laravel 12.
- Usar Alpine incluido/integrado con Livewire; evitar una segunda inicialización.
- Mantener Tailwind/Vite existente.
- No instalar frameworks SPA.

## Estructura de UI

Crear:

- layout Blade con Vite, Livewire styles/scripts, idioma español y viewport;
- ruta `GET /courier` con nombre estable;
- `CourierDashboard` como coordinador de página;
- componentes o secciones para métricas, recomendación, feed de ofertas y detalle;
- componentes Blade reutilizables para badge, métrica, botón y tarjeta.

Puede iniciarse con un componente Livewire principal y extraer hijos sólo si evita complejidad de sincronización.

## Funcionalidad mínima

- estado vacío con botón “Iniciar turno”;
- elegir escenario demo y seed;
- mostrar reloj simulado, tiempo restante y estado;
- pausar/reanudar/avanzar manualmente;
- mostrar al menos cinco tarjetas cuando estén disponibles;
- mostrar pago, pickup, distancia total, tiempo, neto, MXN/h, riesgo y score;
- resaltar ranking y `AI PICK` sin esconder alternativas;
- mostrar batch recomendado y secuencia;
- aceptar plan con loading, disabled y feedback de error/éxito;
- actualizar ganancias, posición lógica y pedidos al avanzar;
- polling de aproximadamente un segundo que invoque la sincronización idempotente del reloj y luego refresque estado.

No añadir todavía mapa real, eventos dinámicos, Reverb ni LLM.

## Diseño responsive

```text
Móvil: métricas → placeholder mapa → recomendación → ofertas
Desktop: métricas arriba → placeholder mapa izquierda → recomendación/ofertas derecha
```

- Una recomendación debe comprenderse en 2–5 segundos.
- Estados no dependen sólo de color.
- Botones con foco, labels y targets táctiles adecuados.
- Texto visible declara “Escenario simulado”.
- No presentar demand score/future value como dinero ganado.

## Estado Livewire

El componente conserva identificadores y datos serializables, no Models complejos. Cada acción vuelve a consultar estado autoritativo mediante `ShiftService`/`RecommendationService`. La UI nunca recalcula score, utilidad o ganancias. El polling llama `syncElapsedRealTime()`; no suma minutos en el navegador y no duplica avance si hay dos pestañas.

Usar `wire:key` estable en listas y manejar expiraciones sin saltos de identidad.

## Pruebas obligatorias

- `/courier` responde 200 y usa el layout correcto.
- Livewire inicia un run y carga sus dos shifts.
- Tick actualiza reloj/ofertas.
- Recomendación renderiza ranking y batch con cifras del Service.
- Accept cambia el estado y actualiza métricas.
- Acciones inválidas muestran error sin romper el componente.
- HTML contiene etiqueta de simulación y elementos accesibles básicos.
- `npm run build` y `php artisan test` completos.

## Criterios de aceptación

- El flujo actual puede demostrarse desde navegador sin usar Postman o Tinker.
- Mobile y desktop son utilizables.
- Polling mantiene el dashboard actualizado.
- API y Livewire muestran las mismas decisiones porque comparten Services.
- El área de mapa permanece explícitamente como placeholder.

## Commit sugerido

`feat: add responsive Livewire courier dashboard`
