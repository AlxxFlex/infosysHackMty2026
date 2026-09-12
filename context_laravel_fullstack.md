# context.md — Courier AI Agent · Full Laravel + MySQL Edition
## HackMTY 2026 · Infosys Challenge Track 3: “The Courier”

> **Documento maestro del proyecto.**  
> Este archivo debe servir como contexto compartido para el equipo, para asistentes de IA/Codex y como referencia funcional/técnica durante el hackatón.

---

# 1. Resumen ejecutivo

**Courier AI Agent** es una aplicación web standalone y responsive que simula una plataforma de reparto de comida y actúa como copiloto del repartidor.

Durante una jornada simulada aparecen múltiples solicitudes de entrega al mismo tiempo. Cada solicitud contiene información como:

- pago ofrecido;
- distancia hasta el restaurante;
- distancia restaurante → cliente;
- tiempo estimado;
- tiempo de espera estimado en restaurante;
- tráfico;
- zona de destino;
- surge/bonificación;
- restricciones de ruta;
- riesgo de retraso;
- posibilidad de combinarla con otros pedidos.

El objetivo del agente es responder, en segundos:

> **“De los pedidos disponibles, ¿cuáles conviene aceptar para maximizar la ganancia total del repartidor durante su turno?”**

La aplicación **no debe limitarse a ordenar pedidos por precio**. Debe calcular la utilidad económica real de cada pedido y considerar el efecto que aceptar un pedido tendrá sobre los siguientes minutos de la jornada.

Ejemplo visual:

- Pedido #1 — **RECOMENDADO**
- Pedido #4 — **RECOMENDADO**
- Pedido #3 — prioridad media
- Pedido #2 — prioridad baja
- Pedido #5 — evitar

Y debe poder explicar:

> “Recomiendo los pedidos 1 y 4 porque juntos generan $164 MXN netos estimados en 42 minutos, comparten parte de la ruta, requieren 4.1 km menos que atenderlos por separado y terminan en una zona con alta probabilidad de recibir nuevos pedidos.”

La decisión debe ser **explicable, reproducible y sustentada por matemáticas/optimización**, no únicamente por una respuesta de un LLM.

---

# 2. Relación con el reto oficial

El reto oficial plantea que el repartidor recibe un flujo continuo de pedidos y dispone de pocos segundos para aceptar o rechazar. Una mala elección consume gasolina y tiempo; durante tráfico, lluvia o surge, la decisión cambia constantemente.

El desafío oficial es construir un agente capaz de:

1. aceptar o rechazar pedidos;
2. considerar pago, distancia y tiempo;
3. agrupar entregas cercanas;
4. planear rutas eficientes;
5. reaccionar a tráfico, surge y cierres;
6. maximizar ganancias durante un turno limitado;
7. justificar sus decisiones;
8. compararse contra un agente baseline sencillo.

La demo ideal del reto consiste en ejecutar **dos agentes sobre el mismo turno simulado**, mostrando sus ganancias en tiempo real y provocando un evento inesperado —por ejemplo surge o cierre de una calle— para observar cómo reaccionan.

---

# 3. Nombre provisional

**Courier AI**

Alternativas:

- Courier Copilot
- RiderMax
- SmartCourier
- RouteWise
- CourierBrain
- DriverSide AI
- ProfitRoute

Nombre recomendado para el hackatón:

> **Courier AI — Driver-Side Optimization Agent**

Tagline:

> **“Not the highest-paying order. The highest-value decision.”**

---

# 4. Problema

Las apps tradicionales muestran pedidos individualmente y optimizan principalmente el marketplace de la plataforma.

El repartidor necesita optimizar **su propio beneficio**.

Un pedido de $120 MXN puede ser peor que uno de $80 MXN si:

- está muy lejos del punto actual;
- requiere atravesar una zona congestionada;
- tiene larga espera en restaurante;
- termina en una zona sin demanda;
- impide tomar dos pedidos compatibles;
- consume demasiado tiempo de turno;
- tiene alta probabilidad de retraso.

Por ello debemos optimizar:

```text
ganancia económica
+ eficiencia temporal
+ eficiencia de ruta
+ valor de la posición final
+ oportunidad de batching
- combustible/costo operativo
- tiempo improductivo
- riesgo de retraso
- penalizaciones de seguridad/restricciones
```

---

# 5. Hipótesis del producto

Si un repartidor recibe varias ofertas simultáneas y una herramienta calcula:

- utilidad neta;
- ingreso por hora;
- costo por kilómetro;
- costo de llegar al pickup;
- duración real;
- compatibilidad entre pedidos;
- demanda esperada en el destino;
- riesgo operacional;

entonces puede tomar decisiones más rentables que con una estrategia sencilla como:

- aceptar el pedido con mayor pago;
- aceptar el pedido más cercano;
- aceptar siempre;
- aceptar el de mejor MXN/km sin considerar el futuro.

---

# 6. Objetivo principal

Maximizar:

> **Ganancia neta acumulada del repartidor dentro de un turno de duración limitada.**

No maximizar únicamente:

- número de pedidos;
- pago bruto;
- distancia recorrida;
- velocidad.

---

# 7. Objetivos secundarios

- Minimizar kilómetros improductivos.
- Minimizar tiempo sin pedido.
- Evitar decisiones que causen retrasos.
- Aprovechar pedidos compatibles.
- Posicionar al repartidor cerca de zonas de alta demanda.
- Reaccionar a eventos dinámicos.
- Explicar cada recomendación en lenguaje sencillo.
- Mostrar visualmente el efecto de cada decisión.

---

# 8. No objetivos del MVP

Para no sobrecargar el hackatón, el MVP **no necesita**:

- conectarse realmente a Uber, DiDi o Rappi;
- aceptar pedidos reales automáticamente;
- procesar pagos;
- navegar un vehículo real;
- obtener telemetría real;
- implementar un marketplace completo;
- entrenar un modelo de lenguaje;
- hacer predicción de demanda con millones de registros.

Todo puede funcionar sobre un **turno simulado reproducible**.

---

# 9. Persona principal

## Repartidor

Necesidades:

- decidir rápido;
- saber cuánto ganará realmente;
- evitar viajes poco rentables;
- minimizar gasto operativo;
- saber por qué una opción es mejor;
- entender cambios por tráfico/surge;
- terminar el turno con mayor ganancia.

La interfaz debe permitir entender una recomendación en aproximadamente **2–5 segundos**.

---

# 10. Flujo de usuario

1. El repartidor inicia un turno.
2. La app coloca su vehículo en una ubicación del mapa.
3. El simulador genera solicitudes.
4. Las solicitudes aparecen como tarjetas.
5. Laravel calcula rutas y tiempos mediante sus Services.
6. El motor de optimización evalúa cada pedido.
7. El agente calcula combinaciones posibles.
8. La app marca los mejores pedidos.
9. El usuario puede abrir **“¿Por qué?”**.
10. Se acepta una decisión.
11. La simulación avanza.
12. Cambian tráfico, demanda y surge.
13. Aparecen nuevos pedidos.
14. El agente recalcula.
15. Al terminar el turno se comparan:
    - ganancias;
    - kilómetros;
    - pedidos;
    - tiempo;
    - utilidad por hora;
    - decisiones acertadas.

---

# 11. Arquitectura conceptual

El proyecto será un **monolito Laravel**: la misma aplicación sirve las vistas del repartidor, expone la API, ejecuta el simulador, calcula recomendaciones, persiste datos y publica eventos en tiempo real.

```text
┌──────────────────────────────────────────────┐
│                Browser / PWA                 │
│     Vista responsive del repartidor          │
│                                              │
│ Blade + Livewire + Alpine.js + MapLibre GL   │
└───────────────────────┬──────────────────────┘
                        │
                        │ HTTP / Livewire /
                        │ WebSocket
                        ▼
┌──────────────────────────────────────────────┐
│                 LARAVEL APP                  │
│                                              │
│ routes/web.php      routes/api.php           │
│ Blade / Livewire    REST API                 │
│ Controllers         Form Requests            │
│ Events / Reverb     Jobs / Queues            │
│                                              │
│ ScoringService                              │
│ OptimizationService                         │
│ SimulatorService                            │
│ RoutingService                              │
│ DemandService                               │
│ ExplanationService                          │
└───────────────┬───────────────┬──────────────┘
                │               │
                ▼               ▼
          MySQL 8.x       External Services
                          ├── OSRM / OSM
                          └── LLM (optional)
```

## Principio arquitectónico

Aunque todo está dentro de Laravel, se mantienen responsabilidades separadas:

```text
Views / Livewire
      ↓
Application Services
      ↓
Domain calculations
      ↓
Eloquent / MySQL / External APIs
```

Las vistas **no calculan el ranking**.  
Los Controllers **no contienen fórmulas de optimización**.  
Los Models **no deben convertirse en servicios gigantes**.

Toda la lógica del agente vive en clases dedicadas de `app/Services` y, cuando convenga, en objetos de dominio/DTOs.

## Dos superficies de Laravel

### Web

```text
routes/web.php
```

Sirve:

- dashboard del repartidor;
- mapa;
- tarjetas de pedidos;
- panel “¿Por qué?”;
- resultados del turno;
- panel de demo/admin.

### API

```text
routes/api.php
```

Sirve:

- endpoints del simulador;
- recomendaciones en JSON;
- pruebas automatizadas;
- futuras integraciones;
- consumo interno por JavaScript cuando sea conveniente.

Ambas superficies llaman a **los mismos Services**, evitando duplicar lógica.


---

# 12. Principio clave de diseño

## El LLM NO decide qué pedido es matemáticamente óptimo

La decisión principal debe provenir del motor cuantitativo.

El LLM se utiliza principalmente para:

- explicar resultados;
- responder preguntas del juez;
- resumir razones;
- convertir métricas en lenguaje humano;
- eventualmente modificar preferencias mediante lenguaje natural.

Ejemplo:

```text
Optimization Engine
    ↓
{
  "recommended": ["ORD-001", "ORD-004"],
  "score": 91.2,
  "expected_profit": 164,
  "expected_minutes": 42,
  "reasons": [
      "high_hourly_profit",
      "shared_route",
      "high_demand_destination"
  ]
}
    ↓
LLM
    ↓
“Te conviene aceptar los pedidos 1 y 4...”
```

Esto ofrece:

- explicabilidad;
- reproducibilidad;
- menor latencia;
- menor costo;
- menor riesgo de alucinación;
- mejor defensa técnica ante jueces.

---

# 13. Stack recomendado

## Vistas del repartidor

**Laravel Blade + Livewire + Alpine.js**

La interfaz también se construirá dentro del mismo proyecto Laravel.

### Blade

Se usa para:

- layouts;
- vistas;
- componentes visuales;
- páginas de resultados;
- estructura HTML del dashboard.

### Livewire

Se usa para:

- actualizar lista de pedidos;
- aceptar/rechazar planes;
- actualizar contadores;
- abrir paneles de detalle;
- reaccionar al estado del turno sin crear una SPA separada;
- mantener una experiencia cercana a una aplicación.

### Alpine.js

Se usa para interacciones pequeñas del lado del navegador:

- modales;
- tabs;
- paneles expandibles;
- animaciones simples;
- estado visual local.

### MapLibre GL JS + OpenStreetMap

Se usa para:

- mapa;
- markers;
- rutas;
- zonas de surge;
- bloqueos;
- posición del repartidor.

Mapa recomendado:

