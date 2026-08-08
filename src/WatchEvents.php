<?php

namespace Websyspro\DevTools;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Websyspro\DevTools\Enums\DispatchType;

use function is_object;

class WatchEvents
{
  private array $handlers = [];
  private array $directories = [];
  private array $excludePatterns = [];

  public function registerDirectory(
    string $directory
  ): void {
    if( !is_dir( $directory ) ){
      throw new \InvalidArgumentException(
        "Directory not found: {$directory}"
      );
    }
    
    $this->directories[] = realpath( $directory );
  }

  public function excludePattern(
    string $pattern
  ): void {
    $this->excludePatterns[] = $pattern;
  }

  public function on(
    DispatchType $eventType,
    object|callable $handler
  ): void {
    $this->handlers[ $eventType->name ][] = $handler;
  }  

  private function scan(
    array $files = [],
  ): array {
    clearstatcache();

    foreach( $this->directories as $directory ){
      $recursiveIteratorIterators = (
        new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator( 
            $directory, FilesystemIterator::SKIP_DOTS
          )
        )
      );

      foreach ($recursiveIteratorIterators as $item) {
        if( $item->isFile() === false ){
          continue;
        }

        $path = $item->getPathname();

        // Ignora padrões excluídos (ex: Caches, vendor, etc)
        foreach( $this->excludePatterns as $pattern ){
          if( str_contains( $path, DIRECTORY_SEPARATOR . $pattern . DIRECTORY_SEPARATOR ) ){
            continue 2;
          }
        }

        $files[ $path ] = $item->getMTime();
      }
    }

    return $files;
  }

  private function dispatchEvent(
    DispatchType $dispatchType,
    string|null $file = null
  ): void {
    $eventHandlers = $this->handlers[
      $dispatchType->name
    ] ?? [];

    foreach( $eventHandlers as $handler ){
      if( is_object( $handler ) && method_exists( $handler, 'handle' ) ){
        $handler->handle( $dispatchType, $file );
      } elseif( is_callable( $handler ) ){
        $handler( $dispatchType, $file );
      }
    }
  }

  public function listen(
    int $interval = 1
  ): never {
    if( empty( $this->directories ) ){
      throw new \RuntimeException(
        "No directories registered to watch"
      );
    }

    // Dispara evento Started antes do loop
    $this->dispatchEvent(
      DispatchType::Started
    );

    $prev = $this->scan();

    while( true ){
      sleep( $interval );

      $curr = $this->scan();

      foreach( $curr as $file => $time ){
        if( !isset($prev[$file]) ){
          $this->dispatchEvent(
            DispatchType::Created, $file
          );
        } elseif( $prev[$file] !== $time ){
          $this->dispatchEvent(
            DispatchType::Modified, $file
          );
        }
      }

      foreach( $prev as $file => $mtime ){
        if( !isset( $curr[$file] ) ){
          $this->dispatchEvent(
            DispatchType::Deleted, $file
          );
        }
      }      

      $prev = $curr;
    }   
  }
}
