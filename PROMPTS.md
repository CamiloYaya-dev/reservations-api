# Uso de IA: estrategia, prompts y validación técnica

Este archivo documenta los prompts principales utilizados para dirigir el desarrollo de la prueba técnica. El objetivo no fue delegar el problema de forma abierta a la IA, sino convertir los requisitos de negocio en instrucciones técnicas concretas, controlar el alcance, revisar críticamente lo generado y validar el resultado con evidencia ejecutable.

La interacción se organizó en seis etapas: análisis, implementación, validación funcional, auditoría técnica, correcciones y documentación final.

## Estrategia de trabajo con IA

| Etapa | Objetivo | Control aplicado |
| --- | --- | --- |
| 1. Análisis | Convertir el enunciado en requisitos técnicos y detectar los riesgos principales. | No implementar antes de entender reglas de negocio, concurrencia, idempotencia y criterios de aceptación. |
| 2. Implementación | Construir una solución mínima, ejecutable y reproducible. | Definir stack, restricciones del entorno, entregables concretos y límites de alcance. |
| 3. Validación | Comprobar el comportamiento observable de la API y la persistencia. | Verificar respuestas HTTP, stock final, idempotencia y concurrencia con resultados reales. |
| 4. Revisión crítica | Intentar encontrar fallos en la solución ya generada. | Revisar transacciones, locks, errores, seguridad, cobertura y reproducibilidad sin asumir que los tests garantizan corrección. |
| 5. Correcciones | Aplicar únicamente las mejoras aceptadas. | Delimitar cambios, preservar decisiones rechazadas y exigir nuevas pruebas de regresión. |
| 6. Documentación | Explicar arquitectura, ejecución, decisiones y evidencia. | Documentar solamente lo comprobado y separar límites o pendientes de los resultados validados. |

---

## Prompt 1 — análisis técnico antes de implementar

**Objetivo:** transformar el enunciado de la prueba en un diseño técnico verificable antes de escribir código.

```text
Lee completamente el documento adjunto de la prueba técnica y analiza el problema antes de implementar cualquier código.

Quiero resolver la prueba utilizando IA como herramienta de ingeniería, pero la responsabilidad de las decisiones técnicas será mía. Por eso necesito que primero conviertas el enunciado en requisitos concretos y verificables.

Contexto:
- El cargo es Ingeniero Senior.
- El foco principal de la prueba parece ser backend y base de datos; no veo un requisito de frontend.
- El problema más sensible es la concurrencia sobre inventario/reservas.
- Podemos utilizar Docker y PHPUnit si aportan reproducibilidad o capacidad de validación.
- Quiero mantener toda la solución y su proceso de desarrollo en máximo 8 prompts principales.

Antes de implementar:

1. Resume las reglas de negocio y separa requisitos funcionales de no funcionales.
2. Identifica explícitamente los riesgos de concurrencia:
   - sobreventa;
   - solicitudes simultáneas;
   - descuentos o modificaciones duplicadas;
   - idempotencia;
   - consistencia entre stock y reserva;
   - condiciones de carrera.
3. Propón una estrategia transaccional para MySQL/InnoDB y explica por qué evita esos problemas.
4. Define qué restricciones deben existir también en la base de datos y no solamente en PHP.
5. Evalúa si Docker realmente aporta valor para esta prueba. Si lo propones, justifica qué problema resuelve.
6. Evalúa PHPUnit y define qué parte debe cubrirse con pruebas unitarias y qué parte necesita pruebas de integración contra MySQL real.
7. Propón una arquitectura mínima. No agregues capas, patrones o dependencias que no tengan una justificación clara.
8. Define los endpoints necesarios, contratos de entrada/salida y códigos HTTP esperados.
9. Enumera supuestos o ambigüedades del enunciado. No inventes requisitos silenciosamente.
10. Define criterios de aceptación verificables que podamos usar después para decidir si la solución está terminada.

No implementes todavía. Quiero revisar primero el diseño, especialmente la estrategia de concurrencia, transacciones e idempotencia.

Al final entrega:
- requisitos interpretados;
- riesgos;
- arquitectura propuesta;
- estrategia de concurrencia;
- esquema lógico de datos;
- estrategia de pruebas;
- decisiones que debo aprobar antes de implementar.
```

