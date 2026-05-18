@echo off
echo ==========================================
echo Starting MedSurvey Pro (Frontend + Backend)
echo ==========================================
echo.
echo Please make sure your MySQL database (e.g., XAMPP) is running!
echo Backend API: http://127.0.0.1:4001/api/health
echo.
echo The application will be available at: http://127.0.0.1:3000
echo.
npm.cmd run dev:all
pause
