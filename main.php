<?php

use Websyspro\DevTools\WatchEvents;

$watchEvents = new WatchEvents();
$watchEvents->registerDirectory( __DIR__ . "/src" );
$watchEvents->listen();