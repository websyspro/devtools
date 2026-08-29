<?php

namespace Websyspro\DevTools\Interfaces;

class WatchJSON
{
  public function __construct(
    public readonly array $includes,
    public readonly array $excludes,
    public readonly int $webSocketPort,
    public readonly int $httpServerPort,
    public readonly string $documentRoot,
    public readonly string $scriptName
  ){}
}