<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class PathsWithSpacesTest extends BaseTest {
  const FILE_NAME = 'foo bar/herp derp.txt';

  public function exampleRepos(
  ): array<classname<ShipItRepo>, array<ShipItTempDir>> {
    return [
      ShipItRepoGIT::class => array($this->createGitExample()),
      ShipItRepoHG::class => array($this->createHGExample()),
    ];
  }

  /**
   * @dataProvider exampleRepos
   */
  public function testPathWithSpace(
    ShipItTempDir $temp_dir,
  ): void {
    $repo = ShipItRepo::open($temp_dir->getPath(), '.');
    $head = $repo->getHeadChangeset();

    $this->assertNotNull($head);
    // typechecker:
    assert($head !== null);

    $paths = $head->getDiffs()->map($diff ==> $diff['path']);
    $this->assertEquals(
      ImmVector { self::FILE_NAME },
      $paths,
    );
  }

  private function createGitExample(): ShipItTempDir {
    $temp_dir = new ShipItTempDir(__FUNCTION__);
    $path = $temp_dir->getPath();
    $this->execSteps($path, [ 'git', 'init' ]);
    $this->configureGit($temp_dir);
    \mkdir($path.'/'.\dirname(self::FILE_NAME), 0755, /* recursive = */ true);
    $this->execSteps(
      $path,
      [ 'touch', self::FILE_NAME ],
      [ 'git', 'add', '.' ],
      [ 'git', 'commit', '-m', 'initial commit' ],
    );

    return $temp_dir;
  }

  private function createHGExample(): ShipItTempDir {
    $temp_dir = new ShipItTempDir(__FUNCTION__);
    $path = $temp_dir->getPath();
    $this->execSteps($path, [ 'hg', 'init' ]);
    $this->configureHg($temp_dir);
    \mkdir($path.'/'.\dirname(self::FILE_NAME), 0755, /* recursive = */ true);
    $this->execSteps(
      $path,
      [ 'touch', self::FILE_NAME ],
      [ 'hg', 'commit', '-Am', 'initial commit' ],
    );

    return $temp_dir;
  }
}
