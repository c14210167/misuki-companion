@echo off
echo ====================================
echo    STARTING MISUKI SYSTEMS
echo ====================================
echo.

echo [1/3] Starting XAMPP Apache...
cd /d "C:\xampp"
start "" "C:\xampp\apache\bin\httpd.exe"
timeout /t 2 /nobreak > nul

echo [2/3] Starting XAMPP MySQL...
start "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini"
timeout /t 3 /nobreak > nul

echo [3/3] Starting Misuki Discord Bot...
cd /d "D:\XAMPP\htdocs\misuki-companion"
start "Misuki Discord Bot" cmd /k "node discord-bot.js"

echo.
echo ====================================
echo    ALL SYSTEMS ONLINE!
echo ====================================
echo.
echo * XAMPP Apache is running
echo * XAMPP MySQL is running  
echo * Misuki Discord Bot is running
echo.
echo You can now:
echo - Access web app at: http://localhost/misuki-companion
echo - Chat with Misuki on Discord
echo.
echo Press any key to close this window...
pause > nul