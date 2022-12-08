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
 * https://fburl.com/6kehzuiw
 */
namespace Facebook\ImportIt;

use namespace HH\Lib\{Keyset, Vec, Str}; // @oss-enable
use type Facebook\HackTest\DataProvider; // @oss-enable
// @oss-disable: use type DataProvider;

<<\Oncalls('open_source')>>
final class PathFiltersTest extends \Facebook\ShipIt\BaseTest {
  public static function examplesForMoveDirectories(
  ): dict<string, (dict<string, string>, vec<string>, vec<string>)> {
    return dict[
      'second takes precedence (first is more specific)' => tuple(
        dict[
          'foo/public_tld/' => '',
          'foo/' => 'bar/',
        ],
        vec['root_file', 'bar/bar_file'],
        vec['foo/public_tld/root_file', 'foo/bar_file'],
      ),
      'only one rule applied' => tuple(
        dict[
          'foo/' => '',
          'bar/' => 'project_bar/',
        ],
        vec[
          'bar/part of project foo',
          'project_bar/part of project bar',
        ],
        vec['foo/bar/part of project foo', 'bar/part of project bar'],
      ),
      'subdirectories' => tuple(
        dict[
          'foo/test/' => 'testing/',
          'foo/' => '',
        ],
        vec['testing/README', 'src.c'],
        vec['foo/test/README', 'foo/src.c'],
      ),
    ];
  }

  <<DataProvider('examplesForMoveDirectories')>>
  public function testMoveDirectories(
    dict<string, string> $map,
    vec<string> $in,
    vec<string> $expected,
  ): void {
    $changeset = (new \Facebook\ShipIt\ShipItChangeset())
      ->withDiffs(
        Vec\map($in, $path ==> shape('path' => $path, 'body' => 'junk')),
      );
    $changeset = ImportItPathFilters::moveDirectories($changeset, $map);
    \expect(Vec\map($changeset->getDiffs(), $diff ==> $diff['path']))
      ->toEqual($expected);
  }

  public function testMoveDirectoriesThrowsWithDuplciationMappings(): void {
    \expect(() ==> {
      $in = vec[
        'does/not/matter',
      ];
      $changeset = (new \Facebook\ShipIt\ShipItChangeset())
        ->withDiffs(
          Vec\map($in, $path ==> shape('path' => $path, 'body' => 'junk')),
        );
      ImportItPathFilters::moveDirectories(
        $changeset,
        dict['somewhere/' => '', 'elsewhere/' => ''],
      );
    })
      ->toThrow(\Facebook\ShipIt\ShipItImportDisallowedException::class);
  }

  public function testRewriteCppIncludeDirectivePaths(): void {
    $changeset = (new \Facebook\ShipIt\ShipItChangeset())->withDiffs(vec[
      shape(
        'path' => 'junk',
        'body' => Str\join(
          vec[
            '#include "src/test.h"',
            '#include <functional>',
            '#include "other.h"',
            '#include <folly/something.h>',
          ],
          "\n",
        ),
      ),
    ]);
    $changeset = ImportItPathFilters::rewriteIncludeDirectivePaths(
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
          '#include "deep/project/in/fbsource/test.h"',
          '#include <functional>',
          '#include "other.h"',
          '#include <folly/something.h>',
        ],
        "\n",
      ),
    );
  }

  public function testRewriteThriftIncludeDirectivePaths(): void {
    $changeset = (new \Facebook\ShipIt\ShipItChangeset())->withDiffs(vec[
      shape(
        'path' => 'junk',
        'body' => Str\join(
          vec[
            'include "src/test.thrift"',
            'include "other.thrift"',
          ],
          "\n",
        ),
      ),
    ]);
    $changeset = ImportItPathFilters::rewriteIncludeDirectivePaths(
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
          'include "deep/project/in/fbsource/test.thrift"',
          'include "other.thrift"',
        ],
        "\n",
      ),
    );
  }
}
