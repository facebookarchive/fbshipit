<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class PathFiltersTest extends BaseTest {
  public function stripPathsTestData(
  ): array<string, (
    ImmVector<string>, // $patterns,
    ImmVector<string>, // $exceptions,
    ImmSet<string>, // $expected_files,
  )> {
    return [
      'No change' => tuple(
        ImmVector {},
        ImmVector {},
        ImmSet { 'foo', 'bar', 'herp/derp', 'herp/derp-derp', 'derp' },
      ),
      'Remove top level file' => tuple(
        ImmVector { '@^bar$@' },
        ImmVector {},
        ImmSet { 'foo', 'herp/derp', 'herp/derp-derp', 'derp' },
      ),
      'Remove directory ' => tuple(
        ImmVector { '@^herp/@' },
        ImmVector { },
        ImmSet { 'foo', 'bar', 'derp' },
      ),
      'Remove directory contents except one file ' => tuple(
        ImmVector { '@^herp/@' },
        ImmVector { '@^herp/derp-derp@' },
        ImmSet { 'foo', 'bar', 'herp/derp-derp', 'derp' },
      ),
      'Remove file' => tuple (
        ImmVector { '@(^|/)derp(/|$)@' },
        ImmVector { },
        ImmSet { 'foo', 'bar', 'herp/derp-derp' },
      ),
      'Remove file, except if parent directory has specific name' => tuple(
        ImmVector { '@(^|/)derp(/|$)@' },
        ImmVector { '@(^|/)herp/derp$@' },
        ImmSet { 'foo', 'bar', 'herp/derp', 'herp/derp-derp' },
      ),
      'Multiple patterns' => tuple(
        ImmVector { '@^foo$@', '@^bar$@' },
        ImmVector {},
        ImmSet { 'herp/derp', 'herp/derp-derp', 'derp' },
      ),
      'Multiple exceptions' => tuple(
        ImmVector { '@@' },
        ImmVector { '@foo@', '@bar@' },
        ImmSet { 'foo', 'bar' },
      ),
    ];
  }

  /**
   * @dataProvider stripPathsTestData
   */
  public function testStripPaths(
    ImmVector<string> $patterns,
    ImmVector<string> $exceptions,
    ImmSet<string> $expected_files,
  ): void {
    $changeset = (new ShipItChangeset())->withDiffs(
      self::diffsFromMap(ImmMap {
        'foo' => 'placeholder',
        'bar' => 'placeholder',
        'herp/derp' => 'placeholder',
        'herp/derp-derp' => 'placeholder',
        'derp' => 'placeholder',
      }),
    );

    $changeset = ShipItPathFilters::stripPaths(
      $changeset,
      $patterns,
      $exceptions,
    );

    $this->assertEquals(
      $expected_files,
      $changeset->getDiffs()->map($diff ==> $diff['path'])->toImmSet(),
    );
  }

  public function examplesForMoveDirectories(
  ): array<
    string,
    (
      ImmMap<string, string>,
      ImmVector<string>,
      ImmVector<string>,
      ImmVector<string>,
    )
  > {
    return [
      'first takes precedence (first is more specific)' => tuple(
        ImmMap {
          'foo/public_tld/' => '',
          'foo/' => ''
        },
        ImmVector { 'foo/orig_root_file', 'foo/public_tld/public_root_file' },
        ImmVector { 'orig_root_file', 'public_root_file' },
        ImmVector {},
      ),
      // this mapping doesn't make sense given the behavior, just using it to
      // check that order matters
      'first takes precedence (second is more specific)' => tuple(
        ImmMap {
          'foo/' => '',
          'foo/public_tld/' => '',
        },
        ImmVector { 'foo/orig_root_file', 'foo/public_tld/public_root_file' },
        ImmVector { 'orig_root_file', 'public_tld/public_root_file' },
        ImmVector {},
      ),
      'only one rule applied' => tuple(
        ImmMap {
          'foo/' => '',
          'bar/' => 'project_bar/',
        },
        ImmVector { 'foo/bar/part of project foo', 'bar/part of project bar' },
        ImmVector {
          'bar/part of project foo',
          'project_bar/part of project bar',
        },
        ImmVector {},
      ),
      'skipped file is not moved despite match' => tuple(
        ImmMap {
          'foo/' => '',
        },
        ImmVector { 'foo/bar', 'foo/car' },
        ImmVector { 'foo/bar', 'car' },
        ImmVector { '@^foo/bar$@' },
      )
    ];
  }

  /**
   * @dataProvider examplesForMoveDirectories
   */
  public function testMoveDirectories(
    ImmMap<string, string> $map,
    ImmVector<string> $in,
    ImmVector<string> $expected,
    ImmVector<string> $skip_patterns,
  ): void {
    $changeset = (new ShipItChangeset())
      ->withDiffs($in->map($path ==> shape('path' => $path, 'body' => 'junk')));
    $changeset = ShipItPathFilters::moveDirectories(
      $changeset,
      $map,
      $skip_patterns,
    );
    $this->assertEquals(
      $expected->toArray(),
      $changeset->getDiffs()->map($diff ==> $diff['path'])->toArray(),
    );
  }

  public function examplesForStripExceptDirectories(
  ): array<(ImmSet<string>, ImmVector<string>, ImmVector<string>)> {
    return [
      tuple(
        ImmSet { 'foo' },
        ImmVector { 'foo/bar', 'herp/derp' },
        ImmVector { 'foo/bar' },
      ),
      tuple(
        ImmSet { 'foo/' },
        ImmVector { 'foo/bar', 'herp/derp' },
        ImmVector { 'foo/bar' },
      ),
      tuple(
        ImmSet { 'foo' },
        ImmVector { 'foo/bar', 'foobaz' },
        ImmVector { 'foo/bar' },
      ),
      tuple(
        ImmSet { 'foo', 'herp' },
        ImmVector { 'foo/bar', 'herp/derp', 'baz' },
        ImmVector { 'foo/bar', 'herp/derp' },
      ),
    ];
  }

  /**
   * @dataProvider examplesForStripExceptDirectories
   */
  public function testStripExceptDirectories(
    ImmSet<string> $roots,
    ImmVector<string> $paths_in,
    ImmVector<string> $paths_expected,
  ): void {
    $changeset = (new ShipItChangeset())
      ->withDiffs($paths_in->map(
        $path ==> shape('path' => $path, 'body' => 'junk')
      ));
    $changeset = ShipItPathFilters::stripExceptDirectories(
      $changeset,
      $roots,
    );
    $this->assertEquals(
      $paths_expected->toArray(),
      $changeset->getDiffs()->map($diff ==> $diff['path'])->toArray(),
    );
  }
}
