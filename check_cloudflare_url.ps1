# Script kiểm tra và cập nhật Cloudflare URL
# Cách dùng: .\check_cloudflare_url.ps1 "https://gale-assumptions-executives-velocity.trycloudflare.com"

param(
    [Parameter(Mandatory=$true)]
    [string]$Url
)

Write-Host "`n=== KIEM TRA CLOUDFLARE URL ===" -ForegroundColor Cyan
Write-Host ""

# Validate URL format
if (-not ($Url -match '^https://.*\.trycloudflare\.com$')) {
    Write-Host "⚠️  Cảnh báo: URL không đúng format trycloudflare.com" -ForegroundColor Yellow
    Write-Host "   URL: $Url" -ForegroundColor Gray
    Write-Host "   Format mong đợi: https://xxxxx.trycloudflare.com" -ForegroundColor Gray
}

$envFile = ".env"

if (-not (Test-Path $envFile)) {
    Write-Host "❌ Không tìm thấy file .env" -ForegroundColor Red
    exit 1
}

# Đọc file .env
$envContent = Get-Content $envFile

# Kiểm tra FRONTEND_URL
$frontendUrl = ($envContent | Select-String "FRONTEND_URL\s*=\s*(.+)").Matches.Groups[1].Value

Write-Host "📋 KIEM TRA Hien Tai:" -ForegroundColor Yellow
if ($frontendUrl) {
    Write-Host "   FRONTEND_URL: $frontendUrl" -ForegroundColor Gray
    
    if ($frontendUrl -eq $Url) {
        Write-Host "   ✅ FRONTEND_URL đã đúng!" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️  FRONTEND_URL khác với URL mới" -ForegroundColor Yellow
        Write-Host "   URL mới: $Url" -ForegroundColor Cyan
        
        $update = Read-Host "   Bạn có muốn cập nhật FRONTEND_URL? (y/n)"
        if ($update -eq 'y' -or $update -eq 'Y') {
            # Cập nhật FRONTEND_URL
            $content = Get-Content $envFile -Raw
            $content = $content -replace "FRONTEND_URL\s*=.*", "FRONTEND_URL=$Url"
            Set-Content -Path $envFile -Value $content -NoNewline
            
            Write-Host "   ✅ Đã cập nhật FRONTEND_URL thành: $Url" -ForegroundColor Green
            
            # Clear config
            Write-Host "`n📝 Đang clear config cache..." -ForegroundColor Yellow
            php artisan config:clear
            
            Write-Host "   ✅ Đã clear config cache" -ForegroundColor Green
        }
    }
} else {
    Write-Host "   ❌ Không tìm thấy FRONTEND_URL trong .env" -ForegroundColor Red
    
    $add = Read-Host "   Bạn có muốn thêm FRONTEND_URL? (y/n)"
    if ($add -eq 'y' -or $add -eq 'Y') {
        Add-Content -Path $envFile -Value "`nFRONTEND_URL=$Url`n"
        Write-Host "   ✅ Đã thêm FRONTEND_URL: $Url" -ForegroundColor Green
        
        # Clear config
        Write-Host "`n📝 Đang clear config cache..." -ForegroundColor Yellow
        php artisan config:clear
        
        Write-Host "   ✅ Đã clear config cache" -ForegroundColor Green
    }
}

Write-Host "`n🔍 KIEM TRA CORS Config:" -ForegroundColor Yellow

# Kiểm tra CORS config
$corsFile = "config\cors.php"
if (Test-Path $corsFile) {
    $corsContent = Get-Content $corsFile -Raw
    
    if ($corsContent -match "trycloudflare") {
        Write-Host "   ✅ CORS đã hỗ trợ trycloudflare.com pattern" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️  CORS chưa có pattern cho trycloudflare.com" -ForegroundColor Yellow
        Write-Host "   (Có thể cần thêm pattern vào allowed_origins_patterns)" -ForegroundColor Gray
    }
    
    # Kiểm tra FRONTEND_URL trong CORS
    if ($corsContent -match "env\('FRONTEND_URL'") {
        Write-Host "   ✅ CORS đã sử dụng FRONTEND_URL từ .env" -ForegroundColor Green
    }
} else {
    Write-Host "   ⚠️  Không tìm thấy file config/cors.php" -ForegroundColor Yellow
}

Write-Host "`n🔍 KIEM TRA PayOS Service:" -ForegroundColor Yellow

# Kiểm tra PayOSService có dùng FRONTEND_URL không
$payosServiceFile = "app\Services\PayOSService.php"
if (Test-Path $payosServiceFile) {
    $payosContent = Get-Content $payosServiceFile -Raw
    
    if ($payosContent -match "FRONTEND_URL") {
        Write-Host "   ✅ PayOSService đã sử dụng FRONTEND_URL" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️  PayOSService không sử dụng FRONTEND_URL" -ForegroundColor Yellow
    }
} else {
    Write-Host "   ⚠️  Không tìm thấy file PayOSService.php" -ForegroundColor Yellow
}

Write-Host "`n✅ KIEM TRA HOAN TAT!" -ForegroundColor Green
Write-Host ""
Write-Host "📝 Cac buoc tiep theo:" -ForegroundColor Cyan
Write-Host "   1. Restart Laravel server (nếu đang chạy)" -ForegroundColor Gray
Write-Host "   2. Test tạo payment link từ Postman" -ForegroundColor Gray
Write-Host "   3. Kiểm tra returnUrl và cancelUrl trong logs" -ForegroundColor Gray
Write-Host ""


