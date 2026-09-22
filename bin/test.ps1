$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)
New-Item -ItemType Directory -Force reports | Out-Null
New-Item -ItemType Directory -Force evidence | Out-Null
$utf8 = New-Object System.Text.UTF8Encoding($false)
[Console]::OutputEncoding = $utf8
$report = Join-Path (Get-Location) 'reports/windows-tests.txt'
$writer = [System.IO.StreamWriter]::new($report, $false, $utf8)
$writer.AutoFlush = $true

function Invoke-DockerLogged([string]$Command) {
    # All commands below are fixed strings, with no secrets or user input.
    # cmd merges native stderr before PowerShell sees it, avoiding NativeCommandError.
    $start = New-Object System.Diagnostics.ProcessStartInfo
    $start.FileName = $env:ComSpec
    $start.WorkingDirectory = (Get-Location).Path
    $start.Arguments = '/d /s /c "' + $Command + ' 2>&1"'
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.StandardOutputEncoding = $utf8
    $start.StandardErrorEncoding = $utf8
    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $start
    try {
        if (-not $process.Start()) { throw 'No se pudo iniciar Docker.' }
        while (-not $process.StandardOutput.EndOfStream) {
            $line = $process.StandardOutput.ReadLine()
            Write-Host $line
            $writer.WriteLine($line)
        }
        $other = $process.StandardError.ReadToEnd()
        if ($other) { Write-Host $other; $writer.WriteLine($other) }
        $process.WaitForExit()
        return $process.ExitCode
    } finally {
        $process.Dispose()
    }
}

$failure = $null
try {
    Remove-Item reports/junit.xml -ErrorAction SilentlyContinue
    if ((Invoke-DockerLogged 'docker compose --profile test up -d --build --wait test-app') -ne 0) {
        throw 'No se pudo iniciar el entorno aislado de pruebas.'
    }
    if ((Invoke-DockerLogged 'docker compose --profile test build tests') -ne 0) {
        throw 'No se pudo construir el ejecutor de pruebas.'
    }
    $testExit = Invoke-DockerLogged 'docker compose --profile test run --rm -T tests vendor/bin/phpunit --testdox --colors=never --log-junit reports/junit.xml'
    if ($testExit -ne 0) { throw "Pruebas fallidas (codigo $testExit). Revise reports/windows-tests.txt." }
    if (-not (Test-Path reports/junit.xml)) { throw 'Falta el reporte JUnit de esta ejecucion.' }
} catch {
    $failure = $_
} finally {
    try {
        $cleanupExit = Invoke-DockerLogged 'docker compose --profile test stop test-app test-db'
        if ($cleanupExit -ne 0) {
            $writer.WriteLine('AVISO: no se pudieron detener todos los servicios de pruebas.')
            Write-Warning 'No se pudieron detener todos los servicios de pruebas.'
        }
    } catch {
        Write-Warning 'Fallo la limpieza; revise los contenedores test-app y test-db.'
    } finally {
        $writer.Dispose()
    }
    # Track selected evidence even for a failed run; never reuse a stale XML.
    Copy-Item reports/windows-tests.txt evidence/windows-latest.txt -Force
    Remove-Item evidence/windows-latest.xml -ErrorAction SilentlyContinue
    if (Test-Path reports/junit.xml) { Copy-Item reports/junit.xml evidence/windows-latest.xml -Force }
}
if ($null -ne $failure) { throw $failure }
Write-Host 'Pruebas aprobadas. Resultados: reports/windows-tests.txt y reports/junit.xml'
Write-Host 'Copias versionables: evidence/windows-latest.txt y evidence/windows-latest.xml'
