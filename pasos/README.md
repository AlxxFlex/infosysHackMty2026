# Guía de ejecución de los pasos

Esta carpeta convierte la especificación de Courier AI en una secuencia ejecutable. El orden de `00-orden-de-desarrollo.md` es obligatorio.

## Cómo ejecutar un paso

1. Leer `AGENTS.md` completo.
2. Leer `context_laravel_fullstack.md` completo.
3. Leer `00-orden-de-desarrollo.md`.
4. Leer `pasos/00-estructura-objetivo.md`.
5. Leer únicamente el archivo del paso actual.
6. Inspeccionar el repositorio y anotar qué criterios ya se cumplen.
7. Implementar sólo lo que exige el paso actual.
8. Ejecutar las pruebas indicadas y toda la regresión existente.
9. Revisar migrations, compilación y ausencia de secretos.
10. Detenerse y reportar con el formato de `AGENTS.md`.

## Puertas entre pasos

- Un paso no comienza si el anterior no cumple todos sus criterios de aceptación.
- Una funcionalidad futura sólo puede adelantarse si Laravel no compila sin ella; debe ser el mínimo stub posible y quedar documentado.
- Los ejemplos de nombres de archivos pueden ajustarse al código existente, pero no se puede cambiar la separación Blade/Livewire → Services → Models/APIs.
- Los tests de cada paso se conservan. El paso 18 agrega cobertura integral; no sustituye las pruebas anteriores.

## Fuente de verdad

En caso de conflicto:

1. `AGENTS.md` define la forma de trabajo.
2. `00-orden-de-desarrollo.md` define el orden.
3. El archivo del paso define su alcance y aceptación.
4. `context_laravel_fullstack.md` aporta la visión funcional y ejemplos.

## Alcance de esta carpeta

Los pasos 01 y 02 ya están definidos como prerrequisitos externos. Esta carpeta detalla la ejecución desde el paso 03 hasta el 20. El paso 03 debe comprobar explícitamente que Laravel 12 y MySQL están operativos antes de crear el esquema.