### Resultado esperado de este prompt

El análisis debe conducir a una solución pequeña y justificable, basada en PHP/PDO y MySQL/InnoDB, con transacciones cortas, bloqueo de la fila de producto durante la modificación de stock y una restricción única que respalde la idempotencia. Docker y PHPUnit deben utilizarse como medios de reproducibilidad y validación, no como complejidad decorativa.

---

## Prompt 2 — implementación ejecutable y reproducible

**Objetivo:** convertir el diseño aprobado en una solución que pueda ejecutarse y probarse desde Windows 11.

```text
Implementa la solución siguiendo el análisis anterior y manteniendo el alcance mínimo necesario para satisfacer la prueba.

Mi entorno:
- Windows 11.
- Actualmente no tengo Docker instalado.
- La API debe poder probarse desde Postman o curl.
- La base de datos debe entregarse como un script SQL importable.
- El SQL inicial debe contener únicamente estructura: tablas, índices, claves, restricciones y relaciones. No incluyas datos de prueba en el esquema principal.

Requisitos de implementación:

1. Usa PHP con PDO y MySQL/InnoDB según el diseño aprobado.
2. Implementa la operación crítica de reserva dentro de una transacción corta.
3. El stock debe bloquearse/modificarse de manera que dos solicitudes concurrentes no puedan vender las mismas unidades.
4. La idempotencia no puede depender solamente de una comprobación en PHP: debe estar respaldada por una restricción única en base de datos.
5. Define claramente qué ocurre si:
   - no existe el producto;
   - la cantidad es inválida;
   - no hay stock suficiente;
   - se repite la misma solicitud idempotente;
   - ocurre un deadlock o un error transitorio de MySQL;
   - ocurre un error interno no recuperable.
6. No expongas mensajes internos, credenciales ni detalles sensibles en las respuestas HTTP.
7. Mantén el código pequeño y legible. Evita frameworks o patrones adicionales si no son necesarios.
8. Incluye PHPUnit.
9. Separa pruebas unitarias de pruebas de integración que realmente requieran MySQL.
10. Incluye pruebas para reglas de negocio, idempotencia y concurrencia.
11. Si una prueba requiere comportamiento real del motor —por ejemplo locks, duplicate key o deadlocks— no la reemplaces por un mock si eso reduce el valor de la prueba.
12. Incluye Docker/Compose si es la opción más reproducible para PHP + MySQL + PHPUnit en Windows.

Entregables:

- código fuente completo;
- docker-compose y Dockerfiles necesarios;
- composer.json;
- script SQL de esquema;
- pruebas;
- README con instalación y ejecución;
- ejemplos curl/Postman;
- comandos para ejecutar la suite de pruebas.

Las instrucciones para Windows deben ser copiables y seguir un orden exacto, por ejemplo:

1. instalar/verificar Docker Desktop;
2. verificar que Docker esté operativo;
3. levantar servicios;
4. crear/importar el esquema;
5. iniciar la API;
6. ejecutar una petición;
7. ejecutar PHPUnit;
8. detener/limpiar el entorno.

No afirmes que una prueba fue ejecutada si no la ejecutaste realmente. Separa en la documentación:
- verificaciones ejecutadas;
- verificaciones que debo ejecutar yo en Windows;
- resultado esperado de cada verificación.

La salida de este prompt debe ser una solución ya ejecutable, no solamente fragmentos de código o pseudocódigo.
```

---

## Prompt 3 — validación funcional y de concurrencia

**Objetivo:** comprobar con resultados observables que la implementación cumple las invariantes del negocio.

