<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/l0i2ew1f
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\C;

<<\Oncalls('open_source')>>
final class ShipItUtilTest extends BaseTest {
  public function testDiffofDiffs(): void {
    $patch = \file_get_contents(__DIR__.'/git-diffs/diff-in-diff.patch');
    $sections = vec(ShipItUtil::parsePatch($patch));
    \expect(C\count($sections))->toBePHPEqual(
      1,
      'Should only get one section!',
    );
  }
}
