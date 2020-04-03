<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/ddtwcak3
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Vec;
use type Facebook\HackTest\DataProvider; // @oss-enable
// @oss-disable: use type DataProvider;


<<\Oncalls('open_source')>>
final class PathsWithSpacesTest extends ShellTest {
  const FILE_NAME = 'foo bar/herp derp.txt';

  public function exampleRepos(
  ): dict<classname<ShipItRepo>, vec<ShipItTempDir>> {
    return dict[
      ShipItRepoGIT::class => vec[$this->createGitExample()],
      ShipItRepoHG::class => vec[$this->createHGExample()],
    ];
  }

  <<DataProvider('exampleRepos')>>
  public function testPathWithSpace(ShipItTempDir $temp_dir): void {
    $repo = ShipItRepo::open($temp_dir->getPath(), '.');
    $head = $repo->getHeadChangeset();

    $head = \expect($head)->toNotBeNull();

    $paths = Vec\map($head->getDiffs(), $diff ==> $diff['path']);
    \expect($paths)->toBePHPEqual(vec[self::FILE_NAME]);
  }

  private function createGitExample(): ShipItTempDir {
    $temp_dir = new ShipItTempDir(__FUNCTION__);
    $path = $temp_dir->getPath();
    $this->execSteps($path, vec['git', 'init']);
    $this->configureGit($temp_dir);
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \mkdir($path.'/'.\dirname(self::FILE_NAME), 0755, /* recursive = */ true);
    $this->execSteps(
      $path,
      vec['touch', self::FILE_NAME],
      vec['git', 'add', '.'],
      vec['git', 'commit', '-m', 'initial commit'],
    );

    return $temp_dir;
  }

  private function createHGExample(): ShipItTempDir {
    $temp_dir = new ShipItTempDir(__FUNCTION__);
    $path = $temp_dir->getPath();
    $this->execSteps($path, vec['hg', 'init']);
    $this->configureHg($temp_dir);
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \mkdir($path.'/'.\dirname(self::FILE_NAME), 0755, /* recursive = */ true);
    $this->execSteps(
      $path,
      vec['touch', self::FILE_NAME],
      vec['hg', 'commit', '-Am', 'initial commit'],
    );

    return $temp_dir;
  }
}
