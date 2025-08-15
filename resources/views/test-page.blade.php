<!DOCTYPE html>
<html>
<head>
    <title>Test Page</title>
</head>
<body>
    <h1>✅ Laravel is working!</h1>
    <p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
    <p>Environment: {{ app()->environment() }}</p>
    <p>URL: {{ url()->current() }}</p>
    
    <hr>
    <h2>Mini App Links:</h2>
    <ul>
        <li><a href="/miniapp">Main Mini App</a></li>
        <li><a href="/miniapp/debug-page">Debug Page</a></li>
        <li><a href="/miniapp/test">Test Endpoint</a></li>
    </ul>
</body>
</html>
