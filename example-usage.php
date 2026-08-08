<?php

/**
 * Exemplo de uso do WatchEvents com classe como handler
 * 
 * A classe WatchEvents detecta mudanças e dispara eventos.
 * Uma classe handler implementa a lógica de resposta.
 */

require __DIR__ . '/vendor/autoload.php';

use Websyspro\DevTools\WatchEvents;
use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\Handlers\ServerRestartHandler;

// ─── Orquestração com WatchEvents e Handler ──────────────────────────────────

$watcher = new WatchEvents();

// Registra diretório para monitorar
$watcher->registerDirectory( __DIR__ . "/src" );

// Exclui pastas que não devem disparar reload
$watcher->excludePattern( "Caches" );
$watcher->excludePattern( "vendor" );

// Cria o handler que gerencia o servidor
$serverHandler = new ServerRestartHandler(
  serverScript: __DIR__ . "/index.php"
);

// Registra o handler para o evento Started (inicialização)
$watcher->on( DispatchType::Started,  $serverHandler );

// Registra o mesmo handler para eventos de mudança
$watcher->on( DispatchType::Created,  $serverHandler );
$watcher->on( DispatchType::Modified, $serverHandler );
$watcher->on( DispatchType::Deleted,  $serverHandler );

// Inicia o watch (loop infinito)
$watcher->listen( interval: 1 );
