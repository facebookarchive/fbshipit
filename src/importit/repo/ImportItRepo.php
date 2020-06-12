<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
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
  public static function open(
    \Facebook\ShipIt\IShipItLock $lock,
    string $path,
    string $branch,
  ): \Facebook\ShipIt\ShipItRepo {
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    if (\file_exists($path.'/.git')) {
      return new ImportItRepoGIT($lock, $path, $branch);
    }
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    if (\file_exists($path.'/.hg')) {
      return new ImportItRepoHG($lock, $path, $branch);
    }
    throw new ImportItRepoException(
      null,
      "Can't determine type of repo at ".$path,
    );
  }
}