```text
MapLibre GL JS
+
OpenStreetMap
```

La aplicación debe ser **responsive/mobile-first** para que un juez pueda abrirla en laptop o teléfono desde el navegador.

### PWA opcional

Si sobra tiempo, la aplicación Laravel puede configurarse como PWA para:

- instalarse desde el navegador;
- abrirse a pantalla completa;
- parecer una app standalone;
- cachear algunos assets.

Esto es un bonus. El MVP no depende de PWA.


---

## Aplicación Laravel

**Laravel + PHP**

Laravel será **la aplicación completa** y concentrará:

- vistas web del repartidor mediante Blade/Livewire;
- API REST dentro del mismo proyecto;
- estado del turno y pedidos;
- simulador;
- motor de scoring;
- evaluación de combinaciones;
- integración con OSRM;
- eventos en tiempo real;
- persistencia;
- integración con el LLM;
- logs de decisiones y métricas.

Razones:

- el equipo puede trabajar sobre una arquitectura MVC/Service clara;
- Eloquent simplifica persistencia;
- `Http` facilita llamadas a OSRM y al proveedor de IA;
- Events/Broadcasting permiten actualizar las vistas en tiempo real;
- Jobs/Queues permiten mover cálculos no críticos fuera de la petición principal;
- PHPUnit/Pest permiten probar las fórmulas y reglas de negocio;
- Laravel sirve las vistas, la API y la lógica del agente desde una sola aplicación.

---

## Optimización

### MVP recomendado: optimizador nativo en PHP

Para 5–10 ofertas simultáneas y un máximo de 2 pedidos concurrentes, **no necesitamos un solver externo para la primera versión**.

La aplicación Laravel puede:

1. calcular métricas individuales;
2. generar combinaciones de pedidos;
3. generar las secuencias válidas de pickup/dropoff;
4. consultar la matriz de tiempos/distancias;
5. descartar secuencias inviables;
6. calcular utilidad;
7. seleccionar el plan con mayor utilidad.

Ejemplo con dos pedidos A y B:

```text
A_pickup → A_dropoff → B_pickup → B_dropoff
A_pickup → B_pickup → A_dropoff → B_dropoff
A_pickup → B_pickup → B_dropoff → A_dropoff
B_pickup → ...
```

Sólo se consideran secuencias donde cada pickup ocurra antes que su delivery.

Esta estrategia es suficientemente pequeña, rápida y explicable para el hackatón.

### OR-Tools como mejora opcional

El documento del reto menciona Google OR-Tools como recurso. Si después necesitamos resolver instancias mucho mayores, Laravel puede llamar a un pequeño servicio de optimización separado. **No es requisito para el MVP** y Laravel sigue siendo la aplicación principal.

---

## Routing

### MVP recomendado
**OSRM HTTP API + OpenStreetMap**

Laravel consulta OSRM usando su cliente HTTP.

Funciones útiles:

- `route`: ruta entre puntos;
- `table`: matriz de tiempos/distancias;
- `nearest`: ajustar coordenadas a calle;
- `trip`: ordenar varios puntos cuando aplique.

Para el hackatón, OSRM evita tener que ejecutar lógica geoespacial compleja dentro de PHP.

---

## Base de datos

**MySQL 8.x**

MySQL será la base de datos principal de la aplicación Laravel.

Razones:

- integración nativa y madura con Laravel/Eloquent;
- excelente para persistir turnos, pedidos, eventos y recomendaciones;
- sencillo de instalar localmente o ejecutar con Docker;
- fácil de compartir entre integrantes del equipo;
- permite índices, relaciones, transacciones y consultas agregadas para métricas del demo.

No necesitamos PostGIS para el MVP porque el routing geográfico se delega a OSRM/OpenStreetMap. Las coordenadas pueden almacenarse como `DECIMAL` o, si después se requiere, usando tipos espaciales de MySQL.

Configuración recomendada:

```text
MySQL 8.x
database: courier_ai
charset: utf8mb4
collation: utf8mb4_unicode_ci
```

---

# 13.1 Sesión y autenticación

Para la demo no es obligatorio implementar un sistema complejo de cuentas.

MVP:

```text
un repartidor simulado
+
un turno activo
```

Puede guardarse el `shift_id` activo en sesión:

```php
session(['active_shift_id' => $shift->id]);
```

Si se requiere login:

- Laravel Breeze/fortify es opcional;
- no invertir tiempo en auth antes de tener el simulador funcionando.

La prioridad es:

```text
turno
→ pedidos
→ recomendación
→ aceptación
→ resultado
```


---

## IA

Opciones:

- Gemini;
- Gemma;
- Ollama + modelo local;
- cualquier LLM disponible en el hackatón.

El sistema debe seguir funcionando aunque el LLM no esté disponible, porque el ranking es determinista.

---

# 14. Estructura del sistema

Un solo repositorio y una sola aplicación Laravel:

```text
courier-ai/
│
├── app/
│   ├── DTOs/
│   │   ├── CourierState.php
│   │   ├── EnvironmentState.php
│   │   ├── EvaluatedOrder.php
│   │   └── OptimizedPlan.php
│   │
│   ├── Enums/
│   │   ├── OrderStatus.php
│   │   ├── ShiftStatus.php
│   │   ├── Priority.php
│   │   └── ReasonCode.php
│   │
│   ├── Events/
│   │   ├── NewOrderAvailable.php
│   │   ├── RankingUpdated.php
│   │   ├── SurgeStarted.php
│   │   ├── RoadClosed.php
│   │   └── DeliveryCompleted.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── DashboardController.php
│   │   │   ├── ShiftController.php
│   │   │   ├── SimulationController.php
│   │   │   ├── OrderController.php
│   │   │   ├── RecommendationController.php
│   │   │   └── PlanController.php
│   │   ├── Requests/
│   │   └── Resources/
│   │
│   ├── Jobs/
│   │   ├── GenerateExplanation.php
│   │   └── RunBenchmark.php
│   │
│   ├── Livewire/
│   │   ├── CourierDashboard.php
│   │   ├── OrderFeed.php
│   │   ├── RecommendationPanel.php
│   │   ├── ShiftMetrics.php
│   │   ├── WhyPanel.php
│   │   ├── ResultsComparison.php
│   │   └── DemoControlPanel.php
│   │
│   ├── Models/
│   │   ├── Shift.php
│   │   ├── Order.php
│   │   ├── Recommendation.php
│   │   ├── Delivery.php
│   │   └── SimulationEvent.php
│   │
│   ├── Services/
│   │   ├── RoutingService.php
│   │   ├── ScoringService.php
│   │   ├── OptimizationService.php
│   │   ├── SimulatorService.php
│   │   ├── DemandService.php
│   │   ├── ExplanationService.php
│   │   ├── BenchmarkService.php
│   │   └── ShiftService.php
│   │
│   └── Support/
│       ├── Geo.php
│       ├── Normalizer.php
│       └── Money.php
│
├── bootstrap/
├── config/
│   └── courier.php
│
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── DemoScenarioSeeder.php
│       └── DemandZoneSeeder.php
│
├── public/
│
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   ├── app.js
│   │   ├── map.js
│   │   └── echo.js
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php
│       ├── dashboard/
│       │   └── index.blade.php
│       ├── results/
│       │   └── show.blade.php
│       ├── demo/
│       │   └── control.blade.php
│       ├── components/
│       └── livewire/
│           ├── courier-dashboard.blade.php
│           ├── order-feed.blade.php
│           ├── recommendation-panel.blade.php
│           ├── shift-metrics.blade.php
│           ├── why-panel.blade.php
│           └── results-comparison.blade.php
│
├── routes/
│   ├── web.php
│   ├── api.php
│   └── channels.php
│
├── storage/
├── tests/
│   ├── Feature/
│   └── Unit/
│
├── .env.example
├── artisan
├── composer.json
├── package.json
├── vite.config.js
├── context.md
└── README.md
```

## Regla de estructura

```text
Blade/Livewire = presentación
Controllers = coordinación HTTP
Services = lógica del negocio/agente
Models = persistencia
Events = actualización en tiempo real
Jobs = trabajo asíncrono
```


---

# 15. Modelo de datos

## Order

```json
{
  "id": "ORD-001",
  "restaurant": {
    "id": "REST-12",
    "name": "Burger House",
    "lat": 25.6710,
    "lon": -100.3090
  },
  "customer": {
    "lat": 25.6860,
    "lon": -100.2960
  },
  "base_pay_mxn": 78.0,
  "surge_bonus_mxn": 20.0,
  "estimated_restaurant_wait_min": 5,
  "created_at": "2026-09-12T19:12:00",
  "expires_in_seconds": 25,
  "pickup_deadline": "2026-09-12T19:28:00",
  "delivery_deadline": "2026-09-12T19:50:00"
}
```

---

## CourierState

```json
{
  "courier_id": "SIM-001",
  "lat": 25.675,
  "lon": -100.310,
  "vehicle": "motorcycle",
  "shift_remaining_min": 132,
  "current_orders": [],
  "max_concurrent_orders": 2,
  "fuel_cost_per_km_mxn": 1.25
}
```

---

## EnvironmentState

```json
{
  "simulation_time": "2026-09-12T19:12:00",
  "weather": "clear",
  "traffic_factor": 1.20,
  "surge_zones": [
    {
      "zone": "centro",
      "multiplier": 1.3
    }
  ],
  "road_closures": []
}
```

---

## EvaluatedOrder

```json
{
  "order_id": "ORD-001",
  "gross_pay": 98.0,
  "distance_to_pickup_km": 1.1,
  "delivery_distance_km": 4.8,
  "total_distance_km": 5.9,
  "travel_time_min": 18.0,
  "restaurant_wait_min": 5.0,
  "total_time_min": 23.0,
  "operating_cost_mxn": 7.38,
  "net_profit_mxn": 90.62,
  "net_mxn_per_hour": 236.4,
  "destination_demand_score": 0.82,
  "lateness_risk": 0.08,
  "batch_compatibility": 0.73,
  "score": 91.2,
  "priority": "high"
}
```

---

# 16. Matemática base

## 16.1 Pago bruto

Para un pedido \(i\):

```text
GrossPay_i = BasePay_i + SurgeBonus_i + OtherBonus_i
```

---

## 16.2 Distancia total

```text
D_i =
    Distance(current_position, restaurant_i)
  + Distance(restaurant_i, customer_i)
```

Definimos:

```text
DeadheadDistance_i =
    Distance(current_position, restaurant_i)
```

La distancia hasta el restaurante es especialmente importante porque normalmente es recorrido todavía no remunerado de forma directa.

---

# 17. Tiempo real estimado

```text
T_i =
    TimeToPickup_i
  + RestaurantWait_i
  + DeliveryTravelTime_i
```

Con tráfico:

```text
AdjustedTravelTime = BaseTravelTime × TrafficFactor
```

Ejemplo:

```text
BaseTravelTime = 16 min
TrafficFactor = 1.30

AdjustedTravelTime = 20.8 min
```

---

# 18. Costo operativo

MVP:

```text
OperatingCost_i = TotalDistance_i × CostPerKm
```

Ejemplo:

```text
5.9 km × $1.25 MXN/km = $7.38 MXN
```

Este costo puede representar una combinación simplificada de:

- gasolina;
- mantenimiento;
- desgaste;
- depreciación.

Para la demo conviene que `CostPerKm` sea configurable.

---

# 19. Ganancia neta

```text
NetProfit_i = GrossPay_i - OperatingCost_i
```

