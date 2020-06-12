<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/knpiszng
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Str;

final class ShipItDeleteCorruptedRepoPhase extends ShipItPhase {
  public function __construct(private ShipItRepoSide $side) {
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
  public function getCLIArguments(): vec<ShipItCLIArgument> {
    return vec[
      shape(
        'long_name' => $this->side.'-allow-nuke',
        'description' => 'Allow FBShipIt to delete the repository if corrupted',
        'write' => $_ ==> $this->unskip(),
      ),
    ];
  }

  <<__Override>>
  public function runImpl(ShipItBaseConfig $config): void {
    $local_path = $this->side === ShipItRepoSide::SOURCE
      ? $config->getSourcePath()
      : $config->getDestinationPath();

    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    if (!\file_exists($local_path)) {
      return;
    }

    if (!$this->isCorrupted($local_path)) {
      return;
    }

    ShipItLogger::err("  Corruption detected, re-cloning\n");
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $path = \dirname($local_path);
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    if (Str\contains(\php_uname('s'), 'Darwin')) {
      // MacOS doesn't have GNU rm
      (new ShipItShellCommand($path, 'rm', '-rf', $local_path))
        ->runSynchronously();
    } else {
      (
        new ShipItShellCommand(
          $path,
          'rm',
          '-rf',
          '--preserve-root',
          $local_path,
        )
      )
        ->runSynchronously();
    }
  }

  private function isCorrupted(string $local_path): bool {
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    if (\file_exists($local_path.'/.git/')) {
      return $this->isCorruptedGitRepo($local_path);
    }
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    if (\file_exists($local_path.'/.hg/')) {
      return $this->isCorruptedHGRepo($local_path);
    }
    return false;
  }

  private function isCorruptedGitRepo(string $local_path): bool {
    $commands = vec[
      vec['git', 'show', 'HEAD'],
      vec['git', 'fsck'],
    ];

    foreach ($commands as $command) {
      $exit_code = (new ShipItShellCommand($local_path, ...$command))
        ->setNoExceptions()
        ->runSynchronously()
        ->getExitCode();
      if ($exit_code !== 0) {
        return true;
      }
    }

    return false;
  }

  private function isCorruptedHGRepo(string $local_path): bool {
    // Given ShipItRepoHG's lock usage, there should never be a transaction in
    // progress if we have the lock.
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    if (\file_exists($local_path.'/.hg/store/journal')) {
      return true;
    }

    $result = (
      new ShipItShellCommand(
        $local_path,
        'hg',
        'log',
        '-r',
        'tip',
        '--template',
        "{node}\n",
      )
    )
      ->setNoExceptions()
      ->setEnvironmentVariables(dict['HGPLAIN' => '1'])
      ->runSynchronously();

    if ($result->getExitCode() !== 0) {
      return true;
    }
    $revision = Str\trim($result->getStdOut());
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    if (\preg_match('/^0+$/', $revision)) {
      // 000000...0 is not a valid revision ID, but it's what we get
      // for an empty repository
      return true;
    }

    return false;
  }
}
