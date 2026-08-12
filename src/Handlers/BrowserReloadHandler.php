<?php

namespace Websyspro\DevTools\Handlers;

use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\Interfaces\EventHandler;
use Websyspro\DevTools\WatchEvents;
use Websyspro\DevTools\WebSocket\Server;
use Websyspro\Logger\Terminal;
use Websyspro\Logger\Styled;

/**
 * BrowserReloadHandler - Handler para hot reload no navegador
 * 
 * Inicia um servidor WebSocket e envia notificações quando arquivos são modificados,
 * fazendo com que os browsers conectados recarreguem automaticamente.
 * 
 * @package Websyspro\DevTools\Handlers
 */
class BrowserReloadHandler implements EventHandler
{
  /**
   * Instância do servidor WebSocket
   * 
   * @var Server
   */
  private Server $server;

  /**
   * Instância do WatchEvents
   * 
   * @var WatchEvents
   */
  private WatchEvents $watchEvents;

  /**
   * PID do processo filho do WebSocket
   * 
   * @var int|null
   */
  private int|null $websocketPid = null;

  /**
   * Construtor do handler
   * 
   * Inicia o servidor WebSocket em processo separado automaticamente
   * se ainda não estiver rodando.
   * 
   * @param int $port Porta do servidor WebSocket (padrão: 8080)
   */
  public function __construct(
    private int $port = 8080
  ){
    if (!$this->isWebSocketRunning()) {
      $this->startWebSocketServer();
    }
  }

  /**
   * Verifica se o servidor WebSocket já está rodando
   * 
   * @return bool True se já estiver rodando, False caso contrário
   */
  private function isWebSocketRunning(
  ): bool {
    $socket = @fsockopen(
      "127.0.0.1", $this->port, $errno, $errstr, 1
    );
    
    if( $socket ){
      fclose($socket);
      return true;
    }
    
    return false;
  }

  /**
   * Inicia o servidor WebSocket em processo separado
   * 
   * @return void
   */
  private function startWebSocketServer(
  ): void {
    $this->server = new Server($this->port);

    // Fork process para rodar WebSocket em background
    $pid = pcntl_fork();

    if ($pid === -1) {
      Terminal::init()
        ->text("[BrowserReload] ", new Styled(color: [255,200,15]))
        ->text("ERROR: Failed to fork WebSocket process", new Styled(color: [255,0,0]))
        ->eof();
      return;
    } elseif ($pid === 0) {
      // Processo filho: Roda WebSocket Server
      $this->server->start();
      exit(0);
    } else {
      // Processo pai: Salva PID e continua
      $this->websocketPid = $pid;
      
      // Aguarda WebSocket inicializar
      sleep(1);
    }
  }

  /**
   * Registra a instância do WatchEvents
   * 
   * @param WatchEvents $watchEvents Instância do observador de eventos
   * @return void
   */
  public function watch(
    WatchEvents $watchEvents
  ): void {
    $this->watchEvents = $watchEvents;
  }

  /**
   * Manipula eventos de mudança de arquivos
   * 
   * Envia mensagem de reload para todos os browsers conectados
   * quando um arquivo é criado, modificado ou deletado.
   * 
   * @param DispatchType $dispatchType Tipo do evento
   * @param string|null $file Caminho do arquivo modificado
   * @return void
   */
  public function handle(
    DispatchType $dispatchType,
    string|null $file = null
  ): void {
    // Ignora evento Started
    if ($dispatchType === DispatchType::Started) {
      return;
    }

    // Detecta tipo de arquivo para reload inteligente
    $reloadType = $this->getReloadType($file);

    // Envia broadcast para todos os clientes via socket
    $this->sendBroadcastMessage([
      'reload' => true,
      'type' => $reloadType,
      'file' => $file ? basename($file) : null,
      'timestamp' => time()
    ]);
  }

  /**
   * Envia mensagem de broadcast para o servidor WebSocket
   * 
   * Usa socket TCP para se comunicar com o processo do WebSocket
   * 
   * @param array $data Dados a serem enviados
   * @return void
   */
  private function sendBroadcastMessage(
    array $data
  ): void {
    // Cria conexão TCP com o servidor WebSocket
    $socket = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 1);
    
    if (!$socket) {
      return;
    }

    // Envia comando de broadcast (protocolo interno)
    $message = json_encode(['command' => 'broadcast', 'data' => $data]);
    fwrite($socket, $message . "\n");
    fclose($socket);
  }

  /**
   * Determina o tipo de reload baseado na extensão do arquivo
   * 
   * @param string|null $file Caminho do arquivo
   * @return string Tipo de reload (full, css, script)
   */
  private function getReloadType(
    string|null $file
  ): string {
    if ($file === null) {
      return 'full';
    }

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    return match ($extension) {
      'css' => 'css',
      'js' => 'script',
      default => 'full'
    };
  }

  /**
   * Destrutor - Para o servidor WebSocket ao finalizar
   * 
   * @return void
   */
  public function __destruct()
  {
    if ($this->websocketPid !== null) {
      posix_kill($this->websocketPid, SIGTERM);
    }
  }
}
