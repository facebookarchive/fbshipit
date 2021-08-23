<?hh
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

/**
 * Dummy class to disable repository locks without changing ShipIt logic.
 */
<<\Oncalls('open_source')>>
final class ShipItDummyLock implements IShipItLock {

  <<__ReturnDisposable>>
  public function getExclusive(): ShipItExclusiveLock {
    return new ShipItExclusiveLock($this);
  }

  public function release(): void {}
}
