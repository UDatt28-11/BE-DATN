<!DOCTYPE html>
<html>
<head>
    <title>BE-DATN-kien API Documentation</title>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Montserrat:300,400,700|Roboto:300,400,700" >
    <link rel="stylesheet" href="{{ asset('vendor/swagger-api/swagger-ui/dist/swagger-ui.css') }}" >
    <style>
        html{
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *,
        *:before,
        *:after{
            box-sizing: inherit;
        }
        body{
            margin:0;
            background: #fafafa;
        }
        
        /* Custom token persistence UI */
        .token-persistence-banner {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-align: center;
            font-weight: bold;
        }
        .token-persistence-banner.error {
            background: #f44336;
        }
        .token-persistence-banner.info {
            background: #2196F3;
        }
    </style>
</head>

<body>
<div id="swagger-ui"></div>

<script src="{{ asset('vendor/swagger-api/swagger-ui/dist/swagger-ui-bundle.js') }}"> </script>
<script src="{{ asset('vendor/swagger-api/swagger-ui/dist/swagger-ui-standalone-preset.js') }}"> </script>
<script>
window.onload = function() {
  // Begin Swagger UI call region
  const ui = SwaggerUIBundle({
    url: "{{ url('storage/api-docs/api-docs.json') }}",
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [
      SwaggerUIBundle.presets.apis,
      SwaggerUIStandalonePreset
    ],
    plugins: [
      SwaggerUIBundle.plugins.DownloadUrl
    ],
    layout: "StandaloneLayout",
    persistAuthorization: true, // ✅ IMPORTANT: Persist authorization
    onComplete: function() {
      console.log('✅ Swagger UI loaded with token persistence enabled');
      
      // Load token từ localStorage nếu có
      loadSavedToken();
    }
  })
  window.ui = ui
  
  // ===== TOKEN PERSISTENCE LOGIC =====
  const TOKEN_STORAGE_KEY = 'swagger_api_bearer_token';
  
  function saveBearerToken(token) {
    if (token) {
      localStorage.setItem(TOKEN_STORAGE_KEY, token);
      showNotification('✅ Token saved! Will persist after F5', 'success');
      console.log('✅ Bearer token saved to localStorage');
    }
  }
  
  function getSavedToken() {
    return localStorage.getItem(TOKEN_STORAGE_KEY);
  }
  
  function clearSavedToken() {
    localStorage.removeItem(TOKEN_STORAGE_KEY);
    showNotification('❌ Token cleared', 'error');
    console.log('❌ Bearer token cleared');
  }
  
  function loadSavedToken() {
    const savedToken = getSavedToken();
    if (savedToken) {
      // Tìm authorize button
      setTimeout(function() {
        // Hàm gọi authorize
        const authorizeBtn = document.querySelector('[aria-label="authorize"]') || 
                            document.querySelector('button[aria-label*="authorize"]');
        
        if (authorizeBtn) {
          // Simulate click authorize
          authorizeBtn.click();
          
          setTimeout(function() {
            // Tìm input field cho bearer token
            const inputs = document.querySelectorAll('input[type="password"], input[type="text"]');
            let found = false;
            
            inputs.forEach(input => {
              const parent = input.closest('.modal, [class*="auth"]');
              if (parent && !found) {
                input.value = savedToken;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                found = true;
                showNotification('✅ Token loaded from localStorage!', 'success');
                console.log('✅ Saved token auto-loaded');
              }
            });
          }, 500);
        }
      }, 1000);
    }
  }
  
  function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `token-persistence-banner ${type}`;
    notification.textContent = message;
    document.body.insertBefore(notification, document.body.firstChild);
    
    setTimeout(() => {
      notification.remove();
    }, 3000);
  }
  
  // Monitor authorize events
  document.addEventListener('change', function(e) {
    if (e.target && (e.target.type === 'password' || e.target.classList.contains('auth-input'))) {
      const token = e.target.value;
      if (token && token.length > 10) {
        saveBearerToken(token);
      }
    }
  }, true);
  
  // Expose untuk debugging
  window.SwaggerTokenManager = {
    saveToken: saveBearerToken,
    loadToken: loadSavedToken,
    clearToken: clearSavedToken,
    getToken: getSavedToken,
    setToken: (token) => {
      saveBearerToken(token);
      loadSavedToken();
    }
  };
  
  console.log('🔐 Token Persistence: Ready');
  console.log('📝 Commands: window.SwaggerTokenManager.setToken("your_token")');
}
</script>
</body>
</html>
