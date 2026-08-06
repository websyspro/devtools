<?php

namespace Websyspro\DevTools;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Websyspro\Utils\Collection;

class WatchEvents
{
  private Collection $events;
  private Collection $directors;

  private function registerDirectory(
    string $directory
  ): void {
    if (isset( $this->directors ) === false) {
      $this->directors = new Collection();
    }

    $this->directors->add(
      $directory
    );
  }

  private function scanFiles(
    RecursiveIteratorIterator $recursiveIteratorIterators,
    Collection $filesResults = new Collection(),
  ): Collection {
    clearstatcache();

    $recursiveIteratorIterators = (
      new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( 
          "C:\Temp", FilesystemIterator::SKIP_DOTS
        )
      )
    );

    foreach ($recursiveIteratorIterators as $item) {
      if ($item->isFile() === true) {
        $filesResults->add(
          $item->getPathname(),
          $item->getMTime()
        );
      }
    }

    return $filesResults;
  }

  public function registerEvent(
    string $handleEvent
  ): void {
    if (isset( $this->events ) === false) {
      $this->events = new Collection();
    }

    $this->events->add(
      $handleEvent
    );
  }

  public function listen(
  ): void {
    
  }
}