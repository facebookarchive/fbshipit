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
};

class ImportDemoProject {

  public static function filterChangeset(
    ShipItChangeset $changeset,
  ): ShipItChangeset {
    return $changeset
      |> ImportItPathFilters::moveDirectories(
        $$,
        ShipDemoProject::getPathMappings(),
      );
  }

  public static function cliMain(): void {
    // The repository state will be updated and modified, so we need to use a
    // consistent destination repository for all ImportIt phases.
    $source_repo_getter = (ShipItBaseConfig $c) ==> {
      return new ImportItRepoGIT($c->getSourcePath(), $c->getSourceBranch());
    };

    (
      new ShipItPhaseRunner(
        new ShipItBaseConfig(
          /* default working dir = */ '/var/tmp/shipit',
          'fbshipit-target',
          'fbshipit',
          /* source roots = */ keyset['.'],
        ),
        vec[
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
          new ImportItSyncPhase(
            $changeset ==> self::filterChangeset($changeset),
          ),
        ],
      )
    )
      ->run();
  }
}

<<__EntryPoint>>
async function main(): Awaitable<void> {
  require_once(\dirname(__DIR__).'/vendor/autoload.hack'); // @oss-enable
  \Facebook\AutoloadMap\initialize(); // @oss-enable
  ImportDemoProject::cliMain();
}
