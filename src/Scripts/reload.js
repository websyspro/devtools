(function() {
  const webSocket = new WebSocket(
    "ws://localhost:3002"
  );

  webSocket.addEventListener( "open", () => {
    console.log( "Connected" );
  });

  webSocket.addEventListener( "message", (event) => {
    console.log( evemt );

    if( event.data === "reload" ){
      console.log( "Reloading page" );
      window.location.reload();
    }
  });

  webSocket.addEventListener( "close", () => {
    console.log( "Disconnected. Reconnecting..." );
    setTimeout( connect, reconnectDelay );
    reconnectDelay = Math.min(reconnectDelay * 2, 10000);
  });

  webSocket.addEventListener( "error", (err) => {
    console.error( "[DevReload] WebSocket error", err );
    webSocket.close();
  });
})();