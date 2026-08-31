<?php

namespace Websyspro\DevTools\Interfaces;

class WatchJSON
{
  public function __construct(
    public array $includes,
    public array $excludes,
    public string $webSocketHost,
    public int $webSocketPort,
    public int $httpServerPort,
    public string $documentRoot,
    public string $scriptName
  ){}
}