---

# 20. Ganancia neta por hora

Una de las métricas más importantes:

```text
NetHourlyRate_i =
    NetProfit_i / TotalTime_i × 60
```

Ejemplo:

```text
Pago bruto:          $98.00
Costo:                $7.38
Ganancia neta:       $90.62
Tiempo:               23 min

NetHourlyRate =
90.62 / 23 × 60
= $236.40 MXN/h
```

Esto permite comparar pedidos de diferente duración.

---

# 21. Opportunity Cost

Un pedido no solamente cuesta kilómetros.

También bloquea al repartidor durante cierto tiempo.

Si el repartidor podría ganar normalmente:

```text
ExpectedMarketRate = $180 MXN/h
```

y una entrega consume:

```text
30 minutos
```

su costo de oportunidad aproximado es:

```text
OpportunityCost =
180 × 30/60
= $90 MXN
```

No necesariamente lo restaremos literalmente del beneficio; puede utilizarse como señal dentro del score.

---

# 22. Valor de la posición final

Dos pedidos con la misma utilidad inmediata pueden ser diferentes si terminan en zonas distintas.

Definimos:

```text
DestinationValue_i ∈ [0,1]
```

Ejemplo:

```text
0.90 = zona con alta demanda esperada
0.50 = demanda normal
0.10 = zona de baja demanda
```

Podemos estimarla usando una tabla simulada:

```text
ZoneDemand(zone, time_bucket)
```

Ejemplo:

| Zona | 18–19 | 19–20 | 20–21 |
|---|---:|---:|---:|
| Centro | 0.70 | 0.95 | 0.85 |
| Tec | 0.75 | 0.88 | 0.70 |
| San Pedro | 0.60 | 0.83 | 0.90 |
| Periferia | 0.30 | 0.25 | 0.20 |

No necesitamos ML para el MVP. Puede ser una matriz histórica/simulada.

---

# 23. Riesgo de retraso

```text
Slack_i =
DeliveryDeadline_i - PredictedDeliveryTime_i
```

Mientras menor sea el margen, mayor el riesgo.

Una aproximación:

```text
LatenessRisk_i = clamp(
    1 - Slack_i / SAFE_SLACK_MIN,
    0,
    1
)
```

Si además existe tráfico alto, lluvia o cierre:

```text
Risk_i =
    w_traffic × TrafficRisk
  + w_deadline × LatenessRisk
  + w_weather × WeatherRisk
  + w_closure × ClosureRisk
```

---

# 24. Compatibilidad entre dos pedidos

Pedidos `i` y `j` son compatibles cuando:

- restaurantes cercanos;
- destinos en dirección similar;
- ventanas de tiempo compatibles;
- desvío pequeño;
- capacidad del repartidor suficiente.

Podemos medir:

```text
Compatibility(i,j) ∈ [0,1]
```

Una fórmula inicial:

```text
Compatibility =
0.25 × PickupProximity
+ 0.35 × RouteOverlap
+ 0.25 × DestinationAlignment
+ 0.15 × TimeWindowCompatibility
```

---

# 25. Detour Ratio

Para saber si conviene combinar pedidos:

```text
DetourRatio =
BatchedRouteTime /
SeparateRouteTime
```

Ejemplo:

```text
por separado = 52 min
batch = 39 min

DetourRatio = 39 / 52 = 0.75
```

Muy favorable.

Otra forma útil:

```text
TimeSaved = SeparateRouteTime - BatchedRouteTime
```

---

# 26. Score individual

Todos los componentes se normalizan a `[0,1]`.

Definimos:

```text
Score_i = 100 × (
    0.30 × HourlyProfitNorm_i
  + 0.20 × NetProfitNorm_i
  + 0.15 × DestinationValue_i
  + 0.15 × BatchPotential_i
  + 0.10 × PickupEfficiency_i
  + 0.10 × Reliability_i
)
```

donde:

```text
PickupEfficiency = 1 - NormalizedDeadheadDistance
Reliability      = 1 - Risk
```

Pesos iniciales:

| Variable | Peso |
|---|---:|
| ganancia/hora | 30% |
| ganancia neta | 20% |
| valor destino | 15% |
| batching | 15% |
| eficiencia pickup | 10% |
| confiabilidad | 10% |

**Los pesos deben estar configurados en un archivo**, no hardcodeados.

---

# 27. Normalización

Para comparar las ofertas presentes en un mismo instante:

```text
norm(x_i) =
(x_i - min(x)) /
(max(x) - min(x))
```

Si:

```text
max(x) == min(x)
```

usar:

```text
norm(x_i) = 0.5
```

Esto produce un ranking relativo entre ofertas visibles.

---

# 28. Penalizaciones duras

Algunas condiciones no deben resolverse con un score bajo: deben invalidar la opción.

Ejemplos:

```text
if predicted_delivery > hard_deadline:
    feasible = false

if route crosses_closed_road and no_alternative:
    feasible = false

if active_orders >= max_concurrent_orders:
    cannot_accept_more = true

if total_time > shift_remaining:
    feasible = false
```

---

# 29. Optimización de conjuntos

El verdadero problema no es:

> “¿Cuál pedido tiene mejor score?”

Sino:

> “¿Qué conjunto de pedidos maximiza la utilidad total bajo restricciones?”

Variable binaria:

```text
x_i = 1  si aceptamos pedido i
x_i = 0  si lo rechazamos
```

Objetivo simplificado:

```text
MAX:

Σ x_i × NetProfit_i
+ Σ x_i × DestinationFutureValue_i
+ Σ BatchBonus_ij × y_ij
- Σ RiskPenalty_i × x_i
- Σ LatePenalty_i
```

Sujeto a:

```text
Σ RequiredTime_i × x_i <= ShiftRemaining

ConcurrentOrders <= Capacity

pickup_i ocurre antes que delivery_i

DeliveryTime_i <= DeliveryDeadline_i

rutas cerradas no pueden utilizarse
```

---

# 30. Enfoque práctico para el hackatón

No resolver un gigantesco VRP cada segundo.

Con solamente 5–10 ofertas simultáneas podemos:

1. evaluar cada pedido;
2. generar combinaciones:
   - individuales;
   - pares;
   - opcionalmente tríos;
3. obtener matrices de tiempos;
4. descartar combinaciones inviables;
5. enumerar las secuencias válidas de pickup/dropoff en PHP;
6. calcular utilidad total de cada secuencia;
7. elegir la mejor.

Para el tamaño del demo esto se puede resolver directamente en Laravel sin un solver externo.

Con 5 pedidos:

```text
individuales: 5
pares:        10
tríos:        10
```

Total manejable.

---

# 31. Función de utilidad de una combinación

Para conjunto \(S\):

```text
Utility(S) =
ExpectedNetProfit(S)
+ FuturePositionValue(S)
+ BatchEfficiencyBonus(S)
- RiskPenalty(S)
- IdlePenalty(S)
```

Una versión monetizada facilita la explicación:

```text
ExpectedValue(S) =
GrossPay
- OperatingCost
- ExpectedDelayCost
+ EstimatedFutureZoneValue
```

Y una métrica operacional:

```text
ProfitRate(S) =
ExpectedNetProfit(S) / ExpectedMinutes(S) × 60
```

---

# 32. Dos niveles de decisión

## Nivel 1 — Ranking rápido

Se calcula en milisegundos.

Salida:

```text
#1 ORD-001 score 91
#2 ORD-004 score 87
#3 ORD-003 score 66
#4 ORD-002 score 42
#5 ORD-005 score 31
```

## Nivel 2 — Optimización

Evalúa:

```text
{1}
{4}
{1,4}
{1,3}
{3,4}
...
```

Puede descubrir:

> ORD-001 + ORD-004 es mejor que tomar únicamente el pedido con mayor score individual.

---

# 33. Baseline

Es obligatorio demostrar que nuestro agente realmente mejora.

Usar un baseline sencillo y fácil de explicar.

## Baseline recomendado

```text
Greedy Highest Gross Pay
```

Regla:

> De todas las ofertas disponibles, aceptar la de mayor pago bruto que sea factible.

No considera:

- costo;
- tiempo;
- destino;
- batching;
- tráfico futuro.

---

# 34. Agente inteligente

**Courier AI Agent**

Evalúa:

- ingreso neto;
- ingreso/hora;
- pickup;
- ruta;
- tráfico;
- espera;
- destination value;
- batching;
- ventanas;
- turno restante;
- eventos.

La demo ejecutará ambos sobre **exactamente el mismo stream de pedidos**.

---

# 35. Métrica principal de evaluación

```text
ImprovementPercent =
(AgentNetProfit - BaselineNetProfit)
/
BaselineNetProfit
× 100
```

Ejemplo:

```text
Baseline = $512
Courier AI = $624

Improvement = 21.9%
```

Este número debe aparecer grande en la pantalla final.

---

# 36. Métricas secundarias

Mostrar:

- ganancia bruta;
- ganancia neta;
- MXN/hora;
- kilómetros totales;
- kilómetros improductivos;
- minutos activos;
- minutos sin pedido;
- pedidos completados;
- pedidos rechazados;
- retrasos;
- distancia por entrega;
- costo operativo;
- porcentaje de tiempo productivo.

---

# 37. Simulador

El simulador es una de las piezas más importantes.

Debe tener reloj virtual:

```text
18:00
18:01
18:02
...
```

No hace falta esperar tiempo real.

Ejemplo:

```text
1 segundo real = 1 minuto simulado
```

Configuración:

```json
{
  "real_seconds_per_sim_minute": 1
}
```

---

# 38. Generación de pedidos

Un pedido contiene:

```text
spawn_time
restaurant
customer
base_pay
surge
restaurant_wait
expiration
deadlines
```

Los pedidos pueden venir de:

1. escenarios JSON preparados;
2. generador aleatorio reproducible.

Siempre usar seed:

```text
seed = 42
```

Así:

- baseline;
- agente inteligente;

reciben exactamente el mismo turno.

---

# 39. Generador de ofertas

Idea:

```text
number_of_orders ~ Poisson(lambda_by_zone_and_time)

payment =
base_fee
+ distance_component
+ random_component
+ surge
```

No es necesario que la simulación copie exactamente a una plataforma comercial. Lo importante es que sea coherente.

---

# 40. Escenarios de demo

## Scenario A — Normal

- tráfico normal;
- cinco pedidos;
- algunos pedidos aparentemente atractivos;
- batching posible.

## Scenario B — Surge

A mitad del turno:

```text
Zone Tec:
surge 1.0 → 1.5
```

El agente debe recalcular y valorar mejor terminar cerca de Tec.

## Scenario C — Road closure

Una calle/ruta deja de estar disponible.

Se incrementa el tiempo de ciertos pedidos.

El ranking cambia en tiempo real.

## Scenario D — Traffic spike

```text
trafficFactor 1.10 → 1.65
```

## Scenario E — Restaurant delay

El restaurante de un pedido pasa:

```text
wait = 4 min → 17 min
```

El agente debe bajar su prioridad.

---

# 41. Estado de una solicitud

```text
AVAILABLE
RECOMMENDED
ACCEPTED
PICKING_UP
PICKED_UP
DELIVERING
DELIVERED
EXPIRED
REJECTED
```

---

# 42. UI principal

Pantalla principal:

