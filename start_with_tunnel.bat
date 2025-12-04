@echo off
REM Script tự động start Laravel server với tunnel cho PayOS
REM Cách dùng: start_with_tunnel.bat

echo.
echo 🚀 Starting Laravel server with automatic tunnel for PayOS...
echo.

REM Kiểm tra cloudflared
if not exist "cloudflared.exe" (
    echo ❌ Không tìm thấy cloudflared.exe
    echo.
    echo 📥 Vui lòng tải cloudflared từ:
    echo    https://github.com/cloudflare/cloudflared/releases/latest
    echo    Lưu vào thư mục: %CD%
    echo.
    pause
    exit /b 1
)

REM Start cloudflared trong background
echo 📋 Bước 1: Starting cloudflared tunnel...
start "Cloudflared Tunnel" cloudflared.exe tunnel --url http://localhost:8000

REM Chờ cloudflared khởi động
timeout /t 5 /nobreak >nul

echo.
echo ⚠️  Vui lòng copy URL từ cửa sổ Cloudflared (ví dụ: https://xxxxx.trycloudflare.com)
echo.
set /p TUNNEL_URL="Nhập tunnel URL (hoặc Enter để bỏ qua): "

if not "%TUNNEL_URL%"=="" (
    echo.
    echo 📝 Bước 2: Cập nhật .env với tunnel URL...
    
    REM Cập nhật .env (PowerShell)
    powershell -Command "$content = Get-Content .env -Raw; $content = $content -replace 'FRONTEND_URL\s*=.*', 'FRONTEND_URL=%TUNNEL_URL%'; $content = $content -replace 'APP_URL\s*=.*', 'APP_URL=%TUNNEL_URL%'; if ($content -notmatch 'FRONTEND_URL') { $content += \"`nFRONTEND_URL=%TUNNEL_URL%`n\" }; if ($content -notmatch 'APP_URL') { $content += \"`nAPP_URL=%TUNNEL_URL%`n\" }; Set-Content -Path .env -Value $content -NoNewline"
    
    echo ✅ Đã cập nhật FRONTEND_URL và APP_URL
    echo.
    echo 🔄 Bước 3: Clearing config cache...
    php artisan config:clear
    echo ✅ Config cache cleared
)

echo.
echo 🚀 Bước 4: Starting Laravel server...
echo    Port: 8000
echo    URL: http://localhost:8000
if not "%TUNNEL_URL%"=="" (
    echo    Tunnel: %TUNNEL_URL%
)
echo.
echo ⚠️  Giữ terminal này mở để server hoạt động!
echo ⚠️  Để dừng: Nhấn Ctrl+C
echo.

REM Start Laravel server
php artisan serve

