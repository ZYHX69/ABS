@echo off
title Salon System Launcher

set MYSQL_BIN="C:\xampp\mysql\bin"
set MYSQL_USER=root
set MYSQL_PASS=
set DB_NAME=salon
set SQL_FILE=db_dump.sql
set PHP_EXE=C:\xampp\php\php.exe
set PROJECT_PATH=%~dp0
set WS_SERVER=server.php
set HTTP_PORT=8000

set MYSQL_AUTH=-u%MYSQL_USER%
if not "%MYSQL_PASS%"=="" set MYSQL_AUTH=%MYSQL_AUTH% -p%MYSQL_PASS%

:: Ask before dropping
echo This will DELETE the existing '%DB_NAME%' database and re-import %SQL_FILE%.
set /p confirm="Type YES to continue: "
if not "%confirm%"=="YES" (
    echo Aborted.
    pause
    exit /b 0
)

:: Drop and recreate database
echo Dropping and recreating database...
%MYSQL_BIN%\mysql.exe %MYSQL_AUTH% -e "DROP DATABASE IF EXISTS %DB_NAME%; CREATE DATABASE %DB_NAME%;"

:: Import SQL
echo Importing %SQL_FILE%...
%MYSQL_BIN%\mysql.exe %MYSQL_AUTH% %DB_NAME% < "%PROJECT_PATH%%SQL_FILE%"
if %errorlevel% neq 0 (
    echo [ERROR] Import failed.
    pause
    exit /b 1
)

echo Database ready.

:: Start WebSocket server (port 8080)
start "WebSocket Server" cmd /k "cd /d "%PROJECT_PATH%" && %PHP_EXE% %WS_SERVER%"

:: Start PHP built-in web server (port 8000)
start "PHP Web Server" cmd /k "cd /d "%PROJECT_PATH%" && %PHP_EXE% -S localhost:%HTTP_PORT%"

:: Wait a moment for servers to start
timeout /t 2 /nobreak >nul

:: Open browser
start "" "http://localhost:%HTTP_PORT%/index.html"

echo Done. Close both server windows to stop.
pause