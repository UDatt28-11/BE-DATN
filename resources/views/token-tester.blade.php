<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Token Tester - BE-DATN-kien</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="email"],
        input[type="password"],
        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="text"]:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        button:hover {
            background: #764ba2;
        }
        
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .response {
            margin-top: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        
        .response.error {
            background: #fee;
            border-left-color: #f44336;
        }
        
        .response.success {
            background: #efe;
            border-left-color: #4CAF50;
        }
        
        .response-title {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .response-content {
            background: white;
            padding: 10px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .token-display {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            border: 1px solid #ddd;
        }
        
        .token-display label {
            margin-bottom: 5px;
        }
        
        .token-value {
            background: white;
            padding: 10px;
            border-radius: 3px;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            user-select: all;
        }
        
        .copy-btn {
            padding: 8px 15px;
            background: #2196F3;
            font-size: 14px;
            margin-top: 8px;
        }
        
        .copy-btn:hover {
            background: #0b7dda;
        }
        
        .swagger-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .swagger-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .swagger-link a:hover {
            text-decoration: underline;
        }
        
        .instructions {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #2196F3;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .instructions strong {
            color: #1976d2;
        }
        
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 API Token Tester</h1>
        <p class="subtitle">Get Bearer Token & Test API</p>
        
        <div class="instructions">
            <strong>Hướng dẫn:</strong><br>
            1. Nhập email & password<br>
            2. Click "Lấy Token"<br>
            3. Token sẽ được hiển thị<br>
            4. Copy token và dùng trong Swagger UI
        </div>
        
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" placeholder="admin@test.com" value="admin@test.com">
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" placeholder="password123" value="password123">
        </div>
        
        <button onclick="getToken()">
            <span id="btn-text">🔑 Lấy Token</span>
        </button>
        
        <div id="token-response"></div>
        
        <div class="swagger-link">
            👉 <a href="/api/documentation" target="_blank">Mở Swagger UI</a> 
            → Paste token → Click Authorize
        </div>
    </div>

    <script>
        async function getToken() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const btn = event.target;
            const responseDiv = document.getElementById('token-response');
            
            if (!email || !password) {
                showError('Vui lòng nhập email và password');
                return;
            }
            
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Đang lấy token...';
            responseDiv.innerHTML = '';
            
            try {
                const response = await fetch('/api/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password
                    })
                });
                
                const data = await response.json();
                
                if (response.ok && data.data && data.data.token) {
                    showSuccess(data);
                    // Save to localStorage
                    localStorage.setItem('api_bearer_token', data.data.token);
                } else {
                    showError(data.message || 'Lỗi khi lấy token');
                }
            } catch (error) {
                showError('Lỗi: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '🔑 Lấy Token';
            }
        }
        
        function showSuccess(data) {
            const token = data.data.token;
            const user = data.data.user;
            
            const html = `
                <div class="response success">
                    <div class="response-title">✅ Lấy Token Thành Công!</div>
                    <div style="margin: 15px 0; padding: 10px; background: white; border-radius: 3px;">
                        <strong>User:</strong> ${user.full_name}<br>
                        <strong>Email:</strong> ${user.email}<br>
                        <strong>Role:</strong> <span style="color: #4CAF50; font-weight: bold;">${user.role}</span><br>
                    </div>
                    
                    <div class="token-display">
                        <label>Bearer Token (Copy & Paste vào Swagger):</label>
                        <div class="token-value">${token}</div>
                        <button class="copy-btn" onclick="copyToken('${token}')">📋 Copy Token</button>
                    </div>
                    
                    <div style="margin-top: 15px; padding: 10px; background: #fffacd; border-radius: 3px;">
                        <strong>Cách sử dụng:</strong><br>
                        1. Mở <a href="/api/documentation" target="_blank">Swagger UI</a><br>
                        2. Click nút "Authorize"<br>
                        3. Paste token vào field<br>
                        4. Click "Authorize"<br>
                        5. Thử call API!
                    </div>
                </div>
            `;
            
            document.getElementById('token-response').innerHTML = html;
        }
        
        function showError(message) {
            const html = `
                <div class="response error">
                    <div class="response-title">❌ Lỗi</div>
                    <div class="response-content">${message}</div>
                </div>
            `;
            document.getElementById('token-response').innerHTML = html;
        }
        
        function copyToken(token) {
            navigator.clipboard.writeText(token).then(() => {
                alert('✅ Token đã copy vào clipboard!');
            }).catch(() => {
                alert('Lỗi copy, vui lòng copy thủ công');
            });
        }
        
        // Load saved token on page load
        window.addEventListener('load', () => {
            const savedToken = localStorage.getItem('api_bearer_token');
            if (savedToken) {
                // Automatically decode and show info (optional)
            }
        });
        
        // Enter key to login
        document.getElementById('password').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                getToken();
            }
        });
    </script>
</body>
</html>
