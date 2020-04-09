<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt;

class ShipItInvocation implements IShipItArgumentParser {
  use ShipItArgumentsTrait;

  public function parseArgs(
    vec<ShipItCLIArgument> $config,
  ): dict<string, mixed> {
    return $this->getArgs();
  }

  public function run(): void {
    ShipDemoProject::cliMain($this);
  }
}
