<?php

/**
 * Run - Process execution utility
 * 
 * This class provides utilities for executing system commands as background processes.
 * Handles cross-platform differences between Windows and Unix-like systems.
 * 
 * @package Websyspro\DevTools\Shareds
 */

namespace Websyspro\DevTools\Shareds;

use RuntimeException;

class Run
{
  /**
   * Process resource handle
   * 
   * @var resource|null
   */
  public mixed $process;

  /**
   * Get platform-specific null device path
   * 
   * Returns the appropriate null device for silencing output:
   * - Windows: "NUL"
   * - Unix/Linux/macOS: "php://null"
   * 
   * @return string Null device path for current OS
   */
  public function osSystemOrNull(
  ): string {
    return strtoupper(substr(PHP_OS, 0, 3)) === "WIN" 
      ? "NUL" 
      : "php://null";
  }

  /**
   * Execute command as background process
   * 
   * Opens a process with configurable output handling.
   * Can silence output by redirecting to null device.
   * 
   * @param string $message Command to execute
   * @param bool $silence If true, redirect stdout/stderr to null device (default: true)
   * @throws RuntimeException If process cannot be started
   * @return void
   */
  public function command(
    string $message, 
    bool $silence = true
  ): void {
    /* Configure process descriptors for stdin, stdout, stderr */
    $descriptors = [
      0 => ["pipe", "r"],  // stdin
      1 => $silence ? ["file", $this->osSystemOrNull(), "w"] : ["pipe", "w"],  // stdout
      2 => $silence ? ["file", $this->osSystemOrNull(), "w"] : ["pipe", "w"],  // stderr
    ];

    /* Open process with specified command and descriptors */
    $this->process = proc_open($message, $descriptors, $pipes);

    /* Validate process was created successfully */
    if (is_resource($this->process) === false) {
      throw new RuntimeException( "Could not start process: {$message}" );
    }

    /* Close pipes if not silenced */
    if (!$silence && isset($pipes)) {
      foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
          fclose($pipe);
        }
      }
    }
  }
}
