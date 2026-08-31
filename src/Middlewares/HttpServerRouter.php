<?php

namespace Websyspro\DevTools\Middlewares;

use Websyspro\DevTools\Interfaces\WatchJSON;
use function defined;
use function sprintf;

class HttpServerRouter
{
  private int $httpServerPort;
  private string $documentRoot;
  private string $requestUri;
  private string|null $realFilePath;
  private string|null $realFilePathExt;
  private string|null $contentType;
  private string|null $content;

  public function __construct(
  ){
    $this->configDefault();
  }

  public function listen(
  ): void {
    if( $this->isStaticFile() ){
      $this->readFileStatic();
    } else {
      $this->readFileNotStatic();
    }
  }

  private function listMimiTypes(
  ): array {
    return [
      "css"  => "text/css",
      "js"   => "application/javascript",
      "json" => "application/json",
      "png"  => "image/png",
      "jpg"  => "image/jpeg",
      "jpeg" => "image/jpeg",
      "gif"  => "image/gif",
      "svg"  => "image/svg+xml",
      "ico"  => "image/x-icon",
      "woff" => "font/woff",
      "woff2"=> "font/woff2",
      "ttf"  => "font/ttf",
      "eot"  => "application/vnd.ms-fontobject",
      "map"  => "application/json",
    ];
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
          
          [ $this->documentRoot, $this->requestUri ] = [
            $_SERVER[ "DOCUMENT_ROOT" ] ?? getcwd(), 
            $_SERVER[ "REQUEST_URI" ]
          ];

          $this->realFilePath = $this->realFilePath();
        }
      }
    }
  }

  private function realFilePathExt(
    string $realFilePath
  ): string|null {
    $extension = pathinfo( 
      $realFilePath, PATHINFO_EXTENSION 
    );

    if( empty( $extension )){
      return null;
    }

    [ $this->contentType ] = [
      $this->listMimiTypes()[
        $extension
      ]
    ];

    return strtolower(
      $extension
    );
  }

  private function realFilePathExtExists(
  ): bool {
    return $this->realFilePathExt !== null;
  }

  private function isStaticFile(
  ): bool {
    return isset(
      $this->listMimiTypes()[
        $this->realFilePathExt
      ]
    );
  }

  private function defaultIndex(
    string $path
  ): string {
    return sprintf( "%s%sindex.php",
      rtrim( $path, "\\\/" ), DIRECTORY_SEPARATOR
    );
  }

  private function defaultIndexExists(
    string $path
  ): bool {
    return file_exists(
      $this->defaultIndex( $path )
    );
  }  

  private function realFilePath(
  ): string {
    $realFilePath = preg_replace(
      [ "#/#", "#\\\#" ], DIRECTORY_SEPARATOR, sprintf(
        "%s%s", $this->documentRoot, $this->requestUri 
      )
    );

    [ $this->realFilePathExt ] = [
      $this->realFilePathExt(
        $realFilePath
      )
    ];

    if( $this->realFilePathExtExists() === false ){
      if( $this->defaultIndexExists( $realFilePath ) === true ){
        return $this->defaultIndex( $realFilePath );
      }
    }

    return $realFilePath;
  }

  private function addScriptHotReload(
    string $content
  ): string {
    $baseDir = dirname( __DIR__, 1 );
    $baseDirScriptReload = sprintf(
      "%s/Scripts/reload.js", $baseDir
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

  private function extractContent(
  ): string {
    ob_start();
    require $this->realFilePath;
    return $this->addScriptHotReload( ob_get_clean());
  }

  private function readFileStatic(
  ): void {
    header( "Content-Type: {$this->contentType}" );
    readfile( $this->realFilePath );
  }

  private function readFileNotStatic(
  ): void {
    $this->content = $this->extractContent();
    exit( $this->content );
  }
}
