<?php

namespace Websyspro\DevTools;

use InvalidArgumentException;
use Websyspro\DevTools\Enums\DispatchType;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use RuntimeException;

use function is_object;

/**
 * WatchEvents - Sistema de observação de mudanças em arquivos
 * 
 * Monitora diretórios em busca de mudanças (criação, modificação, deleção)
 * e dispara eventos para handlers registrados. Implementa o padrão Observer
 * permitindo múltiplos handlers customizados para cada tipo de evento.
 * 
 * Esta classe é completamente agnóstica ao comportamento dos handlers,
 * funcionando apenas como orquestrador que detecta mudanças e notifica
 * os observadores interessados.
 * 
 * @package Websyspro\DevTools
 * 
 * @example Uso básico com hot reload
 * ```php
 * $watcher = new WatchEvents();
 * 
 * // Registra diretórios para monitorar
 * $watcher->registerDirectory(__DIR__ . "/src");
 * 
 * // Exclui padrões específicos
 * $watcher->excludePattern("vendor");
 * $watcher->excludePattern("Caches");
 * 
 * // Registra handler para eventos
 * $handler = new ServerRestartHandler(__DIR__ . "/index.php");
 * $watcher->on(DispatchType::Started,  $handler);
 * $watcher->on(DispatchType::Modified, $handler);
 * 
 * // Inicia o loop de monitoramento
 * $watcher->listen(interval: 1);
 * ```
 * 
 * @example Múltiplos handlers
 * ```php
 * $watcher = new WatchEvents();
 * $watcher->registerDirectory(__DIR__ . "/src");
 * 
 * // Múltiplos handlers para o mesmo evento
 * $watcher->on(DispatchType::Modified, new LogHandler());
 * $watcher->on(DispatchType::Modified, new TestRunnerHandler());
 * $watcher->on(DispatchType::Modified, new NotificationHandler());
 * 
 * $watcher->listen();
 * ```
 * 
 * @example Handler com closure
 * ```php
 * $watcher->on(DispatchType::Created, function($type, $file) {
 *   echo "Novo arquivo: " . basename($file) . "\n";
 * });
 * ```
 */
class WatchEvents
{
  /**
   * Handlers registrados por tipo de evento
   * 
   * @var array<string, array<object|callable>>
   */
  private array $handlers = [];

  /**
   * Diretórios sendo monitorados
   * 
   * @var array<string>
   */
  private array $directories = [];

  /**
   * Padrões de diretórios/arquivos a ignorar
   * 
   * @var array<string>
   */
  private array $excludePatterns = [];

  /**
   * Registra um diretório para monitoramento
   * 
   * Adiciona um diretório à lista de monitoramento. O diretório será
   * varrido recursivamente em busca de mudanças em arquivos.
   * 
   * @param string $directory Caminho absoluto ou relativo do diretório
   * 
   * @throws InvalidArgumentException Se o diretório não existir
   * 
   * @return void
   * 
   * @example
   * ```php
   * $watcher->registerDirectory(__DIR__ . "/src");
   * $watcher->registerDirectory(__DIR__ . "/config");
   * ```
   */
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

  /**
   * Adiciona um padrão de exclusão
   * 
   * Arquivos cujo caminho contenha o padrão especificado serão ignorados
   * durante o scan. Útil para excluir pastas como vendor, cache, logs, etc.
   * 
   * @param string $pattern Nome do diretório/arquivo a ignorar
   * 
   * @return void
   * 
   * @example
   * ```php
   * $watcher->excludePattern("vendor");
   * $watcher->excludePattern("Caches");
   * $watcher->excludePattern("node_modules");
   * ```
   */
  public function excludePattern(
    string $pattern
  ): void {
    $this->excludePatterns[] = $pattern;
  }

  /**
   * Registra um handler para um tipo de evento
   * 
   * Adiciona um handler (classe ou closure) que será executado quando
   * o evento especificado for disparado. Múltiplos handlers podem ser
   * registrados para o mesmo evento.
   * 
   * Handlers podem ser:
   * - Objeto com método handle(DispatchType, ?string): void
   * - Objeto com método __invoke(DispatchType, ?string): void
   * - Closure/callable com assinatura (DispatchType, ?string): void
   * 
   * @param DispatchType $eventType Tipo de evento (Started, Created, Modified, Deleted)
   * @param object|callable $handler Handler a ser executado
   * 
   * @return void
   * 
   * @example Com classe
   * ```php
   * $watcher->on(DispatchType::Modified, new MyHandler());
   * ```
   * 
   * @example Com closure
   * ```php
   * $watcher->on(DispatchType::Created, function($type, $file) {
   *   echo "Criado: $file\n";
   * });
   * ```
   */
  public function on(
    DispatchType $eventType,
    object|callable $handler
  ): void {
    $this->handlers[ $eventType->name ][] = $handler;
  }  

  /**
   * Varre recursivamente os diretórios registrados
   * 
   * Realiza varredura recursiva dos diretórios monitorados e retorna
   * um array associativo com o caminho completo de cada arquivo e seu
   * timestamp de modificação (mtime).
   * 
   * @param array $files Array acumulador (uso interno)
   * 
   * @return array<string, int> Mapa [filepath => mtime]
   */
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

  /**
   * Dispara um evento para os handlers registrados
   * 
   * Executa todos os handlers registrados para o tipo de evento especificado.
   * Suporta handlers com método handle(), __invoke() ou closures.
   * 
   * @param DispatchType $dispatchType Tipo do evento
   * @param string|null $file Caminho do arquivo (null para Started)
   * 
   * @return void
   */
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

  /**
   * Inicia o loop de monitoramento
   * 
   * Dispara o evento Started e entra em loop infinito monitorando mudanças
   * nos diretórios registrados. A cada intervalo especificado, compara o
   * estado atual dos arquivos com o estado anterior e dispara eventos
   * apropriados (Created, Modified, Deleted).
   * 
   * @param int $interval Intervalo em segundos entre cada varredura
   * 
   * @throws RuntimeException Se nenhum diretório foi registrado
   * 
   * @return never Este método nunca retorna (loop infinito)
   * 
   * @example
   * ```php
   * $watcher = new WatchEvents();
   * $watcher->registerDirectory(__DIR__ . "/src");
   * $watcher->on(DispatchType::Modified, new MyHandler());
   * 
   * // Monitora com intervalo de 2 segundos
   * $watcher->listen(interval: 2);
   * ```
   */
  public function listen(
    int $interval = 1
  ): never {
    if( empty( $this->directories ) ){
      throw new RuntimeException(
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