```text
┌────────────────────────────────────┐
│  Courier AI          $236 MXN/h    │
├────────────────────────────────────┤
│                                    │
│              MAPA                  │
│                                    │
│   YOU ●                            │
│       ───── REST A ─── CUSTOMER    │
│                                    │
├────────────────────────────────────┤
│ AI Recommendation                  │
│                                    │
│ ★ #1 Pedido 01     SCORE 91        │
│   $98 · 5.9 km · 23 min            │
│   $236/h                            │
│   [WHY?] [SELECT]                  │
│                                    │
│ ★ #2 Pedido 04     SCORE 87        │
│                                    │
│   #3 Pedido 03     SCORE 66        │
└────────────────────────────────────┘
```

---


## Ejemplo Blade del dashboard

```blade
<div class="courier-dashboard">
    <section class="metrics">
        <livewire:shift-metrics :shift-id="$shiftId" />
    </section>

    <section class="map-panel">
        <div
            id="courier-map"
            data-shift-id="{{ $shiftId }}"
        ></div>
    </section>

    <aside class="orders-panel">
        <livewire:recommendation-panel
            :shift-id="$shiftId"
        />

        <livewire:order-feed
            :shift-id="$shiftId"
        />
    </aside>
</div>
```

El mapa se controla con JavaScript/MapLibre, mientras Livewire controla el estado de negocio.


# 42.1 Arquitectura de las vistas Laravel

La vista principal será una página Laravel responsive:

```text
GET /courier
    ↓
CourierDashboard (Livewire)
    ├── Map
    ├── ShiftMetrics
    ├── OrderFeed
    ├── RecommendationPanel
    └── WhyPanel
```

## Flujo de render

```text
Browser
   ↓
Blade layout
   ↓
Livewire component
   ↓
ShiftService / OptimizationService
   ↓
MySQL + OSRM
   ↓
Livewire state update
   ↓
DOM update
```

## Componentes Livewire principales

### `CourierDashboard`

Responsable de:

- identificar el turno actual;
- coordinar el estado visual;
- escuchar eventos;
- refrescar métricas;
- pasar datos a componentes hijos.

### `OrderFeed`

Responsable de:

- mostrar pedidos disponibles;
- ordenar por prioridad;
- resaltar `AI PICK`;
- mostrar expiración;
- permitir inspeccionar un pedido.

### `RecommendationPanel`

Responsable de:

- mostrar plan recomendado;
- mostrar pedidos seleccionados;
- utilidad;
- ganancia/hora;
- botones de aceptar/rechazar.

### `WhyPanel`

Responsable de:

- mostrar razones;
- mostrar métricas;
- comparar contra la mejor alternativa;
- mostrar explicación LLM si está disponible.

### `ShiftMetrics`

Responsable de:

- ganancia acumulada;
- MXN/hora;
- kilómetros;
- tiempo restante;
- pedidos completados.

### `DemoControlPanel`

Sólo para la demo:

- start;
- pause;
- speed;
- surge;
- road closure;
- restaurant delay;
- reset;
- new seed.

## Estado en Livewire

Ejemplo conceptual:

```php
class CourierDashboard extends Component
{
    public ?int $shiftId = null;
    public array $orders = [];
    public array $ranking = [];
    public ?array $recommendedPlan = null;
    public array $metrics = [];

    public function mount(ShiftService $shiftService): void
    {
        $this->loadState($shiftService);
    }

    public function refreshState(ShiftService $shiftService): void
    {
        $this->loadState($shiftService);
    }

    private function loadState(ShiftService $shiftService): void
    {
        $state = $shiftService->getDashboardState($this->shiftId);

        $this->orders = $state['orders'];
        $this->ranking = $state['ranking'];
        $this->recommendedPlan = $state['recommended_plan'];
        $this->metrics = $state['metrics'];
    }
}
```

La UI nunca recalcula scores. Sólo presenta el estado producido por los Services.


---

# 43. Prioridad visual

## Alta

- tarjeta destacada;
- borde verde;
- estrella;
- etiqueta `AI PICK`;
- ruta visible.

## Media

- tarjeta normal.

## Baja

- menor contraste;
- puede colapsarse.

No esconder opciones: el objetivo es asistir, no manipular.

---

# 44. Pantalla “Why?”

Ejemplo:

```text
WHY ORDER #01?

Expected net profit
+$90.62

Profit rate
$236.40 MXN/hour

Pickup distance
1.1 km

Destination demand
High (82%)

Delay risk
Low (8%)

AI SCORE
91 / 100
```

Texto:

> “Tiene un pickup corto, la mejor ganancia por hora del grupo y termina en una zona con demanda alta.”

---

# 45. Explicación del batch

```text
AI RECOMMENDS: #01 + #04

Combined pay        $178
Operating cost       -$14
Expected net         $164
Combined time         42m

Separate time         51m
Time saved             9m

Route overlap          76%
Late risk               9%
```

Mensaje:

> “Los pedidos comparten dirección y pueden completarse con sólo 7 minutos adicionales respecto al #1 solo.”

---

# 46. Colores del mapa

- repartidor: azul;
- restaurante: naranja;
- cliente: violeta;
- ruta recomendada: verde;
- ruta secundaria: gris;
- tráfico alto: rojo;
- surge zone: overlay amarillo/verde;
- cierre: rojo con icono.

---

# 46.1 Render del mapa en Laravel

El mapa vive dentro de una vista Blade y se inicializa con MapLibre GL JS.

Ejemplo conceptual:

```javascript
const map = new maplibregl.Map({
    container: 'courier-map',
    style: 'https://demotiles.maplibre.org/style.json',
    center: [-100.316, 25.686],
    zoom: 12
});
```

Cuando Laravel entrega una geometría de ruta:

```javascript
map.getSource('recommended-route')
    .setData(routeGeoJson);
```

Capas sugeridas:

```text
courier-position
restaurants
customers
recommended-route
alternative-routes
surge-zones
road-closures
```

La lógica visual del mapa pertenece a:

```text
resources/js/map.js
```

pero las decisiones de negocio siguen perteneciendo a Laravel/PHP.

---

# 47. Motor de rutas

## Laravel → OSRM

Laravel puede consultar OSRM con el cliente HTTP integrado:

```php
use Illuminate\Support\Facades\Http;

$response = Http::timeout(2)
    ->retry(2, 100)
    ->get($url);

$data = $response->throw()->json();
```

La respuesta se transforma a DTOs internos antes de enviarla al motor de scoring.


## Opción A — OSRM

Para una ruta:

```text
courier → restaurant → customer
```

Solicitar:

```text
distance
duration
geometry
```

Para múltiples pedidos usar el servicio `table` para construir una matriz de tiempos.

Ejemplo conceptual:

```text
         A    B    C    D
A        0    5    9   11
B        5    0    6    8
C        9    6    0    4
D       11    8    4    0
```

Esta matriz alimenta al optimizador.

---

# 48. Caché de rutas

No solicitar la misma ruta repetidamente.

Key:

```text
round(lat1, 4)
round(lon1, 4)
round(lat2, 4)
round(lon2, 4)
traffic_bucket
closure_version
```

TTL sugerido:

```text
30–120 segundos simulados
```

---

# 49. Simulación de tráfico

OSRM por sí mismo no es un feed de tráfico real.

Para el hackatón podemos aplicar factores:

```text
AdjustedTime =
OSRMTime × ZoneTrafficFactor × WeatherFactor
```

Ejemplo:

```text
OSRM = 12 min
traffic = 1.4
rain = 1.15

12 × 1.4 × 1.15
= 19.32 min
```

Esto nos permite provocar eventos reproducibles.

---

# 50. Road closures

Dos opciones.

## Simple

Mantener una lista:

```json
{
  "blocked_edges": ["EDGE-123"]
}
```

y aplicar gran penalización/inviabilidad en el simulador.

## Mejor demo

Mantener escenarios con rutas alternativas precalculadas y al activar el evento cambiar:

```text
route_time
route_distance
```

El mapa puede mostrar el bloqueo sin necesitar modificar un servidor OSRM en vivo.

---

# 51. Demand zones

Dividir el mapa en zonas.

Ejemplo:

```text
CENTRO
TEC
SAN_PEDRO
OBISPADO
SUR
NORTE
```

Cada zona:

```json
{
  "id": "TEC",
  "center": [25.651, -100.289],
  "demand_by_hour": {
    "18": 0.75,
    "19": 0.92,
    "20": 0.88
  }
}
```

---

# 52. Future Position Value

Para monetizar el valor futuro:

```text
FutureValue =
DemandScore_destination
× ExpectedOrderArrivalRate
× ExpectedProfitPerOrder
× HorizonFactor
```

Para MVP, simplificar:

```text
FuturePositionBonus =
DemandScore × MAX_POSITION_BONUS_MXN
```

Ejemplo:

```text
DemandScore = 0.82
MAX_POSITION_BONUS = $20

FuturePositionBonus = $16.40
```

Se debe mostrar separado de la ganancia actual para no confundir dinero real con valor estimado.

---

# 53. Scoring vs Optimization

No confundir.

**Scoring**
sirve para ordenar visualmente ofertas.

**Optimization**
sirve para elegir la mejor combinación/ruta.

El ganador puede ser:

```text
#1 individual score: ORD-01

pero

best plan: ORD-01 + ORD-04
```

---

# 54. Algoritmo general

```text
on_new_orders():

    1. recibir estado actual

    2. obtener tiempos/distancias

    3. evaluar cada pedido
       - payment
       - cost
       - duration
       - hourly rate
       - destination
       - risk
       - batch potential

    4. asignar score individual

    5. generar candidatos
       - single
       - pair
       - optional triple

    6. descartar candidatos inviables

    7. optimizar secuencia pickup/dropoff

    8. calcular utilidad de cada plan

    9. seleccionar plan ganador

   10. construir explanation facts

   11. opcional:
       LLM convierte facts a texto

   12. retornar ranking + plan
```

---

# 55. Pseudocódigo Laravel/PHP

```php
public function recommend(
    CourierState $state,
    Collection $orders,
    EnvironmentState $environment
): array {
    $evaluated = $orders->map(function (Order $order) use ($state, $environment) {
        $route = $this->routingService->evaluateOrder(
            $state->position,
            $order,
            $environment
        );

        return $this->scoringService->calculateMetrics(
            $state,
            $order,
            $route,
            $environment
        );
    });

    $evaluated = $this->scoringService->normalize($evaluated);

    $evaluated = $evaluated->map(function ($order) {
        $order->score = $this->scoringService->calculateScore($order);
        return $order;
    });

    $candidateSets = $this->optimizationService->generateCandidateSets(
        $evaluated,
        $state->maxConcurrentOrders
    );

    $feasiblePlans = collect();

    foreach ($candidateSets as $candidate) {
        $plan = $this->optimizationService->evaluatePlan(
            $state,
            $candidate,
            $environment
        );

        if ($plan->feasible) {
            $feasiblePlans->push($plan);
        }
    }

    $bestPlan = $feasiblePlans->sortByDesc('utility')->first();

    return [
        'ranking' => $evaluated->sortByDesc('score')->values(),
        'recommended_plan' => $bestPlan,
    ];
}
```

---

# 56. Explanation facts

Nunca enviar al LLM únicamente:

```text
“Dime por qué el pedido 1 es mejor.”
```

Enviar datos estructurados:

```json
{
  "selected": ["ORD-001", "ORD-004"],
  "net_profit_mxn": 164,
  "minutes": 42,
  "hourly_rate_mxn": 234.3,
  "route_overlap_percent": 76,
  "time_saved_vs_separate_min": 9,
  "destination_demand_score": 0.82,
  "risk": 0.09,
  "rejected_alternative": {
    "id": "ORD-005",
    "reason": "long_deadhead",
    "deadhead_km": 6.8
  }
}
```

