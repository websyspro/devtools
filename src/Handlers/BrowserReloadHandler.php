<?php

namespace Websyspro\DevTools\Handlers;

use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\Interfaces\EventHandler;
use Websyspro\DevTools\WatchEvents;
use Websyspro\DevTools\WebSocket\Server;
use Websyspro\DevTools\Shareds\Run;

/**
 * BrowserReloadHandler - Handler para hot reload no navegador
 * 
 * Inicia servidor WebSocket e envia notificações quando arquivos são modificados,
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
   * Processo do WebSocket rodando em background
   * 
   * @var Run|null
   */
  private Run|null $websocketProcess = null;

  /**
   * Construtor do handler
   * 
   * @param int $port Porta do servidor WebSocket (padrão: 8080)
   */
  public function __construct(
    private int $port = 8080
  ){
    $this->server = new Server($this->port);
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
   * @param DispatchType $dispatchType Tipo do evento
   * @param string|null $file Caminho do arquivo modificado
   * @return void
   */
  public function handle(
    DispatchType $dispatchType,
    string|null $file = null
  ): void {
    // Evento Started: Inicia o servidor WebSocket em processo separado
    if ($dispatchType === DispatchType::Started) {
      $this->startWebSocketProcess();
      return;
    }

    // Detecta tipo de arquivo para reload inteligente
    $reloadType = $this->getReloadType($file);

    // Envia broadcast para todos os clientes
    $this->server->broadcast([
      'reload' => true,
      'type' => $reloadType,
      'file' => $file ? basename($file) : null,
      'timestamp' => time()
    ]);
  }

  /**
   * Inicia o servidor WebSocket em processo separado
   * 
   * @return void
   */
  private function startWebSocketProcess(
  ): void {
    $websocketBin = dirname(__DIR__, 2) . '/bin/websocket-start';
    $command = PHP_BINARY . ' ' . $websocketBin . ' ' . $this->port;

    $this->websocketProcess = new Run();
    $this->websocketProcess->command($command, silence: true);

    // Aguarda WebSocket inicializar
    sleep(1);
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
}
