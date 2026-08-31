#!/usr/bin/env php
<?php

/**
 * WebSocket Server Runtime
 * 
 * Runtime interno para iniciar o servidor WebSocket.
 * Não deve ser exportado como binário do Composer.
 * Usado pelo BrowserReloadHandler para rodar em processo separado.
 */

defined( "DIR_BASE" ) || define(
  "DIR_BASE", realpath(
    dirname( __DIR__, 5 ) 
  ) . DIRECTORY_SEPARATOR
);

require DIR_BASE . "vendor/autoload.php";

use Websyspro\DevTools\WebSocket\Server;

$server = new Server();
$server->start();