---

# 57. Prompt del explanation agent

```text
SYSTEM:

You are the explanation layer for a courier optimization system.

You DO NOT choose orders.
The optimizer has already chosen them.

Explain the decision using ONLY the metrics supplied.
Never invent distances, money, durations, traffic,
safety conditions or probabilities.

Keep explanations concise enough for a courier
to understand in a few seconds.

Always mention:
1. expected net earnings,
2. time,
3. one major reason for selection,
4. one reason the strongest alternative lost.

If data is insufficient, say so.
```

---

# 58. Endpoint API

Aunque la interfaz principal usa Blade/Livewire, mantenemos una API JSON limpia.

## `routes/web.php`

```php
Route::get('/courier', [DashboardController::class, 'index'])
    ->name('courier.dashboard');

Route::get('/results/{shift}', [DashboardController::class, 'results'])
    ->name('shift.results');

Route::get('/demo/control', [DashboardController::class, 'demo'])
    ->name('demo.control');
```

## `routes/api.php`

```php
Route::post('/simulation/start', [SimulationController::class, 'start']);
Route::post('/simulation/tick', [SimulationController::class, 'tick']);
Route::get('/orders/available', [OrderController::class, 'available']);
Route::post('/recommendations', [RecommendationController::class, 'store']);
Route::post('/plans/accept', [PlanController::class, 'accept']);
Route::post('/simulation/events', [SimulationController::class, 'injectEvent']);
```


En Laravel se definen en:

```text
routes/api.php
```

Ejemplo:

```php
Route::post('/simulation/start', [SimulationController::class, 'start']);
Route::post('/simulation/tick', [SimulationController::class, 'tick']);
Route::get('/orders/available', [OrderController::class, 'available']);
Route::post('/recommendations', [RecommendationController::class, 'store']);
Route::post('/plans/accept', [PlanController::class, 'accept']);
Route::post('/simulation/events', [SimulationController::class, 'injectEvent']);
```


## Estado del turno

```http
GET /api/shift
```

## Iniciar simulación

```http
POST /api/simulation/start
```

Body:

```json
{
  "scenario": "normal",
  "seed": 42
}
```

## Avanzar simulación

```http
POST /api/simulation/tick
```

## Ofertas disponibles

```http
GET /api/orders/available
```

## Obtener recomendación

```http
POST /api/recommendations
```

Body:

```json
{
  "courier_state": {},
  "available_order_ids": [
    "ORD-001",
    "ORD-002",
    "ORD-003"
  ]
}
```

## Aceptar plan

```http
POST /api/plans/accept
```

## Inyectar evento

```http
POST /api/simulation/events
```

```json
{
  "type": "ROAD_CLOSURE",
  "zone": "CENTRO"
}
```

---

# 58.1 Integración entre vistas y API dentro de Laravel

Como las vistas y la API viven en la misma aplicación, no se necesita un cliente frontend separado.

Hay tres formas de comunicación.

## Opción 1 — Livewire actions

Preferida para interacciones normales de la interfaz.

Ejemplo:

```php
public function acceptRecommendedPlan(
    ShiftService $shiftService
): void {
    $shiftService->acceptCurrentRecommendation(
        $this->shiftId
    );

    $this->dispatch('plan-accepted');
    $this->refreshState($shiftService);
}
```

La vista:

```blade
<button
    wire:click="acceptRecommendedPlan"
    wire:loading.attr="disabled"
>
    Aceptar recomendación
</button>
```

## Opción 2 — Fetch desde JavaScript

Útil para el mapa o controles donde JS necesite JSON.

```javascript
const response = await fetch('/api/recommendations', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    body: JSON.stringify(payload)
});

const data = await response.json();
```

## Opción 3 — Eventos en tiempo real

Laravel Reverb/Echo puede enviar:

```text
NewOrderAvailable
RankingUpdated
SurgeStarted
RoadClosed
DeliveryCompleted
```

al navegador.

## Regla importante

La API y Livewire **no implementan dos motores distintos**.

Ambos llaman a:

```text
ScoringService
OptimizationService
SimulatorService
ShiftService
```

Ejemplo:

```text
POST /api/recommendations
        ┐
        │
Livewire refresh
        ├──> RecommendationService / OptimizationService
        │
Demo controller
        ┘
```

De esta manera los resultados son idénticos independientemente de cómo se invoque la lógica.


---

# 59. Respuesta de recomendaciones

```json
{
  "generated_at": "2026-09-12T19:12:04",
  "ranking": [
    {
      "order_id": "ORD-001",
      "rank": 1,
      "score": 91.2,
      "priority": "HIGH"
    },
    {
      "order_id": "ORD-004",
      "rank": 2,
      "score": 87.4,
      "priority": "HIGH"
    }
  ],
  "recommended_plan": {
    "orders": ["ORD-001", "ORD-004"],
    "expected_net_profit_mxn": 164.0,
    "expected_minutes": 42,
    "expected_hourly_rate_mxn": 234.3,
    "route": [],
    "reason_codes": [
      "HIGH_HOURLY_RATE",
      "BATCH_COMPATIBLE",
      "HIGH_DEMAND_DESTINATION"
    ]
  }
}
```

---

# 60. Eventos

Enum:

```text
NEW_ORDER
ORDER_EXPIRED
SURGE_STARTED
SURGE_ENDED
TRAFFIC_CHANGED
ROAD_CLOSED
ROAD_REOPENED
WEATHER_CHANGED
RESTAURANT_DELAY_CHANGED
ORDER_ACCEPTED
PICKUP_COMPLETED
DELIVERY_COMPLETED
```

---

# 61. Tiempo real con Laravel Reverb / Broadcasting

La opción recomendada es:

```text
Laravel Events
+
Broadcasting
+
Laravel Reverb
+
Laravel Echo
```

## Flujo

```text
SimulatorService
      ↓
event(new RankingUpdated(...))
      ↓
Laravel Reverb
      ↓
Browser / Echo
      ↓
Livewire refresh / JS map update
```

Eventos principales:

```text
NewOrderAvailable
OrderExpired
RankingUpdated
SurgeStarted
SurgeEnded
TrafficChanged
RoadClosed
RoadReopened
DeliveryCompleted
ShiftFinished
```

Canal conceptual:

```text
shift.{shiftId}
```

Ejemplo de escucha en navegador:

```javascript
Echo.channel(`shift.${shiftId}`)
    .listen('.ranking.updated', (event) => {
        window.dispatchEvent(
            new CustomEvent('ranking-updated', {
                detail: event
            })
        );
    });
```

Livewire puede reaccionar refrescando su estado.

## Fallback

Si Reverb consume demasiado tiempo de implementación:

```text
wire:poll.1s
```

o polling JS cada segundo es suficiente para el hackatón.

El tiempo real es una mejora de UX; no debe bloquear el MVP.


---

# 62. Máquina de estados del turno

```text
IDLE
  ↓
RUNNING
  ↓
PAUSED
  ↓
FINISHED
```

---

# 63. Máquina de estados del repartidor

```text
AVAILABLE
↓
GOING_TO_PICKUP
↓
WAITING_AT_RESTAURANT
↓
DELIVERING
↓
AVAILABLE
```

Con batching:

```text
GOING_TO_PICKUP_1
PICKUP_1
GOING_TO_PICKUP_2
PICKUP_2
DELIVERY_1
DELIVERY_2
```

El orden se decide mediante optimización.

---

# 64. Ejemplo de cinco pedidos

Estado:

```text
cost/km = $1.25
shift remaining = 120 min
max concurrent = 2
```

| Order | Pay | km | min | Net | Net/hour | Demand |
|---|---:|---:|---:|---:|---:|---:|
| #1 | 98 | 5.9 | 23 | 90.63 | 236.4 | .82 |
| #2 | 135 | 11.8 | 43 | 120.25 | 167.8 | .24 |
| #3 | 74 | 4.6 | 22 | 68.25 | 186.1 | .62 |
| #4 | 86 | 5.1 | 21 | 79.63 | 227.5 | .88 |
| #5 | 151 | 14.9 | 51 | 132.38 | 155.7 | .31 |

Una estrategia ingenua de pago bruto seleccionaría:

```text
#5
```

Courier AI podría destacar:

```text
#1
#4
```

Porque ofrecen:

- mayor utilidad por hora;
- pickups/rutas más eficientes;
- mejor posición final;
- posibilidad de batching.

Si #1 + #4 juntos requieren 36–42 minutos por compartir ruta, su valor supera claramente al pedido #5 aunque individualmente #5 muestre más dinero.

---

# 65. Explicación para un juez

Pregunta:

> ¿Por qué no seleccionaron el pedido que paga $151?

Respuesta esperada:

> El pago nominal es mayor, pero requiere 14.9 km y aproximadamente 51 minutos. Después de costo operativo produce cerca de $156 MXN por hora. Los pedidos 1 y 4 tienen mayor retorno por tiempo y son compatibles; al combinarlos compartimos parte de la ruta, reduciendo tiempo marginal y terminando en una zona con demanda mayor. El agente optimiza la jornada completa, no el pago visible de una sola orden.

---

# 66. Reacción a cambio dinámico

Antes:

```text
#1 score 91
#4 score 87
#3 score 66
```

Se produce cierre.

Ruta #1:

```text
23 min → 38 min
```

Nuevo ranking:

```text
#4 score 90
#3 score 74
#1 score 55
```

UI:

```text
⚠ Route changed

Order #1 dropped from rank 1 → 3
because expected travel time increased by 15 min.
```

Esta escena es excelente para la demo.

---

# 67. Seguridad

El reto menciona tomar decisiones seguras.

En el simulador podemos declarar:

```text
restricted_zone = true
unsafe_weather = true
road_closed = true
```

Una condición de seguridad puede convertirse en restricción dura.

```text
Safety > profit
```

Nunca recomendar una ruta marcada explícitamente como no permitida sólo porque paga más.

---

# 68. Configuración de preferencias

Opcional para demo:

```json
{
  "strategy": "balanced",
  "weights": {
    "hourly_profit": 0.30,
    "net_profit": 0.20,
    "destination": 0.15,
    "batch": 0.15,
    "pickup": 0.10,
    "reliability": 0.10
  }
}
```

Presets:

```text
BALANCED
MAX_PROFIT
LOW_DISTANCE
LOW_RISK
END_SHIFT
```

---

# 69. End-of-shift behavior

Cuando quedan pocos minutos:

```text
shift_remaining <= 30
```

incrementar penalización para pedidos que alejen demasiado al repartidor o excedan el turno.

Opcional:

```text
return-home destination
```

y añadir valor por terminar cerca de casa/base.

---

# 70. Estrategia contextual

El agente puede cambiar de estrategia automáticamente.

Ejemplos:

```text
inicio del turno:
buscar tasa de ganancia alta

hora pico:
aprovechar surge y batching

final del turno:
evitar pedidos largos
```

No hace falta reinforcement learning para demostrar inteligencia contextual.

---

# 71. ¿Es realmente un agente de IA?

Sí, si el sistema mantiene estado, observa el entorno, toma decisiones, actúa y vuelve a evaluar.

Loop:

```text
OBSERVE
  ↓
EVALUATE
  ↓
PLAN
  ↓
RECOMMEND / ACT
  ↓
OBSERVE NEW STATE
```

