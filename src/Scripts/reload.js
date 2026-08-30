(function() {
  let reconnectDelay = 1000;

  function connect() {
    const webSocket = new WebSocket(
      "ws://localhost:3002"
    );

    webSocket.addEventListener( "open", () => {
      console.log( "[DevReload] Connected to WebSocket" );
      reconnectDelay = 1000; // Reset delay on successful connection
    });

    webSocket.addEventListener( "message", (event) => {
      console.log( "[DevReload] Message received:", event.data );

      try {
        const data = JSON.parse( event.data );
        if( data.type === "reload" ){
          console.log( "[DevReload] Reloading page..." );
          window.location.reload();
        }
      } catch(e) {
        // Se não for JSON, trata como string simples
        if( event.data === "reload" ){
          console.log( "[DevReload] Reloading page..." );
          window.location.reload();
        }
      }
    });

    webSocket.addEventListener( "close", () => {
      console.log( "[DevReload] Disconnected. Reconnecting in " + (reconnectDelay/1000) + "s..." );
      setTimeout( connect, reconnectDelay );
      reconnectDelay = Math.min(reconnectDelay * 2, 10000);
    });

    webSocket.addEventListener( "error", (err) => {
      console.error( "[DevReload] WebSocket error", err );
      webSocket.close();
    });
  }

  connect();
})();