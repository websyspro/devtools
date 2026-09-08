<?php

namespace Websyspro\DevTools\Objects;

use Websyspro\DevTools\Enums\RequestExist;
use Websyspro\DevTools\Enums\RequestType;

class RequestTarget
{
  public PathInfos $pathInfos;
  public RequestType $type;

  public function __construct(
    private RequestHandler $requestHandler
  ){
    $this->handler();
    $this->handlerClear();
  }

  private function handler(
  ): void {
    $this->pathInfos = new PathInfos(
      $this->requestHandler
    );
  }
  
  private function handlerClear(
  ): void {
    unset( $this->requestHandler ); 
  }  
}