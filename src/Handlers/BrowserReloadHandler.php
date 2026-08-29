<?php

namespace Websyspro\DevTools\Handlers;

use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\Interfaces\EventHandler;
use Websyspro\DevTools\WatchEvents;
use Websyspro\DevTools\Shareds\Run;
use Websyspro\Logger\Terminal;
use function sprintf;

class BrowserReloadHandler 
implements EventHandler
{
  private WatchEvents $watchEvents;
  private Run|null $websocketProcess = null;

  public function __construct(
    private int $port = 8080
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
    if( $dispatchType === DispatchType::Started ){
      $this->startWebSocketProcess();
    } else {
      Terminal::init()->text( $dispatchType->name );
    }
  }

  private function startWebSocketProcess(
  ): void {
    $this->websocketProcess = new Run();
    $this->websocketProcess->command(
      message: sprintf( 
        "%s %s/Runtimes/websocket-start.php %s", 
        PHP_BINARY, dirname(__FILE__, 2), $this->port
      ), silence: true
    );

    sleep(1);
  }
}
