<?php

namespace Websyspro\DevTools\Handlers;

use Websyspro\DevTools\Consts\Hosts;
use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\Interfaces\EventHandler;
use Websyspro\DevTools\WatchEvents;
use Websyspro\DevTools\Shareds\Run;
use function sprintf;
use function strlen;
use function chr;

class BrowserReloadHandler 
implements EventHandler 
{
  private WatchEvents $watchEvents;
  private Run|null $webSocketProcess = null;
  private Run|null $httpServerProcess = null;

  public function __construct(
    private int $webSocketPort = 3002,
    private int $httpServerPort = 3001
  ){}

  public function watch(
    WatchEvents $watchEvents
  ): void {
    $this->watchEvents = $watchEvents;
  }

  public function handler(
    DispatchType $dispatchType,
    string|null $file = null
  ): void {
    $dispatchType === DispatchType::Started
      ? $this->startWebSocketProcess()
      : $this->sendWebSocketProcess();
  }

  private function startWebSocketProcess(
  ): void {
    $this->webSocketProcess = new Run();
    $this->webSocketProcess->command(
      message: sprintf( 
        "%s %s/Runtimes/websocket-start.php %s", 
        PHP_BINARY, dirname(__FILE__, 2), $this->webSocketPort
      ), silence: true
    );

    // Define document root a partir do watch.json
    $documentRoot = defined('DIR_BASE') ? DIR_BASE : getcwd();
    if (isset($this->watchEvents->watchJSON->includes[0])) {
      $documentRoot .= DIRECTORY_SEPARATOR . $this->watchEvents->watchJSON->includes[0];
    }

    $routerPath = dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . 'Runtimes' . DIRECTORY_SEPARATOR . 'http-server-router.php';

    $this->httpServerProcess = new Run();
    $this->httpServerProcess->command(
      message: sprintf( 
        "%s -S localhost:%s -t %s %s", 
        PHP_BINARY, 
        $this->httpServerPort,
        $documentRoot,
        $routerPath
      ), 
      silence: true,
      env: [
        'WEBSOCKET_PORT' => (string)$this->webSocketPort
      ]
    );    

    sleep(1);
  }

  private function sendWebSocketProcess(
  ): void {
    $socket = @socket_create(
      AF_INET, 
      SOCK_STREAM,
      SOL_TCP
    );
    
    if( $socket === false ){
      return;
    }

    $connected = @socket_connect( 
      $socket, Hosts::$hostname, $this->webSocketPort
    );
    
    if( $connected === false ){
      socket_close( $socket );
      return;
    }

    // Realiza handshake WebSocket
    $key = base64_encode( random_bytes(16) );
    $header = "GET / HTTP/1.1\r\n" .
              "Host: 127.0.0.1:{$this->webSocketPort}\r\n" .
              "Upgrade: websocket\r\n" .
              "Connection: Upgrade\r\n" .
              "Sec-WebSocket-Key: {$key}\r\n" .
              "Sec-WebSocket-Version: 13\r\n\r\n";

    socket_write( $socket, $header );
    socket_read( $socket, 2048 );

    // Envia mensagem de notificação
    $message = json_encode([
      'type' => 'FileNotification',
      'timestamp' => time()
    ]);

    $frame = $this->encodeWebSocketFrame( $message );
    socket_write( $socket, $frame );
    socket_close( $socket );
  }

  private function encodeWebSocketFrame(
    string $message
  ): string {
    $length = strlen( $message );
    $frame = chr(0x81); // Text frame
    
    if( $length <= 125 ){
      $frame .= chr( $length | 0x80 );
    } elseif( $length <= 65535 ){
      $frame .= chr(126 | 0x80) . pack( 'n', $length );
    } else {
      $frame .= chr(127 | 0x80) . pack( 'J', $length );
    }

    // Adiciona máscara (4 bytes aleatórios)
    $mask = random_bytes(4);
    $frame .= $mask;

    // Aplica máscara na mensagem
    for( $i = 0; $i < $length; $i++ ){
      $frame .= $message[$i] ^ $mask[$i % 4];
    }

    return $frame;
  }
}
