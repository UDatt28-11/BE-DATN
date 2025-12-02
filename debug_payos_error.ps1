# Script debug lỗi PayOS "Thông tin truyền lên không đúng"
# Cách dùng: .\debug_payos_error.ps1

Write-Host "`n=== DEBUG PAYOS ERROR: 'Thong tin truyen len khong dung' ===" -ForegroundColor Cyan
Write-Host ""

$envFile = ".env"

if (-not (Test-Path $envFile)) {
    Write-Host "❌ Không tìm thấy file .env" -ForegroundColor Red
    exit 1
}

$envContent = Get-Content $envFile

# 1. Kiểm tra FRONTEND_URL
Write-Host "1. KIEM TRA FRONTEND_URL:" -ForegroundColor Yellow
$frontendUrl = ($envContent | Select-String "FRONTEND_URL\s*=\s*(.+)").Matches.Groups[1].Value

if ($frontendUrl) {
    Write-Host "   FRONTEND_URL: $frontendUrl" -ForegroundColor Gray
    
    if ($frontendUrl -match "localhost|127\.0\.0\.1") {
        Write-Host "   ❌ PHAT HIEN LOCALHOST!" -ForegroundColor Red
        Write-Host "   ⚠️  PayOS KHONG CHAP NHAN localhost trong returnUrl/cancelUrl" -ForegroundColor Red
        Write-Host "   ✅ GIAI PHAP: Cap nhat FRONTEND_URL thanh public URL (cloudflare/ngrok)" -ForegroundColor Green
    } elseif ($frontendUrl -match "trycloudflare|ngrok|loca\.lt") {
        Write-Host "   ✅ FRONTEND_URL la public URL" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️  FRONTEND_URL co the khong phai public URL" -ForegroundColor Yellow
    }
} else {
    Write-Host "   ❌ KHONG TIM THAY FRONTEND_URL trong .env" -ForegroundColor Red
    Write-Host "   ✅ GIAI PHAP: Them FRONTEND_URL vao .env" -ForegroundColor Green
}

Write-Host ""

# 2. Kiểm tra PayOS credentials
Write-Host "2. KIEM TRA PAYOS CREDENTIALS:" -ForegroundColor Yellow
$clientId = ($envContent | Select-String "PAYOS_CLIENT_ID\s*=\s*(.+)").Matches.Groups[1].Value
$apiKey = ($envContent | Select-String "PAYOS_API_KEY\s*=\s*(.+)").Matches.Groups[1].Value
$baseUrl = ($envContent | Select-String "PAYOS_BASE_URL\s*=\s*(.+)").Matches.Groups[1].Value

if ($clientId) {
    Write-Host "   ✅ PAYOS_CLIENT_ID: Co" -ForegroundColor Green
} else {
    Write-Host "   ❌ PAYOS_CLIENT_ID: Khong tim thay" -ForegroundColor Red
}

if ($apiKey) {
    Write-Host "   ✅ PAYOS_API_KEY: Co" -ForegroundColor Green
} else {
    Write-Host "   ❌ PAYOS_API_KEY: Khong tim thay" -ForegroundColor Red
}

if ($baseUrl) {
    Write-Host "   PAYOS_BASE_URL: $baseUrl" -ForegroundColor Gray
    if ($baseUrl -match "api-merchant\.payos\.vn") {
        Write-Host "   ✅ PAYOS_BASE_URL dung" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️  PAYOS_BASE_URL co the sai" -ForegroundColor Yellow
    }
} else {
    Write-Host "   PAYOS_BASE_URL: (dung default: https://api-merchant.payos.vn)" -ForegroundColor Gray
}

Write-Host ""

# 3. Kiểm tra logs gần nhất
Write-Host "3. KIEM TRA LOGS GAN NHAT:" -ForegroundColor Yellow
$logFile = "storage\logs\laravel.log"

