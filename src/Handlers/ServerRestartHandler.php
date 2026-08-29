<?php

namespace Websyspro\DevTools\Handlers;

use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\Interfaces\EventHandler;
use Websyspro\DevTools\WatchEvents;

use Websyspro\Logger\Styled;
use Websyspro\Logger\Terminal;
use function sprintf;
use function is_resource;

/**
 * ServerRestartHandler - Handler para hot reload de servidores PHP
 * 
 * Implementa reinício automático do servidor PHP quando arquivos são modificados.
 * Gerencia o ciclo de vida do processo do servidor, incluindo inicialização,
 * parada e reinício, com suporte multiplataforma (Windows/Unix).
 * 
 * @package Websyspro\DevTools\Handlers
 * 
 * @example Uso básico
 * ```php
 * $handler = new ServerRestartHandler();
 * $watcher = new WatchEvents();
 * $watcher->registerHandler($handler);
 * $watcher->listen();
 * ```
 */
class ServerRestartHandler 
implements EventHandler
{
  private WatchEvents $watchEvents;
  private DispatchType $dispatchType;
  private mixed $serverProcess = null;
  private string|null $file;  

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
    $this->dispatchType = $dispatchType;
    $this->file = $file;

    $this->restartServer();
  }

  private function startServer(
  ): void {
    $this->printHeader();

    $cmd = sprintf( "%s %s", 
      PHP_BINARY, $this->watchEvents->watchJSON->script
    );

    $descriptors = [
      0 => [ "pipe", "r" ],
      1 => STDOUT,
      2 => STDERR,
    ];

    $process = proc_open(
      $cmd, 
      $descriptors,
      $pipes
    );

    if( !is_resource( $process ) ){
      // echo "[ERRO] Falha ao iniciar servidor\n";
      return;
    }

    fclose( $pipes[0] );
    $this->serverProcess = $process;
  }

  /**
   * Reinicia o servidor PHP
   * 
   * Para o servidor atual (se estiver rodando) e inicia um novo processo.
   * Método chamado automaticamente quando mudanças são detectadas.
   * 
   * @return void
   */
  private function restartServer(
  ): void {
    $this->stopServer();
    $this->startServer();
  }  

  /**
   * Para o processo do servidor em execução
   * 
   * Envia sinal de término ao processo do servidor de forma adequada
   * ao sistema operacional (taskkill no Windows, kill no Unix).
   * Aguarda 1 segundo após o término para garantir liberação de recursos.
   * 
   * @return void
   */
  private function stopServer(
  ): void {
    if( !is_resource( $this->serverProcess ) ){
      return;
    }

    $status = proc_get_status(
      $this->serverProcess
    );

    if( $status[ "running" ] ){
      $pid = $status[ "pid" ];

      if( PHP_OS_FAMILY === "Windows" ){
        exec( "taskkill /F /T /PID {$pid} 2>NUL" );
      } else {
        exec( "kill -TERM {$pid} 2>/dev/null" );
      }
    }

    proc_close( $this->serverProcess );
    usleep( 1 * 500000 ); 
  }

  private function printHeaderDispatchTypeText(
  ): string {
    return sprintf(
      "[%s] %s", $this->dispatchType->name, $this->file
    );
  }  

  /**
   * Exibe cabeçalho informativo no terminal
   * 
   * Mostra o nome do sistema, script em execução e timestamp
   * de início do monitoramento com cores ANSI.
   * 
   * @return void
   */
  private function printHeader(
  ): void {
    Terminal::init()
      ->clear()
      ->text( "[Watch] Server Restart Handler", new Styled(
        color: [255,200,15], bgColor: [], bold: true
      ))
      ->eof()
      ->green(
        $this->printHeaderDispatchTypeText()
      )
      ->eof()  
      ->eof();
  }
}
