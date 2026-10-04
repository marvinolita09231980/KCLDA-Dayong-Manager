$ErrorActionPreference = 'Stop'
$launcher = Join-Path $PSScriptRoot 'start-online-background.ps1'
$onlineDirectory = Join-Path $PSScriptRoot 'storage\app\private\online'
foreach ($requiredPath in @($launcher, (Join-Path $onlineDirectory 'ngrok.exe'), (Join-Path $onlineDirectory 'ngrok.yml'), (Join-Path $onlineDirectory 'public-url.txt'))) {
    if (-not (Test-Path -LiteralPath $requiredPath)) { throw 'Complete start-online.ps1 -Setup before installing automatic online startup.' }
}
$startupDirectory = [Environment]::GetFolderPath('Startup')
if (-not $startupDirectory) { throw 'Windows Startup folder could not be located.' }
New-Item -ItemType Directory -Path $startupDirectory -Force | Out-Null
$shortcutPath = Join-Path $startupDirectory 'KCLDA Online.lnk'
$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = Join-Path $PSHOME 'powershell.exe'
$shortcut.Arguments = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}"' -f $launcher
$shortcut.WorkingDirectory = $PSScriptRoot
$shortcut.Description = 'Start KCLDA online access in the background at Windows sign-in'
$shortcut.WindowStyle = 7
$shortcut.Save()
Write-Host "Installed: $shortcutPath"
Write-Host 'KCLDA online access will start in the background when this Windows user signs in.'
Write-Host 'Logs: storage/app/private/online/autostart.log'
