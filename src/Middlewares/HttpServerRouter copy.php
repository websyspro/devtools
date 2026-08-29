<?php

/**
 * HTTP Server Router with Auto-Reload Injection
 * 
 * Router para o servidor embutido do PHP que injeta automaticamente
 * o script WebSocket de hot reload em respostas HTML.
 */

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string
$path = parse_url($requestUri, PHP_URL_PATH);

// Define o documento root
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
$filePath = $documentRoot . $path;

// Se for um diretório, procura por index.php ou index.html
if (is_dir($filePath)) {
    if (file_exists($filePath . '/index.php')) {
        $filePath = $filePath . '/index.php';
    } elseif (file_exists($filePath . '/index.html')) {
        $filePath = $filePath . '/index.html';
    }
}

// Se o arquivo existe e não é PHP, serve diretamente
if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
    return false; // Deixa o servidor embutido servir o arquivo
}

// Se é arquivo PHP ou HTML, captura a saída para injetar o script
ob_start();

if (is_file($filePath)) {
    // Inclui o arquivo PHP
    require $filePath;
} else {
    // 404 - Arquivo não encontrado
    http_response_code(404);
    echo "404 - Not Found";
}

$content = ob_get_clean();

// Injeta o script de reload apenas em respostas HTML
if (stripos($content, '</body>') !== false) {
    $websocketPort = getenv('WEBSOCKET_PORT') ?: 8080;
    
    $script = <<<HTML

<!-- DevTools: Hot Reload Script -->
<script>
(function() {
    const ws = new WebSocket('ws://localhost:{$websocketPort}');
    
    ws.onopen = () => {
        console.log('[DevTools] Hot reload connected');
    };
    
    ws.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            if (data.type === 'reload') {
                console.log('[DevTools] File changed, reloading...');
                location.reload();
            }
        } catch (e) {
            console.error('[DevTools] Error parsing message:', e);
        }
    };
    
    ws.onerror = (error) => {
        console.error('[DevTools] WebSocket error:', error);
    };
    
    ws.onclose = () => {
        console.log('[DevTools] Connection closed, retrying in 2s...');
        setTimeout(() => location.reload(), 2000);
    };
})();
</script>
HTML;
    
    // Injeta antes do </body>
    $content = str_ireplace('</body>', $script . "\n</body>", $content);
}

echo $content;
