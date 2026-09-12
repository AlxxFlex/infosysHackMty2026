# Paso 20 — Hardening, README y entrega final

## Objetivo

Preparar una entrega reproducible, segura y demostrable. No añadir características grandes; corregir integración, documentación, estabilidad y presentación.

## Puerta de entrada

- Paso 19 completo.
- Suite integral verde y demo funcional.

## Hardening técnico

- Validar configuración al arrancar con mensajes accionables.
- Confirmar timeouts/retries de OSRM y LLM.
- Confirmar límites de candidatos, payloads, rate limit e idempotencia.
- Revisar transacciones y locks en tick, accept, event y reset.
- Revisar logs para evitar secretos o payloads innecesarios.
- Mantener fallbacks de OSRM, polling y explicación determinista.
- Manejar estados vacíos, baseline cero, seed inválida y ausencia de rutas.
- Eliminar código muerto, dumps, TODOs bloqueantes y rutas de debug no protegidas.
- Ejecutar Pint y build de producción.

## Seguridad y secretos

- Crear/verificar `.env.example` completo y seguro.
- Confirmar `.env`, credenciales, tokens y artefactos locales ignorados.
- Buscar patrones de API keys y contraseñas en archivos versionados.
- Confirmar que `APP_DEBUG=false` sea la recomendación de producción/demo pública.
- No incluir resultados falsos, capturas con credenciales ni datos atribuidos a plataformas.

## README final

Reemplazar el README de Laravel por documentación del proyecto:

1. problema y propuesta;
2. qué datos son reales y cuáles simulados;
3. arquitectura y responsabilidades;
4. stack y requisitos exactos;
5. instalación de PHP/Composer/Node/MySQL;
6. creación/configuración de base;
7. migrations/seeders/build;
8. ejecución de Laravel, queue y Reverb;
9. modo sin Reverb/LLM/OSRM;
10. ruta `/courier` y panel demo;
11. guion de demo;
12. fórmulas, baseline y optimización;
13. cómo correr tests y benchmark;
14. resultados medidos, con escenario/seeds/configuración;
15. limitaciones y siguientes pasos.

No publicar un porcentaje de mejora hasta ejecutar el benchmark final.

## UX final

- Revisar móvil, tablet y desktop.
- Confirmar teclado, foco, contraste, labels y reduced motion razonable.
- Mostrar loading/error/empty states.
- Formatear MXN, km, minutos, porcentajes y zona horaria consistentemente.
- Toda cifra esperada, simulada o fallback lleva su etiqueta.
- El mapa no bloquea la comprensión si no carga.

## Verificación final obligatoria

1. Instalar desde cero siguiendo README en un entorno limpio.
2. Ejecutar migrations y seeders en MySQL 8.
3. Ejecutar `php artisan test`.
4. Ejecutar `vendor/bin/pint --test`.
5. Ejecutar `npm run build`.
6. Generar caches Laravel compatibles.
7. Ejecutar el benchmark documentado y copiar únicamente resultados reales.
8. Ejecutar diez demos consecutivas con reset entre ellas y registrar fallos.
9. Probar una demo con OSRM caído, LLM none y Reverb apagado.
10. Confirmar demo completa menor a tres minutos.

## Criterios de aceptación final

- `/courier` permite iniciar, observar ofertas, recomendar batch, explicar, aceptar, avanzar, reaccionar a evento y terminar.
- Baseline y Courier AI comparten escenario/seed y se comparan con métricas realizadas.
- Mapa y routing funcionan con fallback explícito.
- Polling, explicación determinista y flujo principal funcionan sin servicios opcionales.
- Tests, formatter, build, migrations y caches pasan.
- Diez demos consecutivas no presentan crashes.
- README permite a otra persona instalar y reproducir la demo.
- No hay secretos ni afirmaciones no sustentadas.
- Todos los criterios de `AGENTS.md`, del orden y de pasos 03–19 permanecen cumplidos.

## Entrega

Conservar evidencia de comandos/resultados ejecutados y responder exactamente con el formato de finalización de `AGENTS.md`.

## Commit sugerido

`docs: harden and document final Courier AI demo`
