@echo off
echo ==========================================
echo  MedSurvey Pro - Public Tunnel
echo ==========================================
echo.
echo Checking if port 3000 is active...
netstat -ano | findstr ":3000.*LISTENING" >nul 2>&1
if errorlevel 1 (
    echo.
    echo [ERROR] Port 3000 is NOT listening!
    echo Please start the dev server first:  npm run dev
    echo.
    pause
    exit /b 1
)
echo [OK] Port 3000 is active.
echo.
echo Starting SSH tunnel via localhost.run...
echo Press Ctrl+C to stop the tunnel.
echo.
ssh -tt -o StrictHostKeyChecking=no -o ServerAliveInterval=30 -o ServerAliveCountMax=3 -R 80:localhost:3000 nokey@localhost.run
echo.
echo Tunnel closed.
pause
