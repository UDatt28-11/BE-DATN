# Script đơn giản: Start Laravel server với hướng dẫn setup tunnel
# Cách dùng: .\start_server.ps1

param(
    [int]$Port = 8000
)

Write-Host "`n🚀 Laravel Server Starter for PayOS" -ForegroundColor Cyan
Write-Host "====================================`n" -ForegroundColor Cyan

# Kiểm tra .env
$envFile = ".env"
if (-not (Test-Path $envFile)) {
    Write-Host "❌ Không tìm thấy file .env" -ForegroundColor Red
    Write-Host "Vui lòng tạo file .env từ .env.example" -ForegroundColor Yellow
    exit 1
}

# Đọc FRONTEND_URL hiện tại
$envContent = Get-Content $envFile -Raw
$currentFrontendUrl = ($envContent | Select-String "FRONTEND_URL\s*=\s*(.+)").Matches.Groups[1].Value

Write-Host "📋 Kiểm tra cấu hình hiện tại:" -ForegroundColor Yellow
if ($currentFrontendUrl) {
    Write-Host "   FRONTEND_URL: $currentFrontendUrl" -ForegroundColor Gray
    
    if ($currentFrontendUrl -match "localhost|127\.0\.0\.1") {
        Write-Host "   ⚠️  FRONTEND_URL đang là localhost" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "💡 Để PayOS hoạt động, bạn cần public URL:" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "   Cách 1: Sử dụng Cloudflared (Khuyến nghị)" -ForegroundColor White
        Write-Host "   1. Mở terminal mới và chạy:" -ForegroundColor Gray
        Write-Host "      .\cloudflared.exe tunnel --url http://localhost:$Port" -ForegroundColor Green
        Write-Host "   2. Copy URL từ output (ví dụ: https://xxxxx.trycloudflare.com)" -ForegroundColor Gray
        Write-Host "   3. Chạy script này lại và nhập URL khi được hỏi" -ForegroundColor Gray
        Write-Host ""
        Write-Host "   Cách 2: Bypass validation (Chỉ để test code)" -ForegroundColor White
        Write-Host "   Thêm vào .env: PAYOS_ALLOW_LOCALHOST=true" -ForegroundColor Gray
        Write-Host "   (Lưu ý: PayOS API vẫn sẽ reject localhost)" -ForegroundColor Yellow
        Write-Host ""
        
        $choice = Read-Host "Bạn muốn: (1) Nhập tunnel URL, (2) Bypass validation, (3) Bỏ qua và chạy server"
        
        if ($choice -eq "1") {
            $tunnelUrl = Read-Host "Nhập tunnel URL (ví dụ: https://xxxxx.trycloudflare.com)"
            if ($tunnelUrl) {
                # Cập nhật .env
                $content = Get-Content $envFile -Raw
                $content = $content -replace "FRONTEND_URL\s*=.*", "FRONTEND_URL=$tunnelUrl"
                if ($content -notmatch "APP_URL\s*=") {
                    $content += "`nAPP_URL=$tunnelUrl`n"
                } else {
                    $content = $content -replace "APP_URL\s*=.*", "APP_URL=$tunnelUrl"
                }
                Set-Content -Path $envFile -Value $content -NoNewline
                
                Write-Host "✅ Đã cập nhật .env" -ForegroundColor Green
                php artisan config:clear
            }
        } elseif ($choice -eq "2") {
            # Thêm PAYOS_ALLOW_LOCALHOST
            $content = Get-Content $envFile -Raw
            if ($content -notmatch "PAYOS_ALLOW_LOCALHOST") {
                $content += "`nPAYOS_ALLOW_LOCALHOST=true`n"
                Set-Content -Path $envFile -Value $content -NoNewline
                Write-Host "✅ Đã thêm PAYOS_ALLOW_LOCALHOST=true" -ForegroundColor Green
            }
        }
    } else {
        Write-Host "   ✅ FRONTEND_URL đã là public URL" -ForegroundColor Green
    }
} else {
    Write-Host "   ⚠️  Chưa có FRONTEND_URL trong .env" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "💡 Bạn cần setup tunnel URL:" -ForegroundColor Cyan
    Write-Host "   1. Chạy: .\cloudflared.exe tunnel --url http://localhost:$Port" -ForegroundColor Green
    Write-Host "   2. Copy URL và chạy script này lại" -ForegroundColor Gray
    Write-Host ""
}

Write-Host "`n🚀 Starting Laravel server on port $Port..." -ForegroundColor Yellow
Write-Host "   URL: http://localhost:$Port" -ForegroundColor Gray
if ($currentFrontendUrl -and $currentFrontendUrl -notmatch "localhost") {
    Write-Host "   Public URL: $currentFrontendUrl" -ForegroundColor Gray
}
Write-Host "`n⚠️  Nhấn Ctrl+C để dừng server`n" -ForegroundColor Yellow

# Start Laravel server
php artisan serve --port=$Port

