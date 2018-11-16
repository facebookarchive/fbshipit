<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
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

  public function __construct(
    string $component,
  ) {
    $path = \sys_get_temp_dir().'/shipit-'.$component.'-';
    $path .= \bin2hex(\random_bytes(32));
    \mkdir($path);
    $this->path = $path;
  }

  public function keep(): void {
    $this->assertMode(ShipItTempDirMode::AUTO_REMOVE);
    $this->mode = ShipItTempDirMode::KEEP;
  }

  public function remove(): void {
    $this->assertMode(ShipItTempDirMode::AUTO_REMOVE);
/* HH_FIXME[4128] Use ShipItShellCommand */
    ShipItUtil::shellExec(
      \sys_get_temp_dir(),
      /* stdin = */ null,
      ShipItUtil::DONT_VERBOSE,
      'rm', '-rf', $this->path,
    );
    $this->mode = ShipItTempDirMode::REMOVED;
  }

  public function getPath(): string {
    return $this->path;
  }

  <<__OptionalDestruct>>
  public function __destruct() {
    if ($this->mode === ShipItTempDirMode::AUTO_REMOVE) {
      $this->remove();
    }
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
