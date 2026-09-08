<?php

/**
 * WebSocket Server Runtime
 * 
 * Runtime interno para iniciar o servidor WebSocket.
 * Não deve ser exportado como binário do Composer.
 * Usado pelo BrowserReloadHandler para rodar em processo separado.
 */
defined( "DevTools_Base_Dir" ) || define(
  "DevTools_Base_Dir", realpath(
    dirname( __DIR__, 5 ) 
  ) . DIRECTORY_SEPARATOR
);


/*
 * Load Autoload
 * */
require_once DevTools_Base_Dir . "vendor/autoload.php";
use Websyspro\DevTools\Middlewares\HttpServerRouter;
use Websyspro\DevTools\Objects\RequestHandler;
use Websyspro\DevTools\Interfaces\DevTools;

/**
 * Load devTools
 */
$devTools = require_once DevTools_Base_Dir . "devTools.php";

/**
 * Validação devTools
 */
if( $devTools instanceof DevTools ){
  $httpServerRouter = new HttpServerRouter(
    directorys: [ 
      ...[ $devTools->documentRoot ],
      ...$devTools->includes
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
  echo "not devTools";
}
