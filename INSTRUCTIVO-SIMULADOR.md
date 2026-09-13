# Instructivo para operar el simulador Courier AI

Este documento explica cómo ejecutar una demostración completa desde el
navegador. Todos los pagos, pedidos, seeds, eventos y métricas del escenario
son simulados.

## 1. Preparar la aplicación

Desde la carpeta del proyecto:

```bash
php artisan serve
```

Si aún no instalaste el proyecto, sigue primero el [README](README.md). Para
una operación sin servicios opcionales, verifica en `.env`:

```env
BROADCAST_CONNECTION=log
LLM_PROVIDER=none
ROUTING_PROVIDER=fallback
```

Abre `http://localhost:8000/courier`. El panel de control de la demostración
está en `http://localhost:8000/demo/control`.

## 2. Operación desde `/courier`

### Iniciar el turno

1. Selecciona el escenario `Demo normal`.
2. Escribe una seed entera no negativa, por ejemplo `42`.
3. Pulsa **Iniciar turno**.
4. El estado cambiará a `RUNNING` y aparecerán los dos agentes comparables:
   `COURIER_AI` y `BASELINE`.

La misma seed produce el mismo escenario. No confundas la hora simulada con la
hora real del sistema.

### Avanzar el reloj

Pulsa **Avanzar 5 min** para publicar ofertas y mover el turno. También puedes
esperar al polling automático mientras el estado sea `RUNNING`.

Los estados disponibles son:

- **Pausar**: detiene el reloj.
- **Reanudar**: continúa un turno pausado.
- **Avanzar N min**: aplica un tick idempotente de `N` minutos.

### Leer el feed y la recomendación

En **Feed de ofertas** observa pago, distancia, tiempo, neto, tasa horaria,
riesgo y score. Una oferta puede ser factible aunque su pago bruto no sea el
mayor.

Pulsa **Calcular recomendación** para que Courier AI evalúe singles y batches
factibles. El resultado muestra:

- neto esperado y costo operativo;
- duración, distancia y pickup;
- tasa neta por hora y riesgo estimado;
- pedidos seleccionados y, si aplica, ahorro/solapamiento del batch.

Pulsa **¿Por qué?** para ver los hechos usados y la alternativa rechazada. La
explicación determinista describe el cálculo; nunca decide qué pedido aceptar.

### Aceptar y completar

1. Pulsa **Aceptar plan**.
2. Avanza uno o varios ticks.
3. Revisa las métricas realizadas de Courier AI y baseline.
4. Cuando termines, pulsa **Terminar turno**.
5. Abre **Ver resultados comparativos del turno**.

Los importes están en MXN; distancias en km; duraciones en minutos; las horas
del turno usan la zona horaria configurada en `SIMULATION_TIMEZONE`.

## 3. Operación desde `/demo/control`

Este panel está diseñado para conducir una historia reproducible rápidamente.

1. Pulsa **Iniciar escenario**. Se preparan seis ofertas y se muestra la seed.
2. Usa **x1**, **x5** o **x20** para cambiar la velocidad de los ticks.
3. Pulsa **Avanzar tick** para aplicar el siguiente minuto simulado.
4. Pulsa **Activar surge**, **Activar cierre** o **Retraso restaurante**.
5. Avanza otro tick para aplicar el evento y observa el aviso de reoptimización
   en `/courier`.
6. Pulsa **Terminar turno** y abre la comparación.

Los eventos se programan para el siguiente tick y son idempotentes: pulsar dos
veces el mismo botón no duplica el evento. El panel no permite cambiar la seed
con un run activo.

### Reiniciar de forma segura

Pulsa **Resetear run actual** y después **Confirmar reset**. Sólo se elimina el
run visible y sus datos relacionados. Usa **Nueva seed** después del reset para
preparar otra variación reproducible.

## 4. Guion rápido para presentar la demo

1. Abre `/demo/control` y pulsa **Iniciar escenario**.
2. Abre `/courier`, avanza 5 minutos y calcula la recomendación.
3. Muestra **¿Por qué?**, el batch y la etiqueta `ESCENARIO SIMULADO`.
4. Acepta el plan y avanza otro tick.
5. En el panel activa surge o cierre; vuelve a avanzar.
6. Termina el turno y muestra Courier AI contra baseline.

El flujo está pensado para durar menos de tres minutos. El mapa es auxiliar:
si MapLibre u OSRM no cargan, continúa con las métricas y la etiqueta de ruta
`estimación fallback`.

## 5. Qué significa cada resultado

- **Esperado**: proyección calculada antes de aceptar el plan.
- **Realizado**: resultado persistido después de ejecutar entregas.
- **Fallback**: OSRM no respondió o está desactivado; la distancia y duración
  provienen de la geometría determinista y llevan advertencia.
- **Baseline**: agente de comparación que elige el mayor pago bruto factible y
  no forma batches.
- **N/D**: no se calculó porcentaje porque el neto baseline fue cero.

La optimización usa ganancia neta, tiempo, riesgo, demora, bonus de batch y
posición futura. El LLM, si se configura, sólo redacta una explicación de los
hechos ya calculados.

## 6. Solución de problemas

### No inicia el turno

Comprueba base de datos y configuración:

```bash
php artisan migrate --seed
php artisan courier:check-config
```

La seed debe ser un entero mayor o igual a cero y el escenario debe existir,
normalmente `demo_normal`.

### No aparece el mapa o falla OSRM

No bloquea la demo. Usa explícitamente:

```env
ROUTING_PROVIDER=fallback
```

Después limpia la configuración y reinicia Laravel:

```bash
php artisan optimize:clear
php artisan serve
```

### No llega una explicación LLM

Usa la explicación determinista. El modo recomendado para una demo autónoma es:

```env
LLM_PROVIDER=none
```

### No funciona tiempo real

El polling del dashboard es suficiente. Reverb sólo es opcional; si quieres
activarlo, ejecuta en otra terminal:

```bash
php artisan reverb:start
```

### El panel devuelve 404

En `local` y `testing` el panel está disponible directamente. En otro entorno
configura `DEMO_ACCESS_TOKEN` y envía el header `X-Demo-Token` correspondiente.

## 7. Repetir una demo

Finaliza y resetea el run antes de iniciar otro. Conserva la seed si necesitas
reproducir exactamente el resultado; usa **Nueva seed** para explorar otra
ejecución. Nunca presentes valores simulados como datos de una plataforma real.