if (Test-Path $logFile) {
    Write-Host "   Dang doc logs..." -ForegroundColor Gray
    
    # Tìm các log liên quan đến PayOS
    $payosLogs = Get-Content $logFile -Tail 200 | Select-String -Pattern "PayOS" -Context 3
    
    if ($payosLogs) {
        Write-Host "   ✅ Tim thay logs PayOS" -ForegroundColor Green
        Write-Host ""
        Write-Host "   === LOGS GAN NHAT ===" -ForegroundColor Cyan
        
        # Hiển thị 5 dòng cuối cùng
        $payosLogs | Select-Object -Last 10 | ForEach-Object {
            if ($_ -match "returnUrl|cancelUrl") {
                Write-Host "   $_" -ForegroundColor Yellow
            } elseif ($_ -match "error|Error|ERROR|fail|Fail|FAIL") {
                Write-Host "   $_" -ForegroundColor Red
            } else {
                Write-Host "   $_" -ForegroundColor Gray
            }
        }
    } else {
        Write-Host "   ⚠️  Khong tim thay logs PayOS gan day" -ForegroundColor Yellow
    }
} else {
    Write-Host "   ⚠️  Khong tim thay file log" -ForegroundColor Yellow
}

Write-Host ""

# 4. Kiểm tra request data trong logs
Write-Host "4. KIEM TRA REQUEST DATA TRONG LOGS:" -ForegroundColor Yellow

if (Test-Path $logFile) {
    $requestLogs = Get-Content $logFile -Tail 500 | Select-String -Pattern "PayOS API request|request_sent|returnUrl|cancelUrl" -Context 5
    
    if ($requestLogs) {
        Write-Host "   === REQUEST DATA GAN NHAT ===" -ForegroundColor Cyan
        
        $requestLogs | Select-Object -Last 20 | ForEach-Object {
            if ($_ -match "returnUrl.*localhost|cancelUrl.*localhost") {
                Write-Host "   ❌ $_" -ForegroundColor Red
            } elseif ($_ -match "returnUrl|cancelUrl") {
                Write-Host "   $_" -ForegroundColor Yellow
            } else {
                Write-Host "   $_" -ForegroundColor Gray
            }
        }
    } else {
        Write-Host "   ⚠️  Khong tim thay request data trong logs" -ForegroundColor Yellow
    }
}

Write-Host ""

# 5. Tóm tắt và khuyến nghị
Write-Host "=== TOM TAT VA KHUYEN NGHI ===" -ForegroundColor Cyan
Write-Host ""

if ($frontendUrl -match "localhost|127\.0\.0\.1") {
    Write-Host "❌ VAN DE CHINH: FRONTEND_URL chua localhost" -ForegroundColor Red
    Write-Host ""
    Write-Host "✅ GIAI PHAP:" -ForegroundColor Green
    Write-Host "   1. Cap nhat FRONTEND_URL trong .env thanh public URL" -ForegroundColor White
    Write-Host "   2. Vi du: FRONTEND_URL=https://gale-assumptions-executives-velocity.trycloudflare.com" -ForegroundColor White
    Write-Host "   3. Chay: php artisan config:clear" -ForegroundColor White
    Write-Host "   4. Restart Laravel server" -ForegroundColor White
    Write-Host ""
} else {
    Write-Host "✅ FRONTEND_URL da dung public URL" -ForegroundColor Green
    Write-Host ""
    Write-Host "⚠️  Cac nguyen nhan khac co the:" -ForegroundColor Yellow
    Write-Host "   1. Format du lieu khong dung (orderCode, amount, items)" -ForegroundColor White
    Write-Host "   2. Ky tu dac biet trong description/name" -ForegroundColor White
    Write-Host "   3. orderCode trung lap" -ForegroundColor White
    Write-Host "   4. amount khong khop voi items total" -ForegroundColor White
    Write-Host ""
    Write-Host "✅ KHUYEN NGHI:" -ForegroundColor Green
    Write-Host "   1. Xem chi tiet logs: Get-Content storage\logs\laravel.log -Tail 100" -ForegroundColor White
    Write-Host "   2. Tim 'PayOS API request' de xem request data" -ForegroundColor White
    Write-Host "   3. So sanh voi request thanh cong tu Postman" -ForegroundColor White
}

Write-Host ""
Write-Host "=== HOAN TAT ===" -ForegroundColor Green

