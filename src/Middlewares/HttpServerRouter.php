<?php

namespace Websyspro\DevTools\Middlewares;

use Websyspro\DevTools\Enums\ErrorReporting;
use Websyspro\DevTools\Interfaces\DevTools;
use RuntimeException;
use Throwable;

use Websyspro\Utils\Collection;
use function defined;
use function sprintf;

class HttpServerRouter
{
  private string $documentRoot;
  private string $requestUri;
  private string|null $realFilePath;
  private string|null $realFilePathExt;
  private string|null $contentType;
  private DevTools $devTools;

  public function __construct(
  ){
    $this->configDefault();
    $this->configInitial();
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
    if( !defined( "DIR_BASE" )){
      throw new RuntimeException(
        "DIR_BASE is not defined"
      );
    }

    $devTools = sprintf(
      "%sdevTools.php", DIR_BASE
    );

    if( !file_exists( $devTools )){
      throw new RuntimeException(
        "devTools.php not found: {$devTools}"
      );
    }    

    $this->devTools = require $devTools;
    if( !($this->devTools instanceof DevTools) ){
      throw new RuntimeException(
        "Invalid watch.json structure"
      );
    }

    [ $this->documentRoot, $this->requestUri ] = [
      $_SERVER[ "DOCUMENT_ROOT" ] ?? getcwd(), parse_url(
        $_SERVER[ "REQUEST_URI" ], PHP_URL_PATH
      )
    ];

    $this->realFilePath = $this->realFilePath();
  }

  private function configInitial(
  ): void {
    if( empty( $this->devTools->errorReporting) === false ){
      $errorReporting = array_map( 
        fn( ErrorReporting $errorReporting ) => $errorReporting->name,
          $this->devTools->errorReporting 
      );

      error_reporting( 
        implode( "|", $errorReporting )
      );
    }

    $envs = new Collection(
      file( sprintf( 
        "%s.env", DIR_BASE
      ))
    );

    $envs = $envs->where( fn(string $line) => preg_match( "#^(\#|;)#", $line) === 0 );
    $envs = $envs->where( fn(string $line) => empty( trim( $line )) === false );
    $envs = $envs->mapper( fn(string $line) => explode( "=", $line ));
    $envs = $envs->mapper( function( array $env ){
      [ $key, $val ] = $env;

      putenv( sprintf(
        "%s=%s", trim( $key ), trim( $val, " \t\n\r\0\x0B\"'" )
      ));
    });    
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

    if( isset( $this->listMimiTypes()[$extension] )){
      [ $this->contentType ] = [
        $this->listMimiTypes()[
          $extension
        ]
      ];
    }

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

  private function throwableError(
    Throwable $throwable    
  ): string {
    return implode( PHP_EOL, [
      "<!DOCTYPE html>",
      "<html lang=\"en\">",
      "<head>",
        "<meta charset=\"UTF-8\">",
        "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">",
        "<title>Error</title>",
        "<link rel=\"stylesheet\" href=\"./css.css\" />",
      "</head>",
      "<body>",
        sprintf( "<pre><strong>%s</strong>\n%s:%d</pre>",
          htmlspecialchars( $throwable->getMessage()),
          htmlspecialchars( $throwable->getFile()), $throwable->getLine()
        ),
      "</body>",  
      "</html>"
    ]);
  }

  private function extractContent(
  ): string {
    ob_start();

    try {
      require $this->realFilePath;
      return $this->addScriptHotReload( ob_get_clean());
    } catch( Throwable $throwable ){
      ob_end_clean();
      http_response_code(500);
      return $this->addScriptHotReload(
        $this->throwableError( $throwable)
      );
    }
  }

  private function readFileStatic(
  ): void {
    header( "Content-Type: {$this->contentType}" );
    readfile( $this->realFilePath );
  }

  private function readFileNotStatic(
  ): void {
    exit( $this->extractContent() );
  }
}
