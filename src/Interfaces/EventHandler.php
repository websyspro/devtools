<?php

namespace Websyspro\DevTools\Interfaces;

use Websyspro\DevTools\Enums\DispatchType;

interface EventHandler
{
  public function handle(
    DispatchType $dispatchType,
    string|null $file = null
  ): void;
}
