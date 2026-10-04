param(
    [switch]$Setup,
    [switch]$ChangeDomain,
    [switch]$InstallOnly
)

$ErrorActionPreference = 'Stop'
$projectDirectory = $PSScriptRoot
$onlineDirectory = Join-Path $projectDirectory 'storage\app\private\online'
$ngrokPath = Join-Path $onlineDirectory 'ngrok.exe'
$configPath = Join-Path $onlineDirectory 'ngrok.yml'
$addressPath = Join-Path $onlineDirectory 'public-url.txt'
$phpServer = $null
$savedEnvironment = @{}

try {
    New-Item -ItemType Directory -Path $onlineDirectory -Force | Out-Null
    if (-not (Test-Path -LiteralPath $ngrokPath)) {
        Write-Host 'Downloading ngrok from its official distribution server...'
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        $archivePath = Join-Path $onlineDirectory 'ngrok.zip'
        Invoke-WebRequest -Uri 'https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-windows-amd64.zip' -OutFile $archivePath -UseBasicParsing
        Expand-Archive -LiteralPath $archivePath -DestinationPath $onlineDirectory -Force
        if (-not (Test-Path -LiteralPath $ngrokPath)) { throw 'The ngrok download did not contain ngrok.exe.' }
    }
    $signature = Get-AuthenticodeSignature -FilePath $ngrokPath
    if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'ngrok') {
        throw 'The ngrok executable does not have a valid ngrok publisher signature. Download the official Windows client from https://ngrok.com/download/windows.'
    }
    if ($InstallOnly) {
        Write-Host 'ngrok is installed. Run .\start-online.ps1 -Setup after creating your free account.'
        return
    }

    if ($ChangeDomain) {
        if (-not (Test-Path -LiteralPath $configPath)) { throw 'Run with -Setup first.' }
        Write-Host 'Copy the EXACT assigned domain from https://dashboard.ngrok.com/domains. Do not invent a name.'
        $domain = (Read-Host 'Domain shown in your ngrok dashboard').Trim().ToLowerInvariant() -replace '^https://', ''
        $domain = $domain.TrimEnd('/')
        if ($domain -notmatch '^[a-z0-9][a-z0-9-]*\.ngrok-free\.(app|dev)$') { throw 'Enter the full assigned ngrok domain without a path.' }
        $configuration = [IO.File]::ReadAllText($configPath)
        if ([regex]::Matches($configuration, '(?m)^    url: https://[^\r\n]+').Count -ne 1) { throw 'Unexpected configuration format. Run with -Setup again.' }
        $configuration = [regex]::Replace($configuration, '(?m)^    url: https://[^\r\n]+', "    url: https://$domain")
        [IO.File]::WriteAllText($configPath, $configuration, (New-Object Text.UTF8Encoding($false)))
        [IO.File]::WriteAllText($addressPath, "https://$domain", (New-Object Text.UTF8Encoding($false)))
        $configuration = $null
    }

    if ($Setup -or -not (Test-Path -LiteralPath $configPath) -or -not (Test-Path -LiteralPath $addressPath)) {
        Write-Host 'Create a FREE account at https://dashboard.ngrok.com/signup'
        Write-Host 'Copy the EXACT assigned domain from https://dashboard.ngrok.com/domains. Choosing your own name requires a paid plan.'
        $domain = (Read-Host 'Assigned domain (for example assigned-name.ngrok-free.app)').Trim() -replace '^https://', ''
        $domain = $domain.TrimEnd('/')
        if ($domain -notmatch '^[a-z0-9][a-z0-9-]*\.ngrok-free\.(app|dev)$') {
            throw 'Enter the assigned free ngrok domain only, without a path.'
        }
        Write-Host 'Find your authtoken at https://dashboard.ngrok.com/get-started/your-authtoken'
        $secureToken = Read-Host 'Paste the authtoken here (hidden; do not send it in chat)' -AsSecureString
        $tokenPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureToken)
        try {
            $token = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($tokenPointer)
            if ($token -notmatch '^[a-zA-Z0-9_-]+$') { throw 'The authtoken contains unexpected characters.' }
            $configuration = "version: 3`nagent:`n  authtoken: '$token'`n  web_addr: false`nendpoints:`n  - name: kclda`n    url: https://$domain`n    upstream:`n      url: http://127.0.0.1:8001`n      protocol: http1`n"
            [IO.File]::WriteAllText($configPath, $configuration, (New-Object Text.UTF8Encoding($false)))
            [IO.File]::WriteAllText($addressPath, "https://$domain", (New-Object Text.UTF8Encoding($false)))
        } finally {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($tokenPointer)
            $token = $null
            $configuration = $null
            $secureToken.Dispose()
        }
    }

    & $ngrokPath config check --config $configPath
    if ($LASTEXITCODE -ne 0) { throw 'The ngrok configuration is invalid. Run with -Setup again.' }

    $publicUrl = [IO.File]::ReadAllText($addressPath).Trim()
    if ($publicUrl -notmatch '^https://[a-z0-9][a-z0-9-]*\.ngrok-free\.(app|dev)$') { throw 'Invalid saved public address. Run with -Setup again.' }
    $phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
    $phpPath = if ($phpCommand) { $phpCommand.Source } else { 'C:\xampp82\php\php.exe' }
    if (-not (Test-Path -LiteralPath $phpPath)) { throw 'PHP 8.2 or newer was not found.' }

    $onlineEnvironment = @{
        APP_ENV = 'production'; APP_DEBUG = 'false'; APP_URL = $publicUrl
        SESSION_SECURE_COOKIE = 'true'; SESSION_COOKIE = 'kclda_online_session'
    }
    foreach ($name in $onlineEnvironment.Keys) {
        $savedEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
        [Environment]::SetEnvironmentVariable($name, $onlineEnvironment[$name], 'Process')
    }
    & $phpPath (Join-Path $projectDirectory 'scripts\online-preflight.php')
    if ($LASTEXITCODE -ne 0) { throw 'The app is not ready for online access. Follow the message above and retry.' }

    $probe = New-Object Net.Sockets.TcpClient
    try {
        $probe.Connect('127.0.0.1', 8001)
        throw 'Port 8001 is already in use. Close the previous online launcher first.'
    } catch [Net.Sockets.SocketException] {
        # This launch owns a separate local server, so local mode is unaffected.
    } finally { $probe.Dispose() }

    $phpServer = Start-Process -FilePath $phpPath -ArgumentList ('"{0}" serve --host=127.0.0.1 --port=8001 --tries=1 --no-reload' -f (Join-Path $projectDirectory 'artisan')) -WorkingDirectory $projectDirectory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $onlineDirectory 'server-output.log') -RedirectStandardError (Join-Path $onlineDirectory 'server-error.log')
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        if ($phpServer.HasExited) { break }
        try {
            $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8001/admin/login' -UseBasicParsing -TimeoutSec 2
            if ($response.Content -match 'KCLDA Dayong Manager') { $ready = $true; break }
        } catch { }
        Start-Sleep -Milliseconds 500
    }
    if (-not $ready) { throw 'The online server could not start. Check storage/app/private/online/server-error.log.' }
    $shortcutPath = Join-Path $projectDirectory 'KCLDA Online.url'
    [IO.File]::WriteAllText($shortcutPath, "[InternetShortcut]`r`nURL=$publicUrl/admin`r`n")
    Write-Host "KCLDA address: $publicUrl/admin"
    Write-Host 'Bookmark it as KCLDA Dayong Manager. Keep this window and computer running.'
    Write-Host 'Press Ctrl+C to stop online access. Your database remains on this computer.'
    & $ngrokPath start kclda --config $configPath
    if ($LASTEXITCODE -ne 0) { throw 'ngrok could not connect. For ERR_NGROK_313, run .\start-online.ps1 -ChangeDomain and paste the EXACT assigned domain from https://dashboard.ngrok.com/domains. Your saved token will be kept.' }
} catch {
    Write-Error $_.Exception.Message -ErrorAction Continue
    exit 1
} finally {
    if ($phpServer -and -not $phpServer.HasExited) {
        & taskkill.exe /PID $phpServer.Id /T /F 2>$null | Out-Null
    }
    foreach ($name in $savedEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($name, $savedEnvironment[$name], 'Process')
    }
}
