# Validación de la solución

## Resultado final

La versión actual fue ejecutada en Windows/Docker con:

* PHP 8.3.33.
* PHPUnit 12.5.35.
* 38 pruebas.
* 154 aserciones.
* 0 fallos.
* 0 errores.

Evidencia:

* [`evidence/windows-latest.txt`](../evidence/windows-latest.txt)
* [`evidence/windows-latest.xml`](../evidence/windows-latest.xml)

El resultado JUnit final separa:

* Unit: 24 pruebas / 78 aserciones.
* Integration: 14 pruebas / 76 aserciones.

## Casos relevantes comprobados

La suite valida, entre otros:

* reserva exitosa;
* stock insuficiente sin descuento parcial;
* replay idempotente sin segundo descuento;
* conflicto cuando una misma clave se reutiliza con otro payload;
* producto inexistente;
* validaciones de entrada;
* constraints `UNIQUE`, `FOREIGN KEY` y `CHECK` de MySQL;
* rollback cuando falla la inserción;
* concurrencia con solicitudes distintas sin sobreventa;
* concurrencia con la misma clave descontando una sola vez;
* carrera de la misma clave sobre productos distintos;
* recuperación real ante MySQL `1062`;
* retry de la transacción completa ante un deadlock real `1213`.

## Prueba crítica de concurrencia

El escenario exigido por el enunciado está automatizado:

* stock inicial: `1`;
* solicitud A: `quantity = 1`;
* solicitud B: `quantity = 1`;
* resultado esperado y comprobado: una reserva exitosa, una rechazada y stock final `0`.

La prueba utiliza procesos independientes contra MySQL real; no simula el bloqueo mediante mocks ni SQLite.

## Reejecución

Desde PowerShell:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\bin\test.ps1
```

El script actualiza:

```text
reports/windows-tests.txt
reports/junit.xml
evidence/windows-latest.txt
evidence/windows-latest.xml
```

Para cualquier modificación funcional posterior, el resultado que debe considerarse válido es el correspondiente a la última ejecución real de esta suite.
