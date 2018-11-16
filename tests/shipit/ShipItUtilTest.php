<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItUtilTest extends BaseTest {
  public function testDiffofDiffs(): void {
    $patch = \file_get_contents(__DIR__.'/git-diffs/diff-in-diff.patch');
    $sections = Vector {};
    $sections->addAll(ShipItUtil::parsePatch($patch));
    $this->assertEquals(1, $sections->count(), 'Should only get one section!');
  }
}
