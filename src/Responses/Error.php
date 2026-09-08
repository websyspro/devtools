<?php

namespace Websyspro\DevTools\Responses;

use Throwable;

class Error
{
  public static function html(
    Throwable $throwable
  ): string {
    return implode(
      PHP_EOL, [
        "<!DOCTYPE html>",
        "<html lang=\"en\">",
        "<head>",
          "<meta charset=\"UTF-8\">",
          "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">",
          "<title>Error</title>",
        "</head>",
        "<body>",
          "{$throwable->getMessage()}",
        "</body>",
        "</html>"
      ]
    );
  }
}