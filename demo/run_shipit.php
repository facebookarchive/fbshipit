<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt;

final class ShipDemoProject {
  private static string $sourceRoot = "fb-examples";

  public static function getPathMappings(): dict<string, string> {
    return dict[
      self::$sourceRoot => 'examples',
    ];
  }

  public static function filterChangeset(
    ShipItChangeset $changeset,
  ): ShipItChangeset {
    return $changeset
      |> ShipItPathFilters::stripExceptSourceRoots(
        $$,
        keyset[self::$sourceRoot],
      )
      |> ShipItPathFilters::moveDirectories($$, self::getPathMappings());
  }

  public static async function genCliMain(): Awaitable<void> {
    $manifest = (
      new ShipItManifest(
        /* default working dir = */ '/var/tmp/shipit',
        /* source repo name */ 'fbshipit',
        /* destination repo name */ 'fbshipit-target',
        /* source roots */ keyset[self::$sourceRoot],
      )
    )->withSourceBranch('main');

    $phases = vec[
      new DemoSourceRepoInitPhase(),
      new ShipItPullPhase(ShipItRepoSide::SOURCE),
      new ShipItGitHubInitPhase(
        DemoGitHubUtils::$committerUser,
        'fbshipit-demo',
        ShipItRepoSide::DESTINATION,
        ShipItTransport::SSH,
        DemoGitHubUtils::class,
      ),
      new ShipItCreateNewRepoPhase(
        async ($changeset) ==> self::filterChangeset($changeset),
        shape(
          'name' => DemoGitHubUtils::$committerName,
          'email' => DemoGitHubUtils::$committerEmail,
        ),
      ),
      new ShipItPullPhase(ShipItRepoSide::DESTINATION),
      new ShipItSyncPhase(
        async ($_config, $changeset) ==> self::filterChangeset($changeset),
      ),
      new ShipItPushPhase(),
    ];

    try {
      await (new ShipItPhaseRunner($manifest, $phases))->genRun();
    } catch (ShipItExitException $e) {
      PHP\fb\exit($e->exitCode);
    }
  }
}

<<__EntryPoint>>
async function gen_main(): Awaitable<void> {
  require_once(\dirname(__DIR__).'/vendor/autoload.hack'); // @oss-enable
  \Facebook\AutoloadMap\initialize(); // @oss-enable
  await ShipDemoProject::genCliMain();
}
