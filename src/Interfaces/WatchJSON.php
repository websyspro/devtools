<?php

namespace Websyspro\DevTools\Interfaces;

class WatchJSON
{
  public function __construct(
    public readonly array $includes,
    public readonly array $excludes,
    public readonly string $directoryRoot,
    public readonly string $script
  ){}
}