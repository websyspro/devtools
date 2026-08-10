<?php

namespace Websyspro\DevTools\Handlers;

use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\Interfaces\EventHandler;
use Websyspro\DevTools\WatchEvents;

class ServerRestartHandler 
implements EventHandler
{
  private WatchEvents $watchEvents;
  private mixed $serverProcess = null;
  private string $serverScript;

  public function __construct(
  ){}

  public function watch(
    WatchEvents $watchEvents
  ): void {
    $this->watchEvents = $watchEvents;
  }

  public function handle(
    DispatchType $dispatchType,
    string|null $file = null
  ): void {
    $this->log( $dispatchType->name, basename( $file ) );
    $this->restartServer();
  }

  private function startServer(
  ): void {
    $this->clearTerminal();
    $this->printHeader();

    $cmd = PHP_BINARY . " " . $this->watchEvents->watchJSON->script;
    
    $descriptors = [
      0 => [ "pipe", "r" ],
      1 => STDOUT,
      2 => STDERR,
    ];

    $process = proc_open( $cmd, $descriptors, $pipes );

    if( !is_resource( $process ) ){
      echo "[ERRO] Falha ao iniciar servidor\n";
      return;
    }

    fclose( $pipes[0] );
    $this->serverProcess = $process;
  }

  private function stopServer(): void
  {
    if( !is_resource( $this->serverProcess ) ){
      return;
    }

    $status = proc_get_status( $this->serverProcess );

    if( $status['running'] ){
      $pid = $status['pid'];

      if( PHP_OS_FAMILY === "Windows" ){
        exec( "taskkill /F /T /PID {$pid} 2>NUL" );
      } else {
        exec( "kill -TERM {$pid} 2>/dev/null" );
      }
    }

    proc_close( $this->serverProcess );
    echo "[INFO] Servidor encerrado\n";
  }

  private function restartServer(): void
  {
    $this->stopServer();
    sleep(1);
    $this->startServer();
  }

  private function clearTerminal(): void
  {
    echo "\033[2J\033[H";
  }

  private function printHeader(): void
  {
    $time = date( 'Y-m-d H:i:s' );
    echo "\033[35mPHP Watch — Hot Reload\033[0m\n";
    echo "\033[32mExecutando {$this->serverScript}\033[0m\n";
    echo "\033[32mMonitorando mudanças — {$time}\033[0m\n\n";
  }

  private function log(
    string $event,
    string $file
  ): void {
    $time = date( 'Y-m-d H:i:s' );
    echo "\033[35m[{$time}] {$event}: {$file}\033[0m\n";
  }
}
