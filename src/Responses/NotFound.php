<?php

namespace Websyspro\DevTools\Responses;

class NotFound
{
  public static function html(
  ): string {
    return implode(
      PHP_EOL, [
        "<!DOCTYPE html>",
        "<html lang=\"en\">",
        "<head>",
          "<meta charset=\"UTF-8\">",
          "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">",
          "<title>Not Found</title>",
        "</head>",
        "<body>",
          "404 - Not found",
        "</body>",
        "</html>"
      ]
    );
  }
}