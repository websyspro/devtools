<?php

/**
 * Exemplo de uso do Browser Hot Reload
 * 
 * Este exemplo mostra como usar o BrowserReloadHandler junto com
 * o ServerRestartHandler para ter hot reload completo (servidor + navegador)
 */

require __DIR__ . '/vendor/autoload.php';

use Websyspro\DevTools\WatchEvents;
use Websyspro\DevTools\WebSocket\Server;
use Websyspro\DevTools\Handlers\ServerRestartHandler;
use Websyspro\DevTools\Handlers\BrowserReloadHandler;

// ─── Setup ───────────────────────────────────────────────────────────────────

// 1. Cria servidor WebSocket (roda em processo separado)
$websocketServer = new Server( port: 8080 );

// 2. Cria watcher
$watcher = new WatchEvents();

// ─── Handlers ────────────────────────────────────────────────────────────────

// Handler 1: Reinicia servidor PHP quando arquivos mudam
$serverHandler = new ServerRestartHandler();
$watcher->registerHandler( $serverHandler );

// Handler 2: Notifica browsers para recarregar
$browserHandler = new BrowserReloadHandler( $websocketServer );
$watcher->registerHandler( $browserHandler );

// ─── Inicialização ───────────────────────────────────────────────────────────

// NOTA: Em uso real, você precisa rodar o WebSocket em processo separado.
// Opção 1: Use o binário bin/dev-server que faz isso automaticamente
// Opção 2: Rode manualmente em dois terminais:
//
//   Terminal 1: php vendor/bin/websocket-server
//   Terminal 2: php vendor/bin/server-restart
//
// O bin/dev-server faz tudo isso pra você com pcntl_fork()

echo "Para desenvolvimento, use:\n";
echo "  php vendor/bin/dev-server\n\n";
echo "Ou rode separadamente:\n";
echo "  Terminal 1: php vendor/bin/websocket-server\n";
echo "  Terminal 2: php vendor/bin/server-restart\n";
