<?php

namespace Websyspro\DevTools\Handlers;

use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\Interfaces\EventHandler;
use Websyspro\DevTools\WatchEvents;
use Websyspro\DevTools\Shareds\Run;
use function sprintf;

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
    // Constructor vazio - WebSocket será iniciado no handle(Started)
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

    // Para outros eventos, não faz nada
    // O WebSocket Server que está rodando em processo separado
    // vai receber as conexões dos browsers
  }

  /**
   * Inicia o servidor WebSocket em processo separado
   * 
   * @return void
   */
  private function startWebSocketProcess(
  ): void {
    $this->websocketProcess = new Run();
    $this->websocketProcess->command(
      message: sprintf( 
        "%s vendor/bin/websocket-start %s", 
          PHP_BINARY, $this->port
      ), silence: true
    );

    // Aguarda WebSocket inicializar
    sleep(1);
  }
}
