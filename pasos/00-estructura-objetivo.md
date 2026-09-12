# Estructura objetivo de Courier AI

Este documento conserva el diseño acumulado. No autoriza crear todos los archivos de una vez: cada paso crea únicamente su parte.

## Principios inmutables

```text
Blade / Livewire
        ↓
Application Services
        ↓
Models / MySQL / OSRM / proveedor LLM
```

- Laravel 12 y PHP 8.2 o superior.
- Monolito Laravel; no React, Vue ni aplicación móvil separada.
- Blade presenta; Livewire coordina estado de interfaz.
- Controllers y Form Requests coordinan HTTP.
- Services contienen simulación, cálculos, scoring y optimización.
- Eloquent Models representan persistencia y relaciones, no algoritmos.
- Events notifican cambios y Jobs ejecutan trabajo asíncrono.
- El LLM explica una decisión ya calculada y nunca selecciona pedidos.
- MySQL 8 es la base principal. Dinero se persiste con `DECIMAL`, nunca `FLOAT`.

## Árbol objetivo

```text
app/
├── DTOs/
│   ├── Coordinates.php
│   ├── CourierState.php
│   ├── EnvironmentState.php
│   ├── EvaluatedOrder.php
│   ├── OptimizedPlan.php
│   └── RouteData.php
├── Enums/
│   ├── AgentType.php
│   ├── CourierStatus.php
│   ├── EventType.php
│   ├── OrderStatus.php
│   ├── Priority.php
│   ├── ReasonCode.php
│   └── ShiftStatus.php
├── Events/
├── Http/
│   ├── Controllers/Api/
│   ├── Requests/
│   └── Resources/
├── Jobs/
├── Livewire/
├── Models/
│   ├── SimulationRun.php
│   ├── Shift.php
│   ├── Order.php
│   ├── ShiftOrder.php
│   ├── Recommendation.php
│   ├── Delivery.php
│   ├── SimulationEvent.php
│   ├── DemandZone.php
│   ├── RouteCache.php
│   ├── BenchmarkRun.php
│   └── BenchmarkResult.php
├── Services/
│   ├── BaselineService.php
│   ├── BenchmarkService.php
│   ├── DemandService.php
│   ├── ExplanationService.php
│   ├── OptimizationService.php
│   ├── RecommendationService.php
│   ├── RoutingService.php
│   ├── ScoringService.php
│   ├── ScenarioService.php
│   ├── ShiftService.php
│   └── SimulatorService.php
└── Support/
    ├── Geo.php
    ├── Money.php
    └── Normalizer.php
config/
└── courier.php
database/
├── data/scenarios/
├── factories/
├── migrations/
└── seeders/
resources/
├── css/app.css
├── js/
│   ├── app.js
│   ├── echo.js
│   └── map.js
└── views/
    ├── components/
    ├── dashboard/
    ├── demo/
    ├── layouts/
    ├── livewire/
    └── results/
routes/
├── api.php
├── channels.php
└── web.php
tests/
├── Feature/
└── Unit/
```

Los nombres pueden simplificarse cuando dos clases no aporten separación real, pero nunca debe duplicarse lógica entre API y Livewire.

## Modelo persistente compartido

```text
SimulationRun (escenario + seed + reloj + entorno)
├── Shift COURIER_AI
├── Shift BASELINE
├── Orders (ofertas inmutables compartidas)
└── SimulationEvents (afectan al mismo escenario)

Shift
├── ShiftOrders (estado independiente de cada oferta por agente)
├── Recommendations
└── Deliveries
```

Separar `orders` de `shift_orders` evita que la aceptación del baseline altere el estado de Courier AI. Ambos ven las mismas ofertas y conservan decisiones independientes.

## Flujo funcional final

```text
Iniciar SimulationRun con escenario + seed
  → crear los dos shifts comparables
  → avanzar reloj virtual
  → publicar ofertas del escenario
  → obtener rutas OSRM o fallback
  → calcular métricas y score individual
  → generar singles y batches factibles
  → elegir plan de mayor utilidad
  → baseline elige mayor pago bruto factible
  → aceptar/ejecutar planes
  → actualizar posición, tiempo, costos y ganancias
  → aplicar eventos y reoptimizar
  → finalizar y comparar resultados reales
```

## Contratos que no deben romperse

- El score normalizado sirve para ranking visual; la utilidad absoluta decide el plan.
- Ganancia realizada y valor futuro estimado son campos separados.
- Las transiciones de estado se ejecutan dentro de transacciones y son idempotentes.
- Toda fecha funcional usa el reloj simulado, no `now()` directamente.
- Las coordenadas se manejan internamente como `lat, lon`; OSRM recibe `lon,lat` sólo en su adaptador.
- El routing devuelve siempre el mismo DTO, tanto con OSRM como con fallback.
- Todas las configuraciones que afectan resultados quedan guardadas en un snapshot para reproducibilidad.
- Ningún secreto se almacena en código, fixtures, respuestas API ni commits.

## Definición funcional final

La aplicación se considera completa únicamente cuando `/courier` permite ejecutar sin intervención técnica un turno reproducible, observar al menos cinco ofertas, obtener ranking y batch, aceptar una recomendación, reaccionar a un evento dinámico, terminar el turno y comparar Courier AI contra el baseline con cifras calculadas.
