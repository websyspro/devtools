<?php

namespace Websyspro\DevTools\Interfaces;

use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\WatchEvents;

interface EventHandler
{
  public function watch(
    WatchEvents $watchEvents
  ): void;

  public function handler(
    DispatchType $dispatchType,
    string|null $file = null
  ): void;
}
