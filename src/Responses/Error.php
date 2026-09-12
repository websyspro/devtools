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
        "<body style=\"font-family:Verdana; background-color:rgb(225,225,225); padding: 24px;\">",
          "<div style=\"background-color:rgb(255,255,255); padding: 24px; border-radius: 8px; border-left:6px solid rgb(200,0,0)\">",
            "<h2 style=\"border-bottom: 1px solid rgb(235,235,235); padding-bottom: 24px;\">PHP Error</h2>",
            "<div style=\"font-size:.9rem;\">",
              "<strong>Message:</strong> {$throwable->getMessage()}<br/>",
              "<strong>File:</strong> {$throwable->getFile()}<br/>",
              "<strong>Line:</strong> {$throwable->getLine()}",
            "</div>",
          "</div>",
        "</body>",
        "</html>"
      ]
    );
  }
}