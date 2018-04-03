<?hh // strict
/**
 * Copyright (c) 2017-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;

final class ShipItUtilTest extends BaseTest {
  public function testDiffofDiffs(): void {
    $patch = \file_get_contents(__DIR__.'/git-diffs/diff-in-diff.patch');
    $sections = Vector {};
    $sections->addAll(ShipItUtil::parsePatch($patch));
    $this->assertEquals(2, $sections->count(), 'Should only get one section!');
  }
}
