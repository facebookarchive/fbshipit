<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItDeleteCorruptedRepoPhase extends ShipItPhase {
  public function __construct(
    private ShipItRepoSide $side,
  ) {
    // Skipped by default, 'unskipped' by --$SIDE-allow-nuke flag
    $this->skip();
  }

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Delete '.$this->side.' repository if corrupted';
  }

  <<__Override>>
  public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => $this->side.'-allow-nuke',
        'description' => 'Allow FBShipIt to delete the repository if corrupted',
        'write' => $_ ==> $this->unskip(),
      ),
    };
  }

  <<__Override>>
  public function runImpl(
    ShipItBaseConfig $config,
  ): void {
    $local_path =
      $this->side === ShipItRepoSide::SOURCE
      ? $config->getSourcePath()
      : $config->getDestinationPath();

    if (!\file_exists($local_path)) {
      return;
    }

    $lock_sh = ShipItRepo::createSharedLockForPath($local_path);

    if (!$this->isCorrupted($local_path)) {
      return;
    }

    \fwrite(\STDERR, "  Corruption detected, re-cloning\n");
    $lock_ex = $lock_sh->getExclusive();
    (new ShipItShellCommand(
      \dirname($local_path),
      'rm', '-rf', '--preserve-root', $local_path,
    ))->runSynchronously();
  }

  protected function isCorrupted(string $local_path): bool {
    if (\file_exists($local_path.'/.git/')) {
      return $this->isCorruptedGitRepo($local_path);
    }
    if (\file_exists($local_path.'/.hg/')) {
      return $this->isCorruptedHGRepo($local_path);
    }
    return false;
  }

  protected function isCorruptedGitRepo(string $local_path): bool {
    $commands = ImmVector {
      ImmVector { 'git', 'show', 'HEAD' },
      ImmVector { 'git', 'fsck' },
    };

    foreach ($commands as $command) {
      $exit_code = (new ShipItShellCommand($local_path,...$command))
        ->setNoExceptions()
        ->runSynchronously()
        ->getExitCode();
      if ($exit_code !== 0) {
        return true;
      }
    }

    return false;
  }

  protected function isCorruptedHGRepo(string $local_path): bool {
    // Given ShipItRepoHG's lock usage, there should never be a transaction in
    // progress if we have the lock.
    if (\file_exists($local_path.'/.hg/store/journal')) {
      return true;
    }

    $result = (new ShipItShellCommand(
      $local_path,
      'hg', 'log', '-r', 'tip', '--template', "{node}\n"
    ))
      ->setNoExceptions()
      ->setEnvironmentVariables(ImmMap { 'HGPLAIN' => '1' })
      ->runSynchronously();

    if ($result->getExitCode() !== 0) {
      return true;
    }
    $revision = \trim($result->getStdOut());
    if (\preg_match('/^0+$/', $revision)) {
      // 000000...0 is not a valid revision ID, but it's what we get
      // for an empty repository
      return true;
    }

    return false;
  }
}
