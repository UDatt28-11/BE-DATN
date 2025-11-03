/**
 * Swagger UI Token Persistence Script
 * Lưu Bearer token vào localStorage để không mất khi F5
 */

(function() {
    // Tên key trong localStorage
    const TOKEN_KEY = 'swagger_bearer_token';
    
    // Hàm lưu token khi authorize
    function saveToken() {
        // Chờ UI render xong
        setTimeout(function() {
            // Tìm input field của bearer token
            const authInputs = document.querySelectorAll('input[value*="Bearer"], input[placeholder*="Bearer"]');
            
            authInputs.forEach(input => {
                if (input.value && input.value.includes('Bearer ')) {
                    localStorage.setItem(TOKEN_KEY, input.value);
                    console.log('✅ Token saved to localStorage');
                }
            });
        }, 500);
    }
    
    // Hàm tải token từ localStorage
    function loadToken() {
        const savedToken = localStorage.getItem(TOKEN_KEY);
        
        if (savedToken) {
            setTimeout(function() {
                // Tìm input field để điền token
                const authInputs = document.querySelectorAll('input[type="password"], input[placeholder*="token"], input[placeholder*="Bearer"]');
                
                authInputs.forEach(input => {
                    if (input.placeholder && (input.placeholder.includes('Bearer') || input.placeholder.includes('token'))) {
                        input.value = savedToken;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        console.log('✅ Token loaded from localStorage');
                    }
                });
                
                // Alternative: Search for authorize button and set token
                const tryAltMethod = () => {
                    const allInputs = document.querySelectorAll('input');
                    for (let input of allInputs) {
                        const parent = input.closest('.try-out, .execute-wrapper, [class*="auth"]');
                        if (parent) {
                            input.value = savedToken;
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                };
                
                tryAltMethod();
            }, 1000);
        }
    }
    
    // Hàm clear token
    function clearToken() {
        localStorage.removeItem(TOKEN_KEY);
        console.log('✅ Token cleared from localStorage');
    }
    
    // Listen untuk authorize/logout buttons
    document.addEventListener('DOMContentLoaded', function() {
        loadToken();
        
        // Watch untuk authorize button
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.textContent && node.textContent.includes('Authorize')) {
                            saveToken();
                        }
                        if (node.textContent && node.textContent.includes('Logout')) {
                            clearToken();
                        }
                    });
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    
    // Auto-save token khi input berubah
    document.addEventListener('change', function(e) {
        if (e.target.type === 'password' || e.target.value.includes('Bearer ')) {
            saveToken();
        }
    }, true);
    
    // Expose functions globally untuk debugging
    window.SwaggerTokenManager = {
        saveToken: saveToken,
        loadToken: loadToken,
        clearToken: clearToken,
        getToken: () => localStorage.getItem(TOKEN_KEY),
        setToken: (token) => {
            localStorage.setItem(TOKEN_KEY, token);
            console.log('✅ Token set manually');
        }
    };
    
    console.log('🔐 Swagger Token Manager loaded. Use window.SwaggerTokenManager to manage tokens.');
})();
