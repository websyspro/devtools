<?php

namespace Websyspro\DevTools\Handlers;

use Websyspro\DevTools\Consts\Hosts;
use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\Interfaces\EventHandler;
use Websyspro\DevTools\WatchEvents;
use Websyspro\DevTools\Shareds\Run;
use Websyspro\Logger\Styled;
use Websyspro\Logger\Terminal;
use function sprintf;
use function strlen;
use function chr;

class BrowserReloadHandler 
extends EventHandler 
{
  private WatchEvents $watchEvents;
  private Run|null $webSocketProcess = null;
  private Run|null $httpServerProcess = null;

  public function __construct(
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
      : $this->sendWebSocketProcess( $dispatchType, $file );
  }

  private function startWebSocketProcess(
  ): void {
    $this->webSocketProcess = new Run();
    $this->webSocketProcess->command(
      message: sprintf( 
        "%s %s/Runtimes/websocket-start.php", 
        PHP_BINARY, dirname(__FILE__, 2)
      ), silence: true
    );

    $this->httpServerProcess = new Run();
    $this->httpServerProcess->command(
      message: sprintf( 
        "%s -S localhost:%s -t %s%s %s/Runtimes/http-server-router.php", 
        PHP_BINARY, $this->watchEvents->watchJSON->httpServerPort, 
        DIR_BASE, $this->watchEvents->watchJSON->documentRoot, dirname(__FILE__, 2)
      ), silence: true
    );    

    sleep(1);

    $this->headerTerminal();
  }

  private function headerTerminal(
  ): void {
    Terminal::init()
      ->text( "DevTools v1.0.0 - " )
      ->yellow( "Browser Reloader" )->line()
      ->text( " - Local:" )->spc()
      ->green( "http://localhost:{$this->watchEvents->watchJSON->httpServerPort}" )
      ->line()
      ->line()
      ->cursorHide();
  }  

  private function sendWebSocketProcess(
    DispatchType $dispatchType,
    string|null $file = null
  ): void {
    $handlerPort = $this->watchEvents->watchJSON->webSocketPort;
    $handler = @socket_create(
      AF_INET, SOCK_STREAM, SOL_TCP
    );
    
    if( $handler ){
      $handlerConected = @socket_connect( 
        $handler, Hosts::$hostname, $handlerPort
      );
      
      if( $handlerConected === false ){
        socket_close( $handler );
      } else {
        $handlerKey = base64_encode( random_bytes(16) );
        $handlerResponse = implode(
          "\r\n", [
            "GET / HTTP/1.1",
            "Host: 127.0.0.1:{$handlerPort}",
            "Upgrade: websocket",
            "Connection: Upgrade",
            "Sec-WebSocket-Key: {$handlerKey}",
            "Sec-WebSocket-Version: 13",
            "\r\n"
          ]
        );

        socket_write( $handler, $handlerResponse );
        socket_read( $handler, 2048 );
        socket_write( $handler, $this->encodeWebSocketFrame( "notification-client" ) );
        socket_close( $handler );

        $this->bodyTerminal(
          $dispatchType, $file
        );
      }
    }
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

  private function bodyTerminal(
    DispatchType $dispatchType,
    string|null $file = null
  ): void {
    Terminal::init()
      ->cursorPosition(4)
      ->text( sprintf( " %s ", $dispatchType->name ), match( $dispatchType ){
          DispatchType::Created  => new Styled([255,255,255], [0,200, 50], true),
          DispatchType::Modified => new Styled([255,255,255], [0,100, 130], true),
          DispatchType::Deleted  => new Styled([255,255,255], [200,0, 0], true),
            default => null
        }
      )
      ->spc()
      ->brightWhite( $file )
      ->text( sprintf( " - %s", date( "d/m/Y h:i:s" )));
  }
}
