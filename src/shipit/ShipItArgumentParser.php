<?hh
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, Vec};

interface IShipItArgumentParser {
  public function parseArgs(
    vec<ShipItCLIArgument> $config,
  ): dict<string, mixed>;
}

final class ShipItCLIArgumentParser implements IShipItArgumentParser {
  public function parseArgs(
    vec<ShipItCLIArgument> $config,
  ): dict<string, mixed> {
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    return \getopt(
      Vec\map($config, $opt ==> Shapes::idx($opt, 'short_name', ''))
        |> Str\join($$, ''),
      Vec\map($config, $opt ==> $opt['long_name']),
    )
      |> dict($$);
  }
}
