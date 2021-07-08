<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/beb4xz2n
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, Vec}; // @oss-enable
use type Facebook\HackTest\DataProvider; // @oss-enable
// @oss-disable: use type DataProvider;


<<\Oncalls('open_source')>>
final class AssertValidFilterPhaseTest extends BaseTest {
  public async function testAllowsValidCombination(): Awaitable<void> {
    $phase = new ShipItAssertValidFilterPhase(
      async $changeset ==> $changeset->withDiffs(
        Vec\filter(
          $changeset->getDiffs(),
          $diff ==> Str\slice($diff['path'], 0, 4) === 'foo/',
        ),
      ),
    );
    await $phase->genAssertValid(keyset['foo/']);
    // no exception thrown :)
  }

  public static function exampleEmptyRoots(
  ): dict<string, vec<keyset<string>>> {
    return dict[
      'empty set' => vec[keyset[]],
      'empty string' => vec[keyset['']],
      '.' => vec[keyset['.']],
      './' => vec[keyset['./']],
    ];
  }

  <<DataProvider('exampleEmptyRoots')>>
  public async function testAllowsIdentityFunctionForEmptyRoots(
    keyset<string> $roots,
  ): Awaitable<void> {
    $phase = new ShipItAssertValidFilterPhase(async $changeset ==> $changeset);
    await $phase->genAssertValid($roots);
    // no exception thrown :)
  }

  public async function testThrowsForIdentityFunctionWithRoots(
  ): Awaitable<void> {
    \expect(async () ==> {
      $phase = new ShipItAssertValidFilterPhase(
        async $changeset ==> $changeset,
        // stuff outside of 'foo' should be removed
      );
      await $phase->genAssertValid(keyset['foo/']);
    })
      // @oss-disable: ->toThrow(\InvariantViolationException::class);
    ->toThrow(InvariantException::class); // @oss-enable
  }

  public async function testThrowsForEmptyChangeset(): Awaitable<void> {
    \expect(async () ==> {
      $phase = new ShipItAssertValidFilterPhase(
        async $_changeset ==> (new ShipItChangeset()),
      );
      await $phase->genAssertValid(keyset['foo/']);
    })
      // @oss-disable: ->toThrow(\InvariantViolationException::class);
    ->toThrow(InvariantException::class); // @oss-enable
  }

  public async function testThrowsForPartialMatch(): Awaitable<void> {
    \expect(async () ==> {
      $phase = new ShipItAssertValidFilterPhase(
        async $changeset ==> $changeset->withDiffs(
          Vec\filter(
            $changeset->getDiffs(),
            $diff ==> Str\slice($diff['path'], 0, 3) === 'foo',
          ),
        ),
      );
      await $phase->genAssertValid(keyset['foo/', 'herp/']);
    })
      // @oss-disable: ->toThrow(\InvariantViolationException::class);
    ->toThrow(InvariantException::class); // @oss-enable
  }

  public async function testAllowsForStrippedPaths(): Awaitable<void> {
    $phase = new ShipItAssertValidFilterPhase(
      async $changeset ==> $changeset->withDiffs(
        Vec\filter(
          $changeset->getDiffs(),
          $diff ==> Str\slice($diff['path'], 0, 4) === 'foo/',
        ),
      ),
      vec['@bar@'],
    );
    await $phase->genAssertValid(keyset['foo/', 'bar/']);
  }
}
