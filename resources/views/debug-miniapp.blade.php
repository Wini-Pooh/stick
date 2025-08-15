<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Debug Telegram Mini App</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background: var(--tg-theme-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 16px;
        }
        
        .debug-section {
            background: var(--tg-theme-secondary-bg-color, #f8f8f8);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        
        .code-block {
            background: #f5f5f5;
            border-radius: 4px;
            padding: 12px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .btn-test {
            background: var(--tg-theme-button-color, #007bff);
            color: var(--tg-theme-button-text-color, #ffffff);
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            margin: 8px 4px;
            cursor: pointer;
            width: 100%;
        }
        
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin: 8px 0;
        }
        
        .alert-info { background: #d1ecf1; color: #0c5460; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .alert-warning { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h1>🔍 Debug Telegram Mini App</h1>
        
        <div class="debug-section">
            <h3>📱 Telegram WebApp Info</h3>
            <div id="webapp-info" class="code-block">Loading...</div>
        </div>
        
        <div class="debug-section">
            <h3>🌐 URL Analysis</h3>
            <div id="url-info" class="code-block">Loading...</div>
        </div>
        
        <div class="debug-section">
            <h3>🔑 InitData Analysis</h3>
            <div id="initdata-info" class="code-block">Loading...</div>
        </div>
        
        <div class="debug-section">
            <h3>🧪 Connection Tests</h3>
            <button class="btn-test" onclick="testSimpleGet()">Test Simple GET</button>
            <button class="btn-test" onclick="testSimplePost()">Test Simple POST</button>
            <button class="btn-test" onclick="testProfileDebug()">Test Profile Debug</button>
            <button class="btn-test" onclick="testDebugDebug()">Test Debug Debug</button>
            <div id="test-results" class="mt-3"></div>
        </div>
    </div>

    <script>
        // Получаем CSRF токен
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Инициализация Telegram WebApp
        let tg = window.Telegram.WebApp;
        tg.expand();
        
        // Получаем initData из разных источников
        function extractInitData() {
            const urlParams = new URLSearchParams(window.location.search);
            const hashParams = new URLSearchParams(window.location.hash.substring(1));
            
            let sources = {
                'URL query params': urlParams.get('initData'),
                'Hash fragment (tgWebAppData)': hashParams.get('tgWebAppData'),
                'Telegram WebApp API': tg.initData
            };
            
            // Декодируем tgWebAppData если есть
            if (sources['Hash fragment (tgWebAppData)']) {
                sources['Hash fragment (decoded)'] = decodeURIComponent(sources['Hash fragment (tgWebAppData)']);
            }
            
            return sources;
        }
        
        // Показать информацию о WebApp
        function showWebAppInfo() {
            const webappInfo = {
                initData: tg.initData,
                initDataUnsafe: tg.initDataUnsafe,
                version: tg.version,
                platform: tg.platform,
                colorScheme: tg.colorScheme,
                themeParams: tg.themeParams,
                isExpanded: tg.isExpanded,
                viewportHeight: tg.viewportHeight,
                viewportStableHeight: tg.viewportStableHeight,
                headerColor: tg.headerColor,
                backgroundColor: tg.backgroundColor,
                isClosingConfirmationEnabled: tg.isClosingConfirmationEnabled,
                isVerticalSwipesEnabled: tg.isVerticalSwipesEnabled
            };
            
            document.getElementById('webapp-info').textContent = JSON.stringify(webappInfo, null, 2);
        }
        
        // Показать информацию о URL
        function showUrlInfo() {
            const urlInfo = {
                'Current URL': window.location.href,
                'Protocol': window.location.protocol,
                'Host': window.location.host,
                'Pathname': window.location.pathname,
                'Search': window.location.search,
                'Hash': window.location.hash,
                'User Agent': navigator.userAgent,
                'Referrer': document.referrer
            };
            
            document.getElementById('url-info').textContent = JSON.stringify(urlInfo, null, 2);
        }
        
        // Показать информацию о initData
        function showInitDataInfo() {
            const sources = extractInitData();
            const finalInitData = sources['Hash fragment (decoded)'] || sources['URL query params'] || sources['Telegram WebApp API'] || '';
            
            let parsed = null;
            if (finalInitData) {
                try {
                    const params = new URLSearchParams(finalInitData);
                    parsed = {};
                    for (const [key, value] of params) {
                        parsed[key] = value;
                        if (key === 'user') {
                            try {
                                parsed[key + '_parsed'] = JSON.parse(value);
                            } catch (e) {
                                parsed[key + '_parse_error'] = e.message;
                            }
                        }
                    }
                } catch (e) {
                    parsed = { error: e.message };
                }
            }
            
            const initDataInfo = {
                'Sources': sources,
                'Final InitData': finalInitData,
                'InitData Length': finalInitData.length,
                'Parsed InitData': parsed
            };
            
            document.getElementById('initdata-info').textContent = JSON.stringify(initDataInfo, null, 2);
        }
        
        // Функции тестирования
        async function testSimpleGet() {
            addTestResult('🔄 Testing simple GET...', 'info');
            try {
                const response = await fetch('/miniapp/test');
                const data = await response.json();
                addTestResult(`✅ GET test successful: ${response.status}`, 'success');
                addTestResult(JSON.stringify(data, null, 2), 'info');
            } catch (error) {
                addTestResult(`❌ GET test failed: ${error.message}`, 'danger');
            }
        }
        
        async function testSimplePost() {
            addTestResult('🔄 Testing simple POST...', 'info');
            try {
                const sources = extractInitData();
                const initData = sources['Hash fragment (decoded)'] || sources['URL query params'] || sources['Telegram WebApp API'] || '';
                
                const response = await fetch('/miniapp/test-post', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ 
                        initData: initData,
                        test: 'simple_post'
                    })
                });
                const data = await response.json();
                addTestResult(`✅ POST test successful: ${response.status}`, 'success');
                addTestResult(JSON.stringify(data, null, 2), 'info');
            } catch (error) {
                addTestResult(`❌ POST test failed: ${error.message}`, 'danger');
            }
        }
        
        async function testProfileDebug() {
            addTestResult('🔄 Testing profile debug...', 'info');
            try {
                const sources = extractInitData();
                const initData = sources['Hash fragment (decoded)'] || sources['URL query params'] || sources['Telegram WebApp API'] || '';
                
                const response = await fetch('/miniapp/profile-debug', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ initData: initData })
                });
                const data = await response.json();
                addTestResult(`✅ Profile debug successful: ${response.status}`, 'success');
                addTestResult(JSON.stringify(data, null, 2), 'info');
            } catch (error) {
                addTestResult(`❌ Profile debug failed: ${error.message}`, 'danger');
            }
        }
        
        async function testDebugDebug() {
            addTestResult('🔄 Testing debug debug...', 'info');
            try {
                const sources = extractInitData();
                const initData = sources['Hash fragment (decoded)'] || sources['URL query params'] || sources['Telegram WebApp API'] || '';
                
                const response = await fetch('/miniapp/debug-debug', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ initData: initData })
                });
                const data = await response.json();
                addTestResult(`✅ Debug debug successful: ${response.status}`, 'success');
                addTestResult(JSON.stringify(data, null, 2), 'info');
            } catch (error) {
                addTestResult(`❌ Debug debug failed: ${error.message}`, 'danger');
            }
        }
        
        function addTestResult(message, type) {
            const testResults = document.getElementById('test-results');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.style.whiteSpace = 'pre-wrap';
            alertDiv.textContent = message;
            testResults.appendChild(alertDiv);
            
            // Scroll to bottom
            testResults.scrollTop = testResults.scrollHeight;
        }
        
        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            showWebAppInfo();
            showUrlInfo();
            showInitDataInfo();
        });
    </script>
</body>
</html>
