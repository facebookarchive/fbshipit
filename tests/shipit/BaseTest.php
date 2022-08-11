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
 * https://fburl.com/xm1y32k1
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Vec}; // @oss-enable

abstract class BaseTest extends \Facebook\HackTest\HackTest { // @oss-enable
// @oss-disable: abstract class BaseTest extends \WWWTest {

  public static async function createData(): Awaitable<void> {} // @oss-enable
  public async function beforeEach(): Awaitable<void> {} // @oss-enable
  public async function afterEach(): Awaitable<void> {} // @oss-enable

  <<__Override>> // @oss-enable
  public async function beforeEachTestAsync(): Awaitable<void> { // @oss-enable
    await $this->beforeEach(); // @oss-enable
  } // @oss-enable

  <<__Override>> // @oss-enable
  public async function afterEachTestAsync(): Awaitable<void> { // @oss-enable
    await $this->afterEach(); // @oss-enable
  } // @oss-enable

  <<__Override>> // @oss-enable
  public static async function beforeFirstTestAsync(): Awaitable<void> { // @oss-enable
    await static::createData(); // @oss-enable
  } // @oss-enable

  protected static function diffsFromMap(
    dict<string, string> $diffs,
  ): vec<ShipItDiff> {
    return Vec\map_with_key(
      $diffs,
      ($path, $body) ==> shape('path' => $path, 'body' => $body),
    );
  }
}
