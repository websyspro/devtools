<?php

namespace Websyspro\DevTools\WebSocket;

use Websyspro\Logger\Terminal;
use function array_merge;
use function in_array;
use function strlen;
use function chr;
use function ord;

class Server
{
  private $socket = null;
  private array $clients = [];
  private int $port;

  public function __construct(
    int $port = 8081
  ){
    $this->port = $port;
  }

  public function start(
  ): never {
    $this->createSocket();
    $this->printStartMessage();
    $this->listen();
  }

  private function createSocket(
  ): void {
    $this->socket = socket_create(
      AF_INET, SOCK_STREAM, SOL_TCP
    );

    socket_set_option(
      $this->socket, SOL_SOCKET, SO_REUSEADDR, 1
    );

    socket_bind(
      $this->socket, '0.0.0.0', $this->port
    );

    socket_listen(
      $this->socket
    );
  }

  private function printStartMessage(
  ): void {
    Terminal::init()
      ->clear()
      ->text("[WebSocket] Server Started" )
      ->eof()
      ->text("Listening on ")
      ->text("ws://localhost:{$this->port}")
      ->eof()
      ->text("Waiting for browser connections...")
      ->eof()
      ->eof();
  }

  private function listen(
  ): never {
    while( true ){
      $read = array_merge( [$this->socket], $this->clients );
      $write = null;
      $except = null;

      if( socket_select( $read, $write, $except, 0, 200000 ) < 1 ){
        continue;
      }

      if( in_array( $this->socket, $read ) ){
        $this->acceptConnection();
        unset( $read[ array_search( $this->socket, $read ) ] );
      }

      foreach( $read as $client ){
        $this->handleClient( $client );
      }
    }
  }

  private function acceptConnection(
  ): void {
    $client = socket_accept( $this->socket );

    if( $client === false ){
      return;
    }

    $this->clients[] = $client;
    $this->performHandshake( $client );

    Terminal::init()
      ->text("[WebSocket] ")
      ->green("Client connected")
      ->text(" (Total: " . count($this->clients) . ")")
      ->eof();
  }

  private function performHandshake(
    $client
  ): void {
    $request = socket_read( $client, 5000 );

    preg_match( "#Sec-WebSocket-Key: (.*)\r\n#", $request, $matches );

    if( empty( $matches[1] ) ){
      return;
    }

    $key = $matches[1];
    $acceptKey = base64_encode(
      sha1( "{$key}258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true )
    );

    $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

    socket_write(
      $client, 
      $response
    );
  }

  private function handleClient(
    $client
  ): void {
    $data = @socket_read( $client, 2048, PHP_BINARY_READ );

    if( $data === false || $data === '' ){
      $this->disconnectClient( $client );
      return;
    }

    if( ord( $data[0] ) === 0x88 ){
      $this->disconnectClient( $client );
      return;
    }

    // Decodifica a mensagem WebSocket
    $message = $this->decodeFrame( $data );
    
    if( $message ){
      $payload = json_decode( $message, true );
      
      // Se receber FileNotification, faz broadcast para todos os clientes
      if( isset($payload['type']) && $payload['type'] === 'FileNotification' ){
        $this->broadcast([
          'type' => 'reload',
          'message' => 'File changed, reloading...'
        ]);
      }
    }
  }

  private function disconnectClient(
    $client
  ): void {
    $key = array_search( $client, $this->clients );

    if( $key !== false ){
      unset( $this->clients[$key] );
      socket_close( $client );

      Terminal::init()
        ->text("[WebSocket] ")
        ->text("Client disconnected")
        ->text(" (Total: " . count($this->clients) . ")")
        ->eof();
    }
  }

  public function broadcast(
    array $data
  ): void {
    if( empty( $this->clients ) ){
      return;
    }

    $message = json_encode( $data );
    $frame = $this->encodeFrame( $message );

    foreach( $this->clients as $client ){
      @socket_write( $client, $frame, strlen( $frame ) );
    }

    Terminal::init()
      ->text("[WebSocket] ")
      ->text("Broadcast sent")
      ->text(" → {$message}")
      ->eof();
  }

  private function encodeFrame(
    string $message
  ): string {
    $length = strlen( $message );
    $frame = chr(0x81);

    if( $length <= 125 ){
      $frame .= chr( $length );
    } elseif( $length <= 65535 ){
      $frame .= chr(126) . pack( 'n', $length );
    } else {
      $frame .= chr(127) . pack( 'J', $length );
    }

    return $frame . $message;
  }

  private function decodeFrame(
    string $data
  ): string|false {
    if( strlen( $data ) < 2 ){
      return false;
    }

    $length = ord( $data[1] ) & 127;
    $maskStart = 2;
    
    if( $length === 126 ){
      $maskStart = 4;
      $length = unpack( 'n', substr( $data, 2, 2 ) )[1];
    } elseif( $length === 127 ){
      $maskStart = 10;
      $length = unpack( 'J', substr( $data, 2, 8 ) )[1];
    }

    $masks = substr( $data, $maskStart, 4 );
    $decoded = '';
    
    for( $i = 0; $i < $length; $i++ ){
      $decoded .= $data[ $maskStart + 4 + $i ] ^ $masks[ $i % 4 ];
    }

    return $decoded;
  }

  public function stop(
  ): void {
    foreach( $this->clients as $client ){
      socket_close( $client );
    }

    if( $this->socket ){
      socket_close( $this->socket );
    }

    Terminal::init()
      ->text("[WebSocket] ")
      ->text("Server stopped")
      ->eof();
  }
}
