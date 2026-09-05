$ErrorActionPreference = 'Stop'

try {
    $projectDirectory = $PSScriptRoot
    $url = 'http://127.0.0.1:8000/admin'
    $phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
    $phpPath = if ($phpCommand) { $phpCommand.Source } else { 'C:\xampp82\php\php.exe' }

    if (-not (Test-Path -LiteralPath $phpPath)) {
        throw 'PHP was not found. Install PHP or add php.exe to PATH.'
    }

    function Test-DayongServer {
        try {
            $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 2
            return $response.Content -match 'KCLDA Dayong Manager'
        } catch {
            return $false
        }
    }

    if (-not (Test-DayongServer)) {
        $listener = New-Object System.Net.Sockets.TcpClient
        try {
            $listener.Connect('127.0.0.1', 8000)
            throw 'Port 8000 is already used by another application. Close that application and try again.'
        } catch [System.Net.Sockets.SocketException] {
            # The port is available.
        } finally {
            $listener.Dispose()
        }

        $logDirectory = Join-Path $projectDirectory 'storage\logs'
        New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
        $artisanPath = Join-Path $projectDirectory 'artisan'
        $serverOptions = @{
            FilePath = $phpPath
            ArgumentList = ('"{0}" serve --host=127.0.0.1 --port=8000 --tries=1 --no-reload' -f $artisanPath)
            WorkingDirectory = $projectDirectory
            WindowStyle = 'Hidden'
            RedirectStandardOutput = (Join-Path $logDirectory 'web-launcher-output.log')
            RedirectStandardError = (Join-Path $logDirectory 'web-launcher-error.log')
            PassThru = $true
        }
        $server = Start-Process @serverOptions

        $ready = $false
        for ($attempt = 0; $attempt -lt 30; $attempt++) {
            if (Test-DayongServer) {
                $ready = $true
                break
            }
            if ($server.HasExited) {
                break
            }
            Start-Sleep -Milliseconds 500
        }
        if (-not $ready) {
            throw "The web app could not start. Check the web-launcher logs in $logDirectory."
        }
    }

    Start-Process $url
} catch {
    Add-Type -AssemblyName System.Windows.Forms
    [System.Windows.Forms.MessageBox]::Show(
        $_.Exception.Message,
        'Dayong Manager Web',
        [System.Windows.Forms.MessageBoxButtons]::OK,
        [System.Windows.Forms.MessageBoxIcon]::Error
    ) | Out-Null
    exit 1
}
