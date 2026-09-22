param([int]$Port = 8080)
$ErrorActionPreference = 'Stop'
if ($Port -lt 1 -or $Port -gt 65535) { throw 'Puerto invalido.' }
$root = Split-Path -Parent $PSScriptRoot
$target = Join-Path $root '.env'
if (Test-Path $target) {
    Write-Host '.env ya existe; se conservan las credenciales. No se modifica la base de datos.'
    exit 0
}
function New-LocalSecret {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return ([BitConverter]::ToString($bytes)).Replace('-', '').ToLowerInvariant()
}
$appSecret = New-LocalSecret
$rootSecret = New-LocalSecret
$text = "DB_PASSWORD=$appSecret`nDB_ROOT_PASSWORD=$rootSecret`nAPP_PORT=$Port`n"
[System.IO.File]::WriteAllText($target, $text, (New-Object System.Text.UTF8Encoding($false)))
Write-Host ".env creado con credenciales aleatorias locales. Puerto HTTP: $Port. No publiques este archivo."
