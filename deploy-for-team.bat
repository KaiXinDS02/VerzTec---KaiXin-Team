@echo off
echo 🐳 VerzTec Team Deployment Script
echo ==================================
echo.

echo 📋 Step 1: Starting Docker containers...
docker-compose up -d

echo.
echo ⏳ Step 2: Waiting for database to be ready...
timeout /t 15 /nobreak >nul

echo.
echo 🔧 Step 3: Running conversation system migration...
docker exec web php /var/www/html/docker_migration.php

echo.
echo 🎉 Team deployment completed!
echo.
echo 📍 Access Points:
echo • Web Application: http://localhost:8080
echo • Database: localhost:3306
echo • OnlyOffice: http://localhost:8081
echo.
echo 👥 Team Members can now:
echo • Access the conversation system at http://localhost:8080
echo • Use the ChatGPT-style interface
echo • Share conversations and collaborate
echo.
echo 🔍 To check logs:
echo   docker-compose logs web
echo   docker-compose logs db
echo.
pause
