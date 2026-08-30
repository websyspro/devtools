<?php

namespace Websyspro\DevTools\Middlewares;

use Websyspro\DevTools\Consts\Hosts;
use Websyspro\DevTools\Interfaces\WatchJSON;

class HttpServerRouter
{
  private int $websocketPort;
  private string $documentRoot;
  private WatchJSON $watchJSON;

  public function __construct(
  ){
    $this->configDefault();
  }

  private function configDefault(
  ): void {
    // Pega porta do WebSocket da variável de ambiente ou usa 8080 como padrão
    $this->websocketPort = (int)(getenv('WEBSOCKET_PORT') ?: 8080);
    $this->documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? getcwd();
    
    if( defined( "DIR_BASE" )){
      $watchFile = sprintf(
        "%swatch.json", DIR_BASE
      );

      if( file_exists( $watchFile )){
        $this->watchJSON = new WatchJSON(
          ...(array)json_decode(
            file_get_contents( $watchFile )
          )
        );      
      }
    }
  }  

  public function listen(): void
  {
    $requestUri = $_SERVER['REQUEST_URI'];
    $path = parse_url($requestUri, PHP_URL_PATH);
    $filePath = $this->documentRoot . $path;

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
      return; // Deixa o servidor embutido servir o arquivo
    }

    // Captura a saída para injetar o script
    ob_start();

    if (is_file($filePath)) {
      require $filePath;
    } else {
      http_response_code(404);
      echo "404 - Not Found";
    }

    $content = ob_get_clean();

    // Injeta o script de reload apenas em respostas HTML
    if (stripos($content, '</body>') !== false) {
      $content = $this->injectReloadScript($content);
    }

    echo $content;
  }

  private function injectReloadScript(string $content): string
  {
    $script = <<<HTML

<!-- DevTools: Hot Reload Script -->
<script>
(function() {
    const ws = new WebSocket('ws://localhost:{$this->websocketPort}');
    
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

    return str_ireplace('</body>', $script . "\n</body>", $content);
  }
}
