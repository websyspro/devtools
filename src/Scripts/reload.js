(function() {
  let reconnectDelay = 1000;
  const wsUrls = [
    "ws://localhost:3002",
    "ws://127.0.0.1:3002",
    "ws://0.0.0.0:3002"
  ];
  let currentUrlIndex = 0;

  function connect() {
    const wsUrl = wsUrls[currentUrlIndex];
    console.log( "[DevReload] Attempting to connect to " + wsUrl );
    
    const webSocket = new WebSocket( wsUrl );

    webSocket.addEventListener( "open", () => {
      console.log( "[DevReload] ✓ Connected to WebSocket at " + wsUrl );
      reconnectDelay = 1000; // Reset delay on successful connection
      currentUrlIndex = 0; // Reset to first URL on success
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

    webSocket.addEventListener( "close", (event) => {
      console.log( "[DevReload] Disconnected (code: " + event.code + "). Reconnecting in " + (reconnectDelay/1000) + "s..." );
      setTimeout( connect, reconnectDelay );
      reconnectDelay = Math.min(reconnectDelay * 2, 10000);
    });

    webSocket.addEventListener( "error", (err) => {
      console.error( "[DevReload] ✗ WebSocket error at " + wsUrl, err );
      console.log( "[DevReload] Trying next URL..." );
      
      // Tenta próximo URL na lista
      currentUrlIndex = (currentUrlIndex + 1) % wsUrls.length;
      
      webSocket.close();
    });
  }

  connect();
})();