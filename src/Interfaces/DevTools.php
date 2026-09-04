<?php

namespace Websyspro\DevTools\Interfaces;

use Websyspro\DevTools\Enums\ErrorReporting;

class DevTools
{
  public function __construct(
    public readonly array $includes,
    public readonly array $excludes,
    public readonly string $webSocketHost,
    public readonly string $webSocketPort,
    public readonly string $httpServerPort,
    public readonly string $documentRoot,
    public readonly ErrorReporting $errorReporting,
    public readonly string $scriptName
  ){}
}