Puede presentarse como un **decision-making agent** con:

- perception:
  pedidos + mapa + tráfico + surge;
- state:
  posición + turno + pedidos activos;
- reasoning:
  scoring + optimización;
- planning:
  combinatorial optimizer en Laravel/PHP;
- explanation:
  LLM;
- adaptation:
  recalcular al cambiar el entorno.

---

# 72. No venderlo como “un chatbot”

La propuesta central no es:

> “Le preguntamos a Gemini cuál pedido le gusta.”

La propuesta debe ser:

> **Un agente de optimización driver-side que combina routing, operations research y AI explanation.**

Eso es técnicamente más sólido.

---

# 73. Operations Research detrás del sistema

Conceptos que podemos mencionar:

- Vehicle Routing Problem — VRP;
- Pickup and Delivery Problem — PDP;
- VRP with Time Windows — VRPTW;
- combinatorial optimization;
- shortest path;
- opportunity cost;
- greedy baseline;
- constrained optimization;
- rolling horizon optimization;
- dynamic re-optimization.

---

# 74. Rolling Horizon

Nuestro turno cambia constantemente.

En lugar de planear todo desde las 18:00:

```text
cada vez que:
- llega pedido;
- expira pedido;
- cambia tráfico;
- aparece surge;
- ocurre cierre;
- termina entrega;
```

ejecutamos de nuevo:

```text
Optimize(current_state, current_offers)
```

Esto es **rolling horizon optimization**.

Es una excelente explicación técnica ante jueces.

---

# 75. Horizonte de decisión

Para evitar sobreoptimizar un futuro incierto:

```text
planning_horizon = 30–60 min
```

No necesitamos predecir la jornada completa.

---

# 76. Arquitectura del agente

```text
Observation
   │
   ├── Courier State
   ├── Orders
   ├── Routes
   ├── Traffic
   ├── Surge
   └── Demand
   │
   ▼
Feature Builder
   │
   ▼
Economic Model
   │
   ▼
Candidate Generator
   │
   ▼
Constraint Solver
   │
   ▼
Best Plan
   │
   ├── Recommendation
   └── Explanation Facts
          │
          ▼
      LLM Explanation
```

---

# 77. Componentes principales de Laravel

Todos estos componentes se implementan como clases de servicio de Laravel bajo:

```text
app/Services/
```

Los Controllers deben ser delgados: validan la petición, llaman a los Services y retornan Resources/JSON. La lógica matemática no debe vivir en Controllers ni Models.


## RoutingService

Responsabilidades:

- route;
- distance;
- duration;
- route geometry;
- matrix;
- cache.

## ScoringService

- cost;
- net profit;
- hourly profit;
- destination score;
- risk;
- normalized score.

## OptimizationService

- generación de candidatos;
- batching;
- restricciones;
- generación de secuencias válidas;
- cálculo de utilidad por plan;
- selección del mejor plan;
- límite de tiempo/cantidad de combinaciones para proteger latencia.

## SimulatorService

- clock;
- stream;
- events;
- courier movement;
- completion.

## DemandService

- current zone demand;
- future position value.

## ExplanationService

- reason codes;
- deterministic fallback;
- LLM text.

---

# 78. Reason codes

Utilizar enum.

```text
HIGH_HOURLY_RATE
HIGH_NET_PROFIT
SHORT_PICKUP
LOW_DEADHEAD
HIGH_DEMAND_DESTINATION
BATCH_COMPATIBLE
LOW_DELAY_RISK
SURGE_ADVANTAGE
SHIFT_FIT

LOW_HOURLY_RATE
LONG_PICKUP
LOW_DEMAND_DESTINATION
HIGH_DELAY_RISK
TRAFFIC_PENALTY
ROAD_CLOSURE
SHIFT_TOO_SHORT
INCOMPATIBLE_BATCH
```

Esto permite explicar incluso si falla el LLM.

---

# 79. Explicación determinista fallback

```php
if (! $llmAvailable) {
    $explanation = $this->templateEngine->fromReasonCodes($reasonCodes);
}
```

Ejemplo:

```text
Recommended because:
• $236 MXN/h estimated net rate
• 1.1 km pickup
• high-demand destination

Order #5 ranked lower because:
• 14.9 km route
• 51 min commitment
```

La demo nunca depende de internet para explicar.

---

# 79.1 Laravel Cache y Jobs

Usar `Cache` para:

- matrices OSRM;
- rutas repetidas;
- demanda por zona;
- estado derivado de corta duración.

Ejemplo conceptual:

```php
$route = Cache::remember(
    $cacheKey,
    now()->addMinutes(2),
    fn () => $this->routingService->fetchRoute($from, $to)
);
```

Jobs opcionales:

- generar explicaciones LLM;
- correr benchmarks;
- precalcular escenarios.

El ranking principal debe seguir siendo síncrono y rápido.

---

# 80. Persistencia

# 80.1 Ejemplo de migration Laravel para MySQL

Ejemplo para `orders`:

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('external_id', 50)->unique();
    $table->dateTime('spawn_time');

    $table->string('restaurant_name', 150);
    $table->decimal('restaurant_lat', 10, 7);
    $table->decimal('restaurant_lon', 10, 7);

    $table->decimal('customer_lat', 10, 7);
    $table->decimal('customer_lon', 10, 7);

    $table->decimal('base_pay', 10, 2);
    $table->decimal('surge_bonus', 10, 2)->default(0);

    $table->unsignedInteger('restaurant_wait_min')->default(0);

    $table->dateTime('pickup_deadline')->nullable();
    $table->dateTime('delivery_deadline')->nullable();

    $table->string('status', 30)->index();

    $table->timestamps();

    $table->index('spawn_time');
});
```

Usar `DECIMAL` para dinero, no `FLOAT`/`DOUBLE`, para evitar errores de precisión monetaria.

---


Tablas mínimas:

```text
shifts
orders
recommendations
events
deliveries
route_cache
```

---

# 81. Esquema MySQL

## shifts

```text
id
scenario
seed
started_at
finished_at
agent_type
gross_earnings
net_earnings
distance_km
```

## orders

```text
id
spawn_time
restaurant_lat
restaurant_lon
customer_lat
customer_lon
base_pay
surge
wait_min
deadline
```

## recommendations

```text
id
shift_id
simulation_time
selected_order_ids
score
expected_profit
reason_json
```

---

# 82. Reproducibilidad

Toda demo debe guardar:

```text
scenario_id
seed
config_version
weights
```

Así podemos repetir exactamente un resultado.

---

# 83. Modos de ejecución

## Demo mode

Escenario curado.

Garantiza:

- buenas decisiones visibles;
- batch;
- surge;
- cierre.

## Random challenge mode

Juez presiona:

```text
GENERATE FRESH SHIFT
```

Seed aleatorio.

Ambos agentes reciben el mismo turno.

Esto responde directamente al requisito de probarse en un turno nuevo.

---

# 84. Comparación lado a lado

Pantalla ideal:

```text
BASELINE                    COURIER AI

$428                        $517
$151/hour                   $193/hour

32.1 km                     25.3 km
6 orders                    7 orders
2 delays                    0 delays

          +20.8% NET PROFIT
```

---

# 85. Demo principal — 3 minutos

## 0:00–0:25 — Problema

“Un repartidor ve varios pedidos pero el mayor pago no significa la mayor ganancia.”

## 0:25–0:50 — Cinco ofertas

Mostrar 5 pedidos.

Destacar #1 y #4.

Abrir `WHY`.

## 0:50–1:20 — Matemática

Mostrar:

```text
net profit
MXN/hour
distance
batch overlap
destination demand
risk
```

## 1:20–1:50 — Dos agentes

Iniciar el mismo turno.

```text
baseline vs Courier AI
```

Contadores avanzan.

## 1:50–2:15 — Evento

Botón:

```text
ROAD CLOSURE
```

El ranking cambia.

Mostrar explicación.

## 2:15–2:45 — Resultado

Courier AI supera al baseline.

## 2:45–3:00 — Cierre

> “Platforms optimize the network. Courier AI optimizes for the driver.”

---

# 86. Botones de control del demo

Panel secreto/admin:

```text
START SHIFT
PAUSE
SPEED x1 / x5 / x20
TRIGGER SURGE
TRIGGER ROAD CLOSURE
ADD RESTAURANT DELAY
END SHIFT
RESET
NEW RANDOM SEED
```

Esto hace la demo controlable.

---

# 87. Unit tests críticos

## Economic calculations

```text
gross pay
operating cost
net profit
hourly rate
```

## Normalization

- valores normales;
- todos iguales;
- negativos.

## Feasibility

- deadline;
- turno restante;
- capacidad.

## Ranking

Cambiar costo/tiempo debe producir orden esperado.

## Batching

Un par compatible debe ganar sobre rutas separadas cuando realmente ahorra tiempo.

## Events

Road closure debe invalidar o penalizar rutas afectadas.

---

# 88. Pruebas de invariantes

Siempre:

```text
NetProfit <= GrossPay
```

si costo >= 0.

Siempre:

```text
TotalTime > 0
TotalDistance >= 0
```

Si:

```text
hard safety constraint violated
```

entonces:

```text
feasible == false
```

---

# 89. Tests de explicación

El texto no puede mencionar métricas que no existen.

La respuesta del LLM debe validarse conceptualmente con facts.

Para hackatón basta mantener el prompt muy restrictivo.

---

# 90. Métrica experimental

Ejecutar, por ejemplo:

```text
100 simulated shifts
```

con seeds:

```text
1..100
```

Comparar:

```text
mean net profit
median net profit
win rate
distance
late deliveries
```

Tabla:

| Metric | Baseline | Courier AI |
|---|---:|---:|
| Avg net profit | ... | ... |
| Avg MXN/h | ... | ... |
| Avg km | ... | ... |
| Late deliveries | ... | ... |
| Win rate | — | ... |

Esto proporciona evidencia cuantitativa mucho más fuerte que una sola demo.

---

# 91. Win Rate

```text
WinRate =
shifts where AgentProfit > BaselineProfit
/
total shifts
```

Ejemplo:

```text
84 / 100 = 84%
```

No inventar este resultado. Debe calcularse con la simulación real.

---

# 92. Ablation test opcional

Comparar:

```text
A: highest pay
B: net/hour only
C: + destination
D: + batching
E: full agent
```

Sirve para mostrar qué añade valor.

---

# 93. Qué datos son simulados

Ser transparentes.

**Real:**

- geometría de calles si usamos OSM;
- rutas/distancias calculadas sobre calles reales.

**Simulado:**

- ofertas;
- tarifas;
- surge;
- demanda;
- tráfico, si no tenemos fuente real;
- espera de restaurante;
- deadlines.

No decir que tenemos datos de Uber/DiDi/Rappi si no los tenemos.

---

# 94. Ética y producto

La aplicación debe presentarse como:

> herramienta de apoyo al repartidor.

No:

> sistema que controla al repartidor.

Mostrar siempre:

```text
Recommendation
```

no:

```text
Mandatory action
```

El usuario puede ignorar el agente.

---

# 95. Privacidad

Para una app real:

- minimizar almacenamiento de ubicación;
- no vender datos de ubicación;
- no guardar historial innecesario;
- permitir borrar datos;
- no inferir “zonas inseguras” a partir de atributos personales.

En el hackatón usamos datos simulados.

---

# 96. Riesgos técnicos

## API de routing caída

Mitigación:

- cache;
- escenarios con rutas precalculadas;
- fallback euclidean/haversine para demo.

## LLM sin conexión

Mitigación:

- explicación determinista.

## El optimizador combinatorio tarda

Mitigación:

- máximo 5–10 ofertas visibles;
- máximo pares en el MVP;
- tríos sólo como bonus;
- poda temprana de candidatos inviables;
- cachear matrices de OSRM;
- límite de combinaciones evaluadas;
- fallback greedy por score si se supera el presupuesto de tiempo.

## mapa sin tiles

Mitigación:

- cache/offline;
- escenario previsualizado.

---

# 97. Fallback de distancia

Haversine:

```text
distance_geo(a,b)
```

No representa carreteras, pero mantiene la app funcional.

Aplicar:

```text
estimated_road_distance =
haversine × ROAD_FACTOR
```

por ejemplo:

```text
ROAD_FACTOR = 1.25
```

Sólo como fallback.

---

# 98. Latencia objetivo

Recomendación:

```text
< 1 segundo ideal
< 2 segundos aceptable
```

El LLM no debe bloquear el ranking.

Flujo:

```text
optimizer result → UI immediately

