#!/usr/bin/env php
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

require BASE_DIR . "vendor/autoload.php";

use Websyspro\DevTools\WebSocket\Server;

$server = new Server();
$server->listen();
