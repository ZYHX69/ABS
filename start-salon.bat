@echo off
cd /d C:\xampp\htdocs\ABS2

:: Import database (only if needed)
echo Importing database...
"C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS salon;"
"C:\xampp\mysql\bin\mysql.exe" -u root salon < db_dump.sql
echo Database ready.

start "WebSocket Server" cmd /k php server.php
start "PHP Web Server" cmd /k php -S localhost:8000
timeout /t 2
start http://localhost:8000/index.html
pause