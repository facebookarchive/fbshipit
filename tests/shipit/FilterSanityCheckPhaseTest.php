<?hh // strict
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;

final class FilterSanityCheckPhaseTest extends BaseTest {
  public function testAllowsValidCombination(): void {
    $phase = new ShipItFilterSanityCheckPhase(
      $changeset ==> $changeset->withDiffs(
        $changeset->getDiffs()->filter(
          $diff ==> substr($diff['path'], 0, 4) === 'foo/',
        ),
      ),
      ImmSet { 'foo/' },
    );
    $phase->assertValid();
    // no exception thrown :)
  }

  public function exampleEmptyRoots(): array<string, array<ImmSet<string>>> {
    return [
      'empty set' => [ImmSet { }],
      'empty string' => [ImmSet { '' }],
      '.' => [ImmSet { '.' }],
      './' => [ImmSet { './' }],
    ];
  }

  /**
   * @dataProvider exampleEmptyRoots
   */
  public function testAllowsIdentityFunctionForEmptyRoots(
    ImmSet<string> $roots,
  ): void {
    $phase = new ShipItFilterSanityCheckPhase(
      $changeset ==> $changeset,
      $roots,
    );
    $phase->assertValid();
    // no exception thrown :)
  }

  /**
   * @expectedException \HH\InvariantException
   */
  public function testThrowsForIdentityFunctionWithRoots(): void {
    $phase = new ShipItFilterSanityCheckPhase(
      $changeset ==> $changeset, // stuff outside of 'foo' should be removed
      ImmSet { 'foo/' },
    );
    $phase->assertValid();
  }

  /**
   * @expectedException \HH\InvariantException
   */
  public function testThrowsForEmptyChangeset(): void {
    $phase = new ShipItFilterSanityCheckPhase(
      $changeset ==> (new ShipItChangeset()),
      ImmSet { 'foo/' },
    );
    $phase->assertValid();
  }

  /**
   * @expectedException \HH\InvariantException
   */
  public function testThrowsForPartialMatch(): void {
    $phase = new ShipItFilterSanityCheckPhase(
      $changeset ==> $changeset->withDiffs(
        $changeset->getDiffs()->filter(
          $diff ==> substr($diff['path'], 0, 3) === 'foo',
        )
      ),
      ImmSet { 'foo/', 'herp/' },
    );
    $phase->assertValid();
  }
}
