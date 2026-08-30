<?php

namespace Websyspro\DevTools\WebSocket;

use function array_merge;
use function in_array;
use function strlen;
use function chr;
use function ord;
use function array_search;
use function socket_select;
use function strpos;
use function preg_match;
use function trim;
use function base64_encode;
use function sha1;
use function socket_write;
use function socket_read;
use function socket_close;
use function substr;
use function pack;
use function json_encode;
use function json_decode;

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

    error_log( "[WebSocket] Server started on 0.0.0.0:{$this->port}" );
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

      // Timeout de 10ms para responsividade
      if( socket_select( $read, $write, $except, 0, 10000 ) < 1 ){
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
      $errorCode = socket_last_error( $this->socket );
      $errorMsg = socket_strerror( $errorCode );
      error_log( "[WebSocket] Failed to accept connection: {$errorMsg}" );
      return;
    }

    $request = socket_read( $client, 5000 );

    if( $request === false ){
      error_log( "[WebSocket] Failed to read client request" );
      socket_close( $client );
      return;
    }

    error_log( "[WebSocket] New connection - Request type: " . substr($request, 0, 50) );

    // Verifica se é uma requisição HTTP comum (não WebSocket)
    if( $this->isHttpRequest( $request ) ){
      $this->handleHttpRequest( $client, $request );
      return;
    }

    // É uma requisição WebSocket - faz o handshake
    $this->clients[] = $client;
    $this->performHandshake( $client, $request );
  }

  private function isHttpRequest(
    string $data
  ): bool {
    // POST requests são sempre HTTP
    // GET sem "Upgrade: websocket" é HTTP
    return strpos($data, "POST" ) === 0 || 
           (strpos($data, "GET" ) === 0 && strpos($data, "Upgrade: websocket" ) === false);
  }

  private function handleHttpRequest(
    $socket,
    string $data
  ): void {
    error_log( "[WebSocket] Handling HTTP request" );

    // Extrai o corpo da requisição
    preg_match( "#\r\n\r\n(.*)$#s", $data, $match );
    $body = $match[1] ?? "";

    // Usa o corpo como mensagem, padrão "reload"
    $message = $body ?: "reload";
    
    $this->broadcast([
      "type" => "reload",
      "message" => $message
    ]);

    // Envia resposta HTTP 200
    $response = "HTTP/1.1 200 OK\r\n" .
                "Content-Type: application/json\r\n" .
                "Content-Length: 21\r\n" .
                "Connection: close\r\n\r\n" .
                '{"status":"success"}';

    socket_write( $socket, $response, strlen($response) );
    socket_close( $socket );

    error_log( "[WebSocket] HTTP reload sent to " . count($this->clients) . " client(s)" );
  }

  private function performHandshake(
    $client,
    string $request
  ): void {
    error_log( "[WebSocket] Performing WebSocket handshake" );

    preg_match(
      "#Sec-WebSocket-Key:\s*(.+?)\s*\r\n#i",
      $request,
      $matches
    );

    if( empty( $matches[1] ) ){
      error_log( "[WebSocket] Sec-WebSocket-Key not found" );
      error_log( "[WebSocket] Request headers:\n" . substr($request, 0, 500) );
      $this->removeClient( $client );
      return;
    }

    $key = trim( $matches[1] );
    $acceptKey = base64_encode(
      sha1( $key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true )
    );

    error_log( "[WebSocket] Client key: {$key}" );
    error_log( "[WebSocket] Accept key: {$acceptKey}" );

    // Extrai Origin se presente (para CORS)
    $origin = '';
    if( preg_match( "#Origin:\s*(.+?)\s*\r\n#i", $request, $originMatch ) ){
      $origin = trim( $originMatch[1] );
      error_log( "[WebSocket] Origin: {$origin}" );
    }

    // Monta a resposta do handshake
    $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: {$acceptKey}\r\n";
    
    // Adiciona headers CORS se Origin foi enviado
    if( !empty( $origin ) ){
      $response .= "Access-Control-Allow-Origin: {$origin}\r\n";
      $response .= "Access-Control-Allow-Credentials: true\r\n";
    }
    
    $response .= "\r\n";

    $written = socket_write(
      $client, 
      $response,
      strlen( $response )
    );

    if( $written === false ){
      $errorCode = socket_last_error( $client );
      $errorMsg = socket_strerror( $errorCode );
      error_log( "[WebSocket] Failed to write handshake: {$errorMsg}" );
      $this->removeClient( $client );
    } else {
      error_log( "[WebSocket] ✓ Handshake successful - {$written} bytes written" );
      error_log( "[WebSocket] Total clients: " . count($this->clients) );
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

    // 0x88 = Close frame
    if( ord( $data[0] ) === 0x88 ){
      error_log( "[WebSocket] Client sent close frame" );
      $this->disconnectClient( $client );
      return;
    }

    $message = $this->decodeFrame( $data );
    
    if( $message ){
      error_log( "[WebSocket] Message received: {$message}" );
      $payload = json_decode( $message, true );
      
      if( isset($payload['type']) && $payload["type"] === "FileNotification" ){
        error_log( "[WebSocket] Broadcasting reload notification" );
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
      error_log( "[WebSocket] Client disconnected. Total clients: " . count($this->clients) );
    }
  }

  private function removeClient(
    $client
  ): void {
    $this->disconnectClient( $client );
  }

  public function broadcast(
    array $data
  ): void {
    if( empty( $this->clients ) ){
      error_log( "[WebSocket] No clients to broadcast to" );
      return;
    }

    $message = json_encode( $data );
    $frame = $this->encodeFrame( $message );

    error_log( "[WebSocket] Broadcasting to " . count($this->clients) . " client(s): {$message}" );

    foreach( $this->clients as $index => $client ){
      $result = @socket_write( $client, $frame, strlen( $frame ) );
      
      if( $result === false ){
        error_log( "[WebSocket] Failed to send to client, removing" );
        socket_close( $client );
        unset( $this->clients[$index] );
      }
    }
  }

  private function encodeFrame(
    string $message
  ): string {
    $length = strlen( $message );
    
    if( $length <= 125 ){
      return pack("CC", 0x81, $length) . $message;
    } elseif( $length <= 65535 ){
      return pack("CCn", 0x81, 126, $length) . $message;
    } else {
      return pack("CCNN", 0x81, 127, 0, $length) . $message;
    }
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
      if( strlen($data) < 4 ){
        return false;
      }
      $maskStart = 4;
      $length = unpack( 'n', substr( $data, 2, 2 ) )[1];
    } elseif( $length === 127 ){
      if( strlen($data) < 10 ){
        return false;
      }
      $maskStart = 10;
      $length = unpack( 'J', substr( $data, 2, 8 ) )[1];
    }

    if( strlen($data) < $maskStart + 4 + $length ){
      return false;
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

    error_log( "[WebSocket] Server stopped" );
  }
}
