<?php

namespace Websyspro\DevTools\WebSocket;

use Websyspro\DevTools\Interfaces\WatchJSON;
use function array_merge;
use function in_array;
use function strlen;
use function chr;
use function ord;

class Server
{
  public WatchJSON $watchJSON;
  private $socket = null;
  private array $clients = [];

  public function __construct(
  ){
    $this->configDefault();
  }

  private function configDefault(
  ): void {
    if( defined( "DIR_BASE" )){
      $watchFile = sprintf(
        "%swatch.json", DIR_BASE
      );

      if( file_exists( $watchFile )){
        $this->watchJSON = new WatchJSON(
          ...(array)json_decode( file_get_contents( $watchFile ))
        );
      }
    }
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
      $this->socket, "0.0.0.0", $this->watchJSON->webSocketPort
    );

    socket_listen(
      $this->socket
    );
  }

  private function printStartMessage(
  ): void {
    // Server running in background
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
    $client = socket_accept(
      $this->socket
    );

    if( $client === false ){
      return;
    }

    $this->clients[] = $client;
    $this->performHandshake( $client );
  }

  private function performHandshake(
    $client
  ): void {
    $request = socket_read(
      $client, 5000
    );

    if( $request === false ){
      error_log( "[WebSocket] Failed to read handshake request" );
      return;
    }

    preg_match(
      "#Sec-WebSocket-Key:\s*(.+?)\s*\r\n#i",
      $request,
      $matches
    );

    if( empty( $matches[1] ) ){
      error_log( "[WebSocket] Sec-WebSocket-Key not found in request" );
      error_log( "[WebSocket] Request headers: " . substr($request, 0, 500) );
      return;
    }

    $key = trim( $matches[1] );
    $acceptKey = base64_encode(
      sha1( $key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true )
    );

    $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

    $written = socket_write(
      $client, 
      $response,
      strlen( $response )
    );

    if( $written === false ){
      error_log( "[WebSocket] Failed to write handshake response" );
    } else {
      error_log( "[WebSocket] Handshake successful for client" );
    }
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

    $message = $this->decodeFrame( $data );
    
    if( $message ){
      $payload = json_decode( $message, true );
      
      if( isset($payload['type']) && $payload["type"] === "FileNotification" ){
        $this->broadcast([
          "type" => "reload",
          "message" => "File changed, reloading..."
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
  }
}
