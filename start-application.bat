@echo off
echo ================================================
echo       VerzTec Application Startup Script
echo ================================================
echo.

echo [1/3] Starting Docker containers (Website, Database, OnlyOffice)...
docker-compose up -d
if %errorlevel% neq 0 (
    echo ERROR: Failed to start Docker containers!
    echo Make sure Docker Desktop is running.
    pause
    exit /b 1
)

echo.
echo [2/3] Waiting for containers to be ready...
timeout /t 10 /nobreak >nul

echo.
echo [3/3] Starting the Chatbot server...
echo.
echo Opening chatbot in a new terminal window...
start "VerzTec Chatbot" cmd /k "cd /d \"%~dp0chatbot\" && echo Starting VerzTec Chatbot... && python -m uvicorn main:app --reload --host 0.0.0.0 --port 8000"

echo.
echo ================================================
echo           Services are starting up!
echo ================================================
echo.
echo Main Website:     http://localhost:8080
echo Chatbot API:      http://localhost:8000
echo OnlyOffice:       http://localhost:8081
echo Database:         localhost:3306
echo.
echo The chatbot will open in a separate terminal window.
echo Wait for it to show "Uvicorn running on http://0.0.0.0:8000" 
echo before accessing the website.
echo.
echo Press Ctrl+C in the chatbot terminal to stop the chatbot.
echo Run 'docker-compose down' to stop other services.
echo.
pause
