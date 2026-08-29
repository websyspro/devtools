<?php

namespace Websyspro\DevTools;

use Websyspro\DevTools\Interfaces\EventHandler;
use Websyspro\DevTools\Interfaces\WatchJSON;
use Websyspro\DevTools\Enums\DispatchType;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use InvalidArgumentException;
use FilesystemIterator;
use RuntimeException;
use Websyspro\Logger\Terminal;
use function is_object;
use function defined;
use function sprintf;

class WatchEvents
{
  private array $handlers = [];
  private array $directories = [];
  private array $excludePatterns = [];
  private array $filesPrevious = [];
  private array $filesCurrents = [];
  public WatchJSON $watchJSON;

  public function __construct(
  ){
    $this->configDefault();
  }
  
  private function configDefault(
  ): void {
    if( defined( "DIR_BASE" )){
      $watchFile = sprintf(
        "%swatch.json", DIR_BASE
      );

      if( file_exists( $watchFile )){
        $this->watchJSON = new WatchJSON(
          ...(array)json_decode(
            file_get_contents( $watchFile )
          )
        );

        foreach( $this->watchJSON->includes as $include ){
          $this->registerDirectory( $include );
        }

        foreach( $this->watchJSON->excludes as $exclude ){
          $this->excludePattern( $exclude );
        }
      }
    }
  }

  public function registerDirectory(
    string $directory
  ): void {
    if( !is_dir( $directory ) ){
      throw new InvalidArgumentException(
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

  public function registerHandler(
    EventHandler $handler
  ): void {
    if( method_exists( $handler, "watch" )){
      $handler->watch( $this );
    }

    $this->handlers[ DispatchType::Started->name  ][] = $handler;
    $this->handlers[ DispatchType::Created->name  ][] = $handler;
    $this->handlers[ DispatchType::Modified->name ][] = $handler;
    $this->handlers[ DispatchType::Deleted->name  ][] = $handler;
  }   

  public function on(
    DispatchType $eventType,
    object|callable $handler
  ): void {
    $this->handlers[ $eventType->name ][] = $handler;
  }  

  private function scanFiles(
    array $files = [],
  ): array {
    foreach( $this->directories as $directory ){
      $recursiveIteratorIterators = (
        new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator( 
            $directory, FilesystemIterator::SKIP_DOTS
          )
        )
      );

      foreach ($recursiveIteratorIterators as $iterator) {
        if( $iterator->isFile() === false ){
          continue;
        }

        $shouldExclude = false;
        foreach( $this->excludePatterns as $pattern ){
          if( str_contains( $iterator->getPathname(), DIRECTORY_SEPARATOR . $pattern . DIRECTORY_SEPARATOR ) ){
            $shouldExclude = true;
            break;
          }
        }

        if( $shouldExclude ){
          continue;
        }

        $files[ $iterator->getPathname() ] = $iterator->getMTime();
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
      } elseif( is_callable( $handler )){
        $handler( $dispatchType, $file );
      }
    }
  }

  private function scanFilesPrevius(
  ): void {
    $this->filesPrevious = $this->scanFiles();
  }

  private function scanfilesCurrents(
  ): void {
    $this->filesCurrents = $this->scanFiles();
  }
  
  private function scanPreviusFromCurrents(
  ): void {
    $this->filesPrevious = $this->filesCurrents;
  }

  public function listen(
  ): never {
    if( empty( $this->directories ) ){
      throw new RuntimeException(
        "No directories registered to watch"
      );
    }

    if( empty( $this->watchJSON->script )){
      throw new RuntimeException(
        "No scripts registered to watch"
      );
    }

    Terminal::init()
      ->text( "Checou aqui!!!!" );

    $this->scanFilesPrevius();
    $this->dispatchEvent(
      DispatchType::Started
    );

    while( true ){
      sleep( 1 );

      $this->scanfilesCurrents();

      foreach( $this->filesCurrents as $file => $time ){
        if( isset($this->filesPrevious[$file]) === false ){
          $this->dispatchEvent( DispatchType::Created, $file );
        } elseif( $this->filesPrevious[$file] !== $time ){
          $this->dispatchEvent( DispatchType::Modified, $file );
        }
      }

      foreach( $this->filesPrevious as $file => $mtime ){
        if( isset( $this->filesCurrents[$file]) === false ){
          $this->dispatchEvent( DispatchType::Deleted, $file );
        }
      }      

      $this->scanPreviusFromCurrents();
    }   
  }
}
