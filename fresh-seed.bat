@echo off
REM Script để fresh và seed lại toàn bộ database trên Windows
REM Usage: fresh-seed.bat

echo 🔄 Starting fresh database migration and seeding...
echo.

REM Kiểm tra xem có đang trong thư mục đúng không
if not exist "artisan" (
    echo ❌ Error: artisan file not found. Please run this script from the Laravel root directory.
    pause
    exit /b 1
)

REM Xác nhận từ người dùng
set /p confirm="⚠️  This will DROP all tables and recreate them. Continue? (y/N): "
if /i not "%confirm%"=="y" (
    echo ❌ Cancelled.
    pause
    exit /b 1
)

echo.
echo 📦 Step 1: Dropping all tables...
php artisan migrate:fresh

echo.
echo 🌱 Step 2: Seeding database...
php artisan db:seed

echo.
echo ✅ Done! Database has been fresh migrated and seeded.
echo.
echo 📊 Default credentials:
echo    Admin: admin@staybook.com / password
echo    Owner: owner@staybook.com / password
echo    User:  user@staybook.com / password
echo.
pause



