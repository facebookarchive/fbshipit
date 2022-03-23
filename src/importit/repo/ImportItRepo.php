<?hh
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 * @format
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/3vzsnpy8
 */
namespace Facebook\ImportIt;

final class ImportItRepoException extends \Exception {
  public function __construct(?ImportItRepo $repo, string $message) {
    if ($repo !== null) {
      $message = \get_class($repo).": ".$message;
    }
    parent::__construct($message);
  }
}

/**
 * Repo handler interface
 * For agnostic communication with git, hg, etc...
 */
abstract class ImportItRepo {
  /**
   * Factory
   */
  public static async function genOpen(
    \Facebook\ShipIt\IShipItLock $lock,
    string $path,
    string $branch,
  ): Awaitable<\Facebook\ShipIt\IShipItRepo> {
    if (PHP\file_exists($path.'/.git')) {
      $repo = new ImportItRepoGIT($lock, $path);
      await $repo->genSetBranch($branch);
      return $repo;
    }
    if (PHP\file_exists($path.'/.hg')) {
      $repo = new ImportItRepoHG($lock, $path);
      await $repo->genSetBranch('master');
      return $repo;
    }
    throw
      new ImportItRepoException(null, "Can't determine type of repo at ".$path);
  }
}
