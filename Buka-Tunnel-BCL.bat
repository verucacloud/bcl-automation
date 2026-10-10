@echo off
title BCL Automation - Cloudflare Tunnel & PHP Server
cls
echo ========================================================
echo   MENGHUBUNGKAN BACKEND AUTOMATION BCL KE INTERNET
echo ========================================================
echo.
echo 1. Menjalankan PHP 8.2 di port 8080...
start "" /b "C:\xampp\php\php.exe" -S 127.0.0.1:8080 -t "C:\xampp\htdocs" >nul 2>&1
timeout /t 2 >nul
echo 2. Menghubungkan tunnel ke Cloudflare...
echo.
echo Link publik HTTPS Anda akan muncul di bawah ini:
echo (Cari baris yang ada tulisan: https://...trycloudflare.com)
echo.
echo Tekan Ctrl + C jika ingin menghentikan tunnel.
echo ========================================================
echo.
"C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --url http://localhost:8080
pause
