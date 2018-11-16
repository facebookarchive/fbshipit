<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

interface ShipItDestinationRepo {
  require extends ShipItRepo;

  /**
   * Find the contents of the fbshipit-source-id: header in the latest commit.
   *
   * @param $roots list of paths that contain synced commits.
   */
  public function findLastSourceCommit(
    ImmSet<string> $roots,
  ): ?string;

  /**
   * Generate a text patch ready for committing
   */
  public static function renderPatch(ShipItChangeset $patch): string;

  /**
   * Commit a standardized patch to the repo
   */
  public function commitPatch(ShipItChangeset $patch): string;

  /**
   * push local changes to the upstream
   */
  public function push(): void;
}
