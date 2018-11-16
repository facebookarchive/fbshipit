<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ImportIt;

class ImportItRepoException extends \Exception {
  public function __construct(?ImportItRepo $repo, string $message) {
    if ($repo !== null) {
      $message = \get_class($repo) . ": " . $message;
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
  public static function open(
    string $path,
    string $branch,
  ):  \Facebook\ShipIt\ShipItRepo {
    if (\file_exists($path.'/.git')) {
      return new ImportItRepoGIT($path, $branch);
    }
    if (\file_exists($path.'/.hg')) {
      return new ImportItRepoHG($path, $branch);
    }
    throw new ImportItRepoException(
      null,
      "Can't determine type of repo at ".$path,
    );
  }
}
