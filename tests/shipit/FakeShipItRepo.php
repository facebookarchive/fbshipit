<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class FakeShipItRepo extends ShipItRepo {
  public function __construct(
    private ?ShipItChangeset $headChangeset = null,
  ) {
    $tempdir = new ShipItTempDir('FakeShipItRepo');
    parent::__construct($tempdir->getPath(), '');
  }

  <<__Override>>
  public function getHeadChangeset(): ?ShipItChangeset {
    return $this->headChangeset;
  }

  <<__Override>>
  protected function setBranch(string $branch): bool {
    return true;
  }

  <<__Override>>
  public function updateBranchTo(string $base_rev): void {}

  <<__Override>>
  public function clean(): void {}

  <<__Override>>
  public function pull(): void {}

  <<__Override>>
  public function pushLfs(string $pullEndpoint, string $pushEndpoint): void {}

  <<__Override>>
  public function getOrigin(): string {
    return '';
  }

  <<__Override>>
  public static function getDiffsFromPatch(
    string $patch,
  ): ImmVector<ShipItDiff> {
    return ImmVector {};
  }
}
