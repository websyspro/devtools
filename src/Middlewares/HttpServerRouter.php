<?php

namespace Websyspro\DevTools\Middlewares;

use Websyspro\DevTools\Interfaces\WatchJSON;
use function defined;

class HttpServerRouter
{
  private string $documentRoot;
  private int $httpServerPort;

  public function __construct(
  ){
    $this->configDefault();
  }

  private function configDefault(
  ): void {
    if( defined( "DIR_BASE" )){
      $watchFile = sprintf(
        "%swatch.json", DIR_BASE
      );

      if( file_exists( $watchFile )){
        $watchJSON = new WatchJSON(
          ...(array)json_decode(
            file_get_contents( $watchFile )
          )
        );

        if( $watchJSON instanceof WatchJSON ){
          $this->httpServerPort = $watchJSON->httpServerPort;
          $this->documentRoot = $_SERVER[
            "DOCUMENT_ROOT"
          ] ?? getcwd();
        }
      }
    }

    print_r($this);
  }   
}