```text
Ahora quiero validar la solución antes de considerarla correcta.

No cambies código todavía. Primero define y ejecutemos una batería de comprobaciones reproducibles sobre la implementación actual.

Necesito verificar como mínimo:

1. Reserva válida:
   - crear una reserva de N unidades;
   - comprobar código HTTP y cuerpo;
   - consultar la base de datos y verificar que el stock se redujo exactamente N.

2. Idempotencia:
   - repetir exactamente la misma solicitud con la misma clave idempotente;
   - comprobar que no se crea una segunda reserva;
   - comprobar que el stock no vuelve a disminuir;
   - confirmar que se devuelve el mismo reservation_id o el comportamiento definido por el contrato.

3. Stock insuficiente:
   - solicitar más unidades de las disponibles;
   - comprobar que no cambia el stock;
   - comprobar que no queda una reserva parcial.

4. Concurrencia:
   - ejecutar varias solicitudes simultáneas contra el mismo producto;
   - usar un stock suficientemente pequeño para que no todas puedan aprobar;
   - verificar que la suma de unidades reservadas nunca supera el stock inicial;
   - verificar que el stock nunca queda negativo.

5. Persistencia:
   - indicar consultas SQL concretas para comprobar reservas, claves idempotentes y stock final.

6. Suite automatizada:
   - ejecutar PHPUnit;
   - reportar número de pruebas y aserciones;
   - distinguir pruebas unitarias de integración.

7. Entorno Windows/Docker:
   - dame los comandos exactos que debo ejecutar;
   - si te comparto la salida real, interprétala y compárala contra los criterios de aceptación;
   - no des por válida una prueba únicamente porque el comando terminó sin lanzar una excepción.

Para cada comprobación indica:
- precondición;
- comando/petición;
- resultado esperado;
- evidencia que debo revisar;
- qué fallo técnico revelaría un resultado distinto.

Si aparece una diferencia entre lo esperado y lo ejecutado, trátala como un hallazgo y no como un problema de presentación hasta demostrarlo.
```

### Evidencia obtenida durante la validación

Se verificó una reserva y su repetición idempotente: la primera respondió HTTP 201 y la repetición HTTP 200 conservando el mismo `reservation_id`. La consulta posterior mostró una única reserva y stock 7 después de reservar 3 unidades sobre un stock inicial de 10.

La primera ejecución completa de PHPUnit en Docker/Windows reportó **35 pruebas y 91 aserciones aprobadas**. La ejecución también permitió detectar problemas operativos de presentación de PowerShell que posteriormente se incluyeron en la auditoría.

---

## Prompt 4 — auditoría crítica de la solución generada

**Objetivo:** revisar la implementación intentando demostrar dónde puede fallar, en lugar de limitarse a confirmar que funciona.

```text
Haz una revisión crítica completa de la solución actual como si fueras el revisor técnico de una prueba para Ingeniero Senior.

No quiero una validación complaciente. Parte de la premisa de que la implementación puede contener errores aunque PHPUnit esté en verde.

Revisa específicamente:

1. Reglas de negocio
   - ¿Existe alguna ruta que permita sobreventa?
   - ¿Una solicitud repetida puede descontar stock dos veces?
   - ¿Puede existir una reserva sin el descuento correspondiente o viceversa?

2. Transacciones y concurrencia
   - límites exactos de cada transacción;
   - orden de locks;
   - SELECT ... FOR UPDATE o mecanismo equivalente;
   - nivel de aislamiento relevante;
   - rollback en todas las ramas de error;
   - posibilidad de deadlocks;
   - comportamiento ante reintentos.

3. Idempotencia
   - condición de carrera entre comprobar y crear;
   - respaldo mediante constraint UNIQUE;
   - tratamiento de MySQL 1062;
   - respuesta cuando dos requests con la misma clave llegan simultáneamente.

4. Manejo de errores
   - diferencia entre error de cliente, conflicto, error interno y error transitorio;
   - uso correcto de 4xx, 500 y 503;
   - exposición accidental de información sensible.

5. Base de datos
   - tipos;
   - índices;
   - foreign keys;
   - constraints;
   - consistencia del esquema con el código.

6. Tests
   - qué invariantes están realmente demostradas;
   - qué ramas solamente parecen cubiertas;
   - si existe una prueba que fuerce realmente un duplicate key 1062;
   - si existe una prueba que produzca un deadlock 1213 real;
   - posibles falsos positivos.

7. Docker y reproducibilidad
   - versiones;
   - healthchecks;
   - orden de arranque;
   - limpieza del entorno;
   - diferencias relevantes entre Linux y Windows.

8. Scripts de Windows/PowerShell
   - códigos de salida;
   - encoding;
   - stderr;
   - limpieza aun cuando fallen pruebas;
   - salida que pueda confundirse con un error aunque PHPUnit haya aprobado.

9. Seguridad y mantenibilidad
   - secretos;
   - validación de input;
   - SQL injection;
   - dependencias innecesarias;
   - complejidad accidental.

Para cada hallazgo entrega:
- ID;
- severidad;
- archivo/componente afectado;
- explicación técnica;
- escenario de reproducción;
- impacto;
- corrección mínima recomendada;
- prueba que debería impedir una regresión.

Separa:
- errores que deben corregirse;
- mejoras recomendadas;
- mejoras opcionales.

No propongas reescribir la solución desde cero si un cambio localizado resuelve el problema.
```

