<?php

namespace Websyspro\DevTools\Interfaces;

use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\WatchEvents;

/**
 * Interface EventHandler
 * 
 * Define o contrato para handlers que respondem a eventos de mudança de arquivos
 * detectados pelo WatchEvents.
 * 
 * Esta interface permite criar handlers customizados que executam ações específicas
 * quando arquivos são criados, modificados, deletados ou quando o watcher é iniciado.
 * 
 * @package Websyspro\DevTools\Interfaces
 * 
 * @example
 * ```php
 * class MyCustomHandler implements EventHandler
 * {
 *   public function handle(DispatchType $dispatchType, string|null $file = null): void
 *   {
 *     if ($dispatchType === DispatchType::Started) {
 *       echo "Watcher iniciado!\n";
 *       return;
 *     }
 * 
 *     echo "Evento: {$dispatchType->name} - Arquivo: " . basename($file) . "\n";
 *   }
 * }
 * 
 * $watcher = new WatchEvents();
 * $watcher->on(DispatchType::Modified, new MyCustomHandler());
 * ```
 */
interface EventHandler
{
  public function watch(
    WatchEvents $watchEvents
  ): void;

  /**
   * Processa um evento de mudança de arquivo
   * 
   * Este método é chamado automaticamente pelo WatchEvents quando um evento
   * é disparado. A implementação define o comportamento específico para cada
   * tipo de evento.
   * 
   * @param DispatchType $dispatchType Tipo do evento (Started, Created, Modified, Deleted)
   * @param string|null $file Caminho completo do arquivo afetado. Null para evento Started
   * 
   * @return void
   * 
   * @example
   * ```php
   * public function handle(DispatchType $dispatchType, string|null $file = null): void
   * {
   *   match($dispatchType) {
   *     DispatchType::Started  => $this->initialize(),
   *     DispatchType::Created  => $this->onCreated($file),
   *     DispatchType::Modified => $this->onModified($file),
   *     DispatchType::Deleted  => $this->onDeleted($file),
   *   };
   * }
   * ```
   */
  public function handle(
    DispatchType $dispatchType,
    string|null $file = null
  ): void;
}
