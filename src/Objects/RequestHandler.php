<?php

namespace Websyspro\DevTools\Objects;

use Websyspro\DevTools\Enums\RequestType;

class RequestHandler
{
  public RequestType $requestType;
  public RequestTarget $requestTarget;

  public function __construct(
    public string $uri,
    public string $directory,
    public bool $friendlyUrl = false
  ){
    $this->handler();
  }

  private function handlerIsDirectory(
  ): void {
    $this->requestType = empty(
      pathinfo( $this->uri, PATHINFO_EXTENSION )
    ) === false ? RequestType::File : RequestType::Directory;
  }

  private function handlerRequestProps(
  ): void {}  

  private function handlerRequestTarget(
  ): void {
    $this->requestTarget = new RequestTarget( $this );
  }  

  private function handler(
  ): void {
    $this->handlerIsDirectory();
    $this->handlerRequestProps();
    $this->handlerRequestTarget();
  }
}