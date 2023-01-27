<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/gljv7xc0
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Keyset, Vec, Str}; // @oss-enable
use type Facebook\HackTest\DataProvider; // @oss-enable
// @oss-disable: use type DataProvider;

<<\Oncalls('open_source')>>
final class PathFiltersTest extends BaseTest {
  public static function stripPathsTestData(): dict<string, (
    vec<string>, // $patterns,
    vec<string>, // $exceptions,
    keyset<string>, // $expected_files,
  )> {
    return dict[
      'No change' => tuple(
        vec[],
        vec[],
        keyset['foo', 'bar', 'herp/derp', 'herp/derp-derp', 'derp'],
      ),
      'Remove top level file' => tuple(
        vec['@^bar$@'],
        vec[],
        keyset['foo', 'herp/derp', 'herp/derp-derp', 'derp'],
      ),
      'Remove directory ' =>
        tuple(vec['@^herp/@'], vec[], keyset['foo', 'bar', 'derp']),
      'Remove directory contents except one file ' => tuple(
        vec['@^herp/@'],
        vec['@^herp/derp-derp@'],
        keyset['foo', 'bar', 'herp/derp-derp', 'derp'],
      ),
      'Remove file' => tuple(
        vec['@(^|/)derp(/|$)@'],
        vec[],
        keyset['foo', 'bar', 'herp/derp-derp'],
      ),
      'Remove file, except if parent directory has specific name' => tuple(
        vec['@(^|/)derp(/|$)@'],
        vec['@(^|/)herp/derp$@'],
        keyset['foo', 'bar', 'herp/derp', 'herp/derp-derp'],
      ),
      'Multiple patterns' => tuple(
        vec['@^foo$@', '@^bar$@'],
        vec[],
        keyset['herp/derp', 'herp/derp-derp', 'derp'],
      ),
      'Multiple exceptions' =>
        tuple(vec['@@'], vec['@foo@', '@bar@'], keyset['foo', 'bar']),
    ];
  }

  <<DataProvider('stripPathsTestData')>>
  public function testStripPaths(
    vec<string> $patterns,
    vec<string> $exceptions,
    keyset<string> $expected_files,
  ): void {
    $changeset = (new ShipItChangeset())->withDiffs(
      self::diffsFromMap(dict[
        'foo' => 'placeholder',
        'bar' => 'placeholder',
        'herp/derp' => 'placeholder',
        'herp/derp-derp' => 'placeholder',
        'derp' => 'placeholder',
      ]),
    );

    $changeset =
      ShipItPathFilters::stripPaths($changeset, $patterns, $exceptions);

    \expect(Keyset\map($changeset->getDiffs(), $diff ==> $diff['path']))
      ->toEqual($expected_files);
  }

  public static function examplesForMoveDirectories(
  ): dict<string, (dict<string, string>, vec<string>, vec<string>)> {
    return dict[
      'first takes precedence (first is more specific)' => tuple(
        dict[
          'foo/public_tld/' => '',
          'foo/' => '',
        ],
        vec['foo/orig_root_file', 'foo/public_tld/public_root_file'],
        vec['orig_root_file', 'public_root_file'],
      ),
      // this mapping doesn't make sense given the behavior, just using it to
      // check that order matters
      'first takes precedence (second is more specific)' => tuple(
        dict[
          'foo/' => '',
          'foo/public_tld/' => '',
        ],
        vec['foo/orig_root_file', 'foo/public_tld/public_root_file'],
        vec['orig_root_file', 'public_tld/public_root_file'],
      ),
      'only one rule applied' => tuple(
        dict[
          'foo/' => '',
          'bar/' => 'project_bar/',
        ],
        vec['foo/bar/part of project foo', 'bar/part of project bar'],
        vec[
          'bar/part of project foo',
          'project_bar/part of project bar',
        ],
      ),
    ];
  }

  <<DataProvider('examplesForMoveDirectories')>>
  public function testMoveDirectories(
    dict<string, string> $map,
    vec<string> $in,
    vec<string> $expected,
  ): void {
    $changeset = (new ShipItChangeset())
      ->withDiffs(
        Vec\map($in, $path ==> shape('path' => $path, 'body' => 'junk')),
      );
    $changeset = ShipItPathFilters::moveDirectories($changeset, $map);
    \expect(Vec\map($changeset->getDiffs(), $diff ==> $diff['path']))
      ->toEqual($expected);
  }

  public static function examplesForStripExceptDirectories(
  ): vec<(keyset<string>, vec<string>, vec<string>)> {
    return vec[
      tuple(keyset['foo'], vec['foo/bar', 'herp/derp'], vec['foo/bar']),
      tuple(keyset['foo/'], vec['foo/bar', 'herp/derp'], vec['foo/bar']),
      tuple(keyset['foo'], vec['foo/bar', 'foobaz'], vec['foo/bar']),
      tuple(
        keyset['foo', 'herp'],
        vec['foo/bar', 'herp/derp', 'baz'],
        vec['foo/bar', 'herp/derp'],
      ),
    ];
  }

  <<DataProvider('examplesForStripExceptDirectories')>>
  public function testStripExceptDirectories(
    keyset<string> $roots,
    vec<string> $paths_in,
    vec<string> $paths_expected,
  ): void {
    $changeset = (new ShipItChangeset())
      ->withDiffs(
        Vec\map($paths_in, $path ==> shape('path' => $path, 'body' => 'junk')),
      );
    $changeset = ShipItPathFilters::stripExceptDirectories($changeset, $roots);
    \expect(Vec\map($changeset->getDiffs(), $diff ==> $diff['path']))
      ->toEqual($paths_expected);
  }

  public function testRewriteCppIncludeDirectivePaths(): void {
    $changeset = (new ShipItChangeset())->withDiffs(vec[
      shape(
        'path' => 'junk',
        'body' => Str\join(
          vec[
            '#include "deep/project/in/fbsource/test.h"',
            '#include <functional>',
            '#include "other.h"',
            '#include <folly/something.h>',
          ],
          "\n",
        ),
      ),
    ]);
    $changeset = ShipItPathFilters::rewriteIncludeDirectivePaths(
      $changeset,
      dict[
        'deep/project/in/fbsource/' => 'src/',
        'root/ignored/' => '',
        'folly/' => 'folly/',
      ],
    );
    \expect($changeset->getDiffs()[0]['body'])->toEqual(
      Str\join(
        vec[
          '#include "src/test.h"',
          '#include <functional>',
          '#include "other.h"',
          '#include <folly/something.h>',
        ],
        "\n",
      ),
    );
  }

  public function testRewriteThriftIncludeDirectivePaths(): void {
    $changeset = (new ShipItChangeset())->withDiffs(vec[
      shape(
        'path' => 'junk',
        'body' => Str\join(
          vec[
            'include "deep/project/in/fbsource/test.thrift"',
            'include "other.thrift"',
          ],
          "\n",
        ),
      ),
    ]);
    $changeset = ShipItPathFilters::rewriteIncludeDirectivePaths(
      $changeset,
      dict[
        'deep/project/in/fbsource/' => 'src/',
        'root/ignored/' => '',
        'folly/' => 'folly/',
      ],
    );
    \expect($changeset->getDiffs()[0]['body'])->toEqual(
      Str\join(
        vec[
          'include "src/test.thrift"',
          'include "other.thrift"',
        ],
        "\n",
      ),
    );
  }
}