### Hallazgos identificados

| ID | Hallazgo | Clasificación |
| --- | --- | --- |
| R1 | El manejador general devolvía 503 también ante errores permanentes. | Corrección necesaria |
| R2 | Las pruebas no forzaban explícitamente una colisión MySQL 1062 ni un deadlock 1213 real. | Corrección necesaria |
| R3 | La evidencia de ejecución en Windows quedaba ignorada/no reflejada correctamente en la documentación. | Corrección necesaria |
| R4 | El script de PowerShell podía mostrar progreso como `NativeCommandError`, presentar problemas de encoding y no garantizar toda la limpieza. | Corrección necesaria |
| R5 | Las imágenes Docker usaban etiquetas de versión en vez de digests inmutables. | Mejora opcional |

La auditoría no encontró una ruta reproducible de sobreventa o doble descuento dentro de los supuestos documentados, pero sí detectó diferencias entre “la funcionalidad parece correcta” y “las ramas de recuperación están explícitamente probadas”.

---

## Prompt 5 — aplicación controlada de correcciones

**Objetivo:** aplicar únicamente las decisiones aceptadas después de la auditoría y volver a demostrar el comportamiento.

```text
Aplica R1, R2, R3 y R4. No apliques R5.

Quiero una corrección incremental sobre la solución existente, no una reimplementación.

Decisiones:

R1:
- diferencia errores internos permanentes de errores transitorios;
- no respondas 503 para cualquier excepción;
- conserva logging útil para diagnóstico sin filtrar detalles sensibles al cliente.

R2:
- agrega pruebas de integración que fuercen de forma real las ramas relevantes de MySQL;
- debe existir evidencia de una colisión 1062;
- debe existir evidencia de recuperación ante un deadlock 1213 real;
- no simules estos dos casos con mocks si el objetivo es comprobar el comportamiento del motor.

R3:
- conserva la evidencia relevante de ejecución;
- actualiza la documentación para que el estado de las pruebas coincida con lo realmente ejecutado;
- evita declarar como pendiente una validación que ya fue realizada.

R4:
- corrige el script de PowerShell para que:
  - preserve correctamente el exit code;
  - no convierta salida normal/progreso en un falso NativeCommandError;
  - utilice UTF-8 de forma consistente;
  - ejecute limpieza mediante finally o mecanismo equivalente aunque falle la suite;
  - deje una evidencia legible de la ejecución.

R5:
- no fijes las imágenes Docker mediante digest.
- conserva las etiquetas de versión actuales.
- documenta esta decisión como una limitación de reproducibilidad, no como un error funcional.

Entrégame únicamente:
1. lista de archivos que cambian;
2. contenido completo de cada archivo que debo reemplazar;
3. explicación breve del motivo de cada cambio;
4. pruebas nuevas o modificadas;
5. comandos exactos para volver a ejecutar la validación;
6. criterios concretos para considerar que R1-R4 quedaron cerrados.

Después de aplicar los archivos, la solución debe volver a ejecutar toda la suite existente además de las nuevas pruebas. No elimines pruebas para conseguir que la suite pase.
```

### Validación después de las correcciones

