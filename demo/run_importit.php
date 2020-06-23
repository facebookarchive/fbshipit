<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ImportIt;

use type Facebook\ShipIt\{
  ShipDemoProject,
  DemoGitHubUtils,
  DemoSourceRepoInitPhase,
  ShipItPhaseRunner,
  ShipItBaseConfig,
  ShipItChangeset,
  ShipItCleanPhase,
  ShipItPullPhase,
  ShipItGitHubInitPhase,
  ShipItRepoSide,
  ShipItTransport,
  ShipItExitException,
};

final class ImportDemoProject {

  public static function filterChangeset(
    ShipItChangeset $changeset,
  ): ShipItChangeset {
    return $changeset
      |> ImportItPathFilters::moveDirectories(
        $$,
        ShipDemoProject::getPathMappings(),
      );
  }

  public static async function cliMainAsync(): Awaitable<void> {
    $config = new ShipItBaseConfig(
      /* default working dir = */ '/var/tmp/shipit',
      /* source repo name */ 'fbshipit-target',
      /* destination repo name */ 'fbshipit',
      /* source roots = */ keyset['.'],
    );

    $phases = vec[
      new DemoSourceRepoInitPhase(),
      new ShipItCleanPhase(ShipItRepoSide::DESTINATION),
      new ShipItPullPhase(ShipItRepoSide::DESTINATION),
      new ShipItGitHubInitPhase(
        'facebook',
        'fbshipit',
        ShipItRepoSide::SOURCE,
        ShipItTransport::HTTPS,
        DemoGitHubUtils::class,
      ),
      new ShipItCleanPhase(ShipItRepoSide::SOURCE),
      new ShipItPullPhase(ShipItRepoSide::SOURCE),
      new ImportItSyncPhase($changeset ==> self::filterChangeset($changeset)),
    ];

    try {
      (new ShipItPhaseRunner($config, $phases))->run();
    } catch (ShipItExitException $e) {
      exit($e->exitCode);
    }
  }
}

<<__EntryPoint>>
async function mainAsync(): Awaitable<void> {
  require_once(\dirname(__DIR__).'/vendor/autoload.hack'); // @oss-enable
  \Facebook\AutoloadMap\initialize(); // @oss-enable
  await ImportDemoProject::cliMainAsync();
}
