<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt;

enum ShipItScopedFlockOperation: int {
  MAKE_EXCLUSIVE = \LOCK_EX;
  MAKE_SHARED = \LOCK_SH;
  RELEASE = \LOCK_UN;
}

class ShipItScopedFlock {
  private bool $debug;
  private bool $released = false;

  public static function createShared(
    string $path,
  ): ShipItScopedFlock {
    $dir = \dirname($path);
    if (!\file_exists($dir)) {
      \mkdir($dir, /* mode = */ 0755, /* recursive = */ true);
    }
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
    $this->debug = \getenv('FBSHIPIT_DEBUG_FLOCK');

    switch ($constructBehavior) {
      case ShipItScopedFlockOperation::MAKE_EXCLUSIVE:
        $this->debugWrite('Acquiring exclusive lock...');
        break;
      case ShipItScopedFlockOperation::MAKE_SHARED:
        $this->debugWrite('Acquiring shared lock...');
        break;
      default:
        throw new \Exception('Invalid lock operation');
    }

    if (!\flock($fp, $constructBehavior)) {
      throw new \Exception('Failed to acquire lock');
    }

    $this->debugWrite('...lock acquired.');
  }

  public function release(): void {
    invariant(
      $this->released === false,
      "Tried to release lock twice",
    );

    switch ($this->destructBehavior) {
      case ShipItScopedFlockOperation::MAKE_SHARED:
        $this->debugWrite('Downgrading to shared lock...');
        $after = "...lock downgraded.";
        break;
      case ShipItScopedFlockOperation::RELEASE:
        $this->debugWrite('Releasing lock...');
        $after = '...lock released.';
        break;
      default:
        throw new \Exception('Invalid release operation');
    }

    if (!\flock($this->fp, $this->destructBehavior)) {
      throw new \Exception('Failed to weaken lock');
    }
    $this->debugWrite($after);
    $this->released = true;
  }

  private function debugWrite(string $message): void {
    if (!$this->debug) {
      return;
    }
    \fprintf(\STDERR, "  [flock] %s\n    %s\n", $message, $this->path);
  }

  <<__OptionalDestruct>>
  public function __destruct() {
    if ($this->released) {
      return;
    }

    $this->release();
  }
}
