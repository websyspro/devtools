#!/usr/bin/env php
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

require DevTools_Base_Dir . "vendor/autoload.php";

use Websyspro\DevTools\WebSocket\Server;

$server = new Server();
$server->listen();
