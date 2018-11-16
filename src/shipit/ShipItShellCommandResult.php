<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItShellCommandResult {
  public function __construct(
    private int $exitCode,
    private string $stdout,
    private string $stderr,
  ) {
  }

  public function getExitCode(): int {
    return $this->exitCode;
  }

  public function getStdOut(): string {
    return $this->stdout;
  }

  public function getStdErr(): string {
    return $this->stderr;
  }
}
