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

use namespace HH\Lib\{Dict, Str, Vec}; // @oss-enable

final class ShipItShellCommand {
  const type TFailureHandler = (function(ShipItShellCommandResult): void);
  private vec<string> $command;

  private dict<string, string> $environmentVariables = dict[];
  private bool $throwForNonZeroExit = true;
  private ?string $stdin = null;
  private bool $outputToScreen = false;
  private int $retries = 0;
  private ?self::TFailureHandler $failureHandler = null;
  private bool $showShellExecs = false;

  public function __construct(private ?string $path, string ...$command) {
    $this->command = vec($command);
    if (ShipItEnv::getEnv("SHIPIT_OUTPUT_EXECS") !== null) {
      $this->showShellExecs = true;
    }
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
    return Vec\map($this->command, $str ==> PHP\escapeshellarg($str))
      |> Str\join($$, ' ');
  }

  private function runOnceSynchronously(): ShipItShellCommandResult {
    $fds = dict[
      0 => vec['pipe', 'r'],
      1 => vec['pipe', 'w'],
      2 => vec['pipe', 'w'],
    ];
    $stdin = $this->stdin;
    if ($stdin === null) {
      unset($fds[0]);
    }

    $env_vars = Dict\merge(ShipItEnv::getAll(), $this->environmentVariables);

    $command = $this->getCommandAsString();
    if ($this->showShellExecs) {
      (new ShipItVerboseLogger(true))->out(
        "Shell command: %s; cwd: %s",
        $command,
        $this->path ?? ".",
      );
    }
    $pipes = vec[];
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    $fp = \proc_open($command, $fds, inout $pipes, $this->path, dict($env_vars));
    if (!$fp || !\HH\is_any_array($pipes)) {
      throw new \Exception("Failed executing $command");
    }
    if ($stdin !== null) {
      while (Str\length($stdin)) {
        $written = PHP\fwrite($pipes[0], $stdin);
        if ($written === 0) {
          $status = PHP\proc_get_status($fp);
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
      PHP\fclose($pipes[0]);
    }

    $stdout_stream = $pipes[1];
    $stderr_stream = $pipes[2];
    PHP\stream_set_blocking($stdout_stream, false);
    PHP\stream_set_blocking($stderr_stream, false);
    $stdout = '';
    $stderr = '';
    while (true) {
      $ready_streams = vec[$stdout_stream, $stderr_stream];
      $null_byref = null;
      do {
        $result = PHP\stream_select(
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
      foreach (($ready_streams as Container<_>) as $stream) {
        $out = PHP\fread($stream as resource, 1024) as string;
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
    $exitcode = PHP\proc_close($fp);

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
