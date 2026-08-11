<?php

namespace Websyspro\DevTools\Handlers;

use Websyspro\DevTools\Enums\DispatchType;
use Websyspro\DevTools\Interfaces\EventHandler;
use Websyspro\DevTools\WatchEvents;

use Websyspro\Logger\Styled;
use Websyspro\Logger\Terminal;
use function sprintf;
use function is_resource;

/**
 * ServerRestartHandler - Handler para hot reload de servidores PHP
 * 
 * Implementa reinício automático do servidor PHP quando arquivos são modificados.
 * Gerencia o ciclo de vida do processo do servidor, incluindo inicialização,
 * parada e reinício, com suporte multiplataforma (Windows/Unix).
 * 
 * @package Websyspro\DevTools\Handlers
 * 
 * @example Uso básico
 * ```php
 * $handler = new ServerRestartHandler();
 * $watcher = new WatchEvents();
 * $watcher->registerHandler($handler);
 * $watcher->listen();
 * ```
 */
class ServerRestartHandler 
implements EventHandler
{
  /**
   * Instância do WatchEvents para acessar configurações
   * 
   * @var WatchEvents
   */
  private WatchEvents $watchEvents;

  /**
   * Recurso do processo do servidor em execução
   * 
   * @var resource|null
   */
  private mixed $serverProcess = null;

  /**
   * Caminho do script do servidor a executar
   * 
   * @var DispatchType
   */
  private DispatchType $dispatchType;

  /**
   * Caminho do script do servidor a executar
   * 
   * @var string|null
   */
  private string|null $file;  

  /**
   * Construtor do handler
   * 
   * Inicializa o handler de reinício do servidor.
   * 
   * @return void
   */
  public function __construct(
  ){}

  /**
   * Registra a instância do WatchEvents
   * 
   * Chamado automaticamente pelo WatchEvents quando o handler é registrado
   * via registerHandler(). Armazena a referência para acesso às configurações.
   * 
   * @param WatchEvents $watchEvents Instância do observador de eventos
   * 
   * @return void
   */
  public function watch(
    WatchEvents $watchEvents
  ): void {
    $this->watchEvents = $watchEvents;
  }

  /**
   * Manipula eventos de mudança de arquivos
   * 
   * Método principal chamado pelo WatchEvents quando um evento ocorre.
   * Registra o evento no log e reinicia o servidor automaticamente.
   * 
   * @param DispatchType $dispatchType Tipo do evento (Started, Created, Modified, Deleted)
   * @param string|null $file Caminho do arquivo modificado (null para Started)
   * 
   * @return void
   */
  public function handle(
    DispatchType $dispatchType,
    string|null $file = null
  ): void {
    $this->dispatchType = $dispatchType;
    $this->file = $file;

    $this->restartServer();
  }

  /**
   * Inicia o processo do servidor PHP
   * 
   * Cria um novo processo para executar o script do servidor definido
   * no watch.json. Limpa o terminal e exibe cabeçalho informativo antes
   * de iniciar. Redireciona stdout e stderr para o terminal atual.
   * 
   * @return void
   */
  private function startServer(
  ): void {
    $this->clearTerminal();
    $this->printHeader();

    $cmd = sprintf( "%s %s", 
      PHP_BINARY, $this->watchEvents->watchJSON->script
    );

    $descriptors = [
      0 => [ "pipe", "r" ],
      1 => STDOUT,
      2 => STDERR,
    ];

    $process = proc_open(
      $cmd, 
      $descriptors,
      $pipes
    );

    if( !is_resource( $process ) ){
      echo "[ERRO] Falha ao iniciar servidor\n";
      return;
    }

    fclose( $pipes[0] );
    $this->serverProcess = $process;
  }

  /**
   * Reinicia o servidor PHP
   * 
   * Para o servidor atual (se estiver rodando) e inicia um novo processo.
   * Método chamado automaticamente quando mudanças são detectadas.
   * 
   * @return void
   */
  private function restartServer(
  ): void {
    $this->stopServer();
    $this->startServer();
  }  

  /**
   * Para o processo do servidor em execução
   * 
   * Envia sinal de término ao processo do servidor de forma adequada
   * ao sistema operacional (taskkill no Windows, kill no Unix).
   * Aguarda 1 segundo após o término para garantir liberação de recursos.
   * 
   * @return void
   */
  private function stopServer(
  ): void {
    if( !is_resource( $this->serverProcess ) ){
      return;
    }

    $status = proc_get_status(
      $this->serverProcess
    );

    if( $status[ "running" ] ){
      $pid = $status[ "pid" ];

      if( PHP_OS_FAMILY === "Windows" ){
        exec( "taskkill /F /T /PID {$pid} 2>NUL" );
      } else {
        exec( "kill -TERM {$pid} 2>/dev/null" );
      }
    }

    proc_close( $this->serverProcess );
    echo "[INFO] Servidor encerrado\n";

    sleep(1);
  }

  /**
   * Limpa o terminal
   * 
   * Usa sequências ANSI para limpar a tela e posicionar o cursor
   * no início. Compatível com terminais modernos.
   * 
   * @return void
   */
  private function clearTerminal(
  ): void {
    echo "\033[2J\033[H";
  }

  /**
   * Exibe cabeçalho informativo no terminal
   * 
   * Mostra o nome do sistema, script em execução e timestamp
   * de início do monitoramento com cores ANSI.
   * 
   * @return void
   */
  private function printHeader(
  ): void {
    Terminal::init()
      ->clear()
      ->text( "[Watch] Server Restart Handler", new Styled(
        color: [255,200,15], bgColor: [], bold: true
      ))
      ->eof()
      ->text(
        $this->dispatchType->name, match( $this->dispatchType ){
          DispatchType::Started => new Styled(
            [0, 0, 0], [255, 255, 255], true
          ),
          DispatchType::Created => new Styled(
            [255,255,255], [0,200,0], true
          ),
          DispatchType::Modified => new Styled(
            [255,255,255], [0,100,180], true
          ),
          DispatchType::Deleted => new Styled(
            [255,255,255], [255,0,0], true
          )
      })
      ->eof();

    /*
    $time = date( 'Y-m-d H:i:s' );
    echo "\033[35mPHP Watch — Hot Reload\033[0m\n";
    echo "\033[32mExecutando {$this->watchEvents->watchJSON->script}\033[0m\n";
    echo "\033[32mMonitorando mudanças — {$time}\033[0m\n\n"; 
    */
  }
}
