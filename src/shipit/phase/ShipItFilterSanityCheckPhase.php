<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/tceaz97c
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, C};

final class ShipItFilterSanityCheckPhase extends ShipItPhase {
  const TEST_FILE_NAME = 'shipit_test_file.txt';

  public function __construct(
    private (function(ShipItChangeset): ShipItChangeset) $filter,
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
  public function getCLIArguments(): vec<ShipItCLIArgument> {
    return vec[
      shape(
        'long_name' => 'skip-filter-sanity-check',
        'description' => 'Skip the filter sanity check.',
        'write' => $_ ==> $this->skip(),
      ),
    ];
  }

  <<__Override>>
  protected function runImpl(ShipItBaseConfig $config): void {
    $this->assertValid($config->getSourceRoots());
  }

  // Public for testing
  public function assertValid(keyset<string> $source_roots): void {
    $filter = $this->filter;
    $allows_all = false;
    foreach ($source_roots as $root) {
      $test_file = $root.'/'.self::TEST_FILE_NAME;
      $test_file = Str\replace($test_file, '//', '/');
      $changeset = (new ShipItChangeset())
        ->withDiffs(vec[
          shape('path' => $test_file, 'body' => 'junk'),
        ]);
      $changeset = $filter($changeset);
      if (C\count($changeset->getDiffs()) !== 1) {
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

    if ($allows_all || C\is_empty($source_roots)) {
      return;
    }

    $path = '!!!shipit_test_file!!!';
    $changeset = (new ShipItChangeset())
      ->withDiffs(vec[
        shape('path' => $path, 'body' => 'junk'),
      ]);
    $changeset = $filter($changeset);
    invariant(
      C\is_empty($changeset->getDiffs()),
      'Path "%s" is not in a sourceRoot, but passes filter',
      $path,
    );
  }
}
