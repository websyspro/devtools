<?php

namespace Websyspro\DevTools\Middlewares;

use Websyspro\DevTools\Interfaces\WatchJSON;
use function sprintf;

class HttpServerRouter_
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
    print_r($_SERVER);

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

    // Se o arquivo existe e não é PHP, serve com o Content-Type correto
    if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
      $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'eot'  => 'application/vnd.ms-fontobject',
        'map'  => 'application/json',
      ];

      $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
      $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

      header("Content-Type: {$mime}");
      readfile($filePath);
      exit;
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

  private function injectReloadScript(
    string $content
  ): string {
    $baseDir = dirname( __DIR__, 1 );
    $baseDirScriptReload = sprintf(
      "%s/Scripts/reload.js", $baseDir
    );

    if( !file_exists( $baseDirScriptReload )){
      return $content;
    }

    return str_ireplace(
      "</body>", sprintf(
        "\n<script>\n%s\n</script>\n</body>", file_get_contents(
          $baseDirScriptReload
        )
      ), $content
    );
  }
}
