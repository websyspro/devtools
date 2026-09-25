<?php

/**
 * WebSocket Server Runtime
 * 
 * Runtime interno para iniciar o servidor WebSocket.
 * Não deve ser exportado como binário do Composer.
 * Usado pelo BrowserReloadHandler para rodar em processo separado.
 */
defined( "BASE_DIR" ) || define(
  "BASE_DIR", realpath(
    dirname( __DIR__, 5 ) 
  ) . DIRECTORY_SEPARATOR
);


/*
 * Load Autoload
 * */
require_once BASE_DIR . "vendor/autoload.php";
use Websyspro\DevTools\Middlewares\HttpServerRouter;
use Websyspro\DevTools\Objects\RequestHandler;

$serverJson = json_decode(
  file_get_contents( sprintf(
    "%s\server.json", BASE_DIR
  ))
);

/**
 * Validação serverJson
 */
if( is_object( $serverJson )){
  $httpServerRouter = new HttpServerRouter(
    directorys: [ 
      ...[ $serverJson->documentRoot ],
      ...$serverJson->includes
    ], friendlyUrl: true 
  );

  $handlerResponse = $httpServerRouter->handlerResponse();
  if( $handlerResponse->empty() === false ){
    [ $requestHandler ] = $handlerResponse->toArray();

    if( $requestHandler instanceof RequestHandler ){
      if( $requestHandler->requestTarget->pathInfos->isStatic()){
        $requestHandler->requestTarget->pathInfos->handlerStaticResponse();
      } else {
        ob_start();
        
        try {
          require_once $requestHandler->requestTarget->pathInfos->file->name;
          exit( $httpServerRouter->addHotReload( ob_get_clean()));
        } catch ( Throwable $throwable ){
          ob_get_clean();
          $httpServerRouter->error( $throwable );
        }
      }
    }
  } else {
    $httpServerRouter->notFound();
  }
} else {
  echo "not server config";
}
