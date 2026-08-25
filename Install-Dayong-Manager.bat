@echo off
setlocal
set "INSTALL_DIR=C:\KCLDA\KCLDA-Dayong-Manager-Bank-Ledger-Windows-x64"
for %%I in ("%~dp0.") do set "SOURCE_DIR=%%~fI"
echo Updating KCLDA Dayong Manager...
taskkill /IM KCLDA.DayongManager.exe /F >nul 2>&1
if not exist "C:\KCLDA" mkdir "C:\KCLDA"
if not exist "%INSTALL_DIR%" mkdir "%INSTALL_DIR%"
if /I not "%SOURCE_DIR%"=="%INSTALL_DIR%" (
  robocopy "%SOURCE_DIR%" "%INSTALL_DIR%" /E /R:2 /W:1 /NFL /NDL /NJH /NJS /NP >nul
  if errorlevel 8 (
    echo.
    echo The update could not be copied to:
    echo %INSTALL_DIR%
    echo Right-click this BAT file and select "Run as administrator", then try again.
    pause
    exit /b 1
  )
)
powershell -NoProfile -ExecutionPolicy Bypass -Command "$s=(New-Object -ComObject WScript.Shell).CreateShortcut([Environment]::GetFolderPath('Desktop')+'\KCLDA Dayong Manager.lnk');$s.TargetPath='%INSTALL_DIR%\KCLDA.DayongManager.exe';$s.WorkingDirectory='%INSTALL_DIR%';$s.Description='KCLDA Dayong Membership and Collection Manager';$s.Save()"
echo.
echo Update complete at:
echo %INSTALL_DIR%
echo Your existing database and user records have been preserved.
start "" "%INSTALL_DIR%\KCLDA.DayongManager.exe"
pause
