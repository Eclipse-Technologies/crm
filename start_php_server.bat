@echo off
REM Start PHP Development Server on localhost:8010
REM Location: CRM folder

cd /d "%~dp0"

echo.
echo Starting PHP Development Server...
echo URL: http://localhost:8010
echo Press Ctrl+C to stop the server
echo.

php -S localhost:8010

pause
