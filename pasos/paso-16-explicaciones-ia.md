# Paso 16 — Explicaciones deterministas e IA opcional

## Objetivo

Explicar por qué ganó un plan usando sólo métricas calculadas. La explicación determinista es obligatoria; el proveedor LLM es una mejora opcional y nunca decide.

## Puerta de entrada

- Paso 15 completo.
- Recomendaciones contienen facts, alternativas y reason codes suficientes.

## Contrato de facts

Crear un payload inmutable con:

- pedidos seleccionados;
- neto esperado, duración, distancia y tasa/hora;
- pickup/deadhead;
- ahorro de batch y route overlap cuando existan;
- demanda/future value claramente estimados;
- riesgo y restricciones;
- mejor alternativa rechazada y diferencia cuantitativa;
- provider/fallback de routing;
- versión de configuración.

Nunca pedir al LLM que derive cifras o elija pedidos.

## Explicación determinista

`ExplanationService` debe poder producir siempre:

- resumen de una o dos frases;
- lista de métricas principales;
- razón principal de selección;
- razón de pérdida de la mejor alternativa;
- mensajes específicos para batch, cierre, surge y final de turno;
- texto correcto cuando faltan facts opcionales.

Usar templates asociados a `ReasonCode`, no texto disperso en Blade.

## Proveedor LLM opcional

- Definir interfaz/adaptador para que `LLM_PROVIDER=none` sea válido.
- Enviar prompt de sistema restrictivo y facts JSON.
- Timeout corto, salida limitada y sin secretos.
- Ejecutar mediante Job cuando sea posible; el ranking llega primero.
- Validar que la salida no incluya pedidos o números ajenos a los facts.
- Ante error, timeout o validación fallida conservar el texto determinista.
- No registrar API keys ni prompts con datos sensibles.

No agregar una clave real. La aplicación completa debe pasar con provider `none`.

## UI “¿Por qué?”

- Botón accesible en el plan recomendado.
- Panel/modal Alpine o Livewire con facts visibles y texto.
- Indicar si la explicación es determinista o generada por IA.
- Separar “ganancia esperada” de “ganancia realizada”.
- Mostrar la alternativa comparada.
- El panel no puede bloquear aceptar o avanzar el turno.

## Pruebas obligatorias

- Cada reason code relevante produce texto determinista.
- Facts de single y batch se presentan sin inventar valores.
- Alternativa incluye diferencias calculadas.
- Provider `none`, timeout, 500 y JSON inválido usan fallback.
- HTTP fake confirma prompt y ausencia de capacidad decisoria.
- Validador rechaza IDs/números no presentes en facts.
- Job no modifica ranking ni plan seleccionado.
- Componente Why funciona sin JavaScript crítico/LLM.
- Regresión completa.

## Criterios de aceptación

- Toda recomendación responde cuánto paga, cuesta, tarda, deja neto, deja por hora y por qué ganó.
- La mejor alternativa tiene una razón cuantitativa de pérdida.
- La app funciona totalmente sin proveedor LLM.
- El LLM no escribe campos decisorios ni altera estado.
- No se presentan afirmaciones sin respaldo.

## Commit sugerido

`feat: explain optimized plans with deterministic AI-safe facts`
