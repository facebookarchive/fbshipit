<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

class ShipItShellCommand {
  const type TFailureHandler = (function(ShipItShellCommandResult): void);
  private ImmVector<string> $command;

  private Map<string, string> $environmentVariables = Map {};
  private bool $throwForNonZeroExit = true;
  private ?string $stdin = null;
  private bool $outputToScreen = false;
  private int $retries = 0;
  private ?self::TFailureHandler $failureHandler = null;

  public function __construct(
    private ?string $path,
    /* HH_FIXME[4033] type hint */ ...$command
  ) {
    $this->command = new ImmVector($command);
  }

  public function setStdIn(string $input): this {
    $this->stdin = $input;
    return $this;
  }

  public function setOutputToScreen(): this {
    $this->outputToScreen = true;
    return $this;
  }

  public function setEnvironmentVariables(
    ImmMap<string, string> $vars,
  ): this {
    $this->environmentVariables->setAll($vars);
    return $this;
  }

  public function setNoExceptions(): this {
    $this->throwForNonZeroExit = false;
    return $this;
  }

  public function setRetries(int $retries): this {
    invariant(
      $retries >= 0,
      "Can't have a negative number of retries"
    );
    $this->retries = $retries;
    return $this;
  }

  public function setFailureHandler<TIgnored>(
    (function(ShipItShellCommandResult):TIgnored) $handler,
  ): this {
    // Wrap so that the function returns void instead of TIgnored
    $this->failureHandler = ($result ==> { $handler($result); });
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
      } catch (ShipItShellCommandException $ex) {
        --$tries_remaining;
        continue;
      }
      invariant_violation('Unreachable');
    }
    return $this->runOnceSynchronously();
  }

  private function getCommandAsString(): string {
    return \implode(' ', $this->command->map($str ==> \escapeshellarg($str)));
  }

  private function runOnceSynchronously(): ShipItShellCommandResult {
    $fds = array(
      0 => array('pipe', 'r'),
      1 => array('pipe', 'w'),
      2 => array('pipe', 'w'),
    );
    $stdin = $this->stdin;
    if ($stdin === null) {
      unset($fds[0]);
    }
    /* HH_FIXME[2050] undefined $_ENV */
    $env_vars = (new Map($_ENV))->setAll($this->environmentVariables);

    $command = $this->getCommandAsString();
    $pipes = null;
    $fp = \proc_open(
      $command,
      $fds,
      &$pipes,
      $this->path,
      $env_vars->toArray(),
    );
    if (!$fp || !\is_array($pipes)) {
      throw new \Exception("Failed executing $command");
    }
    if ($stdin !== null) {
      while (\strlen($stdin)) {
        $written = \fwrite($pipes[0], $stdin);
        if ($written === 0) {
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
        $stdin = \substr($stdin, $written);
      }
      \fclose($pipes[0]);
    }

    $stdout_stream = $pipes[1];
    $stderr_stream = $pipes[2];
    \stream_set_blocking($stdout_stream, false);
    \stream_set_blocking($stderr_stream, false);
    $stdout = '';
    $stderr = '';
    while (true) {
      $ready_streams = [$stdout_stream, $stderr_stream];
      $null_byref = null;
      $result = \stream_select(
        &$ready_streams,
        /* write streams = */ &$null_byref,
        /* exception streams = */ &$null_byref,
        /* timeout = */ null,
      );
      if ($result === false) {
        break;
      }
      $all_empty = true;
      foreach ($ready_streams as $stream) {
        $out = \fread($stream, 1024);
        if (\strlen($out) === 0) {
          continue;
        }
        $all_empty = false;

        if ($stream === $stdout_stream) {
          $stdout .= $out;
          $this->maybeFwrite(\STDOUT, $out);
          continue;
        }
        if ($stream === $stderr_stream) {
          $stderr .= $out;
          $this->maybeFwrite(\STDERR, $out);
          continue;
        }

        invariant_violation('Unhandled stream!');
      }

      if ($all_empty) {
        break;
      }
    }
    $exitcode = \proc_close($fp);

    $result = new ShipItShellCommandResult(
      $exitcode,
      $stdout,
      $stderr,
    );

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

  private function maybeFwrite(resource $stream, string $out): void {
    if (!$this->outputToScreen) {
      return;
    }
    \fwrite($stream, $out);
  }
}