LLM explanation → async
```

---

# 99. Logging

Cada decisión:

```json
{
  "time": "19:23",
  "offers": ["001", "002", "004"],
  "selected": ["001", "004"],
  "utility": 174.8,
  "reason_codes": [
    "BATCH_COMPATIBLE",
    "HIGH_HOURLY_RATE"
  ],
  "config_version": "v1"
}
```

Sirve para defender una decisión frente al juez.

---

# 100. Observabilidad en demo

Panel de debug opcional:

```text
Optimizer: 37 ms
Routing: 84 ms
Candidates evaluated: 15
Feasible candidates: 8
Winner utility: 174.8
```

Ayuda a demostrar que el sistema realmente está calculando.

---

# 101. Implementación por prioridad

## P0 — imprescindible

- mapa;
- repartidor;
- pedidos simulados;
- rutas;
- tarjetas;
- costo;
- net profit;
- net/hour;
- score;
- ranking;
- aceptar pedido;
- reloj;
- earnings counter;
- baseline;
- comparación final.

## P1 — debe intentarse

- batching;
- optimizador combinatorio nativo en Laravel;
- demand zones;
- surge;
- road closure;
- explicación “Why?”;
- side-by-side demo.

## P2 — bonus

- LLM;
- preguntas del juez;
- escenarios aleatorios;
- 100-run benchmark;
- configuración de estrategia;
- heatmap.

---

# 102. Orden recomendado de construcción

1. Definir modelos.
2. Construir simulador sin mapa.
3. Implementar cálculos.
4. Implementar baseline.
5. Implementar agente.
6. Escribir tests.
7. Conectar routing.
8. Integrar mapa.
9. Añadir UI de ranking.
10. Implementar eventos.
11. Implementar batching.
12. Añadir LLM.
13. Crear demo side-by-side.
14. Ejecutar benchmarks.
15. Ensayar pitch.

---

# 103. División de trabajo sugerida

## Persona A — Laravel UI / Livewire

- Blade;
- Livewire;
- Alpine.js;
- CSS/Tailwind;
- dashboard;
- mapa MapLibre;
- tarjetas;
- modales;
- animaciones;
- resultados.

## Persona B — Laravel API / Simulator

- Controllers;
- API;
- Eloquent;
- migrations;
- MySQL;
- SimulatorService;
- reloj;
- pedidos;
- Events/Reverb;
- seeders.

## Persona C — Optimization / Routing

- fórmulas;
- ScoringService;
- OptimizationService;
- batching;
- secuencias pickup/dropoff;
- OSRM;
- tests.

## Persona D — AI / Data / Demo

- ExplanationService;
- LLM;
- demand zones;
- escenarios;
- benchmark;
- demo;
- pitch.

Si son 3 personas:

```text
A = Blade/Livewire + mapa
B = Laravel API + simulador + MySQL
C = optimización + OSRM + IA + benchmark
```

Aunque las tareas se dividan, **todo termina integrado en el mismo proyecto Laravel**.


---

# 104. Definition of Done — MVP

El proyecto está listo si:

- inicia un turno;
- aparecen al menos 5 pedidos;
- el mapa dibuja rutas;
- todos los pedidos muestran pago/distancia/tiempo;
- el agente produce ranking;
- explica #1;
- puede recomendar dos pedidos juntos;
- el usuario acepta;
- cambian ganancias/posición/tiempo;
- existe baseline;
- ambos reciben el mismo escenario;
- existe al menos un evento dinámico;
- el agente recalcula;
- pantalla final muestra mejora.

---

# 105. Criterios oficiales → Feature

## Results

**Pregunta:** ¿gana más que un baseline?

Feature:

```text
same-shift simulator
baseline vs AI
final delta
```

## Judgment

**Pregunta:** ¿toma decisiones sensatas y las defiende?

Feature:

```text
Why panel
reason codes
decision log
```

## Feasibility

**Pregunta:** ¿podría servir a un repartidor real?

Feature:

```text
sub-second ranking
responsive Laravel web UX
real street routing
driver-side metrics
```

## Clarity

**Pregunta:** ¿se entiende lo que decidió?

Feature:

```text
priority cards
map route
score
short explanation
```

---

# 106. Preguntas probables de jueces

## “¿Dónde está la IA?”

Respuesta:

> Nuestro agente observa continuamente el estado del turno, evalúa alternativas, optimiza bajo restricciones, actualiza el plan cuando cambia el entorno y utiliza un modelo de lenguaje para explicar decisiones. No delegamos la matemática al LLM; la decisión se genera mediante un motor cuantitativo y de optimización.

## “¿Por qué no están usando OR-Tools directamente?”

> El reto menciona OR-Tools como una opción, pero nuestro MVP recibe pocas ofertas simultáneas y sólo permite un número pequeño de pedidos concurrentes. Eso hace posible enumerar en Laravel las combinaciones y secuencias válidas, aplicar restricciones y elegir el máximo de manera determinista. Si escalamos a decenas o cientos de pedidos, podemos mover la optimización avanzada a un solver especializado sin cambiar el contrato de la API ni las vistas Laravel.

## “¿Qué pasa si el modelo alucina?”

> El LLM no selecciona pedidos. Sólo recibe métricas verificadas y explica una decisión ya tomada. Si falla, tenemos templates deterministas.

## “¿Cómo saben que gana más?”

> Ejecutamos el mismo turno con la misma seed para baseline y agente y comparamos la ganancia neta. También podemos ejecutar múltiples seeds.

## “¿Qué parte es real?”

> Utilizamos calles/routing reales de OpenStreetMap. El mercado de pedidos, surge y tráfico se simulan porque no tenemos acceso a los feeds privados de plataformas.

---

# 107. Pitch técnico corto

> Courier AI treats every delivery offer as an operations-research decision. We estimate the true net value of each order using travel time, distance, operating cost, restaurant waiting time, demand at the destination and batching compatibility. Then a rolling-horizon optimizer selects the best feasible plan. When traffic, surge or road conditions change, the agent re-optimizes. An LLM explains the result, but never invents or makes the core economic decision.

---

# 108. Pitch de negocio corto

> Las plataformas optimizan su red. Nosotros optimizamos la jornada del repartidor.

---

# 109. Diferenciador

No somos:

- otro mapa;
- otro chatbot;
- un simple calculador de MXN/km.

Somos:

> **un sistema de decisión que valora el pedido actual por su efecto sobre la ganancia total del turno.**

---

# 110. Roadmap posterior

## Fase 2

- tráfico real;
- weather API;
- predicción de demanda;
- personalización por vehículo;
- perfiles de costo;
- aprendizaje histórico.

## Fase 3

- integración con wearables/audio;
- recomendación por voz;
- on-device models;
- offline maps.

## Fase 4

- aprendizaje contextual por repartidor;
- bandits/reinforcement learning;
- multi-platform aggregation, sólo si existen APIs/permiso legal.

---

# 111. Ideas de ML futuras

No necesarias para MVP:

## Demand prediction

Features:

```text
zone
hour
weekday
weather
surge
historical orders
```

Target:

```text
orders_next_15_min
```

Modelos:

- gradient boosting;
- random forest;
- temporal model.

## Restaurant waiting prediction

Features:

```text
restaurant
hour
weekday
order_size
traffic
```

Target:

```text
waiting_minutes
```

---

# 112. Reinforcement Learning — por qué NO empezar aquí

Un RL agent requeriría:

- simulador calibrado;
- muchas iteraciones;
- reward design;
- estabilidad;
- entrenamiento.

Para un hackatón, optimización combinatoria + reglas + rolling horizon es:

- más confiable;
- explicable;
- fácil de demostrar;
- rápido de construir;
- totalmente implementable dentro de Laravel.

RL puede mencionarse como futura evolución.

---

# 113. Reward para una versión futura

```text
reward =
+ net_profit
- lateness_penalty
- idle_time_penalty
- unsafe_action_penalty
+ end_zone_value
```

---

# 114. Dataset

El documento oficial sugiere como recursos:

- OpenStreetMap;
- OSMnx u OSRM;
- Google OR-Tools;
- Solomon VRPTW;
- datasets públicos de food delivery.

Para el MVP, recomendamos no depender de un dataset externo para la demo. Crear escenarios propios sobre calles reales permite controlar y explicar mejor el experimento.

Los datasets externos pueden utilizarse para:

- calibrar distribuciones;
- estimar distancias;
- probar patrones;
- generar benchmarks.

---

# 115. Dependencias del proyecto Laravel

## Proyecto base

```bash
composer create-project laravel/laravel courier-ai
cd courier-ai
```

## Livewire

```bash
composer require livewire/livewire
```

Livewire permite construir la interfaz dinámica sin mantener un frontend separado.

## Broadcasting / tiempo real

```bash
php artisan install:broadcasting
```

La configuración puede usar Laravel Reverb.

Si la versión de Laravel utilizada requiere instalarlo de forma explícita, seguir la documentación correspondiente a esa versión.

## Assets frontend del mismo proyecto Laravel

```bash
npm install
npm install maplibre-gl laravel-echo pusher-js
```

Uso:

- `maplibre-gl`: mapa, markers y rutas;
- `laravel-echo`: escucha de eventos broadcast;
- `pusher-js`: cliente de protocolo requerido por Echo/Reverb según configuración.

## Testing

Laravel incluye PHPUnit. Pest es opcional:

```bash
composer require pestphp/pest-plugin-laravel --dev
```

## Cliente HTTP

No se necesita paquete adicional para OSRM o LLM:

```php
use Illuminate\Support\Facades\Http;
```

Laravel proporciona el cliente HTTP necesario.

## Base de datos

Driver:

```text
pdo_mysql
```

Debe estar habilitado en PHP.

## No se requieren

Para el MVP no se requiere:

```text
frontend framework separado
aplicación cliente separada
solver externo obligatorio
```

El motor de scoring y la enumeración de combinaciones se implementan como servicios PHP dentro de Laravel.

OR-Tools queda como evolución opcional si el problema crece significativamente.


---

# 116. Configuración

`.env`:

```env
APP_NAME=CourierAI
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=courier_ai
DB_USERNAME=root
DB_PASSWORD=

ROUTING_PROVIDER=osrm
OSRM_BASE_URL=https://router.project-osrm.org

LLM_PROVIDER=none
LLM_API_KEY=

