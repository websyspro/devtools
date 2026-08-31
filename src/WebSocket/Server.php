<?php

use Websyspro\DevTools\Interfaces\WatchJSON;

class Server
{
  private array $clients = [];
  private Socket $handler;
  private WatchJSON $watchJSON;

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
      }
    }
  }  

  public function listen(
  ): void {
    $this->handler = socket_create(
      AF_INET, SOCK_STREAM, SOL_TCP
    );

    if( !$this->handler ){
      throw new RuntimeException(
        socket_strerror( socket_last_error())
      );
    }

    socket_set_option(
      $this->handler, 
      SOL_SOCKET, SO_REUSEADDR, 1
    );

    $handlerBind = socket_bind(
      $this->handler, 
      $this->watchJSON->webSocketHost, 
      $this->watchJSON->webSocketPort
    );

    if( $handlerBind === false ){
      throw new RuntimeException(
        socket_strerror( socket_last_error( $this->handler ))
      );
    }

    if( socket_listen( $this->handler ) === false ){
      throw new RuntimeException(
        socket_strerror( socket_last_error( $this->handler ))
      );
    }

    socket_set_nonblock( $this->handler );

    $this->started();
  }

  private function started(
  ): void {
    if( $this->handler ){
      while( true ){
        $handlerRead = [ $this->handler ];

        foreach( $this->clients as $client ){
          $handlerRead[] = $client;
        }

        $handlerWrite = null;
        $handlerExcept = null;
        
        $handlerSelect = socket_select(
          $handlerRead, $handlerWrite, $handlerExcept, 0, 100000
        );

        if( $handlerSelect === false ){
          continue;
        }

        if( in_array( $this->handler, $handlerRead, true )){
          $handlerClient = socket_accept( $this->handler );

          if( $handlerClient !== false ){
            socket_set_nonblock(
              $handlerClient
            );

            $this->clients[] = $handlerClient;
          }

          $handlerReadKey = array_search(
            $this->handler, $handlerRead, true
          );

          unset( $handlerRead[ $handlerReadKey ]);
        }

        foreach( $handlerRead as $handlerClient ){
          $handlerData = @socket_read(
            $handlerClient, 8192, PHP_BINARY_READ
          );
          
          if( $handlerData === false || $handlerData === "" ){
            $this->disconnect( $handlerClient );
            continue;
          }
          
          if( str_contains( $handlerData, "Sec-WebSocket-Key:" )){
            $this->handshake( $handlerClient, $handlerData );
            continue;
          }


          $handlerMessage = $this->decode( $handlerData );
          if( $handlerMessage === null ){
            continue;
          }


          $this->handleMessage(
            $handlerMessage
          );          
        }
      }
    }
  }

  private function disconnect(
    Socket $handlerClient
  ): void {
    $handlerClientKey = array_search(
      $handlerClient, $this->clients, true
    );

    if( $handlerClientKey !== false ){
      unset($this->clients[ $handlerClientKey ]);
    }

    @socket_close(
      $handlerClient
    );
  }

  private function handshake(
    Socket $handlerClient,
    string $handlerData
  ): void {
    preg_match(
      "#Sec-WebSocket-Key:\s*(.+)\r\n#i",
      $handlerData, $matches
    );

    [ , $handlerClientKey ] = $matches;

    if( isset( $handlerClientKey ) === false ){
      $this->disconnect( $handlerClient );
      return;
    }

    $handlerAccept = base64_encode(
      sha1( "{$handlerClientKey}258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true )
    );

    $handlerResponse = implode(
      "\r\n", [
        "HTTP/1.1 101 Switching Protocols",
        "Upgrade: websocket",
        "Connection: Upgrade",
        "Sec-WebSocket-Accept: {$handlerAccept}",
        "\r\n"
      ]
    );

    socket_write(
      $handlerClient, 
      $handlerResponse, strlen(
        $handlerResponse
      ) 
    );
  }

  private function decode(
    string $handlerData,
    string $handlerDataResult = ""
  ): string|null {
    if( strlen( $handlerData ) < 2 ){
      return null;
    }

    $handlerDataLength = ord( $handlerData[ 1 ]) & 127;
    $handlerDataOffSet = 2;

    if( $handlerDataLength === 126 ){
      if( strlen( $handlerData ) < 4 ){
        return null;
      }

      $handlerDataLength = unpack(
        "n", substr( $handlerData, 2, 2 )
      )[1];

      $handlerDataOffSet = 4;
    } else
    if( $handlerDataLength === 127 ){
      if( strlen( $handlerData ) < 10 ){
        return null;
      }

      $handlerDataParts = unpack(
        "J", substr( $handlerData, 2, 8 )
      );

      $handlerDataLength = $handlerDataParts[1];
      $handlerDataOffSet = 10;
    }

    $handlerDataIsMasked = ( 
      ord( $handlerData[1]) & 128
    ) !== 0;

    if( $handlerDataIsMasked === false ){
      return substr(
        $handlerData,
        $handlerDataOffSet,
        $handlerDataLength
      );
    }

    if( strlen( $handlerData ) < $handlerDataOffSet + 4 ){
      return null;
    }

    $handlerDataMask = substr(
      $handlerData, $handlerDataOffSet, 4
    );

    $handlerDataOffSet += 4;
    $handlerDataPayload = substr(
      $handlerData,
      $handlerDataOffSet,
      $handlerDataLength
    );

    for ($i = 0; $i < $handlerDataLength; $i++) {
      $handlerDataResult .= (
        $handlerDataPayload[ $i ] ^ 
        $handlerDataMask[ $i % 4 ]
      );
    }

    return $handlerDataResult;
  }

  private function handleMessage(
    string $handlerMessage
  ): void {
    if( $handlerMessage === "notification-client" ){
      $this->broadcast( "reload" );
    } else {
      $jsonDecode = json_decode(
        $handlerMessage, true
      );

      if( is_array( $jsonDecode ) && ( $jsonDecode["type"] ?? null ) === "notification-client" ){
        $this->broadcast( "reload" );
      }
    }
  }

  private function broadcast(
    string $handlerMessage
  ): void {
    $handlerFrame = $this->encode(
      $handlerMessage
    );

    foreach( $this->clients as $client ){
      @socket_write(
        $client, $handlerFrame, strlen( $handlerFrame )
      );
    }
  }

  private function encode(
    string $handlerMessage
  ): string {
    $handlerMessageLength = strlen(
      $handlerMessage
    );

    if( $handlerMessageLength <= 125 ){
      return sprintf( 
        "%s%s%s", chr( 0x81 ), chr(
          $handlerMessageLength
        ), $handlerMessage
      );
    }

    if( $handlerMessageLength <= 65535 ){
      return sprintf(
        "%s%s%s%s", chr( 0x81 ), chr(126), pack(
          "n", $handlerMessageLength
        ), $handlerMessage 
      );
    }

    return sprintf(
      "%s%s%s%s", chr( 0x81 ), chr(127), pack(
        "J", $handlerMessageLength
      ), $handlerMessage 
    );
  }  
}