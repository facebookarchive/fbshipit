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
  <<__ReturnDisposable>>
  public function getExclusive(): ShipItExclusiveLock;
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
    $dir = PHP\dirname($path);
    if (!PHP\file_exists($dir)) {
      PHP\mkdir($dir, /* mode = */ 0755, /* recursive = */ true);
    }
    $fp = PHP\fopen($path, 'w+');
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

  <<__ReturnDisposable>>
  public function getExclusive(): ShipItExclusiveLock {
    if (
      $this->constructBehavior === ShipItScopedFlockOperation::MAKE_EXCLUSIVE
    ) {
      return new ShipItExclusiveLock($this);
    }

    return new ShipItExclusiveLock(new ShipItScopedFlock(
      $this->path,
      $this->fp,
      ShipItScopedFlockOperation::MAKE_EXCLUSIVE,
      ShipItScopedFlockOperation::MAKE_SHARED,
    ));
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

    $flock_result = PHP\flock($fp, $constructBehavior);
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

    $flock_result = PHP\flock($this->fp, $this->destructBehavior);
    if (!$flock_result) {
      throw new \Exception('Failed to weaken lock');
    }
    $this->released = true;
    if ($this->destructBehavior === ShipItScopedFlockOperation::RELEASE) {
      try {
        PHP\fclose($this->fp);
        /* HH_IGNORE_ERROR[2049] __PHPStdLib */
        /* HH_IGNORE_ERROR[4107] __PHPStdLib */
        \unlink($this->path);
      } catch (\Exception $_e) {
        // if these files already don't exist do nothing
      }
    }
  }

  private function debugWrite(string $message, int $level): void {
    if (self::$verbose & $level) {
      ShipItLogger::err("  [flock] %s: %s\n", $message, $this->path);
    }
  }

  public static function getLockFilePathForRepoPath(string $repo_path): string {
    return PHP\dirname($repo_path).
      '/'.
      PHP\basename($repo_path).
      '.fbshipit-lock';
  }
}

final class ShipItExclusiveLock implements \IDisposable {
  public function __construct(private IShipItLock $lock) {}

  public function __dispose(): void {
    $this->lock->release();
  }
}
