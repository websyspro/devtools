<?php

namespace Websyspro\DevTools\Interfaces;

class DevTools
{
  public function __construct(
    public readonly array $includes,
    public readonly array $excludes,
    public readonly string $webSocketHost,
    public readonly string $webSocketPort,
    public readonly string $httpServerPort,
    public readonly string $documentRoot,
    public readonly string $errorReporting,
    public readonly string $scriptName
  ){}
}