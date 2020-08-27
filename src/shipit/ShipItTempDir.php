<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/v6y4kokw
 */

namespace Facebook\ShipIt;

enum ShipItTempDirMode: string {
  AUTO_REMOVE = 'AUTO_REMOVE';
  KEEP = 'KEEP';
  REMOVED = 'REMOVE';
}

final class ShipItTempDir {
  private string $path;
  private ShipItTempDirMode $mode = ShipItTempDirMode::AUTO_REMOVE;

  public function __construct(string $component) {
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $path = \sys_get_temp_dir().'/shipit-'.$component.'-';
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $path .= \bin2hex(\random_bytes(32));
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    \mkdir($path);
    $this->path = $path;
  }

  public function keep(): void {
    $this->assertMode(ShipItTempDirMode::AUTO_REMOVE);
    $this->mode = ShipItTempDirMode::KEEP;
  }

  public function remove(): void {
    $this->assertMode(ShipItTempDirMode::AUTO_REMOVE);
    (
      /* HH_FIXME[2049] __PHPStdLib */
      /* HH_FIXME[4107] __PHPStdLib */
      new ShipItShellCommand(\sys_get_temp_dir(), 'rm', '-rf', $this->path)
    )->runSynchronously();
    $this->mode = ShipItTempDirMode::REMOVED;
  }

  public function getPath(): string {
    return $this->path;
  }

  public function __clone(): noreturn {
    invariant_violation("Can't touch^Wclone this");
  }

  private function assertMode(ShipItTempDirMode $mode): void {
    invariant(
      $this->mode === $mode,
      'Mode is %s, expected %s',
      $this->mode,
      $mode,
    );
  }
}
