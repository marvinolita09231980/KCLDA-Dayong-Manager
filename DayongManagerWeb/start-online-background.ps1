$ErrorActionPreference = 'Stop'
$onlineDirectory = Join-Path $PSScriptRoot 'storage\app\private\online'
New-Item -ItemType Directory -Path $onlineDirectory -Force | Out-Null
$logPath = Join-Path $onlineDirectory 'autostart.log'
$mutex = New-Object Threading.Mutex($false, 'Local\KCLDAOnlineAutostart')
$ownsMutex = $false
try {
    try { $ownsMutex = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $ownsMutex = $true }
    if (-not $ownsMutex) { return }
    $powershellPath = Join-Path $PSHOME 'powershell.exe'
    $launcher = Join-Path $PSScriptRoot 'start-online.ps1'
    while ($true) {
        # A manual online launch can keep using the same port.
        $probe = New-Object Net.Sockets.TcpClient
        $portBusy = $false
        try { $probe.Connect('127.0.0.1', 8001); $portBusy = $true } catch [Net.Sockets.SocketException] { } finally { $probe.Dispose() }
        if (-not $portBusy) {
            if ((Test-Path -LiteralPath (Join-Path $onlineDirectory 'ngrok.yml')) -and (Test-Path -LiteralPath (Join-Path $onlineDirectory 'public-url.txt'))) {
                if ((Test-Path -LiteralPath $logPath) -and (Get-Item -LiteralPath $logPath).Length -gt 5MB) {
                    Move-Item -LiteralPath $logPath -Destination (Join-Path $onlineDirectory 'autostart-previous.log') -Force
                }
                Add-Content -LiteralPath $logPath -Value "$(Get-Date -Format s) Starting KCLDA online access."
                & $powershellPath -NoProfile -NonInteractive -ExecutionPolicy Bypass -File $launcher *>> $logPath
                Add-Content -LiteralPath $logPath -Value "$(Get-Date -Format s) Launcher stopped (exit $LASTEXITCODE); retrying in 60 seconds."
            } else {
                Add-Content -LiteralPath $logPath -Value 'Online setup is missing. Run start-online.ps1 -Setup manually first.'
                return
            }
        }
        Start-Sleep -Seconds 60
    }
} finally {
    if ($ownsMutex) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}
