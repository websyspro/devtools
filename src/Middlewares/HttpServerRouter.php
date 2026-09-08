<?php

namespace Websyspro\DevTools\Middlewares;

use Throwable;
use Websyspro\DevTools\Objects\RequestHandler;
use Websyspro\DevTools\Responses\NotFound;
use Websyspro\DevTools\Responses\Error;
use Websyspro\DevTools\Enums\FileExist;
use Websyspro\Utils\Collection;
use function defined;
use function sprintf;

class HttpServerRouter
{
  public string $uri;

  public Collection $dirs;

  public function __construct(
    private array $directorys = [],
    private bool $friendlyUrl = false
  ){
    $this->handlerEnvs();
    $this->handlerSession();
  }

  public function handlerEnvs(
  ): void {
    if( defined( "DevTools_Base_Dir" )){
      $dotEnvsPath = sprintf(
        "%s%s", DevTools_Base_Dir, ".env"
      );

      if( file_exists( $dotEnvsPath )){
        $dotEnvs = new Collection(
          file( $dotEnvsPath )
        );

        $dotEnvs = $dotEnvs->where( 
          fn(string $line) => (
            preg_match( "#^(\#|;)#", $line) === 0 
            && empty( trim( $line )) === false
          )
        );

        $dotEnvs = $dotEnvs->mapper( 
          function( string $env ){
            [ $key, $txt ] = explode( "=", $env );

            putenv( sprintf(
              "%s=%s", trim( $key ), trim(
                $txt, " \t\n\r\0\x0B\"'"
              )
            ));
          }
        );        
      }
    }
  }

  private function handlerSession(
  ): void {
    $this->uri = parse_url(
      $_SERVER[ "REQUEST_URI" ], PHP_URL_PATH
    );
    
    $_SERVER = array_merge(
      $_SERVER, [ "PHP_SELF", $this->uri ]
    );
  }

  private function defineRealPath(
    string $dir
  ): string {
    return sprintf( "%s%s", DevTools_Base_Dir, $dir );
  }

  private function defineRealPathExist(
    string $dir
  ): bool {
    return file_exists( $dir );
  }
  
  public function addHotReload(
    string $content
  ): string {
    $baseDirScriptReload = sprintf(
      "%s/Scripts/reload.js", dirname( __DIR__, 1 )
    );

    if( !file_exists( $baseDirScriptReload )){
      return $content;
    }

    return str_ireplace(
      "</body>", sprintf(
        "\n<script>\n%s\n</script>\n</body>", file_get_contents(
          $baseDirScriptReload
        )
      ), $content
    );
  }  

  public function handlerResponse(
    Collection $routers = new Collection()
  ): Collection {
    $routers = new Collection(
      items: $this->directorys
    );

    $routers = $routers->mapper( 
      fn: fn( string $directory ): string => (
        $this->defineRealPath( $directory )
      )
    );

    $routers = $routers->where(
      fn: fn( string $directory ): bool => (
        $this->defineRealPathExist( $directory )
      )
    );

    $routers = $routers->mapper( 
      fn: fn( string $directory ): RequestHandler => (
        new RequestHandler(
          uri: $this->uri, 
          friendlyUrl: $this->friendlyUrl, 
          directory: $directory 
        )
      )
    );

    return $routers->where(
      fn: fn( RequestHandler $requestHandler ): bool => (
        $requestHandler->requestTarget->pathInfos->fileExist === FileExist::Yes
      )
    );
  }

  public function notFound(
  ): void {
    http_response_code( 404 );
    exit( NotFound::html());
  }

  public function error(
    Throwable $throwable
  ): void {
    http_response_code( 501 );
    exit( Error::html( $throwable ));
  }  
}