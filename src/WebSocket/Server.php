<?php

namespace Websyspro\DevTools\WebSocket;

use Websyspro\Logger\Terminal;
use Websyspro\Logger\Styled;

/**
 * WebSocket Server - Servidor WebSocket para hot reload
 * 
 * Gerencia conexões WebSocket para comunicação em tempo real com browsers.
 * Mantém lista de clientes conectados e faz broadcast de mensagens de reload.
 * 
 * @package Websyspro\DevTools\WebSocket
 */
class Server
{
  /**
   * Socket TCP do servidor
   * 
   * @var resource|null
   */
  private $socket = null;

  /**
   * Lista de clientes conectados
   * 
   * @var array<resource>
   */
  private array $clients = [];

  /**
   * Porta do servidor WebSocket
   * 
   * @var int
   */
  private int $port;

  /**
   * Construtor do servidor WebSocket
   * 
   * @param int $port Porta onde o servidor vai escutar (padrão: 8080)
   */
  public function __construct(
    int $port = 8081
  ){
    $this->port = $port;
  }

  /**
   * Inicia o servidor WebSocket
   * 
   * Cria socket TCP, faz bind na porta e entra em loop infinito
   * aceitando conexões e processando mensagens.
   * 
   * @return never
   */
  public function start(
  ): never {
    $this->createSocket();
    $this->printStartMessage();
    $this->listen();
  }

  /**
   * Cria o socket TCP do servidor
   * 
   * @return void
   */
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

  /**
   * Exibe mensagem de inicialização
   * 
   * @return void
   */
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

  /**
   * Loop principal do servidor
   * 
   * Aceita novas conexões e processa mensagens dos clientes conectados.
   * 
   * @return never
   */
  private function listen(
  ): never {
    while( true ){
      $read = array_merge( [$this->socket], $this->clients );
      $write = null;
      $except = null;

      if( socket_select( $read, $write, $except, 0, 200000 ) < 1 ){
        continue;
      }

      // Nova conexão
      if( in_array( $this->socket, $read ) ){
        $this->acceptConnection();
        unset( $read[ array_search( $this->socket, $read ) ] );
      }

      // Mensagens dos clientes
      foreach( $read as $client ){
        $this->handleClient( $client );
      }
    }
  }

  /**
   * Aceita nova conexão de cliente
   * 
   * @return void
   */
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

  /**
   * Realiza handshake WebSocket
   * 
   * @param resource $client Socket do cliente
   * @return void
   */
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
      sha1( $key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true )
    );

    $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

    socket_write( $client, $response );
  }

  /**
   * Processa mensagens do cliente
   * 
   * @param resource $client Socket do cliente
   * @return void
   */
  private function handleClient(
    $client
  ): void {
    $data = @socket_read( $client, 2048, PHP_BINARY_READ );

    if( $data === false || $data === '' ){
      $this->disconnectClient( $client );
      return;
    }

    // Detecta close frame
    if( ord( $data[0] ) === 0x88 ){
      $this->disconnectClient( $client );
      return;
    }
  }

  /**
   * Desconecta cliente
   * 
   * @param resource $client Socket do cliente
   * @return void
   */
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

  /**
   * Envia mensagem para todos os clientes conectados
   * 
   * @param array $data Dados a serem enviados (será convertido em JSON)
   * @return void
   */
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

  /**
   * Codifica mensagem em frame WebSocket
   * 
   * @param string $message Mensagem a ser codificada
   * @return string Frame WebSocket binário
   */
  private function encodeFrame(
    string $message
  ): string {
    $length = strlen( $message );
    $frame = chr(0x81); // Text frame

    if( $length <= 125 ){
      $frame .= chr( $length );
    } elseif( $length <= 65535 ){
      $frame .= chr(126) . pack( 'n', $length );
    } else {
      $frame .= chr(127) . pack( 'J', $length );
    }

    return $frame . $message;
  }

  /**
   * Para o servidor e fecha todas as conexões
   * 
   * @return void
   */
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
