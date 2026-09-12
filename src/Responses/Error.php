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
        "<html>",
        "<head>",
          "<meta charset=\"UTF-8\">",
          "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">",
          "<title>Error</title>",
        "</head>",
        "<body style=\"background-color:rgb(235,235,235); padding: 24px;\">",
          "<strong>Error:</strong> {$throwable->getMessage()}<br/>",
          "<strong>File:</strong> {$throwable->getFile()}<br/>",
          "<strong>Line:</strong> {$throwable->getLine()}",
        "</body>",
        "</html>"
      ]
    );
  }
}