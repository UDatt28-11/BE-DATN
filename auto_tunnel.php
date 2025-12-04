<?php

/**
 * Auto Tunnel Helper - Tự động tạo tunnel URL cho PayOS
 * 
 * Script này tự động start cloudflared và cập nhật .env
 * Chạy: php auto_tunnel.php
 */

$port = $argv[1] ?? 8000;
$cloudflaredPath = __DIR__ . '/cloudflared.exe';

echo "🚀 Auto Tunnel Helper for PayOS\n";
echo "================================\n\n";

// Kiểm tra cloudflared
if (!file_exists($cloudflaredPath)) {
    echo "❌ Không tìm thấy cloudflared.exe\n";
    echo "📥 Đang tải cloudflared...\n";
    
    $cloudflaredUrl = "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe";
    
    $ch = curl_init($cloudflaredUrl);
    $fp = fopen($cloudflaredPath, 'w');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    
    if (file_exists($cloudflaredPath)) {
        echo "✅ Đã tải cloudflared thành công\n";
    } else {
        echo "❌ Không thể tải cloudflared tự động\n";
        echo "Vui lòng tải thủ công từ: https://github.com/cloudflare/cloudflared/releases/latest\n";
        exit(1);
    }
}

echo "📋 Starting cloudflared tunnel on port $port...\n";

// Start cloudflared
$command = escapeshellarg($cloudflaredPath) . " tunnel --url http://localhost:$port 2>&1";
$process = popen($command, 'r');

// Đọc output để lấy URL
$tunnelUrl = null;
$maxAttempts = 20;
$attempt = 0;

while ($tunnelUrl === null && $attempt < $maxAttempts) {
    $line = fgets($process);
    if ($line === false) {
        usleep(500000); // 0.5 seconds
        $attempt++;
        continue;
    }
    
    echo $line;
    
    // Tìm URL trong output
    if (preg_match('/https:\/\/([a-z0-9-]+)\.trycloudflare\.com/', $line, $matches)) {
        $tunnelUrl = 'https://' . $matches[1] . '.trycloudflare.com';
        break;
    }
    
    $attempt++;
}

if ($tunnelUrl === null) {
    echo "\n⚠️  Không thể lấy URL từ cloudflared tự động\n";
    echo "Vui lòng chạy cloudflared thủ công:\n";
    echo "  .\\cloudflared.exe tunnel --url http://localhost:$port\n";
    exit(1);
}

echo "\n✅ Tunnel URL: $tunnelUrl\n";

// Cập nhật .env
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    echo "❌ Không tìm thấy file .env\n";
    exit(1);
}

$envContent = file_get_contents($envFile);

// Cập nhật FRONTEND_URL
if (preg_match('/FRONTEND_URL\s*=/', $envContent)) {
    $envContent = preg_replace('/FRONTEND_URL\s*=.*/', "FRONTEND_URL=$tunnelUrl", $envContent);
} else {
    $envContent .= "\nFRONTEND_URL=$tunnelUrl\n";
}

// Cập nhật APP_URL
if (preg_match('/APP_URL\s*=/', $envContent)) {
    $envContent = preg_replace('/APP_URL\s*=.*/', "APP_URL=$tunnelUrl", $envContent);
} else {
    $envContent .= "\nAPP_URL=$tunnelUrl\n";
}

file_put_contents($envFile, $envContent);

echo "✅ Đã cập nhật .env với tunnel URL\n";
echo "\n📝 Các bước tiếp theo:\n";
echo "   1. Chạy: php artisan config:clear\n";
echo "   2. Chạy: php artisan serve\n";
echo "   3. Giữ cloudflared chạy trong terminal khác\n";
echo "\n⚠️  Lưu ý: URL sẽ thay đổi mỗi lần restart cloudflared\n";

