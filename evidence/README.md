# Evidencia seleccionada y versionable

- `windows-before-r1-r4.txt`: salida original adjuntada por el candidato. Docker en
  Windows, PHP 8.3.33, PHPUnit 12.5.35: 35 pruebas, 91 aserciones. Es anterior
  a R1-R4; conserva los mensajes de PowerShell tal como fueron recibidos.
- `local-r1-r4.txt` y `local-r1-r4.xml`: suite actual ejecutada en Linux, PHP 8.3.6,
  MySQL 8.0.46: 38 pruebas, 154 aserciones. Incluye errores reales 1062 y 1213.
- `windows-after-r1-r4.txt`: registro íntegro de la ejecución final adjuntada por
  el candidato: Windows/Docker, PHP 8.3.33 y PHPUnit 12.5.35, 38 pruebas y 154
  aserciones aprobadas; incluye la recuperación real de 1062 y 1213.
- `windows-latest.txt` y `windows-latest.xml`: los genera bin/test.ps1 en Windows.
  El registro final confirma la finalización del script y anuncia esas copias.
  El XML no fue adjuntado: consérvalo desde tu equipo para incluirlo en Git.
  El script copia también resultados fallidos y elimina un XML anterior antes de
  ejecutar; consulte siempre el contenido, no solo la existencia del archivo.

Esta carpeta no está excluida por .gitignore. `reports/` conserva salidas de trabajo.
