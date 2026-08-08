<?php

namespace Websyspro\DevTools\Handlers;

use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\Interfaces\EventHandler;

class LogHandler implements EventHandler
{
  public function handle(
    string $file,
    DispatchType $dispatchType
  ): void {
    $time  = date( 'Y-m-d H:i:s' );
    $event = $dispatchType->name;
    $name  = basename( $file );

    echo "[{$time}] {$event}: {$name}\n";
  }
}
