<?hh // strict
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;

class ShipItRepoException extends \Exception {
  public function __construct(?ShipItRepo $repo, string $message) {
    if ($repo !== null) {
      $message = get_class($repo) . ": $message";
    }
    parent::__construct($message);
  }
}

/**
 * Repo handler interface
 * For agnostic communication with git, hg, etc...
 */
abstract class ShipItRepo {
  private ShipItScopedFlock $lock;

  /**
   * @param $path the path to the repository
   */
  public function __construct(
    protected string $path,
    string $branch,
  ) {
    $this->lock = self::createSharedLockForPath($path);
    $this->setBranch($branch);
  }

  /**
   * Get the ShipItChangeset of the HEAD revision in the current branch.
   */
  public abstract function getHeadChangeset(): ?ShipItChangeset;

  protected function getSharedLock(): ShipItScopedFlock {
    return $this->lock;
  }

  const VERBOSE_FETCH = 1;
  const VERBOSE_SHELL = 2;
  const VERBOSE_SHELL_OUTPUT = 4;
  const VERBOSE_SHELL_INPUT = 8;

  // Level of verbosity for -v option
  const VERBOSE_STANDARD = 3;

  static public int $VERBOSE = 0;

  const TYPE_GIT = 'git';
  const TYPE_HG  = 'hg';

  public function getPath(): string {
    return $this->path;
  }

  public static function createSharedLockForPath(
    string $repo_path
  ): ShipItScopedFlock {
    $lock_path = dirname($repo_path).'/'.basename($repo_path).'.fbshipit-lock';
    return ShipItScopedFlock::createShared($lock_path);
  }

  /**
   * Implement to allow changing branches
   */
  protected abstract function setBranch(string $branch): bool;

  public abstract function updateBranchTo(string $base_rev): void;

  /**
   * Cleans our checkout.
   */
  public abstract function clean(): void;

  /**
   * Updates our checkout
   */
  public abstract function pull(): void;

  public static function typedOpen<Trepo as ShipItRepo>(
    classname<Trepo> $interface,
    string $path,
    string $branch,
  ): Trepo {
    $repo = ShipItRepo::open($path, $branch);
    invariant(
      /* HH_FIXME[4162]: Instanceof on a generic classname is now an error.
       * Consider using different logic to avoid use of classname<Trepo>.
       */
      $repo instanceof $interface,
      '%s is a %s, needed a %s',
      $path,
      get_class($repo),
      $interface,
    );
    return $repo;
  }

  /**
   * Factory
   */
  public static function open(
    string $path,
    string $branch,
  ): ShipItRepo {
    if (file_exists($path.'/.git')) {
      return new ShipItRepoGIT($path, $branch);
    }
    if (file_exists($path.'/.hg')) {
      return new ShipItRepoHG($path, $branch);
    }
    throw new ShipItRepoException(
      null,
      "Can't determine type of repo at ".$path,
    );
  }

  /**
   * Convert a hunk to a ShipItDiff shape
   */
  protected static function parseDiffHunk(string $hunk): ShipItDiff {
    list($header, $body) = explode("\n", $hunk, 2);
    $matches = array();
    preg_match('@^diff --git [ab]/(.*?) [ab]/(.*?)$@', trim($header), $matches);
    return shape(
      'path' => $matches[1],
      'body' => $body,
    );
  }
}
