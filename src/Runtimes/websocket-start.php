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
    dirname( __DIR__, 7 ) 
  ) . DIRECTORY_SEPARATOR
);

require DIR_BASE . "vendor/autoload.php";
use Websyspro\DevTools\WebSocket\Server;

$port = isset($argv[1]) ? (int)$argv[1] : 8081;
$server = new Server($port);
$server->start();
