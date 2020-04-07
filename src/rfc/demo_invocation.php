<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */


namespace Facebook;

<<__EntryPoint>>
async function main(): Awaitable<void> {
  require_once(\dirname(\dirname(__DIR__)).'/vendor/autoload.hack');
  AutoloadMap\initialize();

  try {
    (new ShipIt\ShipItInvocation())
      ->skipSourcePull()
      //->skipDestinationPull()
      ->skipPush()
      ->run();
  } catch (ShipIt\ShipItShellCommandException $e) {
    // This makes using shipit from Hack much more flexible.
    // There's no need to shell out to a new php process.
    // For example, we can customize the way output is written to stdout:
    echo "\n*** ShipIt Failed ***\n".$e->getError();
    // Or send the exception to some logging service:
    // SomeLoggingService::logShipItFailure($e);
    // Or rethrow a different exception to send the correct
    // signal to CI without reverse engineering stdout/stderr:
    // throw SomeCIServiceTryAgainException($e)
  }
}
