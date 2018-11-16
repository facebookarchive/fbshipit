<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItFilterSanityCheckPhase extends ShipItPhase {
  const TEST_FILE_NAME = 'shipit_test_file.txt';

  public function __construct(
    private (function(ShipItChangeset):ShipItChangeset) $filter,
  ) {}

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Sanity-check commit filter';
  }

  <<__Override>>
  public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'skip-filter-sanity-check',
        'description' => 'Skip the filter sanity check.',
        'write' => $x ==> $this->skip(),
      ),
    };
  }

  <<__Override>>
  protected function runImpl(ShipItBaseConfig $config): void {
    $this->assertValid($config->getSourceRoots());
  }

  // Public for testing
  public function assertValid(ImmSet<string> $sourceRoots): void {
    $filter = $this->filter;
    $allows_all = false;
    foreach ($sourceRoots as $root) {
      $test_file = $root.'/'.self::TEST_FILE_NAME;
      $test_file = \str_replace('//', '/', $test_file);
      $changeset = (new ShipItChangeset())
        ->withDiffs(ImmVector {
          shape('path' => $test_file, 'body' => 'junk')
        });
      $changeset = $filter($changeset);
      if ($changeset->getDiffs()->count() !== 1) {
        invariant_violation(
          "Source root '%s' specified, but is removed by filter; debug: %s\n",
          $root,
          \var_export($changeset->getDebugMessages(), /* return = */ true),
        );
      }

      if ($root === '' || $root === '.' || $root === './') {
        $allows_all = true;
      }
    }

    if ($allows_all || $sourceRoots->count() === 0) {
      return;
    }

    $path = '!!!shipit_test_file!!!';
    $changeset = (new ShipItChangeset())
      ->withDiffs(ImmVector {
        shape('path' => $path, 'body' => 'junk')
      });
    $changeset = $filter($changeset);
    invariant(
      $changeset->getDiffs()->count() === 0,
      'Path "%s" is not in a sourceRoot, but passes filter',
      $path,
    );
  }
}
