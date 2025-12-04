# Script tự động start Laravel server với tunnel (cloudflared) cho PayOS
# Cách dùng: .\start_with_tunnel.ps1
# Hoặc: .\start_with_tunnel.ps1 -Port 8000

param(
    [int]$Port = 8000
)

Write-Host "`n🚀 Starting Laravel server with automatic tunnel for PayOS..." -ForegroundColor Cyan
Write-Host ""

# Kiểm tra cloudflared
$cloudflaredPath = Join-Path $PSScriptRoot "cloudflared.exe"
if (-not (Test-Path $cloudflaredPath)) {
    Write-Host "❌ Không tìm thấy cloudflared.exe" -ForegroundColor Red
    Write-Host "`n📥 Đang tải cloudflared..." -ForegroundColor Yellow
    
    # Tải cloudflared tự động (Windows)
    $cloudflaredUrl = "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe"
    $cloudflaredPath = Join-Path $PSScriptRoot "cloudflared.exe"
    
    try {
        Invoke-WebRequest -Uri $cloudflaredUrl -OutFile $cloudflaredPath -UseBasicParsing
        Write-Host "✅ Đã tải cloudflared thành công" -ForegroundColor Green
    } catch {
        Write-Host "❌ Không thể tải cloudflared tự động" -ForegroundColor Red
        Write-Host "Vui lòng tải thủ công từ: https://github.com/cloudflare/cloudflared/releases/latest" -ForegroundColor Yellow
        Write-Host "Lưu vào: $cloudflaredPath" -ForegroundColor Yellow
        exit 1
    }
}

# Kiểm tra file .env
$envFile = Join-Path $PSScriptRoot ".env"
if (-not (Test-Path $envFile)) {
    Write-Host "❌ Không tìm thấy file .env" -ForegroundColor Red
    Write-Host "Vui lòng tạo file .env từ .env.example" -ForegroundColor Yellow
    exit 1
}

Write-Host "📋 Bước 1: Starting cloudflared tunnel..." -ForegroundColor Yellow
Write-Host "   (Sẽ mở cửa sổ mới cho cloudflared)" -ForegroundColor Gray

# Start cloudflared trong cửa sổ riêng
$cloudflaredProcess = Start-Process -FilePath $cloudflaredPath -ArgumentList "tunnel", "--url", "http://localhost:$Port" -PassThru -WindowStyle Normal

# Chờ cloudflared khởi động
Start-Sleep -Seconds 4

Write-Host ""
Write-Host "⚠️  Vui lòng xem cửa sổ Cloudflared và copy URL (ví dụ: https://xxxxx.trycloudflare.com)" -ForegroundColor Yellow
Write-Host "   URL sẽ hiển thị trong cửa sổ Cloudflared" -ForegroundColor Gray
Write-Host ""

# Yêu cầu người dùng nhập URL
$tunnelUrl = Read-Host "Nhập tunnel URL từ Cloudflared (hoặc Enter để bỏ qua và dùng localhost)"

if ($tunnelUrl) {
    Write-Host "✅ Tunnel URL: $tunnelUrl" -ForegroundColor Green
    
    Write-Host "`n📝 Bước 2: Cập nhật .env với tunnel URL..." -ForegroundColor Yellow
    
    # Đọc .env
    $envContent = Get-Content $envFile -Raw
    
    # Cập nhật FRONTEND_URL
    if ($envContent -match "FRONTEND_URL\s*=") {
        $envContent = $envContent -replace "FRONTEND_URL\s*=.*", "FRONTEND_URL=$tunnelUrl"
    } else {
        $envContent += "`nFRONTEND_URL=$tunnelUrl`n"
    }
    
    # Cập nhật APP_URL
    if ($envContent -match "APP_URL\s*=") {
        $envContent = $envContent -replace "APP_URL\s*=.*", "APP_URL=$tunnelUrl"
    } else {
        $envContent += "`nAPP_URL=$tunnelUrl`n"
    }
    
    # Ghi lại .env
    Set-Content -Path $envFile -Value $envContent -NoNewline
    
    Write-Host "✅ Đã cập nhật FRONTEND_URL và APP_URL" -ForegroundColor Green
    
    # Clear config cache
    Write-Host "`n🔄 Bước 3: Clearing config cache..." -ForegroundColor Yellow
    php artisan config:clear
    Write-Host "✅ Config cache cleared" -ForegroundColor Green
} else {
    Write-Host "⚠️  Không có tunnel URL, PayOS có thể không hoạt động" -ForegroundColor Yellow
}

Write-Host "`n🚀 Bước 4: Starting Laravel server..." -ForegroundColor Yellow
Write-Host "   Port: $Port" -ForegroundColor Gray
Write-Host "   URL: http://localhost:$Port" -ForegroundColor Gray
if ($tunnelUrl) {
    Write-Host "   Tunnel: $tunnelUrl" -ForegroundColor Gray
}
Write-Host "`n⚠️  Giữ terminal này mở để server và tunnel hoạt động!" -ForegroundColor Yellow
Write-Host "⚠️  Để dừng: Nhấn Ctrl+C" -ForegroundColor Yellow
Write-Host ""

# Start Laravel server
php artisan serve --port=$Port

