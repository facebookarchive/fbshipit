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
 * https://fburl.com/du4w2jy6
 */
namespace Facebook\ShipIt;

interface ShipItSourceRepo extends IShipItRepo {
  /**
   * Get the next child of this revision in the current branch.
   *
   * @param $roots list of paths that contain synced commits.
   */
  public function genFindNextCommit(
    string $commit,
    keyset<string> $roots,
  ): Awaitable<?string>;

  /**
   * Get a standardized representation of the specified revision
   */
  public function genChangesetFromID(
    string $revision,
  ): Awaitable<?ShipItChangeset>;

  /**
   * Raw patch file that one might get from git show/hg export.  No header data
   * is included.
   *
   * Useful for testing.
   */
  public function genNativePatchFromID(string $revision): Awaitable<string>;

  /**
   * Raw metadata containing information like the commit message, author, and
   * date that one might get from git show/hg export.
   *
   * Useful for testing.
   */
  public function genNativeHeaderFromID(string $revision): Awaitable<string>;

  /**
   * Get a standardized representation of the string diff. This should be the
   * output from git format-patch, hg-export or similar. Handy for testing.

   * @param $header The author, date, and message information
   * @param $patch The code changes
   */
  public static function getChangesetFromExportedPatch(
    string $header,
    string $patch,
  ): ?ShipItChangeset;

  /**
   * Create a directory containing the specified paths.
   */
  public function genExport(
    keyset<string> $roots,
    bool $do_submodules,
    ?string $rev = null, // defaults to the current revision
  ): Awaitable<shape('tempDir' => ShipItTempDir, 'revision' => string)>;
}
