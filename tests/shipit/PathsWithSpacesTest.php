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
 * https://fburl.com/ddtwcak3
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Vec; // @oss-enable
use type Facebook\HackTest\DataProvider; // @oss-enable
// @oss-disable: use type DataProvider;


<<\Oncalls('open_source')>>
final class PathsWithSpacesTest extends ShellTest {
  const FILE_NAME = 'foo bar/herp derp.txt';

  public static async function genExampleRepos(
  ): Awaitable<dict<classname<ShipItRepo>, vec<ShipItTempDir>>> {
    return dict[
      ShipItRepoGIT::class => vec[await self::genCreateGitExample()],
      ShipItRepoHG::class => vec[await self::genCreateHGExample()],
    ];
  }

  <<DataProvider('genExampleRepos')>>
  public async function testPathWithSpace(
    ShipItTempDir $temp_dir,
  ): Awaitable<void> {
    $repo = await ShipItRepo::genOpen(
      new ShipItDummyLock(),
      $temp_dir->getPath(),
      '.',
    );
    $head = await $repo->genHeadChangeset();

    $head = \expect($head)->toNotBeNull();

    $paths = Vec\map($head->getDiffs(), $diff ==> $diff['path']);
    \expect($paths)->toEqual(vec[self::FILE_NAME]);
  }

  private static async function genCreateGitExample(
  ): Awaitable<ShipItTempDir> {
    $temp_dir = new ShipItTempDir(__FUNCTION__);
    $path = $temp_dir->getPath();
    await self::genExecSteps($path, vec['git', 'init']);
    await self::genConfigureGit($temp_dir);
    PHP\mkdir(
      $path.'/'.PHP\dirname(self::FILE_NAME),
      0755, /* recursive = */
      true,
    );
    await self::genExecSteps(
      $path,
      vec['touch', self::FILE_NAME],
      vec['git', 'add', '.'],
      vec['git', 'commit', '-m', 'initial commit'],
    );

    return $temp_dir;
  }

  private static async function genCreateHGExample(): Awaitable<ShipItTempDir> {
    $temp_dir = new ShipItTempDir(__FUNCTION__);
    $path = $temp_dir->getPath();
    await self::genExecSteps($path, vec['hg', 'init']);
    self::configureHg($temp_dir);
    PHP\mkdir(
      $path.'/'.PHP\dirname(self::FILE_NAME),
      0755, /* recursive = */
      true,
    );
    await self::genExecSteps(
      $path,
      vec['touch', self::FILE_NAME],
      vec['hg', 'commit', '-Am', 'initial commit'],
    );

    return $temp_dir;
  }
}
