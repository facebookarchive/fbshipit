<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt;

use namespace HH\Lib\{C, Vec};

<<__EntryPoint>>
async function run_test_main(): Awaitable<void> {
  require_once(\dirname(\dirname(__DIR__)).'/vendor/autoload.hack');
  \Facebook\AutoloadMap\initialize();

  $phases = vec[
    new ShipItCleanPhase(ShipItRepoSide::SOURCE),
    new ShipItCleanPhase(ShipItRepoSide::DESTINATION),
    new ShipItCreateNewRepoPhase($x ==> $x, shape('name' => '', 'email' => '')),
    new ShipItDeleteCorruptedRepoPhase(ShipItRepoSide::SOURCE),
    new ShipItDeleteCorruptedRepoPhase(ShipItRepoSide::DESTINATION),
    new ShipItFilterSanityCheckPhase($x ==> $x, vec[]),
    new ShipItGitHubInitPhase(
      '',
      '',
      ShipItRepoSide::SOURCE,
      ShipItTransport::HTTPS,
      DemoGitHubUtils::class,
    ),
    new ShipItGitHubInitPhase(
      '',
      '',
      ShipItRepoSide::DESTINATION,
      ShipItTransport::HTTPS,
      DemoGitHubUtils::class,
    ),
    new ShipItPullPhase(ShipItRepoSide::SOURCE),
    new ShipItPullPhase(ShipItRepoSide::DESTINATION),
    new ShipItPushLfsPhase(
      ShipItRepoSide::SOURCE,
      '',
      '',
      false,
      DemoGitHubUtils::class,
    ),
    new ShipItPushLfsPhase(
      ShipItRepoSide::DESTINATION,
      '',
      '',
      false,
      DemoGitHubUtils::class,
    ),
    new ShipItPushPhase(),
    new ShipItSaveConfigPhase('', ''),
    new ShipItVerifyRepoPhase($x ==> $x),
  ];

  ShipItCodegenInvocationArguments::generate(shape(
    "phases" => $phases,
    "filename" => __DIR__.'/ShipItArgumentsTrait.php',
    "trait_name" => 'ShipItArgumentsTrait',
  ));
}