SIMULATION_SPEED=5
DEFAULT_COST_PER_KM_MXN=1.25
MAX_CONCURRENT_ORDERS=2
```

No subir `.env` ni API keys al repositorio.

---

# 117. Configuración Laravel

Crear:

```text
config/courier.php
```

Ejemplo:

```php
<?php

return [
    'scoring' => [
        'hourly_profit' => 0.30,
        'net_profit' => 0.20,
        'destination_value' => 0.15,
        'batch_potential' => 0.15,
        'pickup_efficiency' => 0.10,
        'reliability' => 0.10,
    ],

    'simulation' => [
        'max_concurrent_orders' => 2,
        'planning_horizon_min' => 45,
        'cost_per_km_mxn' => 1.25,
    ],

    'optimization' => [
        'max_candidate_set_size' => 2,
        'max_candidates' => 100,
        'time_budget_ms' => 300,
    ],
];
```

Acceso:

```php
config('courier.scoring.hourly_profit');
```

Los secretos y URLs externas permanecen en `.env`; los pesos y reglas viven en `config/courier.php`.

---

# 118. Git strategy

Branches:

```text
main
dev
feature/courier-dashboard
feature/map
feature/simulator
feature/optimizer
feature/realtime
feature/explanation
```

Pull requests pequeñas.

No esperar hasta el final para integrar.

---

# 119. Commits

```text
feat: add order scoring
feat: add OSRM routing service
feat: add simulated surge event
test: validate net hourly rate
fix: reject plans exceeding shift
```

---

# 120. README mínimo

Debe incluir:

1. problema;
2. solución;
3. arquitectura;
4. screenshots;
5. stack;
6. cómo ejecutar;
7. cómo correr demo;
8. fórmulas;
9. baseline;
10. resultados reales.

---

# 120.1 Configuración completa del proyecto Laravel

El sistema completo vive en un único proyecto.

## Crear proyecto

```bash
composer create-project laravel/laravel courier-ai
cd courier-ai
```

## Configurar aplicación

```bash
cp .env.example .env
php artisan key:generate
```

## Instalar Livewire

```bash
composer require livewire/livewire
```

## Instalar frontend

```bash
npm install
```

Agregar dependencias JS necesarias para mapa y tiempo real:

```bash
npm install maplibre-gl laravel-echo pusher-js
```

> Laravel Reverb usa el protocolo compatible que Laravel Echo necesita para la comunicación en tiempo real.

## Instalar Reverb

```bash
php artisan install:broadcasting
```

Si la versión instalada de Laravel no ofrece ese comando, instalar/configurar Reverb siguiendo la documentación de la versión utilizada.

## Assets

En:

```text
resources/js/app.js
```

importar los módulos utilizados.

Ejemplo conceptual:

```javascript
import './bootstrap';
import 'maplibre-gl/dist/maplibre-gl.css';
import maplibregl from 'maplibre-gl';

window.maplibregl = maplibregl;
```

## Layout principal

```text
resources/views/layouts/app.blade.php
```

Debe contener:

```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    {{ $slot ?? '' }}

    @livewireScripts
</body>
</html>
```

## Vista principal

Ruta:

```php
Route::get('/courier', function () {
    return view('dashboard.index');
})->name('courier.dashboard');
```

Vista:

```blade
<x-layouts.app>
    <livewire:courier-dashboard />
</x-layouts.app>
```

## Diseño responsive

El dashboard debe estar diseñado mobile-first:

```text
Teléfono:
mapa arriba
recomendación principal
pedidos en lista

Desktop:
mapa izquierda
pedidos/recomendaciones derecha
métricas superiores
```

Así la misma aplicación funciona en:

- laptop del demo;
- tablet;
- navegador web responsive.

No existe una aplicación cliente separada.


---

# 121. Comandos de ejecución Laravel + MySQL

Crear la base de datos:

```sql
CREATE DATABASE courier_ai
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Configurar `.env`:

```env
APP_NAME=CourierAI
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=courier_ai
DB_USERNAME=root
DB_PASSWORD=TU_PASSWORD
```

Ejecutar migrations y seeders:

```bash
php artisan migrate
php artisan db:seed
```

Compilar assets:

```bash
npm install
npm run dev
```

Ejecutar Laravel:

```bash
php artisan serve
```

Para una demo cómoda, pueden usar:

```text
Terminal 1:
php artisan serve

Terminal 2:
npm run dev
```

Si usan Reverb:

```text
Terminal 3:
php artisan reverb:start
```

Si usan Queue:

```text
Terminal 4:
php artisan queue:work
```

Tests:

```bash
php artisan test
```

## Abrir la app

```text
http://127.0.0.1:8000/courier
```

## Demo en otro dispositivo de la misma red

Arrancar Laravel escuchando en la red local:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Luego abrir desde otro dispositivo:

```text
http://IP_DE_LA_COMPUTADORA:8000/courier
```

Esto permite enseñar la misma interfaz Laravel desde un teléfono sin desarrollar una aplicación web nativa.


# 122. Docker opcional

Servicios posibles:

```text
app (Laravel + Blade + Livewire)
mysql
reverb
osrm
```

Ejemplo conceptual:

```text
docker-compose.yml
├── app
├── mysql
└── osrm
```

Si el equipo ya tiene MySQL instalado localmente, no es obligatorio dockerizarlo durante el hackatón. La prioridad es mantener una demo estable.

---

# 123. Regla de oro para el hackatón

Primero hacer que funcione:

```text
simulator
+
ranking
+
baseline comparison
```

Después:

```text
map polish
+
LLM
+
advanced optimization
```

Una UI espectacular sin evidencia de mejor ganancia es menos fuerte que una demo sencilla que pruebe una mejora cuantitativa.

---

# 124. Criterios de aceptación por recomendación

Cada recomendación debe poder responder:

```text
¿cuánto paga?
¿cuánto cuesta?
¿cuánto tarda?
¿cuánto deja neto?
¿cuánto deja por hora?
¿qué distancia improductiva tiene?
¿dónde termina?
¿se combina con otro pedido?
¿qué riesgo tiene?
¿por qué ganó?
```

---

# 125. Contrato de confianza

El agente nunca deberá mostrar una afirmación sin respaldo en métricas.

Mal:

> “Esta zona probablemente estará llena de pedidos.”

Bien:

> “La zona tiene demand score 0.82 en el escenario actual.”

Mal:

> “Esta entrega es peligrosa.”

Bien:

> “La ruta está marcada como restringida por el escenario; se descarta.”

---

# 126. Success criteria internos

Antes del pitch:

- [ ] ningún crash durante 10 demos consecutivas;
- [ ] reset funciona;
- [ ] seed produce resultado reproducible;
- [ ] ranking cambia al activar road closure;
- [ ] baseline y AI comienzan iguales;
- [ ] ambos reciben los mismos pedidos;
- [ ] cálculos visibles cuadran;
- [ ] explicación utiliza cifras reales;
- [ ] pantalla final calcula delta correctamente;
- [ ] demo completa < 3 min.

---

# 127. Datos que no debemos inventar en el pitch

No afirmar sin evidencia:

- porcentaje de repartidores que aumentarían ingresos;
- ahorro promedio nacional;
- ingresos reales de Uber/Rappi/DiDi;
- tráfico en tiempo real;
- demanda real de plataformas;
- precisión ML que no medimos.

En su lugar:

> “En nuestro simulador controlado...”

y reportar resultados medidos.

---

# 128. Futuro modelo real

Si en el futuro existieran APIs autorizadas:

```text
Platform Offers
      ↓
Normalizer
      ↓
Courier AI
      ↓
Recommendation Overlay
```

Cada plataforma se normalizaría a:

```text
pay
pickup
dropoff
expiration
estimated time
```

---

# 129. Cierre

La esencia del proyecto es convertir una decisión aparentemente simple:

> “¿Acepto este pedido?”

en un problema formal:

> “¿Qué acción maximiza mi valor económico esperado durante el tiempo restante del turno, dadas mis restricciones y el estado actual de la ciudad?”

Courier AI utiliza:

- mapas reales;
- routing;
- economía unitaria;
- optimización combinatoria;
- rolling horizon;
- simulación;
- explicación con IA;

para responder esa pregunta de manera rápida, transparente y defendible.

---

# 130. Fuentes técnicas y fundamento

## Documento oficial del hackatón

**Infosys Challenge Tracks for HackMTY 2026 — Track 3: The Courier.**

El reto solicita explícitamente maximizar ganancias sobre un turno simulado considerando ofertas, tráfico, aceptación/rechazo, batching, rutas y cambios dinámicos.

## OpenStreetMap / OSRM

OSRM proporciona servicios HTTP de routing. Su API incluye `route`, `nearest`, `table`, `match`, `trip` y `tile`. El servicio `route` encuentra rutas entre coordenadas y `table` puede calcular matrices de duración/distancia útiles para el optimizador.

- https://project-osrm.org/

## Laravel

Laravel será la aplicación principal para vistas, API, persistencia, eventos, simulación, scoring, optimización y comunicación con servicios externos.

- https://laravel.com/docs

## Google OR-Tools

El documento oficial del reto lo menciona como recurso para routing/vehicle-routing. En nuestra arquitectura queda como una mejora opcional para escalar el solver, no como dependencia del MVP Laravel.

- https://developers.google.com/optimization/routing

## MapLibre GL JS

MapLibre GL JS permite renderizar mapas interactivos en el navegador, dibujar rutas, markers, overlays y capas sobre datos de OpenStreetMap.

Se integra directamente en las vistas Laravel mediante Vite/JavaScript.

- https://maplibre.org/

---

# 131. Decisión técnica recomendada final

Para el hackatón construir **un solo proyecto Laravel**:

```text
Laravel / PHP
    +
Blade
    +
Livewire
    +
Alpine.js
    +
Vite
    +
MapLibre GL JS
    +
OpenStreetMap / OSRM
    +
MySQL 8.x
    +
Laravel Reverb (opcional pero recomendado)
    +
Laravel OptimizationService
    +
deterministic scoring
    +
LLM explanation layer
```

Arquitectura:

```text
Browser
  ↓
Laravel Blade / Livewire
  ↓
Services
  ├── ShiftService
  ├── SimulatorService
  ├── RoutingService
  ├── ScoringService
  ├── OptimizationService
  ├── DemandService
  └── ExplanationService
  ↓
MySQL / OSRM / LLM
```

Optimización:

```text
rolling horizon
+
candidate subset enumeration
+
pickup-and-delivery routing
+
hard constraints
+
economic utility
```

Comparación:

```text
Highest-Pay Greedy Baseline
vs
Courier AI Agent
```

Demo:

```text
/courier
    ↓
fresh seeded shift
    +
5 simultaneous offers
    +
batch recommendation
    +
road closure or surge
    +
live re-optimization
    +
final profit comparison
```

## Resultado arquitectónico

El MVP utiliza una sola aplicación y una sola base de código.

Componentes:

```text
Laravel
Blade
Livewire
Alpine.js
Vite
MySQL
MapLibre GL JS
OSRM
Reverb/Echo (si se activa tiempo real)
```

El MVP completo será:

> **Laravel monolith + Blade/Livewire + MySQL + MapLibre/OSRM + motor de optimización en PHP.**

Esta decisión reduce integración, acelera el hackatón y mantiene toda la lógica, las vistas y la API en una sola base de código.

Si el equipo logra ejecutar esa historia completa de forma estable, estará atacando directamente los puntos centrales del reto: **Results, Judgment, Feasibility y Clarity**.
