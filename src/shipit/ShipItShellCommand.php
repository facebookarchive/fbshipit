<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/lhuph3i3
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, Dict, Vec};

final class ShipItShellCommand {
  const type TFailureHandler = (function(ShipItShellCommandResult): void);
  private vec<string> $command;

  private dict<string, string> $environmentVariables = dict[];
  private bool $throwForNonZeroExit = true;
  private ?string $stdin = null;
  private bool $outputToScreen = false;
  private int $retries = 0;
  private ?self::TFailureHandler $failureHandler = null;

  public function __construct(private ?string $path, string ...$command) {
    $this->command = vec($command);
  }

  public function setStdIn(string $input): this {
    $this->stdin = $input;
    return $this;
  }

  public function setOutputToScreen(): this {
    $this->outputToScreen = true;
    return $this;
  }

  public function setEnvironmentVariables(dict<string, string> $vars): this {
    $this->environmentVariables = Dict\merge(
      $this->environmentVariables,
      $vars,
    );
    return $this;
  }

  public function setNoExceptions(): this {
    $this->throwForNonZeroExit = false;
    return $this;
  }

  public function setRetries(int $retries): this {
    invariant($retries >= 0, "Can't have a negative number of retries");
    $this->retries = $retries;
    return $this;
  }

  public function setFailureHandler<TIgnored>(
    (function(ShipItShellCommandResult): TIgnored) $handler,
  ): this {
    // Wrap so that the function returns void instead of TIgnored
    $this->failureHandler = (
      (ShipItShellCommandResult $result) ==> {
        $handler($result);
      }
    );
    return $this;
  }

  public function runSynchronously(): ShipItShellCommandResult {
    $max_tries = $this->retries + 1;
    $tries_remaining = $max_tries;
    invariant(
      $tries_remaining >= 1,
      "Need positive number of tries, got %d",
      $tries_remaining,
    );

    while ($tries_remaining > 1) {
      try {
        $result = $this->runOnceSynchronously();
        // Handle case when $this->throwForNonZeroExit === false
        if ($result->getExitCode() !== 0) {
          throw new ShipItShellCommandException(
            $this->getCommandAsString(),
            $result,
          );
        }
        return $result;
      } catch (ShipItShellCommandException $_ex) {
        --$tries_remaining;
        continue;
      }
    }
    return $this->runOnceSynchronously();
  }

  private function getCommandAsString(): string {
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    return Vec\map($this->command, $str ==> \escapeshellarg($str))
      |> Str\join($$, ' ');
  }

  private function runOnceSynchronously(): ShipItShellCommandResult {
    $fds = darray[
      0 => varray['pipe', 'r'],
      1 => varray['pipe', 'w'],
      2 => varray['pipe', 'w'],
    ];
    $stdin = $this->stdin;
    if ($stdin === null) {
      unset($fds[0]);
    }
    /* HH_FIXME[2050] undefined $_ENV */
    $env_vars = Dict\merge($_ENV, $this->environmentVariables);

    $command = $this->getCommandAsString();
    $pipes = varray[];
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    $fp = \proc_open($command, $fds, inout $pipes, $this->path, dict($env_vars));
    if (!$fp || !\is_array($pipes)) {
      throw new \Exception("Failed executing $command");
    }
    if ($stdin !== null) {
      while (Str\length($stdin)) {
        /* HH_IGNORE_ERROR[2049] __PHPStdLib */
        /* HH_IGNORE_ERROR[4107] __PHPStdLib */
        $written = \fwrite($pipes[0], $stdin);
        if ($written === 0) {
          /* HH_IGNORE_ERROR[2049] __PHPStdLib */
          /* HH_IGNORE_ERROR[4107] __PHPStdLib */
          $status = \proc_get_status($fp);
          if ($status['running']) {
            continue;
          }
          $exitcode = $status['exitcode'];
          invariant(
            $exitcode is int && $exitcode > 0,
            'Expected non-zero exit from process, got %s',
            \var_export($exitcode, true),
          );
          break;
        }
        $stdin = Str\slice($stdin, $written);
      }
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      \fclose($pipes[0]);
    }

    $stdout_stream = $pipes[1];
    $stderr_stream = $pipes[2];
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \stream_set_blocking($stdout_stream, false);
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \stream_set_blocking($stderr_stream, false);
    $stdout = '';
    $stderr = '';
    while (true) {
      $ready_streams = vec[$stdout_stream, $stderr_stream];
      $null_byref = null;
      do {
        /* HH_IGNORE_ERROR[2049] __PHPStdLib */
        /* HH_IGNORE_ERROR[4107] __PHPStdLib */
        $result = \stream_select(
          inout $ready_streams,
          /* write streams = */ inout $null_byref,
          /* exception streams = */ inout $null_byref,
          /* timeout = */ null,
        );
      } while (
        $result === false &&
        /* This **MUST NOT** be a PHP\ wrapper because `errno` is extremely volatile */
        /* HH_IGNORE_ERROR[2049] __PHPStdLib */
        /* HH_IGNORE_ERROR[4107] __PHPStdLib */
        \posix_get_last_error() === 4 // \HH\Lib\OS\__Private\Errno::EINTR
      );
      if ($result === false) {
        break;
      }
      $all_empty = true;
      foreach ($ready_streams as $stream) {
        /* HH_IGNORE_ERROR[2049] __PHPStdLib */
        /* HH_IGNORE_ERROR[4107] __PHPStdLib */
        $out = \fread($stream, 1024);
        if (Str\length($out) === 0) {
          continue;
        }
        $all_empty = false;

        if ($stream === $stdout_stream) {
          $stdout .= $out;
          $this->maybeOut($out);
          continue;
        }
        if ($stream === $stderr_stream) {
          $stderr .= $out;
          $this->maybeErr($out);
          continue;
        }

        invariant_violation('Unhandled stream!');
      }

      if ($all_empty) {
        break;
      }
    }
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    $exitcode = \proc_close($fp);

    $result = new ShipItShellCommandResult($exitcode, $stdout, $stderr);

    if ($exitcode !== 0) {
      $handler = $this->failureHandler;
      if ($handler) {
        $handler($result);
      }
      if ($this->throwForNonZeroExit) {
        throw new ShipItShellCommandException($command, $result);
      }
    }

    return $result;
  }

  private function maybeOut(string $out): void {
    if (!$this->outputToScreen) {
      return;
    }
    ShipItLogger::out('%s', $out);
  }

  private function maybeErr(string $out): void {
    if (!$this->outputToScreen) {
      return;
    }
    ShipItLogger::err('%s', $out);
  }
}
