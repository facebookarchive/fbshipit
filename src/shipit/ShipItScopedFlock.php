<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/90cr93g9
 */

namespace Facebook\ShipIt;

enum ShipItScopedFlockOperation: int as int {
  MAKE_EXCLUSIVE = \LOCK_EX;
  MAKE_SHARED = \LOCK_SH;
  RELEASE = \LOCK_UN;
}

interface IShipItLock {
  public function getExclusive(): this;
  public function release(): void;
}

final class ShipItScopedFlock implements IShipItLock {
  const int DEBUG_EXCLUSIVE = 1;
  const int DEBUG_SHARED = 2;
  const int DEBUG_RELEASE = 4;
  const int DEBUG_ALL = 7;
  private bool $released = false;
  public static int $verbose = 0;

  public static function createShared(string $path): ShipItScopedFlock {
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $dir = \dirname($path);
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    if (!\file_exists($dir)) {
      /* HH_FIXME[2049] __PHPStdLib */
      /* HH_FIXME[4107] __PHPStdLib */
      \mkdir($dir, /* mode = */ 0755, /* recursive = */ true);
    }
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $fp = \fopen($path, 'w+');
    if (!$fp) {
      throw new \Exception('Failed to fopen: '.$path);
    }

    return new ShipItScopedFlock(
      $path,
      $fp,
      ShipItScopedFlockOperation::MAKE_SHARED,
      ShipItScopedFlockOperation::RELEASE,
    );
  }

  public function getExclusive(): ShipItScopedFlock {
    if (
      $this->constructBehavior === ShipItScopedFlockOperation::MAKE_EXCLUSIVE
    ) {
      return $this;
    }

    return new ShipItScopedFlock(
      $this->path,
      $this->fp,
      ShipItScopedFlockOperation::MAKE_EXCLUSIVE,
      ShipItScopedFlockOperation::MAKE_SHARED,
    );
  }

  private function __construct(
    private string $path,
    private resource $fp,
    private ShipItScopedFlockOperation $constructBehavior,
    private ShipItScopedFlockOperation $destructBehavior,
  ) {

    switch ($constructBehavior) {
      case ShipItScopedFlockOperation::MAKE_EXCLUSIVE:
        $this->debugWrite('Acquiring exclusive lock...', self::DEBUG_EXCLUSIVE);
        break;
      case ShipItScopedFlockOperation::MAKE_SHARED:
        $this->debugWrite('Acquiring shared lock...', self::DEBUG_SHARED);
        break;
      default:
        throw new \Exception('Invalid lock operation');
    }

    $_wouldblock = null;
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $flock_result = \flock($fp, $constructBehavior, inout $_wouldblock);
    if (!$flock_result) {
      throw new \Exception('Failed to acquire lock');
    }
  }

  public function release(): void {
    invariant($this->released === false, "Tried to release lock twice");

    switch ($this->destructBehavior) {
      case ShipItScopedFlockOperation::MAKE_SHARED:
        $this->debugWrite('Downgrading to shared lock...', self::DEBUG_RELEASE);
        break;
      case ShipItScopedFlockOperation::RELEASE:
        $this->debugWrite('Releasing lock...', self::DEBUG_RELEASE);
        break;
      default:
        throw new \Exception('Invalid release operation');
    }

    $_wouldblock = null;
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $flock_result = \flock($this->fp, $this->destructBehavior, inout $_wouldblock);
    if (!$flock_result) {
      throw new \Exception('Failed to weaken lock');
    }
    $this->released = true;
    if ($this->destructBehavior === ShipItScopedFlockOperation::RELEASE) {
      /* HH_FIXME[2049] __PHPStdLib */
      /* HH_FIXME[4107] __PHPStdLib */
      \fclose($this->fp);
      /* HH_FIXME[2049] __PHPStdLib */
      /* HH_FIXME[4107] __PHPStdLib */
      \unlink($this->path);
    }
  }

  private function debugWrite(string $message, int $level): void {
    if (self::$verbose & $level) {
      ShipItLogger::err("  [flock] %s: %s\n", $message, $this->path);
    }
  }

  public static function getLockFilePathForRepoPath(string $repo_path): string {
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    return \dirname($repo_path).'/'.\basename($repo_path).'.fbshipit-lock';
  }
}
