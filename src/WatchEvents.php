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

  private int $scanInterval = 1;

  public function registerDirectory(
    string $directory
  ): void {
    if (isset( $this->directors ) === false) {
      $this->directors = new Collection();
    }

    $this->directors->add(
      $directory
    );
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

  private function scan(
    Collection $filesResults = new Collection(),
  ): Collection {
    clearstatcache();

    $this->directors->foreach(
      function( string $dirctory ) use( &$filesResults ) {
        $recursiveIteratorIterators = (
          new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( 
              $dirctory, FilesystemIterator::SKIP_DOTS
            )
          )
        );

        foreach ($recursiveIteratorIterators as $item) {
          if( $item->isFile() === true ){
            $filesResults->add(
              md5( $item->getPathname()), 
              $item->getMTime()
            );
          }
        }
      }
    );

    return $filesResults;
  }

  private function hasCreateFile(
    Collection $prev,
    Collection $curr
  ): void {
    $curr->foreach(
      function( string $file, string $key ) use( $prev ){
        if( $prev->findByKey( $key ) === null ){
          // TODO RESTART
          sleep( 1 );
        }
      }
    );
  }

  private function hasModifyFile(
    Collection $prev,
    Collection $curr
  ): void {
    $curr->foreach(
      function( string $time, string $key ) use( $prev ){
        if( $prev->findByKey( $key ) &&  $prev->findByKey( $key ) !== $time ){
          // TODO RESTART
          sleep( 1 );
        }
      }
    );    
  }
  
  private function hasRemoveFile(
    Collection $prev,
    Collection $curr
  ): void {
    $prev->foreach(
      function( string $time, string $key ) use( $curr ){
        if( $curr->findByKey( $key ) === null ){
          // TODO RESTART
          sleep( 1 );
        }
      }
    );    
  }  

  public function listen(
    Collection $prev = new Collection()
  ): never {
    while( true ){
      sleep(1);

      $curr = $this->scan();

      $this->hasCreateFile( $prev, $curr );
      $this->hasModifyFile( $prev, $curr );
      $this->hasRemoveFile( $prev, $curr );

      $prev = $curr;
    }   
  }
}