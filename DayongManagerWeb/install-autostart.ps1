$ErrorActionPreference = 'Stop'

$projectDirectory = $PSScriptRoot
$launcher = Join-Path $projectDirectory 'start.ps1'
$startupDirectory = [Environment]::GetFolderPath('Startup')
$shortcutPath = Join-Path $startupDirectory 'Dayong Manager Web.lnk'
$powershellPath = Join-Path $PSHOME 'powershell.exe'

if (-not (Test-Path -LiteralPath $launcher)) {
    throw "Launcher was not found: $launcher"
}

if (-not (Test-Path -LiteralPath $startupDirectory -PathType Container)) {
    throw "Windows Startup folder was not found: $startupDirectory"
}

$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = $powershellPath
$shortcut.Arguments = '-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}" -NoBrowser' -f $launcher
$shortcut.WorkingDirectory = $projectDirectory
$shortcut.Description = 'Start KCLDA Dayong Manager Web when signing in to Windows'
$shortcut.WindowStyle = 7
$shortcut.Save()

Write-Host "Automatic startup installed: $shortcutPath"
Write-Host 'The app will start when you next sign in to Windows.'
Write-Host 'Open http://127.0.0.1:8000/admin in your browser.'
