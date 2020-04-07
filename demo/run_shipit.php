<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt;

final class ShipDemoProject {
  public static function getPathMappings(): dict<string, string> {
    return dict[
      'fb-examples' => 'examples',
    ];
  }

  public static function filterChangeset(
    ShipItChangeset $changeset,
  ): ShipItChangeset {
    return $changeset
      |> ShipItPathFilters::stripPaths(
        $$,
        vec[
          "/^src/",
          "/^tests/",
          "/^\.gitignore$/",
          "/^\.hhconfig$/",
          "/^\.travis\.sh$/",
          "/^\.travis\.yml$/",
          "/^CODE_OF_CONDUCT\.md$/",
          "/^CONTRIBUTING\.md$/",
          "/^DEBUGGING\.md$/",
          "/^README\.md$/",
          "/^TESTING\.md$/",
          "/^composer\.json$/",
          "/^composer\.lock$/",
          "/^hh_autoload\.json$/",
          "/^phpunit\.xml$/",
        ],
      )
      |> ShipItPathFilters::moveDirectories($$, self::getPathMappings());
  }

  public static function cliMain(
    IShipItArgumentParser $arg_parser = new ShipItCLIArgumentParser(),
  ): void {
    $config = new ShipItBaseConfig(
      /* default working dir = */ '/var/tmp/shipit',
      /* source repo name */ 'fbshipit',
      /* destination repo name */ 'fbshipit-target',
      /* source roots */ keyset['.'],
    );

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
        ($changeset) ==> self::filterChangeset($changeset),
        shape(
          'name' => DemoGitHubUtils::$committerName,
          'email' => DemoGitHubUtils::$committerEmail,
        ),
      ),
      new ShipItPullPhase(ShipItRepoSide::DESTINATION),
      new ShipItSyncPhase(
        ($config, $changeset) ==> self::filterChangeset($changeset),
      ),
      new ShipItPushPhase(),
    ];

    (new ShipItPhaseRunner($config, $phases, $arg_parser))->run();
  }
}

<<__EntryPoint>>
async function mainAsync(): Awaitable<void> {
  require_once(\dirname(__DIR__).'/vendor/autoload.hack'); // @oss-enable
  \Facebook\AutoloadMap\initialize(); // @oss-enable
  ShipDemoProject::cliMain();
}
