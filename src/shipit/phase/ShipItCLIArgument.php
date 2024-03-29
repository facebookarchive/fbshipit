<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/xcbckn76
 */
namespace Facebook\ShipIt;

type ShipItCLIArgument = shape(
  ?'short_name' => string,
  'long_name' => string,
  'description' => string,
  // Handler function for when the option is set
  ?'write' => (function(string): mixed),
);
