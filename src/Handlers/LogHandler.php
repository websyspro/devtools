<?php

namespace Websyspro\DevTools\Handlers;

use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\Interfaces\EventHandler;

class LogHandler implements EventHandler
{
  public function handle(
    DispatchType $dispatchType,
    string|null $file = null
  ): void {
    $time  = date( 'Y-m-d H:i:s' );
    $event = $dispatchType->name;
    $name  = basename( $file );

    echo "[{$time}] {$event}: {$name}\n";
  }
}