La ejecución posterior en Windows/Docker reportó **38 pruebas y 154 aserciones aprobadas**. La evidencia incluyó las pruebas de colisión `1062` y recuperación de un deadlock `1213` real. La salida final mostró caracteres correctos, ausencia del falso `NativeCommandError` y limpieza/parada de los contenedores utilizados por las pruebas.

---

## Prompt 6 — documentación de arquitectura y cierre técnico

**Objetivo:** hacer que la entrega final explique qué se construyó, por qué se construyó así y cómo verificarlo.

```text
Actualiza la documentación final de la solución utilizando únicamente decisiones y resultados que estén respaldados por el código o por evidencia de ejecución.

Quiero que el README permita a otro ingeniero entender, ejecutar y evaluar la solución sin depender de esta conversación.

Incluye:

1. Objetivo del sistema y reglas de negocio relevantes.
2. Stack utilizado y justificación breve.
3. Instrucciones de instalación y ejecución en Windows 11 con Docker.
4. Creación/importación del esquema.
5. Ejemplos curl para probar la API.
6. Cómo ejecutar la suite completa.
7. Qué pruebas son unitarias y cuáles son de integración.
8. Resultados de validación disponibles y ubicación de la evidencia.
9. Explicación de la estrategia de concurrencia:
   - transacción;
   - lock del producto;
   - actualización de stock;
   - creación de reserva;
   - commit/rollback.
10. Explicación de idempotencia y del constraint UNIQUE.
11. Comportamiento ante duplicate key 1062 y deadlock 1213.
12. Manejo de errores HTTP.
13. Supuestos y límites conocidos.
14. Decisiones de diseño relevantes, incluyendo que no se aplicó R5.

Agrega diagramas Mermaid basados en C4:

- C1: contexto del sistema;
- C2: contenedores/componentes ejecutables;
- C3: componentes internos principales.

No agregues C4 si solamente repetiría clases que ya se entienden en C3.

Además agrega un diagrama de secuencia Mermaid que muestre dos solicitudes concurrentes intentando reservar el mismo producto y dónde se serializa el acceso al stock.

Reglas de documentación:
- no afirmes que algo fue ejecutado si no existe evidencia;
- diferencia claramente resultado observado, comportamiento esperado y limitación conocida;
- evita frases como “100 % seguro”, “sin errores” o “garantizado”;
- prioriza decisiones técnicas y trazabilidad sobre explicaciones genéricas.
```

---

## Evidencia de validación

| Evidencia | Resultado respaldado |
| --- | --- |
| `evidence/windows-before-r1-r4.txt` | Primera suite en Windows/Docker: 35 pruebas y 91 aserciones. |
| `evidence/local-r1-r4.txt` y `.xml` | Validación de R1-R4 en Linux con PHP 8.3.6 y MySQL 8.0.46: 38 pruebas y 154 aserciones. |
| `evidence/windows-after-r1-r4.txt` | Ejecución final en Windows/Docker con PHP 8.3.33 y PHPUnit 12.5.35: 38 pruebas y 154 aserciones. |
| `reports/VALIDATION.md` | Alcance de las verificaciones, resultados y límites documentados. |

## Decisiones técnicas demostradas

La solución final refleja un proceso en el que la IA fue utilizada para diseñar, implementar, revisar y documentar, pero cada etapa estuvo delimitada mediante instrucciones técnicas y criterios verificables.

Las decisiones principales fueron:

- priorizar las invariantes de negocio antes que la arquitectura;
- mantener el alcance en backend y base de datos;
- usar transacciones y locking a nivel de base de datos para proteger el stock;
- respaldar la idempotencia con una restricción única;
- utilizar pruebas de integración reales para comportamiento específico de MySQL;
- diferenciar errores internos de errores transitorios;
- revisar críticamente una solución que ya tenía tests en verde;
- aplicar únicamente las recomendaciones técnicas aceptadas;
- volver a ejecutar toda la suite después de los cambios;
- conservar evidencia reproducible de las validaciones;
- documentar también los límites conocidos y decisiones no aplicadas.

El criterio de cierre no fue que la IA afirmara que la solución era correcta, sino que el comportamiento esperado pudiera contrastarse contra pruebas, respuestas HTTP, estado de base de datos y evidencia de ejecución.
