<?php

namespace Websyspro\DevTools\Interfaces;

use Websyspro\DevTools\Enums\DispatchType;

interface EventHandler
{
  public function handle(
    string $file,
    DispatchType $dispatchType
  ): void;
}
