<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class FilterSanityCheckPhaseTest extends BaseTest {
  public function testAllowsValidCombination(): void {
    $phase = new ShipItFilterSanityCheckPhase(
      $changeset ==> $changeset->withDiffs(
        $changeset->getDiffs()->filter(
          $diff ==> \substr($diff['path'], 0, 4) === 'foo/',
        ),
      ),
    );
    $phase->assertValid(ImmSet { 'foo/' });
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
    );
    $phase->assertValid($roots);
    // no exception thrown :)
  }

  /**
   * @expectedException \HH\InvariantException
   */
  public function testThrowsForIdentityFunctionWithRoots(): void {
    $phase = new ShipItFilterSanityCheckPhase(
      $changeset ==> $changeset, // stuff outside of 'foo' should be removed
    );
    $phase->assertValid(ImmSet { 'foo/' });
  }

  /**
   * @expectedException \HH\InvariantException
   */
  public function testThrowsForEmptyChangeset(): void {
    $phase = new ShipItFilterSanityCheckPhase(
      $changeset ==> (new ShipItChangeset()),
    );
    $phase->assertValid(ImmSet { 'foo/' });
  }

  /**
   * @expectedException \HH\InvariantException
   */
  public function testThrowsForPartialMatch(): void {
    $phase = new ShipItFilterSanityCheckPhase(
      $changeset ==> $changeset->withDiffs(
        $changeset->getDiffs()->filter(
          $diff ==> \substr($diff['path'], 0, 3) === 'foo',
        )
      ),
    );
    $phase->assertValid(ImmSet { 'foo/', 'herp/' });
  }
}
