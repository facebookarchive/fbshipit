<?hh
// (c) Meta Platforms, Inc. and affiliates. Confidential and proprietary.

namespace Facebook\ShipIt;

interface IShipItRepo {
  protected function getSharedLock(): IShipItLock;

  public function getPath(): string;

  public static function genTypedOpen<
    <<__Enforceable>> reify Trepo as IShipItRepo,
  >(IShipItLock $lock, string $path, string $branch): Awaitable<Trepo>;

  /**
   * Factory
   */
  public static function genOpen(
    IShipItLock $lock,
    string $path,
    string $branch,
  ): Awaitable<IShipItRepo>;

  public static function parseDiffHunk(string $hunk): ?ShipItDiff;

  public static function getCommitMessage(ShipItChangeset $changeset): string;

  public static function parsePatch(string $patch): Iterator<string>;

  /**
   * Implement to allow changing branches
   */
  protected function genSetBranch(string $branch): Awaitable<bool>;

  public function genUpdateBranchTo(string $base_rev): Awaitable<void>;

  /**
   * Cleans our checkout.
   */
  public function genClean(): Awaitable<void>;

  /**
   * Updates our checkout
   */
  public function genPull(): Awaitable<void>;

  /**
   * push lfs support
   */
  public function genPushLfs(
    string $lfs_pull_endpoint,
    string $lfs_push_endpoint,
  ): Awaitable<void>;

  /**
   * Get the origin of the checkout.
   */
  public function genOrigin(): Awaitable<string>;

  /**
   * Get the ShipItChangeset of the HEAD revision in the current branch.
   */
  public function genHeadChangeset(): Awaitable<?ShipItChangeset>;

  public static function getDiffsFromPatch(string $patch): vec<ShipItDiff>;

}
