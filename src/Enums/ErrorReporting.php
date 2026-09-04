<?php

namespace Websyspro\DevTools\Enums;

enum ErrorReporting
{
  case E_ALL;
  case E_ERROR;
  case E_RECOVERABLE_ERROR;
  case E_WARNING;
  case E_PARSE;
  case E_NOTICE;
  case E_STRICT;
  case E_CORE_ERROR;
  case E_CORE_WARNING;
  case E_COMPILE_ERROR;
  case E_COMPILE_WARNING;
  case E_USER_ERROR;
  case E_USER_WARNING;
  case E_USER_NOTICE;
  case E_DEPRECATED;
  case E_USER_DEPRECATED;
}