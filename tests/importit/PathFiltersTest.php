<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ImportIt;

final class PathFiltersTest extends \Facebook\ShipIt\BaseTest {
  public function examplesForMoveDirectories(
  ): array<
    string,
    (ImmMap<string,string>, ImmVector<string>, ImmVector<string>)
  > {
    return [
      'second takes precedence (first is more specific)' => tuple(
        ImmMap {
          'foo/public_tld/' => '',
          'foo/' => 'bar/',
        },
        ImmVector { 'root_file', 'bar/bar_file' },
        ImmVector { 'foo/public_tld/root_file', 'foo/bar_file' },
      ),
      'only one rule applied' => tuple(
        ImmMap {
          'foo/' => '',
          'bar/' => 'project_bar/',
        },
        ImmVector {
          'bar/part of project foo',
          'project_bar/part of project bar',
        },
        ImmVector { 'foo/bar/part of project foo', 'bar/part of project bar' },
      ),
      'subdirectories' => tuple(
        ImmMap {
          'foo/test/' => 'testing/',
          'foo/' => '',
        },
        ImmVector { 'testing/README', 'src.c', },
        ImmVector { 'foo/test/README', 'foo/src.c', },
      ),
    ];
  }

  /**
   * @dataProvider examplesForMoveDirectories
   */
  public function testMoveDirectories(
    ImmMap<string, string> $map,
    ImmVector<string> $in,
    ImmVector<string> $expected,
  ): void {
    $changeset = (new \Facebook\ShipIt\ShipItChangeset())
      ->withDiffs($in->map($path ==> shape('path' => $path, 'body' => 'junk')));
    $changeset = ImportItPathFilters::moveDirectories($changeset, $map);
    $this->assertEquals(
      $expected->toArray(),
      $changeset->getDiffs()->map($diff ==> $diff['path'])->toArray(),
    );
  }

  public function testMoveDirectoriesThrowsWithDuplciationMappings(): void {
    /* HH_IGNORE_ERROR[2049]: Y U NO TYPECHECK? */
    $this->expectException(InvariantException::class);
    $in = ImmVector {
      'does/not/matter',
    };
    $changeset = (new \Facebook\ShipIt\ShipItChangeset())
      ->withDiffs($in->map($path ==> shape('path' => $path, 'body' => 'junk')));
    $changeset = ImportItPathFilters::moveDirectories(
      $changeset,
      ImmMap {
        'somewhere/' => '',
        'elsewhere/' => '',
      },
    );
  }
}
