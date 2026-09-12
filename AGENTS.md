# Instrucciones para el agente de IA

> Proyecto: **Courier AI — HackMTY 2026 / Infosys Track 3**

> Stack obligatorio: **Laravel + Blade + Livewire + Alpine.js + MySQL + MapLibre GL JS + OSRM**.

> Regla: **no avanzar al siguiente paso hasta cumplir todos los criterios de aceptación del paso actual**.

## Forma de trabajo obligatoria

El agente debe trabajar **un archivo `pasos/paso-XX-*.md` a la vez**.

Antes de modificar código debe:

1. leer `context_laravel_fullstack.md`;
2. leer `00-orden-de-desarrollo.md`;
3. leer `pasos/00-estructura-objetivo.md`;
4. leer el archivo del paso actual;
5. inspeccionar el código existente;
6. identificar qué ya está implementado;
7. implementar únicamente lo necesario para completar ese paso.

No debe adelantar funcionalidades de pasos futuros salvo que sean estrictamente necesarias para compilar.

## No cambiar el stack

No reemplazar Laravel, Blade/Livewire, MySQL, MapLibre u OSRM sin instrucción explícita.

No crear una app móvil separada ni un frontend React/Vue separado.

## Arquitectura obligatoria

```text
Blade / Livewire
      ↓
Services
      ↓
Models / MySQL / APIs externas
```

- Blade presenta.
- Livewire coordina estado de UI.
- Controllers coordinan HTTP.
- Services contienen lógica.
- Models representan persistencia.
- Events notifican cambios.
- Jobs ejecutan trabajo asíncrono.

Nunca colocar fórmulas de optimización en Blade.
Nunca concentrar la lógica en Controllers.

## Definición de terminado

Un paso sólo está terminado cuando:

- el código compila;
- las migrations necesarias funcionan;
- los tests del paso pasan;
- no se rompen tests anteriores;
- se cumplen todos los criterios de aceptación;
- no se han agregado secretos al repositorio.

Al terminar responder:

```text
PASO XX COMPLETADO

Archivos creados:
- ...

Archivos modificados:
- ...

Pruebas ejecutadas:
- ...

Resultado:
- ...

Pendientes:
- ninguno

Commit sugerido:
- ...
```

Si existe un bloqueo, detenerse y explicarlo.

## Regla de IA

El LLM **no decide qué pedidos aceptar**.

La decisión proviene de:

```text
rutas
+ métricas
+ reglas
+ scoring
+ optimización
```

El LLM sólo explica una decisión ya calculada.

## Datos simulados

No presentar pagos, surge, demanda o tráfico simulado como datos reales de Uber, DiDi o Rappi.

## Prioridad del hackatón

```text
1. simulación
2. cálculos
3. scoring
4. optimización
5. baseline
6. UI
7. mapa
8. eventos dinámicos
9. explicación IA
10. tiempo real/polish
```
